<?php
/**
 * Attendance devices and events. Brand-neutral: any system that can send a
 * national code and a time can feed these tables (push API, file import,
 * simulator).
 */

const ATTENDANCE_DEVICE_TYPES = [
    'face' => 'تشخیص چهره',
    'finger' => 'اثر انگشت',
    'card' => 'کارت',
    'other' => 'سایر',
];
const ATTENDANCE_LOCATIONS = [
    'gate' => 'ورودی مدرسه',
    'classroom' => 'کلاس',
];
const ATTENDANCE_MAX_BATCH = 1000;

/** Create a device; returns [id, key]. The key is shown once and only its hash is stored. */
function attendance_device_create(PDO $pdo, string $name, string $type, string $location, ?string $note, ?string $createdBy): array
{
    if (!isset(ATTENDANCE_DEVICE_TYPES[$type])) $type = 'other';
    if (!isset(ATTENDANCE_LOCATIONS[$location])) $location = 'gate';
    $key = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO attendance_devices (name, type, location, location_note, key_hash, key_hint, created_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $type, $location, $note, hash('sha256', $key), substr($key, 0, 6), $createdBy]);
    return [(int) $pdo->lastInsertId(), $key];
}

/** New key for an existing device (the old one stops working). */
function attendance_device_rotate_key(PDO $pdo, int $deviceId): string
{
    $key = bin2hex(random_bytes(24));
    $pdo->prepare('UPDATE attendance_devices SET key_hash = ?, key_hint = ? WHERE id = ?')
        ->execute([hash('sha256', $key), substr($key, 0, 6), $deviceId]);
    return $key;
}

function attendance_device_by_key(PDO $pdo, string $key): ?array
{
    if ($key === '') return null;
    $st = $pdo->prepare('SELECT * FROM attendance_devices WHERE key_hash = ? AND active = 1');
    $st->execute([hash('sha256', $key)]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Persian/Arabic digits to Latin, trimmed; numeric codes that lost their leading zeros are padded to 10. */
function attendance_normalize_code($value): string
{
    $s = trim((string) $value);
    $s = strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    // devices often store the ID as a number, so 0016196406 arrives as 16196406
    if (preg_match('/^\d{7,9}$/', $s)) $s = str_pad($s, 10, '0', STR_PAD_LEFT);
    return $s;
}

/**
 * Event time to 'Y-m-d H:i:s' in Tehran time, or null.
 * Accepts a unix timestamp, ISO 8601 (with or without offset), 'Y-m-d H:i:s',
 * and Jalali dates such as 1405/07/07 07:42:10.
 */
function attendance_parse_time($value): ?string
{
    $tz = new DateTimeZone('Asia/Tehran');
    if (is_int($value) || (is_string($value) && preg_match('/^\d{9,11}$/', $value))) {
        return (new DateTime('@' . (int) $value))->setTimezone($tz)->format('Y-m-d H:i:s');
    }
    $s = attendance_normalize_digits((string) $value);
    // 2026/09/29 07:42 (common in device exports)
    $s = preg_replace('#^((?:19|20)\d\d)/(\d{1,2})/(\d{1,2})#', '$1-$2-$3', $s);
    $s = preg_replace_callback('#^((?:19|20)\d\d)-(\d{1,2})-(\d{1,2})(?=\D|$)#',
        static fn($m) => sprintf('%s-%02d-%02d', $m[1], $m[2], $m[3]), $s);
    if (preg_match('#^(1[34]\d\d)[/-](\d{1,2})[/-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $s, $m)) {
        require_once __DIR__ . '/jdf.php';
        [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
        $s = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $m[4] ?? 0, $m[5] ?? 0, $m[6] ?? 0);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return null;
    try {
        $dt = new DateTime($s, $tz);
    } catch (Exception $e) {
        return null;
    }
    return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
}

function attendance_normalize_digits(string $s): string
{
    return trim(strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]));
}

function attendance_parse_direction($value): string
{
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['in', 'enter', 'entry', 'checkin', 'check-in', '0', 'ورود'], true)) return 'in';
    if (in_array($v, ['out', 'exit', 'leave', 'checkout', 'check-out', '1', 'خروج'], true)) return 'out';
    return 'unknown';
}

/**
 * Store a batch of events for one device.
 * Each event: {user|codemeli|national_code, time, direction?, score?}.
 * Returns counts plus the rejected rows with their reasons.
 */
function attendance_ingest(PDO $pdo, array $device, array $events, string $source = 'push'): array
{
    $report = ['received' => count($events), 'accepted' => 0, 'duplicates' => 0, 'unknown_person' => 0, 'rejected' => []];
    $latest = (new DateTime('now', new DateTimeZone('Asia/Tehran')))->modify('+10 minutes')->format('Y-m-d H:i:s');

    $findPerson = $pdo->prepare('SELECT idpersonal FROM dbpersonal WHERE codemeli = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT IGNORE INTO attendance_events
        (device_id, codemeli, idpersonal, event_time, direction, score, source, raw)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $people = [];

    $pdo->beginTransaction();
    foreach (array_values($events) as $i => $e) {
        if (!is_array($e)) {
            $report['rejected'][] = ['index' => $i, 'reason' => 'event is not an object'];
            continue;
        }
        $code = attendance_normalize_code($e['user'] ?? $e['codemeli'] ?? $e['national_code'] ?? '');
        if ($code === '' || strlen($code) > 20) {
            $report['rejected'][] = ['index' => $i, 'reason' => 'missing or invalid user'];
            continue;
        }
        $time = attendance_parse_time($e['time'] ?? '');
        if ($time === null) {
            $report['rejected'][] = ['index' => $i, 'reason' => 'missing or invalid time'];
            continue;
        }
        if ($time > $latest) {
            $report['rejected'][] = ['index' => $i, 'reason' => 'time is in the future (check the device clock)'];
            continue;
        }
        if (!array_key_exists($code, $people)) {
            $findPerson->execute([$code]);
            $id = $findPerson->fetchColumn();
            $people[$code] = $id === false ? null : (int) $id;
        }
        $score = isset($e['score']) && is_numeric($e['score']) ? round((float) $e['score'], 2) : null;
        $insert->execute([
            (int) $device['id'], $code, $people[$code], $time,
            attendance_parse_direction($e['direction'] ?? ''), $score, $source,
            mb_substr(json_encode($e, JSON_UNESCAPED_UNICODE), 0, 2000),
        ]);
        if ($insert->rowCount() === 0) {
            $report['duplicates']++;
            continue;
        }
        $report['accepted']++;
        if ($people[$code] === null) $report['unknown_person']++;
    }
    $pdo->commit();
    return $report;
}

function attendance_device_touch(PDO $pdo, int $deviceId, string $ip): void
{
    $pdo->prepare('UPDATE attendance_devices SET last_seen_at = NOW(), last_ip = ? WHERE id = ?')->execute([$ip, $deviceId]);
}

/* ---------------- file import (Excel / CSV exported from a device) ---------------- */

/** All rows of the first sheet with raw cell values (dates stay Excel serial numbers). */
function attendance_sheet_rows(string $path, string $originalName = ''): array
{
    require_once __DIR__ . '/../vendor/autoload.php';
    $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $path, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        $reader->setInputEncoding(\PhpOffice\PhpSpreadsheet\Reader\Csv::GUESS_ENCODING);
        $sheet = $reader->load($path)->getActiveSheet();
    } else {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
    }
    $rows = [];
    foreach ($sheet->toArray(null, true, false, false) as $row) {
        $rows[] = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $row);
    }
    // drop trailing empty rows
    while ($rows && implode('', array_map('strval', end($rows))) === '') array_pop($rows);
    return $rows;
}

/** Guess which column holds what from the header row. */
function attendance_guess_columns(array $header): array
{
    $patterns = [
        'code' => '/کد\s*ملی|كد\s*ملي|codemeli|melli|national|user|شناسه|کد\s*کاربر|personnel|emp|^id$|^no\.?$/iu',
        'date' => '/تاریخ|date|day|روز/iu',
        'time' => '/ساعت|زمان|time|clock/iu',
        'direction' => '/ورود|خروج|وضعیت|نوع|direction|status|type|state|in\/?out/iu',
    ];
    $map = ['code' => null, 'date' => null, 'time' => null, 'direction' => null];
    foreach ($header as $i => $label) {
        $label = (string) $label;
        foreach ($patterns as $key => $re) {
            if ($map[$key] === null && $label !== '' && preg_match($re, $label)) {
                $map[$key] = $i;
                break;
            }
        }
    }
    return $map;
}

/** Date cell (+ optional time cell) to a value attendance_parse_time() understands. */
function attendance_cell_datetime($date, $time): ?string
{
    if ($date === null || $date === '') return null;
    if (is_numeric($date) && (float) $date > 20000 && (float) $date < 80000) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $date, new DateTimeZone('Asia/Tehran'));
        $date = $dt->format((float) $date == floor((float) $date) ? 'Y-m-d' : 'Y-m-d H:i:s');
    }
    if ($time === null || $time === '') return attendance_parse_time($date);
    if (is_numeric($time) && (float) $time < 1) {
        $sec = (int) round((float) $time * 86400);
        $time = sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
    }
    // the date column may carry a midnight time of its own
    $dateOnly = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?.*$/', '', attendance_normalize_digits((string) $date));
    return attendance_parse_time($dateOnly . ' ' . attendance_normalize_digits((string) $time));
}

/**
 * Import sheet rows for one device. $map holds column indexes (code, date,
 * time?, direction?). Returns the ingest report with rejected rows numbered
 * as in the file, plus 'blank' for skipped empty rows.
 */
function attendance_import_rows(PDO $pdo, array $device, array $rows, array $map, bool $hasHeader): array
{
    $events = [];
    $rowNumbers = [];
    $pre = [];
    $blank = 0;
    foreach ($rows as $i => $row) {
        if ($hasHeader && $i === 0) continue;
        $line = $i + 1;
        if (implode('', array_map('strval', $row)) === '') {
            $blank++;
            continue;
        }
        $code = $row[$map['code']] ?? '';
        if (is_float($code)) $code = number_format($code, 0, '', '');
        $time = attendance_cell_datetime($row[$map['date']] ?? null, $map['time'] !== null ? ($row[$map['time']] ?? null) : null);
        if ($time === null) {
            $pre[] = ['row' => $line, 'value' => (string) ($row[$map['date']] ?? ''), 'reason' => 'تاریخ یا ساعت نامعتبر است'];
            continue;
        }
        $events[] = [
            'user' => (string) $code,
            'time' => $time,
            'direction' => $map['direction'] !== null ? ($row[$map['direction']] ?? '') : '',
        ];
        $rowNumbers[] = $line;
    }
    $reasons = [
        'missing or invalid user' => 'کد ملی خالی یا نامعتبر است',
        'missing or invalid time' => 'تاریخ یا ساعت نامعتبر است',
        'time is in the future (check the device clock)' => 'زمان در آینده است (ساعت دستگاه را بررسی کنید)',
    ];
    $report = $events ? attendance_ingest($pdo, $device, $events, 'import')
        : ['received' => 0, 'accepted' => 0, 'duplicates' => 0, 'unknown_person' => 0, 'rejected' => []];
    $problems = $pre;
    foreach ($report['rejected'] as $r) {
        $problems[] = ['row' => $rowNumbers[$r['index']], 'value' => (string) ($events[$r['index']]['user'] ?? ''),
            'reason' => $reasons[$r['reason']] ?? $r['reason']];
    }
    usort($problems, static fn($a, $b) => $a['row'] <=> $b['row']);
    $report['received'] += count($pre);
    $report['rejected'] = $problems;
    $report['blank'] = $blank;
    return $report;
}

/** National codes in a batch that match no student, with how many events each. */
function attendance_unknown_codes(PDO $pdo, int $sinceId): array
{
    $st = $pdo->prepare('SELECT codemeli, COUNT(*) n FROM attendance_events WHERE id > ? AND idpersonal IS NULL GROUP BY codemeli ORDER BY n DESC LIMIT 200');
    $st->execute([$sinceId]);
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Link events whose national code matched nobody to students enrolled since. */
function attendance_relink_unknown(PDO $pdo): int
{
    return (int) $pdo->exec('UPDATE attendance_events e JOIN dbpersonal p ON p.codemeli = e.codemeli
        SET e.idpersonal = p.idpersonal WHERE e.idpersonal IS NULL');
}

/* ---------------- rules and the class register ---------------- */

// register_class_attendance rows written by the device rules carry auto_source =
// 'device' (created_by is the column's teacher, as it must name a real admin).
// A teacher's save clears auto_source, and such rows are never touched again.
const ATTENDANCE_AUTO_SOURCE = 'device';
// only "manual attendance" columns are filled; online attendance has its own source
const ATTENDANCE_REGISTER_TYPES = ['0'];

function attendance_rules(PDO $pdo): array
{
    $defaults = ['enabled' => 0, 'school_start' => '07:30:00', 'late_grace' => 10, 'absent_cutoff' => '09:00:00',
        'min_people_per_day' => 10, 'active_from' => null, 'updated_by' => null, 'updated_at' => null];
    try {
        $row = $pdo->query('SELECT * FROM attendance_rules WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $defaults;
    }
    return $row ? array_merge($defaults, $row) : $defaults;
}

function attendance_now(): DateTime
{
    return new DateTime('now', new DateTimeZone('Asia/Tehran'));
}

/** First arrival 'H:i:s' → present / late. */
function attendance_arrival_status(string $firstTime, array $rules): string
{
    $limit = (new DateTime('2000-01-01 ' . $rules['school_start']))
        ->modify('+' . (int) $rules['late_grace'] . ' minutes')->format('H:i:s');
    return $firstTime > $limit ? 'late' : 'present';
}

/** First non-exit event per national code per day: [code][Y-m-d] => 'H:i:s'. */
function attendance_first_seen(PDO $pdo, array $codes, string $from, string $to): array
{
    $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
    if (!$codes) return [];
    $out = [];
    foreach (array_chunk($codes, 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $st = $pdo->prepare("SELECT codemeli, DATE(event_time) d, TIME(MIN(event_time)) t
            FROM attendance_events
            WHERE codemeli IN ($in) AND event_time >= ? AND event_time < DATE_ADD(?, INTERVAL 1 DAY) AND direction <> 'out'
            GROUP BY codemeli, DATE(event_time)");
        $st->execute(array_merge($chunk, [$from, $to]));
        foreach ($st->fetchAll(PDO::FETCH_NUM) as [$code, $d, $t]) $out[$code][$d] = $t;
    }
    return $out;
}

/** Days in the range on which the devices saw at least min_people_per_day people. */
function attendance_working_days(PDO $pdo, string $from, string $to, int $minPeople): array
{
    $st = $pdo->prepare('SELECT DATE(event_time) d FROM attendance_events
        WHERE event_time >= ? AND event_time < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(event_time) HAVING COUNT(DISTINCT codemeli) >= ?');
    $st->execute([$from, $to, max(1, $minPeople)]);
    return array_flip($st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Fill the manual attendance columns of the class register from device
 * events. A student seen that day is present; one not seen is absent, but
 * only after the cutoff and only on a day the devices were working. Rows a
 * teacher saved, and locked columns, are left alone.
 *
 * @param int|null $classId one class, or every class
 */
function attendance_fill_register(PDO $pdo, ?int $classId = null, ?string $from = null, ?string $to = null): array
{
    $stats = ['present' => 0, 'absent' => 0, 'updated' => 0, 'skipped_manual' => 0];
    $rules = attendance_rules($pdo);
    if (!(int) $rules['enabled']) return $stats;

    $now = attendance_now();
    $today = $now->format('Y-m-d');
    $to = min($to ?? $today, $today);
    $from = $from ?? (clone $now)->modify('-30 days')->format('Y-m-d');
    if (!empty($rules['active_from']) && $from < $rules['active_from']) $from = $rules['active_from'];
    if ($from > $to) return $stats;

    $types = implode(',', array_map(static fn($t) => $pdo->quote($t), ATTENDANCE_REGISTER_TYPES));
    $sql = "SELECT id, class_id, teacher_id, created_by, event_date FROM register_class_events
        WHERE type IN ($types) AND status <> '1' AND event_date BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($classId !== null) {
        $sql .= ' AND class_id = ?';
        $params[] = $classId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $columns = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) return $stats;

    $classIds = array_values(array_unique(array_map('intval', array_column($columns, 'class_id'))));
    $in = implode(',', array_fill(0, count($classIds), '?'));
    $st = $pdo->prepare("SELECT class_id, username FROM dbcourse WHERE activityuser = 1 AND class_id IN ($in)");
    $st->execute($classIds);
    $students = [];
    $allCodes = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$cid, $user]) {
        $students[(int) $cid][] = (string) $user;
        $allCodes[] = (string) $user;
    }

    $seen = attendance_first_seen($pdo, $allCodes, $from, $to);
    $workingDays = attendance_working_days($pdo, $from, $to, (int) $rules['min_people_per_day']);
    $cutoffPassed = $now->format('H:i:s') >= $rules['absent_cutoff'];

    $existing = $pdo->prepare('SELECT id, status, auto_source FROM register_class_attendance WHERE register_class_events_id = ? AND username = ?');
    $insert = $pdo->prepare('INSERT INTO register_class_attendance (register_class_events_id, username, status, auto_source, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $update = $pdo->prepare('UPDATE register_class_attendance SET status = ?, created_at = NOW() WHERE id = ? AND auto_source = ?');

    $pdo->beginTransaction();
    foreach ($columns as $col) {
        $date = (string) $col['event_date'];
        foreach ($students[(int) $col['class_id']] ?? [] as $user) {
            if (isset($seen[$user][$date])) {
                $status = '1';
            } elseif (isset($workingDays[$date]) && ($date < $today || $cutoffPassed)) {
                $status = '0';
            } else {
                continue; // no decision yet
            }
            $existing->execute([(int) $col['id'], $user]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $insert->execute([(int) $col['id'], $user, $status, ATTENDANCE_AUTO_SOURCE,
                    (int) $col['teacher_id'] ?: (int) $col['created_by']]);
                $stats[$status === '1' ? 'present' : 'absent']++;
            } elseif ($row['auto_source'] !== ATTENDANCE_AUTO_SOURCE) {
                $stats['skipped_manual']++;
            } elseif ((string) $row['status'] !== $status) {
                // e.g. marked absent at the cutoff, then arrived late
                $update->execute([$status, (int) $row['id'], ATTENDANCE_AUTO_SOURCE]);
                $stats['updated']++;
            }
        }
    }
    $pdo->commit();
    return $stats;
}

/**
 * Device details for register cells: [username][event_id] =>
 * ['time' => 'H:i' | null, 'late' => bool, 'auto' => bool].
 * $attendanceRows need event_id, username and auto_source.
 */
function attendance_register_marks(PDO $pdo, array $columns, array $attendanceRows): array
{
    $rules = attendance_rules($pdo);
    $dates = [];
    foreach ($columns as $col) {
        if (in_array((string) ($col['type'] ?? ''), ATTENDANCE_REGISTER_TYPES, true) && !empty($col['event_date'])) {
            $dates[(int) $col['id']] = (string) $col['event_date'];
        }
    }
    if (!$dates) return [];
    $users = array_values(array_unique(array_column($attendanceRows, 'username')));
    $seen = attendance_first_seen($pdo, $users, min($dates), max($dates));
    $marks = [];
    foreach ($attendanceRows as $row) {
        $eventId = (int) $row['event_id'];
        if (!isset($dates[$eventId])) continue;
        $time = $seen[$row['username']][$dates[$eventId]] ?? null;
        $auto = ($row['auto_source'] ?? null) === ATTENDANCE_AUTO_SOURCE;
        if (!$auto && $time === null) continue;
        $marks[$row['username']][$eventId] = [
            'time' => $time ? substr($time, 0, 5) : null,
            'late' => $time !== null && attendance_arrival_status($time, $rules) === 'late',
            'auto' => $auto,
        ];
    }
    return $marks;
}
