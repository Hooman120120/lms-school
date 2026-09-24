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
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    exit;
}

if (!isset($_SESSION['Card'])) {
    header('location: ' . url('pay/finance.php'));
    die();
}

if ($_SESSION['Card']['metod'] == 2) {
    if (EntityDBserver($_SESSION['auth']['user'])) {
        $count = countCardDB();
        $rand = rand(1, $count);
        $cardInfo = resultCardDB($rand);
        $insertCard = 'INSERT INTO card_server(username,card_num) VALUES (?,?)';
        $querycard = $conn->prepare($insertCard);
        $querycard->execute([$_SESSION['auth']['user'], $cardInfo['card_num']]);
    }

}
if (isset($_POST['deposit'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (bankCardCheck($_POST['card-debit'])) {
            if (entityCard($_POST['card-debit'])) {
                $duedate = $_POST['duedate'];
                $duedate = explode('/', $duedate);
                $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
                $duedate = implode('-', $duedate);
                $card = $_POST['card-debit'];
                date_default_timezone_set("Iran");
                $ip = GetIp();
                $os = GetOs();
                $status = 'در انتظار بررسی';
                $update = 'UPDATE dbfactor SET status=?,dateresponse=?,card_pan=?,ip=?,os=? WHERE idfactor=?';
                $in = $conn->prepare($update);
                $in->execute([$status, $duedate, $card, $ip, $os, $_SESSION['Card']['factorid']]);

                $_SESSION['CDcard'] = array(
                    "factorid" => $_SESSION['Card']['factorid']

                );
                unset($_SESSION['Card']);
                header('location: ' . url('pay/finance.php'));
                die();

            } else {
                $carderor = 21;
            }
        } else {
            $erorvalue = 1;
        }
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
    <title>پرداخت کارت به کارت | <?php echo __TITLE ?></title>
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
                    <div class=" col-xl-8  ">
                        <div class="card custom-card ">
                            <div class="card-header">
                                <?php

                                ?>
                                <h5 class="mb-3 font-weight-bold tx-14 ">پرداخت فاکتور شماره:<span
                                            class="tx-20 text-danger"> <?php echo $_SESSION['Card']['factorid']; ?># </span>از
                                    طریق کارت به کارت </h5>
                            </div>
                            <div class="card-body">


                                <div class="alert alert-warning" role="alert">
                                    <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                                    تایید واریزی های کارت به کارت در ساعات اداری صورت می پذیرد
                                </div>
                                <div class="alert alert-info" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

                                    چنانچه از واریزی صورتحساب به شماره کارت نمایان شده اطمینان دارید فرم زیر را پر
                                    نمایید
                                </div>
                                <?php if (isset($erorvalue) and $erorvalue == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class="fa fa-bell"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        شماره کارت وارد شده معتبر نمی باشد
                                    </div>
                                <?php } ?>
                                <?php if (isset($erorNot) and $erorNot == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class="fa fa-bell"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        مبلغ واریز می بایست بین 1000 تومان تا 10,000,000 تومان باشد
                                    </div>
                                <?php }
                                if (isset($carderor) and $carderor == 21) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class="fa fa-bell"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        لطفاَ شماره کارتی که متعلق به خودتان و از آن واریز وجه کردید را وارد کنید
                                    </div>
                                <?php } ?>
                                <form method="post" data-parsley-validate="">
                                    <div class="form-group">
                                        <div class="row row-sm">
                                            <div class="col-lg-4">
                                                <p class="mg-b-10">مبلغ کارت به کارت</p>
                                                <div class="input-group mb-3 text-success tx-30 text-center">
                                                    <?php echo number_format($_SESSION['Card']['total']) ?> تومان
                                                </div>
                                            </div>


                                            <div class="col-lg-4">

                                                <p class="mg-b-10">شماره کارت شما<span class="text-danger">*</span></p>
                                                <div class="input-group mb-3">
                                                    <input class="form-control" name="card-debit"
                                                           placeholder="که از آن واریز وجه نمودید"
                                                           required="" type="text" data-placement="bottom"
                                                           data-bs-toggle="tooltip"
                                                           title="اعداد انگلیسی وارد کنید">
                                                </div>

                                            </div>
                                            <div class="col-lg-4">

                                                <p class="mg-b-10">تاریخ واریز وجه<span class="text-danger">*</span></p>

                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6"></i>
                                                        </div>
                                                    </div>
                                                    <input class="form-control fc-datepicker"
                                                           placeholder="1402/08/10"
                                                           type="text" name="duedate" required="">
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input type="hidden" name="token" value="<?php echo $hash ?>"
                                                           required>
                                                </div>


                                            </div>
                                        </div>
                                        <div class="row row-sm">
                                            <div class="col-lg-3"></div>
                                            <div class="col-lg-6">


                                                <div class="pd-t-10">
                                                    <button class="btn ripple btn-block btn-success pd-x-20"
                                                            type="submit"
                                                            name="deposit"> ارجاع به کارشناس جهت بررسی
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                                <hr>

                                <span class="alert-inner--icon"><i class="fe fe-alert-octagon"></i></span>
                                <span class="alert-inner--text"><strong></strong> شما می توانید صورتحساب خود را تا قبل از ثبت اطلاعات کارت به کارت از <a
                                            class="badge badge-info" href="<?php echo url('pay/finance.php') ?>">قسمت صورتحساب </a> بصورت آنلاین پرداخت نمایید. </span>
                                <div class="pd-t-10">
                                    <span class="alert-inner--icon"><i class="fe fe-alert-octagon"></i></span>
                                    <span class="alert-inner--text"><strong></strong>                                همچنین درصورت ثبت واریزی کارت به کارت از
                                    <a class="badge badge-info" href="<?php echo url('pay/finance.php') ?>">قسمت صورتحساب </a> روند بررسی و تایید را مشاهده و پیگیری نمایید </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class=" col-xl-4 ">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">اطلاعات شماره کارت جهت واریز</h5>
                            </div>
                            <?php
                            $cerdit = recieveDetailCARD($_SESSION['auth']['user']);
                            ?>
                            <div class="card-body">


                                <img alt="تصویر" class="img-fluid b-img"
                                     src="<?php echo url($cerdit['card_pic']); ?>">


                                <div class="alert alert-secondary pd-t-20" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-credit-card"></i></span>

                                    شماره کارت : <?php echo $cerdit['card_num']; ?>
                                </div>


                                <div class="alert alert-secondary" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-id-card"></i></span>

                                    به نام <?php echo $cerdit['card_name']; ?>
                                </div>


                                <div class="alert alert-secondary" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-bank"></i></span>

                                    نزد بانک <?php echo $cerdit['card_bank']; ?>
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
