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
if (!Permision('permision_viewwallet')) {
    header('location:' . url("admin/index.php"));
    die();
}
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
if ($_GET['id'] == '') {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
$user=retPerson($_GET['id']);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست تراکنش های کاربر | <?php echo __TITLE ?></title>
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
    <div class="card-body">
        <div class="card custom-card overflow-hidden">
            <div class="card-header border-bottom-0">
                <label class="main-content-label my-auto pt-2">تراکنش های مالی : <span
                        class="text-warning"> <?php echo $user->fname .' '.$user->lname; ?> </span> </label>
                <span class="d-block tx-12 mb-4 mt-1 text-muted">بدهکاری و بستانکاری هایی </span>
            </div>
            <ul class="crypto-transcation list-unstyled mg-b-0">
                <?php

                $sql = 'SELECT * FROM db_wallet WHERE username=? ORDER BY order_id DESC ';
                $query = $conn->prepare($sql);
                $query->execute([$_GET['id']]);
                $result = $query->fetchAll(PDO::FETCH_OBJ);
                if ($query->rowCount() > 0) {
                    foreach ($result

                             as $vid) {

                        if ($vid->mode == 1 and $vid->status == 'موفق') {
                            $badge = 'badge-success';
                            $textColor = 'text-success';
                            $icon = 'fas fa-level-up-alt';
                            $status = 'تایید شده';
                        }
                        if ($vid->mode == 2 and $vid->status == 'موفق') {
                            $badge = 'badge-success';
                            $textColor = 'text-success';
                            $icon = 'fas fa-level-up-alt';
                            $status = 'تایید شده';
                        }
                        if ($vid->mode == 0 and $vid->status == 'موفق') {
                            $badge = 'badge-success';
                            $textColor = 'text-danger';
                            $icon = 'fas fa-level-down-alt';
                            $status = 'تایید شده';
                        }


                        if ($vid->status == 'در انتظار بررسی') {
                            $badge = 'badge-warning';
                            $textColor = 'text-warning';
                            $icon = 'fe fe-loader';
                            $status = 'در حال بررسی';
                        }
                        if ($vid->status == 'منتظر پرداخت') {
                            $badge = 'badge-warning';
                            $textColor = 'text-warning';
                            $icon = 'fe fe-loader';
                            $status = 'منتظر پرداخت';
                        }
                        if ($vid->status == 'رد شده') {
                            $badge = 'badge-danger';
                            $textColor = 'text-muted';
                            $icon = 'fe fe-x-circle';
                            $status = 'رد شده';
                        }
                                 if ($vid->status == 'ناموفق') {
                            $badge = 'badge-danger';
                            $textColor = 'text-muted';
                            $icon = 'fe fe-x-circle';
                            $color = 'text-danger';
                            $status = 'ناموفق';
                        }
                        ?>


                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                            <div class="media align-items-center">
                                <div class=" crypto-icon bg-primary-transparent text-primary"><i
                                        class=" wd-20 ht-20 text-center tx-16"></i>#<?php echo $vid->order_id ?>
                                </div>
                                <div class="media-body mr-3">
                                    <p class="tx-medium mg-b-3 tx-15"><?php echo $vid->detail_wallet ?></p>
                                    <p class="tx-11 mg-b-0 badge <?php echo $badge ?>"><?php echo $status ?></p>
                                </div>
                            </div>
                            <div class="text-right mr-auto my-auto">
                                <h5 class="font-weight-semibold tx-16 mb-0"><?php
                                    if ($vid->fee < 0) {
                                        $fee = $vid->fee * -1;
                                        echo number_format($fee) . '-';
                                    } else {
                                        if ($icon == 'fe fe-x-circle') {
                                            echo '<del>' . number_format($vid->fee) . '</del>';
                                        } else {
                                            echo number_format($vid->fee);
                                        }
                                    } ?> تومان<i
                                        class="<?php echo $textColor ?> tx-16 <?php echo $icon ?> ml-1"></i></h5>
                                <?php
                                $dateno = $vid->datecreted;
                                if ($dateno != NULL) {
                                    $dateno = explode('-', $dateno);
                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                    $dateno = implode('/', $dateno);
                                }
                                ?>
                                <p class="tx-11 mg-b-0 tx-gray-500"><?php echo $dateno ?></p>
                                <p class="tx-11 mg-b-0 tx-gray-500"> شناسه تراکنش: <?php echo $vid->ref_id ?>  </p>
                                <p class="tx-11 mg-b-0 tx-gray-500 ltr">  <?php echo $vid->card_pan ?>: شماره کارت </p>
                                <p class="tx-11 mg-b-0 tx-gray-500 ltr">IP:  <?php echo $vid->ip ?> </p>
                            </div>
                        </li>
                        <?php
                    }
                }
                ?>
            </ul>


        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>