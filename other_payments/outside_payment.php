<?php
// outside_payment.php - اصلاح شده با ساختار صحیح mapping
require_once '../dbconfig.php';

// --- ۱. تشخیص سامانه مبدأ از هدر ---
$headers = getallheaders();
$source = $headers['x-system-id'] ?? 'unknown';

if (!in_array($source, ['system_b', 'system_c'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Source system not recognized. Send X-System-ID: system_b or system_c']));
}

// --- ۲. دریافت و دیکد کردن JSON ورودی ---
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON input']));
}

// --- ۳. بررسی وجود فیلدهای ضروری ---
$required = ['terminalId', 'userName', 'userPassword', 'orderId', 'amount', 'localDate', 'localTime', 'callBackUrl'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Missing required field: $field"]));
    }
}

// --- ۴. استخراج مقادیر ---
$terminalId = $data['terminalId'];
$userName = $data['userName'];
$userPassword = $data['userPassword'];
$orderId = $data['orderId'];
$amount = $data['amount'];
$localDate = $data['localDate'];
$localTime = $data['localTime'];
$additionalData = $data['additionalData'] ?? '';
$payerId = $data['payerId'] ?? 0;
$type = $data['type'] ?? 'purchase'; // 👈 اضافه شد: نوع تراکنش (purchase یا deposit)

// --- ۵. ساخت callBackUrl جدید (با پارامترهای orderId و source) ---
$new_callback = 'https://my.honaramoozesh.ir/other_payments/callback_handler.php?orderId=' . $orderId . '&source=' . $source;

// --- ۶. ساخت XML SOAP برای متد bpPayRequest ---
$soap_xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="http://interfaces.core.sw.bps.com/">
   <soapenv:Header/>
   <soapenv:Body>
      <ser:bpPayRequest>
         <terminalId>' . htmlspecialchars($terminalId) . '</terminalId>
         <userName>' . htmlspecialchars($userName) . '</userName>
         <userPassword>' . htmlspecialchars($userPassword) . '</userPassword>
         <orderId>' . htmlspecialchars($orderId) . '</orderId>
         <amount>' . htmlspecialchars($amount) . '</amount>
         <localDate>' . htmlspecialchars($localDate) . '</localDate>
         <localTime>' . htmlspecialchars($localTime) . '</localTime>
         <additionalData>' . htmlspecialchars($additionalData) . '</additionalData>
         <callBackUrl>' . htmlspecialchars($new_callback) . '</callBackUrl>
         <payerId>' . htmlspecialchars($payerId) . '</payerId>
      </ser:bpPayRequest>
   </soapenv:Body>
</soapenv:Envelope>';

// --- ۷. ذخیره نقشه با ساختار صحیح (آرایه‌ای) ---
$mapping = [];
if (file_exists(__DIR__ . '/mapping.json')) {
    $mapping = json_decode(file_get_contents(__DIR__ . '/mapping.json'), true) ?? [];
}
$mapping[$orderId] = [
    'source' => $source,
    'type' => $type,        // 👈 اضافه شد
    'amount' => (int)$amount // 👈 اضافه شد
];
file_put_contents(__DIR__ . '/mapping.json', json_encode($mapping, JSON_PRETTY_PRINT));

// --- ۸. ارسال SOAP به درگاه ملت با cURL ---
$ch = curl_init('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_xml);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'SOAPAction: "http://tempuri.org/PayRequest"',
    'Content-Length: ' . strlen($soap_xml)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- ۹. بازگرداندن پاسخ ---
http_response_code($http_code);
header('Content-Type: text/xml; charset=utf-8');
echo $response;
exit;
?>