<?php
/**
 * Attendance push endpoint for face / fingerprint / card systems.
 *
 *   POST /api/attendance/push.php
 *   X-Device-Key: <device key>        (or Authorization: Bearer <key>, or ?key=<key>)
 *   Content-Type: application/json
 *
 *   [{"user": "0016196406", "time": "2026-09-29 07:42:10", "direction": "in"}]
 *   or {"events": [...]}  — add "test": true to mark simulator traffic
 *
 * Duplicate events are ignored, so resending a batch is safe.
 */
header('Content-Type: application/json; charset=utf-8');

function attendance_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attendance_reply(405, ['success' => false, 'message' => 'POST only']);
}

$key = (string) ($_SERVER['HTTP_X_DEVICE_KEY'] ?? '');
// Apache hands the Authorization header over only as REDIRECT_* after a rewrite
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : ''));
if ($key === '' && preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
    $key = $m[1];
}
if ($key === '') $key = (string) ($_GET['key'] ?? '');

$raw = file_get_contents('php://input', false, null, 0, 1048577);
if (strlen((string) $raw) > 1048576) {
    attendance_reply(413, ['success' => false, 'message' => 'body larger than 1 MB; send smaller batches']);
}
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    attendance_reply(400, ['success' => false, 'message' => 'body must be JSON']);
}
$test = !empty($body['test']);
$events = isset($body['events']) ? $body['events'] : (array_is_list($body) ? $body : [$body]);
if (!is_array($events) || !$events) {
    attendance_reply(400, ['success' => false, 'message' => 'no events']);
}

// dbconfig prints connection errors; keep them out of the JSON reply
ob_start();
require_once __DIR__ . '/../../dbconfig.php';
ob_end_clean();
require_once __DIR__ . '/../../inc/attendance.php';
if (!isset($conn) || !$conn instanceof PDO) {
    attendance_reply(500, ['success' => false, 'message' => 'database unavailable']);
}

$device = attendance_device_by_key($conn, $key);
if (!$device) {
    attendance_reply(401, ['success' => false, 'message' => 'unknown or inactive device key']);
}
if (count($events) > ATTENDANCE_MAX_BATCH) {
    attendance_reply(413, ['success' => false, 'message' => 'at most ' . ATTENDANCE_MAX_BATCH . ' events per request']);
}

try {
    $report = attendance_ingest($conn, $device, $events, $test ? 'simulator' : 'push');
    attendance_device_touch($conn, (int) $device['id'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('attendance push: ' . $e->getMessage());
    attendance_reply(500, ['success' => false, 'message' => 'could not store events']);
}

attendance_reply(200, ['success' => true, 'device' => $device['name']] + $report);
