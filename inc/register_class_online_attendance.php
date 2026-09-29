<?php
require_once __DIR__ . '/chahard_rooms.php';

function registerClassRoomId(PDO $conn, int $darsId): string
{
    if ($darsId <= 0) return '';
    $stmt = $conn->prepare('SELECT link_class FROM dbdars WHERE id = ? LIMIT 1');
    $stmt->execute([$darsId]);
    return trim((string)$stmt->fetchColumn());
}

/** List real sessions of this lesson's room, without participant data. */
function registerClassOnlineSessions(string $roomId): array
{
    if ($roomId === '') return [];
    $key = chahard_api_key();
    if ($key === '') throw new RuntimeException('اتصال چهاردیواری تنظیم نشده است.');
    $url = substr(chahard_join_url(), 0, -strlen('lms-join.php')) . 'lms-sessions.php';
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['roomId' => $roomId], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $key],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $response = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($response) || empty($response['success']) || !is_array($response['data'] ?? null)) {
        throw new RuntimeException('فهرست نشست‌های چهاردیواری دریافت نشد.');
    }
    return $response['data'];
}

/** A session can belong to one attendance column only. */
function registerClassSessionIsAvailable(PDO $conn, string $sessionId, int $excludeEventId = 0): bool
{
    $stmt = $conn->prepare('SELECT id FROM register_class_events WHERE room_session_id = ? AND id <> ? LIMIT 1');
    $stmt->execute([$sessionId, $excludeEventId]);
    return !$stmt->fetchColumn();
}

function registerClassSelectedSession(PDO $conn, int $darsId, string $date, string $sessionId, int $excludeEventId = 0): bool
{
    if (!preg_match('/^[a-f0-9]{24}$/i', $sessionId) || !registerClassSessionIsAvailable($conn, $sessionId, $excludeEventId)) return false;
    $roomId = registerClassRoomId($conn, $darsId);
    if ($roomId === '') return false;
    $tehran = new DateTimeZone('Asia/Tehran');
    foreach (registerClassOnlineSessions($roomId) as $session) {
        if (!is_array($session) || (string)($session['id'] ?? '') !== $sessionId) continue;
        try {
            return (new DateTimeImmutable((string)$session['startedAt']))->setTimezone($tehran)->format('Y-m-d') === $date;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

/** Fetch verified room durations for one lesson on the column's date. */
function registerClassOnlineDurations(string $roomId, string $date, array $usernames, string $sessionId = ''): array
{
    static $cache = [];
    $cacheKey = $roomId . '|' . $date . '|' . $sessionId . '|' . hash('sha256', implode("\n", $usernames));
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    $key = chahard_api_key();
    if ($key === '' || $roomId === '') throw new RuntimeException('اتصال چهاردیواری تنظیم نشده است.');
    $url = substr(chahard_join_url(), 0, -strlen('lms-join.php')) . 'lms-attendance.php';
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['roomId' => $roomId, 'date' => $date, 'usernames' => $usernames, 'sessionId' => $sessionId], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-API-Key: ' . $key],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    $response = json_decode((string)$body, true);
    if ($status !== 200 || !is_array($response) || empty($response['success']) || !is_array($response['data']['durations'] ?? null)) {
        throw new RuntimeException('گزارش حضور چهاردیواری دریافت نشد.');
    }
    return $cache[$cacheKey] = $response['data']['durations'];
}

function registerClassSyncOnlineAttendance(PDO $conn, array $event, array $students): void
{
    if ((int)($event['type'] ?? -1) !== 2 || empty($event['event_date']) || empty($students)) return;
    $darsId = (int)($event['dars_id'] ?? 0);
    if ($darsId <= 0) return;
    $roomId = registerClassRoomId($conn, $darsId);
    if ($roomId === '') throw new RuntimeException('برای این درس اتاق چهاردیواری تنظیم نشده است.');
    $usernames = array_values(array_unique(array_filter(array_map(
        static fn($student) => (string)($student['username'] ?? ''), $students
    ))));
    if (!$usernames) return;
    $durations = registerClassOnlineDurations($roomId, (string)$event['event_date'], $usernames, (string)($event['room_session_id'] ?? ''));
    registerClassStoreOnlineDurations($conn, $event, $usernames, $durations);
}

/** Store only students actually reported by Room; absent report data leaves existing values intact. */
function registerClassStoreOnlineDurations(PDO $conn, array $event, array $usernames, array $durations): void
{
    $allowed = array_fill_keys($usernames, true);
    $find = $conn->prepare('SELECT id FROM register_class_attendance WHERE register_class_events_id = ? AND username = ? LIMIT 1');
    $update = $conn->prepare('UPDATE register_class_attendance SET status = ?, created_at = CURRENT_TIMESTAMP WHERE register_class_events_id = ? AND username = ?');
    $insert = $conn->prepare('INSERT INTO register_class_attendance
        (register_class_events_id, username, status, created_by, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
    foreach ($durations as $username => $seconds) {
        if (!isset($allowed[$username]) || !is_numeric($seconds)) continue;
        // More than twenty minutes, so exactly 1200 seconds remains absent.
        $eventId = (int)$event['id'];
        $status = (int)$seconds > 1200 ? '1' : '0';
        $find->execute([$eventId, $username]);
        if ($find->fetchColumn()) {
            $update->execute([$status, $eventId, $username]);
        } else {
            $insert->execute([$eventId, $username, $status, (int)$event['teacher_id']]);
        }
    }
}
