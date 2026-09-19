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
    <title> تماس امن |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
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

                <div class="row row-sm safe-call-menu">
                    <p class="p-3" style="font-size: large; font-weight: bold;">تماس امن</p>
                    <!-- ایجاد گروه تماس (permision_addcall) -->
                    <?php if (Permision('permision_addcall')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/add-group.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">ایجاد گروه تماس</div>
                                <div class="safe-call-card-desc">
                                    ایجاد و مدیریت گروه‌های جدید تماس
                                </div>
                            </a>
                        </div>
                    <?php } ?>

                    <!-- گرفته نشده -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/uncall.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">گرفته نشده</div>
                            <div class="safe-call-card-desc">
                                لیست تماس‌هایی که هنوز گرفته نشده‌اند
                            </div>
                        </a>
                    </div>

                    <!-- عدم پاسخ -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/unresponse.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">عدم پاسخ</div>
                            <div class="safe-call-card-desc">
                                تماس‌هایی که پاسخی دریافت نکرده‌اند
                            </div>
                        </a>
                    </div>

                    <!-- خاتمه یافته -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/call-finish.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">خاتمه یافته</div>
                            <div class="safe-call-card-desc">
                                تماس‌های انجام شده و بسته شده
                            </div>
                        </a>
                    </div>

                    <!-- نیاز به تماس مجدد -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/begin.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">نیاز به تماس مجدد</div>
                            <div class="safe-call-card-desc">
                                لیست افرادی که باید دوباره با آن‌ها تماس بگیرید
                            </div>
                        </a>
                    </div>

                    <!-- مخاطبین تکراری -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/dublicateTel.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">مخاطبین تکراری</div>
                            <div class="safe-call-card-desc">
                                شناسایی و مدیریت شماره‌های تکراری
                            </div>
                        </a>
                    </div>

                    <!-- شماره های اشتباه -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/wrong.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">شماره‌های اشتباه</div>
                            <div class="safe-call-card-desc">
                                لیست شماره‌های اشتباه ثبت شده
                            </div>
                        </a>
                    </div>

                    <!-- همه مخاطبین من -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/anytel.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">همه مخاطبین من</div>
                            <div class="safe-call-card-desc">
                                دسترسی به تمام مخاطبین من
                            </div>
                        </a>
                    </div>

                    <!-- مدیریت کل تماس امن + مرکز تبادل تماس (permision_managecall) -->
                    <?php if (Permision('permision_managecall')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/managecall.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">مدیریت کل تماس امن</div>
                                <div class="safe-call-card-desc">
                                    مدیریت و نظارت روی همه تماس‌های امن
                                </div>
                            </a>
                        </div>

                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/hamtel.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">مرکز تبادل تماس</div>
                                <div class="safe-call-card-desc">
                                    مرکز تبادل و هدایت تماس‌ها
                                </div>
                            </a>
                        </div>
                    <?php } ?>

                    <!-- ورودی (permision_addcall) -->
                    <?php if (Permision('permision_addcall')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/importtel.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">ورودی</div>
                                <div class="safe-call-card-desc">
                                    ورود و ایمپورت مخاطبین به سیستم
                                </div>
                            </a>
                        </div>
                    <?php } ?>

                    <!-- گزارش تماس و فایل صوتی (reportcall_permission) -->
                    <?php if (Permision('reportcall_permission')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/reportcall.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">گزارش تماس و فایل صوتی</div>
                                <div class="safe-call-card-desc">
                                    مشاهده گزارش تماس‌ها و فایل‌های ضبط‌شده
                                </div>
                            </a>
                        </div>
                    <?php } ?>

                </div>

            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</div>
</body>
</html>
