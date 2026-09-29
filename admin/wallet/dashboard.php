<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (!inst_feature('wallet')) { http_response_code(404); exit('کیف پول در نسخه مدرسه‌ای فعال نیست.'); }
displayError(0);
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
date_default_timezone_set("Iran");
$datenow = date("Y-m-d");
$timenow = date('H:i');
$dateno = explode('-', $datenow);
$dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
$dateno = implode('/', $dateno);
$timeaknon = $timenow . ' - ' . $dateno;
if (isset($_GET['call']) and $_GET['call'] != '') {

    $ret = retPerson($_GET['call']);
    $tel = $ret->tel1;
    $name = $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: dashboard.php');
    die();
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
    <title> پیشخوان کیف پول | <?php echo __TITLE ?></title>
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
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-8 col-lg-8 col-xl-8">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <label class="main-content-label my-auto pt-2">
                                                گزارش موجودی کیف پول
                                            </label>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش گزارش وضعیت موجودی کیف پول نمایش داده می‌شود. با استفاده از این گزارش می‌توانید میزان اعتبار موجود کاربران و تغییرات مرتبط با موجودی کیف پول را بررسی و پیگیری کنید."
                                                  aria-label="راهنمای گزارش موجودی کیف پول"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>

                                        <div class="mr-auto mt-3 d-flex">
                                            <div class="ml-3 d-flex text-muted tx-13">
                                                <span class="legend bg-danger rounded-circle"></span>
                                                <?= $timeaknon ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> جمع مانده موثر و استفاده نشده وجوهات تایید شده </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-4 my-auto">
                                        <h6 class="mb-3 font-weight-normal">مانده موجودی ها</h6>
                                        <div class="text-right">
                                            <?php
                                            $summo = 'SELECT SUM(fee) as summo FROM db_wallet WHERE status=?';
                                            $qsum = $conn->prepare($summo);
                                            $qsum->execute(['موفق']);
                                            $result = $qsum->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <h5 class="font-weight-bold ml-3 mb-2 text-success"> <?php echo number_format($result->summo) ?>
                                                تومان</h5>
                                            <p class="tx-13 my-auto text-muted"></p>
                                        </div>
                                    </div>
                                    <div class="col-sm-4 my-auto text-center">
                                        <h6 class="mb-3 font-weight-normal">مبلغ واریزی</h6>
                                        <div class="text-center">
                                            <?php
                                            $summo = 'SELECT SUM(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                            $qsum = $conn->prepare($summo);
                                            $qsum->execute(['موفق', 1]);
                                            $result = $qsum->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <h5 class="font-weight-bold ml-3 mb-2 text-primary "><?php echo number_format($result->summo) ?>
                                                تومان</h5>

                                        </div>
                                    </div>
                                    <div class="col-sm-4 my-auto text-center">
                                        <h6 class="mb-3 font-weight-normal">مبلغ مصرف شده</h6>
                                        <div class="text-center">
                                            <?php
                                            $summo = 'SELECT SUM(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                            $qsum = $conn->prepare($summo);
                                            $qsum->execute(['موفق', 0]);
                                            $result = $qsum->fetch(PDO::FETCH_OBJ);
                                            $manfi = $result->summo * -1;
                                            ?>
                                            <h5 class="font-weight-bold ml-3 mb-2 text-danger "> <?php echo number_format($manfi) ?>
                                                - تومان </h5>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>


                        <div class="card custom-card ">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <label class="main-content-label my-auto pt-2">
                                                لیست افراد دارای تمکن مالی
                                            </label>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش لیست افرادی نمایش داده می‌شود که بر اساس اطلاعات مالی ثبت‌شده، دارای اعتبار یا موجودی کافی در کیف پول هستند. این گزارش برای بررسی وضعیت مالی کاربران و پیگیری سوابق اعتباری آن‌ها مورد استفاده قرار می‌گیرد."
                                                  aria-label="راهنمای لیست افراد دارای تمکن مالی"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">  </span>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="row">
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>اقدامات</th>
                                                <th>نام</th>
                                                <th>نوع کاربری</th>
                                                <th>مبلغ موجودی</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql = 'SELECT * FROM dbpersonal INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username ';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                            $results = $query->fetchAll(PDO::FETCH_OBJ);


                                            $id = 1;
                                            foreach ($results
                                                     as $res) {
                                                if (totalWallet($res->codemeli) > 0) {
                                                    ?>
                                                    <tr>
                                                        <td class="wd-15"><?php echo $id ?></td>
                                                        <td class="wd-15">
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
                                                                            <div class="dropdown-item  "
                                                                                 onclick="window.open('viewlisttransaction.php?id='+'<?php echo $res->codemeli ?>','مشاهده تراکنش ها'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i> مشاهده
                                                                                تراکنش ها

                                                                            </div>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->codemeli ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                کاربر
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>


                                                        </td>
                                                        <td class="wd-15"><?php echo $res->fname . ' ' . $res->lname ?></td>
                                                        <td class="wd-15"><?php if ($res->role == 1) {
                                                                echo 'دانش آموز';
                                                            } else {
                                                                echo 'همکار آموزشی';
                                                            } ?></td>
                                                        <td><?php echo number_format(totalWallet($res->codemeli)) ?>
                                                            تومان
                                                        </td>

                                                    </tr>
                                                    <?php
                                                    $id++;
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>
                                    </div>


                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <label class="main-content-label my-auto pt-2">
                                                گزارش آماری بدهکاری و بستانکاری
                                            </label>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش گزارش آماری وضعیت مالی کاربران از نظر بدهکاری و بستانکاری نمایش داده می‌شود. این گزارش امکان بررسی مبالغ بدهی، پرداخت‌های انجام‌شده و وضعیت تسویه حساب کاربران را فراهم می‌کند."
                                                  aria-label="راهنمای گزارش آماری بدهکاری و بستانکاری"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> شامل تعداد همه وضعیت های موجود </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mt-1">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6 ">
                                        <span class="">تعداد واریزی</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['موفق', 1]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4 ">
                                        <div class="d-flex ">
                                            <span class="tx-13 badge badge-success-light"><i
                                                        class="text-success fe fe-arrow-up"></i><b><?php echo number_format($result->summo) ?></b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد مصرف شده</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['موفق', 0]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-danger-light"><i
                                                        class="text-danger fe fe-arrow-down"></i><b><?php echo number_format($result->summo) ?></b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد درحال بررسی</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['درحال بررسی', 1]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-warning-light"><i
                                                        class="text-warning fe fe-loader"></i><b> <?php echo number_format($result->summo) ?> </b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد منتظر پرداخت</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['منتظر پرداخت', 1]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-warning-light"><i
                                                        class="text-warning fe fe-loader"></i><b> <?php echo number_format($result->summo) ?> </b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد ناموفق</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['ناموفق', 1]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-danger-light"><i
                                                        class="text-danger fe fe-x"></i><b> <?php echo number_format($result->summo) ?> </b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد عدم تایید</span>
                                    </div>
                                    <?php
                                    $summo = 'SELECT count(fee) as summo FROM db_wallet WHERE status=? AND mode=?';
                                    $qsum = $conn->prepare($summo);
                                    $qsum->execute(['عدم تایید', 1]);
                                    $result = $qsum->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-danger-light"><i
                                                        class="text-danger fe fe-x"></i><b class=""> <?php echo number_format($result->summo) ?> </b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-2">
                                        <span class="crypto-icon bg-primary-transparent ml-3"><i class="fe fe-bar-chart-2 text-primary"></i></span>
                                    </div>
                                    <div class="col-6">
                                        <span class="">تعداد درخواست بازگشت وجه</span>
                                    </div>

                                    <div class="col-4">
                                        <div class="d-flex">
                                            <span class="tx-13 badge badge-warning-light"><i
                                                        class="text-warning fe fe-loader"></i><b> بزودی </b></span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div><!-- col end -->
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>


