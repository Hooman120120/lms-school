<?php
// ajax/get_sms_detail.php (نسخه ساده)

// اتصال به دیتابیس
require_once '../../../dbconfig.php';
require_once '../../../inc/jdf.php';
header('Content-Type: application/json; charset=utf-8');

// بررسی احراز هویت
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit;
}

// بررسی پارامتر id
$smsId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($smsId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه پیامک نامعتبر است']);
    exit;
}



try {
    $sql = "SELECT * FROM sms_logs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$smsId]);
    $sms = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sms) {
        echo json_encode(['success' => false, 'message' => 'پیامک یافت نشد']);
        exit;
    }

    // پردازش گیرندگان
    if (!empty($sms['recipients'])) {
        $recipients = json_decode($sms['recipients'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $sms['recipients'] = $recipients;
        }
    }

    // تبدیل تاریخ
    if (!empty($sms['send_date'])) {
        $dateParts = explode('-', $sms['send_date']);
        if (count($dateParts) === 3) {
            $jDate = gregorian_to_jalali($dateParts[0], $dateParts[1], $dateParts[2]);
            $sms['persian_date'] = implode('/', $jDate);
        }
    }

    echo json_encode([
        'success' => true,
        'sms' => $sms
    ]);

} catch (Exception $e) {
    error_log("SMS detail error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ]);
    exit;
}