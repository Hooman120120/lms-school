#!/usr/bin/php
<?php
// Cron job only; never run it from a web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function-quiz.php'; // توابع API شما

$max_retries = 3;        // حداکثر ۳ بار تلاش مجدد
$limit = 50;             // هر بار حداکثر ۵۰ درخواست
$interval_minutes = 30;  // فاصله زمانی بین تلاش‌ها (دقیقه)

$sql = "SELECT * FROM api_request_log 
        WHERE is_success = 0 
          AND event_type = 'add_user_to_group' 
          AND retry_count < ? 
          AND (last_retry_at IS NULL OR last_retry_at < DATE_SUB(NOW(), INTERVAL ? MINUTE))
        ORDER BY created_at ASC 
        LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$max_retries, $interval_minutes, $limit]);
$failed = $stmt->fetchAll(PDO::FETCH_OBJ);
error_log("STARTING CRON JOB AT : " . date("Y-m-d H:i:s"));
if($stmt->rowCount() == 0) {
    error_log("no records for synchronization ...");
}
foreach ($failed as $log) {
    $payload = json_decode($log->request_body, true);
    if (!$payload || !isset($payload['ugid']) || !isset($payload['uid'])) {
        continue;
    }

    // تلاش مجدد
    $result = AddUserToGroupByApi($payload['ugid'], ['uid' => $payload['uid']], $log->requested_by);

    $is_success = ($result === true);
    $error_msg = $is_success ? null : ($result['error'] ?? 'Retry failed');

    $update = $conn->prepare("UPDATE api_request_log SET 
        retry_count = retry_count + 1,
        last_retry_at = NOW(),
        is_success = ?,
        error_message = ?
        WHERE id = ?");
    $update->execute([$is_success ? 1 : 0, $error_msg, $log->id]);

    // لاگ ساده برای مانیتورینگ
    error_log("Retry for log_id {$log->id}: " . ($is_success ? "OK" : "FAILED"));
}