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
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

//* End Check Session JUST ONE Session
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> انواع دانش آموزان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<style>
    .op-section:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    .card-link-wrapper {
        display: block;
        text-decoration: none;
        color: inherit;
    }

    .card-link-wrapper .card {
        transition: transform 0.2s ease-in-out;
    }

    .card-link-wrapper:hover .card {
        transform: scale(0.98);
    }

    .safe-call-menu {
        margin-top: 30px;
        justify-content: center;
    }

    .safe-call-card {
        display: block;
        padding: 30px 20px;
        border-radius: 12px;
        text-align: center;
        background: #f7f7f7;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        transition: all 0.2s ease-in-out;
        height: 120px;
    }

    .safe-call-card:hover {
        text-decoration: none;
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        background: #ffffff;
    }

    .safe-call-card-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .safe-call-card-desc {
        font-size: 13px;
        color: #666;
    }
</style>

<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
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
            <div class="card custom-card" style="background-color: #EAEDF0; ">
                <div class="card-header border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            لیست های دانش آموزان
                        </h5>
                    </div>
                </div>
                <div class="card-body row justify-content-around">
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/panel/mypersonalcourse.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title"> دانش آموزان نفر پایه</div>
                            <div class="safe-call-card-desc">
                                لیست دانش آموزان شما بر اساس نفر پایه
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/panel/mystudent.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">دانش آموزان فردی</div>
                            <div class="safe-call-card-desc">
                                لیست دانش آموزان فردی شما
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/panel/canceled_student_list.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">دانش آموزان لغو پشتیبانی</div>
                            <div class="safe-call-card-desc">
                                لیست لغو پشتیبانی شده توسط شما
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/panel/suspension_list.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">دانش آموزان تعلیقی</div>
                            <div class="safe-call-card-desc">
                                لیست دانش آموزان فردی شما
                            </div>
                        </a>
                    </div>


                </div>
            </div>

        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
