<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re= sessionid($_SESSION['auth']['user']);
if(session_id()!= $re['session_id']){
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Check Session JUST ONE Session
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    exit;
}
if(!isset($_SESSION['payment'])){
    header('location: ' . url('pay/finance.php'));
    die();
}
if($_SESSION['payment']['metod']==1){

    include_once ('../inc/mellat/nusoap.php');
    $terminalId = __TERMINAL_BPM;                    // Terminal ID
    $userName = __USERNAME_BPM;                    // Username
    $userPassword = __PASSWORD_BPM;                    // Password
    $orderId = $_SESSION['payment']['factorid'];                        // Order ID
    $amount = $_SESSION['payment']['total'] * 10;                        // Price / Rial
    $localDate = date('Ymd');                    // Date
    $localTime = date('Gis');                    // Time
    $additionalData = 'factorNumber: '.$_SESSION['payment']['factorid'].' ';
    $callBackUrl = "https://my.honaramoozesh.ir/pay/verification.php";    // Callback URL
    $payerId = 0;

//-- تبدیل اطلاعات به آرایه برای ارسال به بانک
    $parameters = array(
        'terminalId' => $terminalId,
        'userName' => $userName,
        'userPassword' => $userPassword,
        'orderId' => $orderId,
        'amount' => $amount,
        'localDate' => $localDate,
        'localTime' => $localTime,
        'additionalData' => $additionalData,
        'callBackUrl' => $callBackUrl,
        'payerId' => $payerId);

    $client = new nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
    $namespace = 'http://interfaces.core.sw.bps.com/';
    $result = $client->call('bpPayRequest', $parameters, $namespace);
//-- بررسی وجود خطا
    if ($client->fault) {
        //-- نمایش خطا
        echo "There was a problem connecting to Bank";
        exit;
    } else {
        $err = $client->getError();
        if ($err) {
            //-- نمایش خطا
            echo "Erore : " . $err;
            exit;
        } else {
            $res = explode(',', $result);
            $ResCode = $res[0];
            if ($ResCode == "0") {


                //-- انتقال به درگاه پرداخت
                echo '<form name="myform" action="https://bpm.shaparak.ir/pgwchannel/startpay.mellat" method="POST">
					<input type="hidden" id="RefId" name="RefId" value="' . $res[1] . '">
				</form>
				<script type="text/javascript">window.onload = formSubmit; function formSubmit() { document.forms[0].submit(); }</script>';

                exit;
            } else {
                //-- نمایش خطا
                echo "Error : " . $result;
                exit;
            }
        }
    }


} else {
    $erorvalue = 1;

}




