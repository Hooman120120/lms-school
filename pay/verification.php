<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
//if (!isset($_SESSION['auth'])) {
//    header('location: ' . url('login.php'));
//    die();
//}
//Protecting against SESSION Hijaking
//if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) { 
//    session_unset();
//    header('location: ' . url('login.php'));
//    die();
//}

//* End Check Session JUST ONE Session
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    exit;
}
if (!isset($_SESSION['payment'])) {
    header('location: ' . url('index.php'));
    die();
}
if ($_SESSION['payment']['metod'] == 3) {
    if ($_SESSION['payment']['verify'] == 100) {
        $queryf = queryFactor($_SESSION['payment']['factorid']);
        $sumItem = $queryf->sumitem;
        $discount = $queryf->discountfee;
        $factorId = $queryf->idfactor;;
        $subtotal = $queryf->subtotal;
        $transition = $queryf->ref_id;
        $dateno = $queryf->dateresponse;
        $dateno = explode('-', $dateno);
        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
        $dateno = implode('/', $dateno);
        $dateno = $dateno . '-' . $queryf->timeresponse;
        $method = 'بدون وجه';
        $total = $queryf->factorfee;
        unset($_SESSION['payment']);

    }

}
if (isset($_SESSION['payment']) and $_SESSION['payment']['metod'] == 1) {



     include_once ('../inc/mellat/nusoap.php');
    $client = new nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
    $namespace = 'http://interfaces.core.sw.bps.com/';
   $terminalId = __TERMINAL_BPM;                    // Terminal ID
    $userName = __USERNAME_BPM;                    // Username
    $userPassword = __PASSWORD_BPM;                    // Password
    $orderId = $_POST['SaleOrderId'];        // Order ID

    $verifySaleOrderId = $_POST['SaleOrderId'];
    $verifySaleReferenceId = $_POST['SaleReferenceId'];

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

            $queryf = queryFactor($_SESSION['payment']['factorid']);
            $detail = 'افزایش موجودی از درگاه برای فاکتور ' . $_SESSION['payment']['factorid'];
            $insert = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ref_id,ip,os)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)';
            $query = $conn->prepare($insert);
            $query->execute([$queryf->username, $_SESSION['payment']['total'], 'موفق', $datenow, $timenow, $detail, 1, $_SESSION['payment']['factorid'], $verifySaleReferenceId, $ip, $os]);
            //
            $detailD = 'کاهش موجودی  برای فاکتور ' . $_SESSION['payment']['factorid'];
            $fee = $_SESSION['payment']['total'] * -1;
            $insertD = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os)
                            VALUES (?,?,?,?,?,?,?,?,?,?)';
            $query = $conn->prepare($insertD);
            $query->execute([$queryf->username, $fee, 'موفق', $datenow, $timenow, $detailD, 0, $_SESSION['payment']['factorid'], $ip, $os]);
            //
            $update = 'UPDATE dbfactor SET status=?,dateresponse=?,timeresponse=?,ref_id=?,ip=?,os=? WHERE idfactor=?';
            $in = $conn->prepare($update);
            $in->execute([$status, $datenow, $timenow, $verifySaleReferenceId, $ip, $os, $_SESSION['payment']['factorid']]);


            $factorId = $queryf->idfactor;
            $update_dbcourse = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
            $query_db = $conn->prepare($update_dbcourse);
            $query_db->execute([1, $factorId]);
            $querys = queryFactor($_SESSION['payment']['factorid']);
            $sumItem = $querys->sumitem;
            $discount = $querys->discountfee;

            $subtotal = $querys->subtotal;
            $transition = $querys->ref_id;
            $dateno = $querys->dateresponse;
            if ($dateno != NULL) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                $dateno = $querys->timeresponse . ' - ' . $dateno;
            } else {
                $dateno = 'اکنون';
            }
            $method = 'پرداخت آنلاین';
            $total = $querys->factorfee;
            // Register Quiz
    include_once '../quizconfig.php';
    include_once '../inc/function-quiz.php';
    $existingUid = checkUserquizByApi($queryf->username);
    if (!$existingUid) {
        $person = retPerson($queryf->username);
        $login = MD5(passUser($queryf->username));
        CreateUserByApi(['aid' => 1, 'username' => $queryf->username, 'password' => $login, 'active' => 1, 'expiration_date' => '3021-03-21', 'fname' => $person->fname, 'lname' => $person->lname, 'pass' => passUser($queryf->username)]);
    }
    $cou = 'SELECT dbcourse.*, codtbl_course.* FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse  WHERE dbcourse.numfactor=?';
    $qpo = $conn->prepare($cou);
    $qpo->execute([$queryf->idfactor]);
    $rew = $qpo->fetchAll(PDO::FETCH_OBJ);
    foreach ($rew as $tbt) {
        $uid = GetUserIdByApi($tbt->username);
        // باز کردن فایل لاگ برای دیباگ
        $logMsg = "\n--- بررسی فاکتور پایه: " . $tbt->idcourse . " ---\n";

        // ۱. دریافت آیدی کلاس
        $class_id = isset($tbt->class_id) ? $tbt->class_id : null;
        $logMsg .= "Class ID: " . ($class_id ? $class_id : "NULL یا خالی") . "\n";

        if ($class_id) {
            // ۲. دریافت ugid از دیتابیس
            $stmt = $conn->prepare("SELECT ugid FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class_info = $stmt->fetch(PDO::FETCH_ASSOC);

            $logMsg .= "نتیجه جستجوی کلاس در دیتابیس: " . ($class_info ? json_encode($class_info) : "کلاس پیدا نشد") . "\n";

            if ($class_info && !empty($class_info['ugid'])) {
                $ugid = $class_info['ugid'];
                $logMsg .= "UGID پیدا شد: " . $ugid . "\n";

                // ۳. گرفتن آیدی کاربر از سامانه آزمون
                
                $logMsg .= "آیدی کاربر در آزمون (UID): " . ($uid ? $uid : "پیدا نشد!") . "\n";

                if ($uid) {
                    // ۴. ثبت در گروه
                    $result = AddUserToGroupByApi($ugid, $uid);
                    $logMsg .= "نتیجه افزودن به گروه: " . json_encode($result) . "\n";
                } else {
                    $logMsg .= "خطا: کاربر در سامانه آزمون پیدا نشد، در نتیجه به گروه اضافه نمی‌شود.\n";
                }
            } else {
                $logMsg .= "خطا: UGID برای این کلاس در جدول classes خالی است.\n";
            }
        } else {
            $logMsg .= "خطا: کاربر هیچ کلاسی را انتخاب نکرده (class_id خالی است).\n";
        }
        
        $package_id = isset($tbt->package_id) ? $tbt->package_id : null;
        
        if ($package_id) {
            if ($uid) {
                $output = AddUserTestPaymentByApi($package_id, $uid);
            }
        }

        // ذخیره لاگ در فایل متنی در همان پوشه (موقتی برای پیدا کردن مشکل)
        file_put_contents('debug_log.txt', $logMsg, FILE_APPEND);
    }


            //
            unset($_SESSION['payment']);
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
        $up = 'UPDATE dbfactor SET status=?,ip=?,os=? WHERE idfactor=?';
        $in = $conn->prepare($up);
        $in->execute([$status, $ip, $os, $_SESSION['payment']['factorid']]);
        $_SESSION['Card'] = array(
            "factorid" => $_SESSION['payment']['factorid'],
            "metod" => 2,
            "sumItem" => $_SESSION['payment']['sumItem'],
            "subtotal" => $_SESSION['payment']['subtotal'],
            "total" => $_SESSION['payment']['total']
        );
        unset($_SESSION['payment']);
        header('location:' . url('pay/finance.php?delivery=00000000notok'));
        //-- پرداخت با خطا همراه بوده
        echo 'Error : ' . $_POST['ResCode'];
        exit;
    }
}






include_once '../temp.html';
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

                        </div>
                        <div class="card-body">
                            <h5 class="mb-3 font-weight-bold tx-14">گزارش پرداخت صورت حساب</h5>
                            <div class="card custom-card cart-details">
                                <div class="card-body">
                                    <div class="alert alert-success mb-0 text-center" role="alert">
                                        <h5 class="mb-3 font-weight-bold tx-14"> پرداخت صورتحساب
                                            شماره <?php echo $factorId ?></h5>
                                        <dl class="dlist-align">
                                            <dt class="">جمع موارد:</dt>

                                            <dd class="text-right mr-auto"> <?php echo number_format($sumItem) ?> تومان
                                            </dd>
                                        </dl>

                                        <dl class="dlist-align">
                                            <dt>تخفیف:</dt>
                                            <dd class="text-right text-danger mr-auto"><?php echo number_format($discount) ?>
                                                تومان
                                            </dd>

                                        </dl>

                                        <dl class="dlist-align">
                                            <dt>قیمت کل:</dt>

                                            <dd class="text-right mr-auto"><?php echo number_format($subtotal) ?>
                                                تومان
                                            </dd>
                                        </dl>
                                        <dl class="dlist-align">
                                            <dt>پرداختی از کیف پول:</dt>

                                            <dd class="text-right mr-auto"> <?php echo number_format($queryf->wallet) ?>
                                                تومان
                                            </dd>
                                        </dl>
                                        <dl class="dlist-align">
                                            <dt>شناسه تراکنش:</dt>

                                            <dd class="text-right mr-auto"> <?php echo $transition ?></dd>
                                        </dl>
                                        <dl class="dlist-align">
                                            <dt>تاریخ ثبت:</dt>

                                            <dd class="text-right mr-auto"><?php echo $dateno ?></dd>
                                        </dl>


                                        <dl class="dlist-align">
                                            <dt>نحوه پرداخت:</dt>


                                            <dt class="text-right  mr-auto">
                                                <?php echo $method ?>
                                            </dt>

                                        </dl>
                                        <hr>
                                        <dl class="dlist-align">

                                            <dt>مبلغ پرداختی:</dt>
                                            <dd class="text-right text-success tx-18  mr-auto">
                                                <strong> <?php echo number_format($total) ?> تومان </strong></dd>
                                        </dl>

                                    </div>
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
<?php
if (ActivatePublic('actsms_success_pay')) {
//Start send sms
    $text = $factorId . ';' . number_format($total);
    SendSms($_SESSION['auth']['tel1'], $text, 171700, $queryf->useradder);
// end send SMS
}
?>

<!-- Main Footer-->
<?php include '../footers.php';

?>
<!--End Footer-->


</body>
</html>



