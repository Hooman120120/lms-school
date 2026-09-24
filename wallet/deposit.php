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
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Check Session JUST ONE Session

include_once '../temp.html';
if (isset($_POST['deposit']) and $_POST['value'] != '') {
    if (filter_input(INPUT_POST, 'value', FILTER_VALIDATE_INT)) {
        $value = $_POST['value'];
        // if ($value >= 10000) {

            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $detail = 'افزایش موجودی حساب از طریق درگاه آنلاین';

            $deposit = 'INSERT INTO db_wallet (username,fee,datecreted,timecreated,detail_wallet,mode) VALUES (?,?,?,?,?,?)';
            $dep = $conn->prepare($deposit);
            $dep->execute([$_SESSION['auth']['user'], $value, $datenow, $timenow, $detail, 1]);
            $lastId = $conn->lastInsertId();
            $_SESSION['deposit'] = array(
                "value" => $value,
                "order" => $lastId
            );

            include_once ('../inc/mellat/nusoap.php');
            $terminalId = __TERMINAL_BPM;                    // Terminal ID
            $userName = __USERNAME_BPM;                    // Username
            $userPassword = __PASSWORD_BPM;                    // Password
            $orderId = time();                        // Order ID
            $amount = $value * 10;                        // Price / Rial
            $localDate = date('Ymd');                    // Date
            $localTime = date('Gis');                    // Time
            $additionalData = 'OrderWallet: '.$lastId.' ';
            $callBackUrl = url('wallet/verification.php');    // Callback URL
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


        // } else {
        //     $erorvalue = 1;

        // }
    }
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
    <title>شارژ حساب | <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row">
                    <div class=" col-xl-8 ">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">شارژ حساب</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($erorvalue) and $erorvalue == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        حداقل مبلغ شارژ حساب 10,000 تومان است
                                    </div>
                                <?php }
                                if (isset($_GET['delivery']) and $_GET['delivery'] == '00000000notok') { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        پرداخت ناموفق !
                                    </div>
                                <?php } ?>


                                <div class="alert alert-warning" role="alert">
                                    <span class="alert-inner--icon"><i class="fe fe-alert-circle"></i></span>

                                    درصورت پرداخت موفق و عدم شارژ حساب، از طریق پیام به پشتیبان اطلاع دهید!
                                </div>
                                <div class="alert alert-info" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

                                    مبلغ خود را بر مبنای <strong> تومان </strong> وارد نمایید!
                                </div>


                                <div class="row row-sm">
                                    <div class="col-xl-4">
                                        <form method="post">
                                            <div class="form-group">

                                                <p class="mg-b-10">مبلغ شارژ حساب</p>


                                                <div class="input-group mb-3">
                                                    <input aria-describedby="basic-addon2" aria-label="شارژ حساب"
                                                           name="value" class="form-control" value="500000"
                                                           placeholder="مبلغ شارژ به تومان" type="number">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                                <div class="pd-t-10">
                                                    <button class="btn ripple btn-block btn-success pd-x-20"
                                                            type="submit"
                                                            name="deposit">پرداخت
                                                        آنلاین
                                                    </button>
                                                </div>

                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class=" col-xl-4 ">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">نکاتی پیرامون پرداخت آنلاین</h5>
                            </div>
                            <div class="card-body">

                                <div class="listgroup-example ">
                                    <ul class="list-group">

                                        <ul class="list-group checked">
                                            <li class="list-group-item"><i class="fe fe-check ml-3 tx-13"
                                                                           aria-hidden="true"></i> سعی کنید در لحظه
                                                پرداخت آنلاین از اینترنت پایدار استفاده نمایید.
                                            </li>
                                            <li class="list-group-item"><i class="fe fe-check ml-3 tx-13"
                                                                           aria-hidden="true"></i> از زمان درخواست
                                                پرداخت تا گرفتن تاییدیه پرداخت از بارگذاری مجدد صفحه (Refresh ) استفاده
                                                نکنید.
                                            </li>
                                            <li class="list-group-item"><i class="fe fe-check ml-3 tx-13"
                                                                           aria-hidden="true"></i>با توجه به فرآیندهای
                                                ایمن پرداخت آنلاین، دکمه بازگشت به صفحه قبل (Back) فعال نبوده و درصورت
                                                استفاده اطلاعات پرداخت از دست خواهد رفت
                                            </li>
                                            <li class="list-group-item"><i class="fe fe-check ml-3 tx-13"
                                                                           aria-hidden="true"></i>درصورت بروز مشکل در
                                                پرداخت آنلاین،کسر وجه از حساب و افزوده نشدن موجودی حساب، از طریق پیام به
                                                پشتیبان واحد مالی ، اطلاع دهید.
                                            </li>

                                        </ul>
                                        </li>

                                    </ul>
                                </div>


                            </div>
                        </div>
                    </div>
                </div>


                <!-- Row -->

            </div>
        </div>
    </div>


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>