<?php

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_actwallet')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!isset($_GET['order'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!CheckOrderNotSuccess($_GET['order'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['verify'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $track = $_POST['track'];
        if (CheckTrackId($track)) {
            $orderId = $_POST['numfactor'];
            $order = queryWallet($orderId);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $ip = GetIp();
            $os = GetOs();
            $ret = retPerson($order->username);
            if (ActivatePublic('actsms_success_card')) {
                //Start send sms
                $text = number_format($order->fee);
                SendSms($ret->tel1, $text, 171706,$order->username);
                // end send SMS
            }
            if (isset($_POST['duedate']) and $_POST['duedate'] != '') {
                $duedate = $_POST['duedate'];
                $duedate = explode('/', $duedate);
                $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
                $duedate = implode('-', $duedate);
            } else {
                $duedate = $order->datecreted;
            }
            $update = 'UPDATE db_wallet SET status=? ,datecreted=?,ref_id=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE order_id=?';
            $query = $conn->prepare($update);
            $query->execute(['موفق', $duedate, $track, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $orderId]);
            echo '<script>window.close() </script>';
        } else {
            echo '   <div class="alert alert-danger" role="alert">
                      <span class="alert-inner--icon"><i class="fe fe-lock"></i></span>
                          <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                            </button>
                                     تایید نشد! کد پیگیری تکراری است
                       </div>';
        }
    }
}
include_once '../../temp.html';
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
    <title> تایید واریزی <?php echo $_GET['order'] ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->
<!-- Page -->
<div class="page">

    <div class="alert alert-info" role="alert">
        <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

        پس از تایید این صفحه بسته شده و صفحه قبلی را بارگذاری مجدد (Refresh /F5) نمایید
    </div>

    <div class="card-body">
        <form method="post" data-parsley-validate="">
            <div class="form-group">
                <div class="row row-sm">
                    <div class="col-6">
                        <p class="mg-b-10">مبلغ اعلامی:</p>
                        <div class="input-group mb-3 text-success tx-30 text-center">
                            <?php $factor = queryWallet($_GET['order']); ?>
                            <?php echo number_format($factor->fee); ?> تومان
                        </div>
                    </div>
                    <div class="col-6">
                        <p class="mg-b-10">زمان واریز :</p>
                        <div class="input-group mb-3 text-danger tx-20 text-center">
                            <?php
                            $dateno = $factor->datecreted;
                            $dateno = explode('-', $dateno);
                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                            $dateno = implode('/', $dateno);
                            echo $dateno;
                            ?>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">

                    <div class="col-6">

                        <p class="mg-b-10">کد پیگیری / شناسه تراکنش<span class="text-danger">*</span></p>
                        <div class="input-group mb-3">
                            <input class="form-control" name="track"
                                   placeholder=" کد پیگیری / شناسه تراکنش"
                                   required="" type="text">
                        </div>

                    </div>
                    <div class="col-6">
                        <p class="mg-b-10">تاریخ واریز وجه(درصورت مغایرت)</p>

                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                </div>
                            </div>
                            <input class="form-control fc-datepicker"
                                   placeholder="در صورت صحیح نبودن تاریخ، ثبت شود"
                                   type="text" name="duedate">
                        </div>
                    </div>
                </div>
                <?php
                $hash = bin2hex(random_bytes(32));
                $_SESSION['token'] = $hash;
                ?>
                <input type="hidden" name="token" value="<?php echo $hash ?>"
                       required>
                <input type="hidden" name="numfactor" value="<?php echo $_GET['order'] ?>"
                       required>
                <div class="col-12 pd-t-30 text-center">
                    <button class="btn ripple  btn-success pd-x-20"
                            type="submit"
                            name="verify">تایید واریزی و افزایش به کیف پول
                    </button>

                </div>

            </div>
        </form>
    </div>


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>