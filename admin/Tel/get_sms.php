<?php
// get_sms.php - دریافت پیامک‌ها از طریق انتقال ترافیک ملی‌پیامک

include_once '../../dbconfig.php';

// ===== تنظیم منطقه زمانی ایران =====
date_default_timezone_set('Asia/Tehran');

// ===== دریافت پارامترهای ارسالی =====
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$body = isset($_GET['body']) ? trim($_GET['body']) : '';

// ===== اعتبارسنجی =====
if (empty($to) || empty($from) || empty($body)) {
    error_log("Invalid SMS transfer data: " . print_r($_GET, true));
    die("Invalid data");
}

// ===== ذخیره در دیتابیس با زمان ایران =====
try {
    // دریافت زمان فعلی به منطقه زمانی ایران
    $now = new DateTime('now', new DateTimeZone('Asia/Tehran'));
    $currentTime = $now->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO sms_inbox (from_number, to_number, body, received_at, is_read) 
                            VALUES (:from, :to, :body, :received_at, 0)");
    $stmt->execute([
        ':from' => $from,
        ':to'   => $to,
        ':body' => $body,
        ':received_at' => $currentTime
    ]);

    error_log("New SMS received from $from to $to at $currentTime");
    echo "OK";

} catch (PDOException $e) {
    error_log("Error saving SMS: " . $e->getMessage());
    echo "ERROR";
}
?>