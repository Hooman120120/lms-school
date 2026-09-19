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

//--End of Protecting against SESSION Hijaking
//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Check Session JUST ONE Session
if (!isset($_SESSION['deposit'])) {
    header('location: ' . url('wallet/deposit.php'));
    die();
}
include_once('../inc/mellat/nusoap.php');
$client = new nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
$namespace = 'http://interfaces.core.sw.bps.com/';
$terminalId = __TERMINAL_BPM;                    // Terminal ID
$userName = __USERNAME_BPM;                    // Username
$userPassword = __PASSWORD_BPM;                    // Password
$orderId = $_POST['SaleOrderId'];        // Order ID

$verifySaleOrderId = $_POST['SaleOrderId'];
if(isset($_POST['SaleReferenceId'])){
$verifySaleReferenceId = $_POST['SaleReferenceId'];
}
$parameters = array(
    'terminalId' => $terminalId,
    'userName' => $userName,
    'userPassword' => $userPassword,
    'orderId' => $orderId,
    'saleOrderId' => $verifySaleOrderId,
    'saleReferenceId' => $verifySaleReferenceId);
// Call the SOAP method
$result = $client->call('bpVerifyRequest', $parameters, $namespace);
if ($result == '0') {
    //-- وریفای به درستی انجام شد٬ درخواست واریز وجه
    // Call the SOAP method
    $result = $client->call('bpSettleRequest', $parameters, $namespace);
    if ($result == '0') {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $status = 'موفق';
        $ip = GetIp();
        $os = GetOs();

        $up = 'UPDATE db_wallet SET status=? , datecreted=?,timecreated=?,ref_id=?,ip=?,os=?  WHERE order_id=? ';
        $in = $conn->prepare($up);
        $in->execute([$status, $datenow, $timenow, $verifySaleReferenceId, $ip, $os, $_SESSION['deposit']['order']]);
        //Start send sms
        if (ActivatePublic('actsms_success_pay')) {
            $text = $_SESSION['deposit']['value'];
            SendSms($_SESSION['auth']['tel1'], $text, 171701, $_SESSION['auth']['user']);
        }
        // end send SMS

    } else {
        //-- در درخواست واریز وجه مشکل به وجود آمد. درخواست بازگشت وجه داده شود.
        $client->call('bpReversalRequest', $parameters, $namespace);
        echo 'Error : ' . $result;
    }
} else {
    //-- وریفای به مشکل خورد٬ نمایش پیغام خطا و بازگشت زدن مبلغ
    $client->call('bpReversalRequest', $parameters, $namespace);
    echo 'Error : ' . $result;
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $status = 'ناموفق';
    $ip = GetIp();
    $os = GetOs();
    $up = 'UPDATE db_wallet SET status=? , datecreted=?,timecreated=?,ip=?,os=?  WHERE order_id=? ';
    $in = $conn->prepare($up);
    $in->execute([$status, $datenow, $timenow, $ip, $os, $_SESSION['deposit']['order']]);


    header('location: deposit.php?delivery=00000000notok');
}

?>

    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
        <meta name="description" content="e">
        <meta name="author" content="hamsepar.ir">
        <!-- Title -->
        <title>گزارش پرداخت حساب| <?php echo __TITLE ?></title>
        <?php include_once '../inc/link.php'; ?>

    </head>
    <body class="main-body leftmenu">
    <!-- Loader -->
    <div id="global-loader">
        <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
    </div>
    <!-- End Loader -->
    <!-- Page -->
    <div class="page">
        <?php
        include_once '../menu.php';
        include_once '../header.php';
        ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="col-lg-12 col-xl-12 col-md-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">شارژ حساب</h5>
                            </div>
                            <div class="card-body">

                                <div>
                                    <div class="alert alert-success mb-0 text-center" role="alert">
                                        <h4 class="alert-heading">پرداخت موفق!</h4>
                                        <p>شارژ حساب با موفقیت انجام شد </p>
                                        <hr>


                                        <p class="mb-0"> مبلغ واریز
                                            :<strong><?php echo number_format($_SESSION['deposit']['value']) ?>
                                                تومان </strong>
                                        </p>
                                        <p class="mb-0"> شناسه تراکنش
                                            :<strong><?php echo $verifySaleReferenceId; ?></strong></p>
                                        <p class="mb-0"> تاریخ واریز
                                            :<strong><?php $convert = explode('-', $datenow);
                                                $convert = gregorian_to_jalali($convert[0], $convert[1], $convert[2]);
                                                $convert = implode('/', $convert);
                                                echo $convert; ?></strong></p>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <!-- End Row -->
                    </div>
                </div>
            </div>


        </div>
    </div>


    <!-- Main Footer-->
    <?php include '../footers.php';
    unset($_SESSION['deposit']);
    ?>
    <!--End Footer-->


    </body>
    </html>
