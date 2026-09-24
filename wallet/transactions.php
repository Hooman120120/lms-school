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
    <title>تراکنش های کیف پول | <?php echo __TITLE ?></title>
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
                <div class="row">
                    <div class="col-sm-12 col-lg-12 col-xl-12 mt-xl-12">
                        <div class="card custom-card card-dashboard-calendar pb-0">
                            <label class="main-content-label mb-2">تراکنش های مالی کیف پول</label>
                            <!--                            <span class="d-block tx-12 mb-2 text-muted">پروژه هایی که کار توسعه در حال اتمام است</span>-->

                            <table class="table  table-hover m-b-0 transcations mt-2" >
                                <tbody>
                                <?php
                                $sql = 'SELECT * FROM db_wallet WHERE username=? ORDER BY order_id DESC ';
                                $query = $conn->prepare($sql);
                                $query->execute([$_SESSION['auth']['user']]);
                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                if ($query->rowCount() > 0) {
                                    foreach ($result
                                             as $vid) {

                                        if ($vid->mode == 1 and $vid->status == 'موفق') {
                                            $badge = 'badge-success';
                                            $textColor = 'text-success';
                                            $icon = 'fas fa-level-up-alt';
                                            $color = 'text-success';
                                            $status = 'تایید شده';
                                        }
                                        if ($vid->mode == 2 and $vid->status == 'موفق') {
                                            $badge = 'badge-success';
                                            $textColor = 'text-success';
                                            $icon = 'fas fa-level-up-alt';
                                            $color = 'text-success';
                                            $status = 'تایید شده';
                                        }
                                        if ($vid->mode == 0 and $vid->status == 'موفق') {
                                            $badge = 'badge-success';
                                            $textColor = 'text-danger';
                                            $icon = 'fas fa-level-down-alt';
                                            $color = 'text-danger';
                                            $status = 'تایید شده';
                                        }


                                        if ($vid->status == 'در انتظار بررسی') {
                                            $badge = 'badge-warning';
                                            $textColor = 'text-warning';
                                            $icon = 'fe fe-loader';
                                            $color = 'text-warning';
                                            $status = 'در حال بررسی';
                                        }
                                                if ($vid->status == 'منتظر پرداخت') {
                                            $badge = 'badge-warning';
                                            $textColor = 'text-warning';
                                            $icon = 'fe fe-loader';
                                            $color = 'text-warning';
                                            $status = 'منتظر پرداخت';
                                        }
                                        if ($vid->status == 'ناموفق') {
                                            $badge = 'badge-danger';
                                            $textColor = 'text-muted';
                                            $icon = 'fe fe-x-circle';
                                            $color = 'text-danger';
                                            $status = 'ناموفق';
                                        }
                                        ?>
                                        <tr>
                                            <td class="wd-5p">
                                                #<?php echo $vid->order_id ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-middle mr-3">
                                                    <div class="d-inline-block">
                                                        <h6 class="mb-1"><?php echo $vid->detail_wallet ?></h6>
                                                        <p class="mb-0 tx-13  badge <?php echo $badge ?>"><?php echo $status ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-left">
                                                <div class="d-inline-block">
                                                    <h6 class="mb-2 tx-15 <?php echo $textColor ?> font-weight-semibold"><?php
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
                                                                class="<?php echo $icon ?> mr-2 <?php echo $color ?> m-r-10"></i>
                                                    </h6>
                                                    <?php
                                                    $dateno = $vid->datecreted;
                                                    if ($dateno != NULL) {
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                    }
                                                    ?>
                                                    <p class="mb-0 tx-11 text-muted"><?php echo $dateno ?> </p>
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
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->
</body>
</html>