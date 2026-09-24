<?php
// callback_handler.php
require_once '../dbconfig.php';
// --- دریافت پارامترهای POST از بانک (طبق مستندات ملت) ---
$bank_params = $_POST;
file_put_contents(__DIR__ . '/callback_debug.txt',
    date('Y-m-d H:i:s') . " - Full POST: " . print_r($bank_params, true) . "\n",
    FILE_APPEND
);

// --- استخراج پارامترهای کلیدی ---
$saleOrderId = $_POST['SaleOrderId'] ?? null; // همان orderId ما
$refId = $_POST['RefId'] ?? null;
$resCode = $_POST['ResCode'] ?? null;
$saleRefId = $_POST['SaleReferenceId'] ?? null;

// --- خواندن mapping.json برای تشخیص نوع تراکنش ---
$source = null;
$type = null;
$amount = 0;

if ($saleOrderId && file_exists(__DIR__ . '/mapping.json')) {
    $mapping = json_decode(file_get_contents(__DIR__ . '/mapping.json'), true) ?? [];
    if (isset($mapping[$saleOrderId])) {
        $source = $mapping[$saleOrderId]['source'] ?? null;
        $type = $mapping[$saleOrderId]['type'] ?? 'purchase';
        $amount = $mapping[$saleOrderId]['amount'] ?? 0;
    }
}
if (!$saleOrderId || !$source) {
    die("خطا: اطلاعات پرداخت یافت نشد.");
}

// --- (اختیاری) ارسال به سرویس verify (در صورت نیاز) ---
// (می‌توانید این بخش را فعال یا غیرفعال کنید)
// --- تعیین آدرس بازگشت بر اساس نوع تراکنش ---
$baseRedirect = '';
if ($type == 'deposit') {
    $baseRedirect = 'https://my.dast-garmi.ir/deposit_result.php';
} else {
    $baseRedirect = 'https://my.dast-garmi.ir/payment_result.php';
}

// --- پارامترهای ارسال به سامانه آزمون ---
$query_params = [
    'orderId' => $saleOrderId,
    'refId' => $refId,
    'resCode' => $resCode,
    'saleRefId' => $saleRefId,
    'amount' => $amount,
    'source' => $source,
    'type' => $type,
    'status' => ($resCode == '0' || $resCode === 0) ? 'success' : 'failed'
];

// هش امضا (اختیاری)
$secret_key = 'YOUR_SECRET_KEY_HERE';
$query_params['hash'] = md5($saleOrderId . $refId . $resCode . $amount . $secret_key);

$redirect_url = $baseRedirect . '?' . http_build_query($query_params);

// --- پاک کردن mapping.json (اختیاری) ---
if (file_exists(__DIR__ . '/mapping.json')) {
    $mapping = json_decode(file_get_contents(__DIR__ . '/mapping.json'), true) ?? [];
    unset($mapping[$saleOrderId]);
    file_put_contents(__DIR__ . '/mapping.json', json_encode($mapping, JSON_PRETTY_PRINT));
}

// --- ریدایرکت کاربر به سامانه آزمون ---
file_put_contents(__DIR__ . '/redirect_log.txt',
    date('Y-m-d H:i:s') . " - Redirecting to: $redirect_url \n",
    FILE_APPEND
);

header('Location: ' . $redirect_url);
exit;