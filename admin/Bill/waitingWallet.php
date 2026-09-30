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
if (!inst_feature('wallet')) {
    http_response_code(404);
    exit('کیف پول در نسخه مدرسه‌ای فعال نیست.');
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
//* End Check Session JUST ONE Session
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $ret = retPerson($_GET['call']);
    $tel = $ret->tel1;
    $name = $ret->fname . ' ' . $ret->lname;
    Call($tel, intval($_SESSION['admin']['dakheli']), $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: waitingWallet.php');
    die();
}
if (isset($_GET['unverify']) and $_GET['unverify'] != '') {
    AdminRequire('finance.wallet.unverify');
    if (CheckOrderNotSuccess($_GET['unverify'])) {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $update = 'UPDATE db_wallet SET status=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE order_id=?';
        $qupdate = $conn->prepare($update);
        $qupdate->execute(['رد شده', $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $_GET['unverify']]);
        if (ActivatePublic('actsms_success_card')) {
            //Start send sms
            $wallet = queryWallet($_GET['unverify']);
            $text = number_format($wallet->fee);
            $ret = retPerson($wallet->username);
            SendSms($ret->tel1, $text, 171707, $wallet->username);
            // end send SMS
        }
        header('location: waitingWallet.php');
        die();
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
    <title> تایید کیف پول | <?php echo __TITLE ?></title>
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
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <?php
                if (AdminCanSee('finance.wallet')) { ?>


                    <!-- Row -->
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <?php if (isset($call) and $call === 1) { ?>
                                        <div class="alert alert-outline-success  text-center " role="alert">
                                            <div class="mg-t-10">
                                                <img style="width: 75px; height: 75px;"
                                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                                با <?php echo $name ?>

                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            </div>

                                        </div>
                                    <?php } ?>
                                    <div class="row">
                                        <div class="col-10">
                                            <div>
                                                <div>
                                                    <div style="display:flex; align-items:center; gap:12px;">
                                                        <h6 class="main-content-label mb-1">درخواست افزایش موجودی کیف پول</h6>

                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش درخواست‌های افزایش موجودی کیف پول که توسط کاربران ثبت شده‌اند نمایش داده می‌شوند. واریزهای انجام‌شده از طریق کیف پول پس از بررسی اطلاعات پرداخت، قابل تأیید یا رد هستند و موجودی کیف پول کاربر بر اساس نتیجه بررسی به‌روزرسانی خواهد شد."
                                                              aria-label="راهنمای درخواست افزایش موجودی کیف پول"
                                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                    </div>

                                                    <p class="text-muted card-sub-title">
                                                        واریزی‌های ثبت‌شده به واسطه کیف پول
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-2 text-left">
                                            <button class="btn btn-sm btn-primary" onclick="window.location.reload()"><i
                                                        class="fe fe-refresh-cw"></i> بارگذاری مجدد
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>ش.سفارش</th>
                                                <th>نام</th>
                                                <th>مبلغ</th>
                                                <th>شماره کارت /حساب واریزی</th>
                                                <th>زمان واریز</th>
                                                <th> کارت تنخواه</th>
                                                <th>اقدامات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql = 'SELECT * FROM db_wallet WHERE status=? ';
                                            $query = $conn->prepare($sql);
                                            $query->execute(['در انتظار بررسی']);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {
                                                foreach ($result

                                                         as $res) {

                                                    ?>
                                                    <tr>
                                                        <th scope="row"><?php echo $res->order_id ?></th>
                                                        <th scope="row"><?php $fullname = retPerson($res->username);
                                                            echo $fullname->fname . ' ' . $fullname->lname ?></th>
                                                        <td> <?php echo number_format($res->fee) ?> تومان</td>
                                                        <td><?php echo $res->card_pan ?></td>
                                                        <?php
                                                        $dateno = $res->datecreted;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td><?php echo $dateno ?></td>
                                                        <td><?php $tankhah = retCardServer($res->username);
                                                            echo substr($tankhah->card_num, 12) ?></td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown" type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('ActivationOrder.php?order='+'<?php echo $res->order_id ?>','تایید واریزی'
                                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i> تایید
                                                                                واریزی
                                                                            </div>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <div class="dropdown-item "
                                                                                 onclick="window.open('Contradictory-wallet.php?order='+'<?php echo $res->order_id ?>','تایید واریزی'
                                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-shuffle"></i>واریزی متناقض
                                                                            </div>


                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?unverify=<?php echo $res->order_id ?>"
                                                                               onclick="return confirm('آیا با عدم تایید این سفارش موافقید؟')"
                                                                            ><i class="fe fe-x-square"></i> عدم تایید
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>


                                                        </td>
                                                    </tr>
                                                <?php }
                                            }
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Row -->
                <?php } else { ?>
                    <div class="card-body">

                        <div class="alert alert-danger" role="alert">
                            <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                            شما مجوز لازم برای مشاهده این بخش را ندارید، با مدیر سامانه تماس بگیرید
                        </div>
                        <div style="text-align: center; padding-top: 20px">
                            <img src="../../assets/img/media/alert.png">

                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
