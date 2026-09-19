<?php
// initiate_payment.php
require_once '../dbconfig.php'; // اگر نیاز به لاگ دارید

// --- دریافت پارامترها از GET ---
$amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
$orderId = isset($_GET['orderId']) ? $_GET['orderId'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : 'purchase'; // 'deposit' یا 'purchase'
$source = isset($_GET['source']) ? $_GET['source'] : 'system_b';

// اعتبارسنجی
if ($amount < 1000 || !$orderId) {
    die('پارامترهای درخواست نامعتبر است.');
}

// --- اطلاعات ثابت درگاه ملت ---
$terminalId = 7877055;
$userName = "7877055";
$userPassword = "98706289";

$localDate = date('Ymd');
$localTime = date('Gis');

// --- ساخت callBackUrl (به callback_handler.php) ---
$callBackUrl = 'https://my.honaramoozesh.ir/other_payments/callback_handler.php?orderId=' . $orderId;

// --- ذخیره نقشه در mapping.json ---
$mapping = [];
if (file_exists(__DIR__ . '/mapping.json')) {
    $mapping = json_decode(file_get_contents(__DIR__ . '/mapping.json'), true) ?? [];
}
$mapping[$orderId] = [
    'source' => $source,
    'type' => $type,
    'amount' => $amount
];
file_put_contents(__DIR__ . '/mapping.json', json_encode($mapping, JSON_PRETTY_PRINT));

// --- تماس با وب‌سرویس بانک ملت ---
include_once '../inc/mellat/nusoap.php'; // فرض می‌کنیم فایل nusoap.php در همین مسیر موجود است
$client = new nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
$namespace = 'http://interfaces.core.sw.bps.com/';

$parameters = array(
    'terminalId' => $terminalId,
    'userName' => $userName,
    'userPassword' => $userPassword,
    'orderId' => $orderId,
    'amount' => $amount,
    'localDate' => $localDate,
    'localTime' => $localTime,
    'additionalData' => ($type == 'deposit' ? 'Wallet Deposit' : 'Product Purchase'),
    'callBackUrl' => $callBackUrl,
    'payerId' => 0
);

$result = $client->call('bpPayRequest', $parameters, $namespace);

if ($client->fault) {
    die("خطا در ارتباط با بانک: " . $client->fault);
} else {
    $err = $client->getError();
    if ($err) {
        die("خطا: " . $err);
    } else {
        $res = explode(',', $result);
        $ResCode = $res[0];
        if ($ResCode == "0") {
            // موفقیت: هدایت به درگاه
            $refId = $res[1];
            echo '<form name="myform" action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat" method="POST">
                    <input type="hidden" name="RefId" value="' . $refId . '">
                  </form>
                  <script type="text/javascript">document.forms[0].submit();</script>';
            exit;
        } else {
            die("خطا در دریافت RefId: کد خطا " . $ResCode);
        }
    }
}
?>