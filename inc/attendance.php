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
