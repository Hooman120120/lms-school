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

if (EntityDBserver($_SESSION['auth']['user'])) {
    $count = countCardDB();
    $rand = rand(1, $count);
    $cardInfo = resultCardDB($rand);
    $insertCard = 'INSERT INTO card_server(username,card_num) VALUES (?,?)';
    $querycard = $conn->prepare($insertCard);
    $querycard->execute([$_SESSION['auth']['user'], $cardInfo['card_num']]);
}
if (isset($_POST['deposit'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $fee = intval($_POST['feecard']);
        if ($fee > 10000000 || $fee < 1000) {
            $erorNot = 1;
        } else {
            if (bankCardCheck($_POST['card-debit'])) {
                if (entityCard($_POST['card-debit'])) {
                    $duedate = $_POST['duedate'];
                    $duedate = explode('/', $duedate);
                    $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
                    $duedate = implode('-', $duedate);
                    $card = $_POST['card-debit'];
                    date_default_timezone_set("Iran");
                    $detail = 'افزایش موجودی حساب از طریق کارت به کارت';
                    $ip = GetIp();
                    $os = GetOs();
                    $status = 'در انتظار بررسی';
                    $deposit = 'INSERT INTO db_wallet (username,fee,status,datecreted,detail_wallet,mode,card_pan,ip,os) VALUES (?,?,?,?,?,?,?,?,?)';
                    $dep = $conn->prepare($deposit);
                    $dep->execute([$_SESSION['auth']['user'], $fee, $status, $duedate, $detail, 1, $card, $ip, $os]);
                    $lastId = $conn->lastInsertId();
                    if ($lastId) {
                        $verify = 100;
                    }
                } else {
                    $carderor = 21;
                }

            } else {
                $erorvalue = 1;
            }
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
                                <h5 class="mb-3 font-weight-bold tx-14">شارژ حساب از طریق کارت به کارت </h5>
                            </div>
                            <div class="card-body">


                                <div class="alert alert-warning" role="alert">
                                    <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                                    تایید واریزی های کارت به کارت در ساعات اداری صورت می پذیرد
                                </div>
                                <div class="alert alert-info" role="alert">
                                    <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

                                    چنانچه از واریزی به شماره کارت نمایان شده اطمینان دارید فرم زیر را پر نمایید
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
                                <?php } ?>
                                <?php if (isset($verify) and $verify == 100) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class="fa fa-bell"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">

                                            <span aria-hidden="true">×</span>
                                        </button>
                                        درخواست بررسی مبلغ: <?php echo number_format($fee) ?> تومان با موفقیت به کارشناس
                                        واحد مالی ارسال شد
                                    </div>
                                <?php } ?>
                                <?php if (isset($carderor) and $carderor == 21) { ?>
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
                                                <div class="input-group mb-3">
                                                    <input min="1000" max="10000000" aria-describedby="basic-addon2"
                                                           aria-label="شارژ حساب"
                                                           name="feecard" class="form-control" value="500000"
                                                           placeholder="مبلغ شارژ به تومان" type="number" required="">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" id="basic-addon2">تومان</span>
                                                    </div>
                                                </div>
                                            </div>


                                            <div class="col-lg-4">

                                                <p class="mg-b-10">شماره کارت شما<span class="text-danger">*</span></p>
                                                <div class="input-group mb-3">
                                                    <input class="form-control" name="card-debit"
                                                           placeholder=" که از آن واریز وجه کردید"
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

                                <div class="listgroup-example ">
                                    <ul class="list-group">

                                        <img alt="تصویر" class="img-fluid b-img"
                                             src="<?php echo url($cerdit['card_pic']); ?>">
                                        <li>

                                            <div class="alert alert-secondary" role="alert">
                                                <span class="alert-inner--icon"><i class="fa fa-credit-card"></i></span>

                                                شماره کارت : <?php echo $cerdit['card_num']; ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="alert alert-secondary" role="alert">
                                                <span class="alert-inner--icon"><i class="fa fa-id-card"></i></span>

                                                به نام <?php echo $cerdit['card_name']; ?>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="alert alert-secondary" role="alert">
                                                <span class="alert-inner--icon"><i class="fa fa-bank"></i></span>

                                                نزد بانک <?php echo $cerdit['card_bank']; ?>
                                            </div>
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