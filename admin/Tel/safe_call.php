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

if (!Permision('permision_viewcall')) {
    header('location: ' . url('admin/index.php'));
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
            display:flex;
            align-items:center;
            justify-content:center;
            gap:7px;
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
                <p class="p-2" style="font-size: large; font-weight: bold;">تماس امن</p>

                <div class="row row-sm safe-call-menu">

                    <!-- ایجاد گروه تماس (permision_addcall) -->
                    <!--                    --><?php //if (Permision('permision_addcall')) { ?>
                    <!--                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                            <a href="--><?php //echo url('admin/Tel/add-group.php') ?><!--" class="safe-call-card">-->
                    <!--                                <div class="safe-call-card-title">ایجاد گروه تماس</div>-->
                    <!--                                <div class="safe-call-card-desc">-->
                    <!--                                    ایجاد و مدیریت گروه‌های جدید تماس-->
                    <!--                                </div>-->
                    <!--                            </a>-->
                    <!--                        </div>-->
                    <!--                    --><?php //} ?>

                    <!-- گرفته نشده -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/uncall.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">گرفته نشده</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                لیست تماس‌هایی که هنوز گرفته نشده‌اند-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- عدم پاسخ -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/unresponse.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">عدم پاسخ</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                تماس‌هایی که پاسخی دریافت نکرده‌اند-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- خاتمه یافته -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/call-finish.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">خاتمه یافته</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                تماس‌های انجام شده و بسته شده-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- نیاز به تماس مجدد -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/begin.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">نیاز به تماس مجدد</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                لیست افرادی که باید دوباره با آن‌ها تماس بگیرید-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- مخاطبین تکراری -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/dublicateTel.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">مخاطبین تکراری</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                شناسایی و مدیریت شماره‌های تکراری-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- شماره های اشتباه -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/wrong.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">شماره‌های اشتباه</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                لیست شماره‌های اشتباه ثبت شده-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- همه مخاطبین من -->
                    <!--                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                        <a href="--><?php //echo url('admin/Tel/anytel.php') ?><!--" class="safe-call-card">-->
                    <!--                            <div class="safe-call-card-title">همه مخاطبین من</div>-->
                    <!--                            <div class="safe-call-card-desc">-->
                    <!--                                دسترسی به تمام مخاطبین من-->
                    <!--                            </div>-->
                    <!--                        </a>-->
                    <!--                    </div>-->

                    <!-- مدیریت کل تماس امن + مرکز تبادل تماس (permision_managecall) -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/managecall.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>مدیریت کل تماس امن</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="مدیریت و نظارت بر همه تماس‌های امن، همراه با گزارش‌گیری کامل، ساخت گروه تماس، و ورود دستی فایل تماس برای شماره‌هایی که در CRM ثبت نشده‌اند. "
                                      aria-label="اطلاعات مدیریت تماس امن"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>
                  <?php if (Permision('permision_managecall')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/hamtel.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title"
                                    <span>مرکز تبادل تماس</span>
                                    <span class="menu-info-trigger"
                                          data-tooltip="از این بخش برای تبادل، هدایت و واگذاری تماس‌ها میان اپراتورها یا کارشناسان استفاده کنید."
                                          aria-label="اطلاعات مرکز تبادل تماس"
                                          onclick="event.preventDefault(); event.stopPropagation();">
                                        <i class="fe fe-info fs-4"></i>
                                    </span>
                                </div>

                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/assign-phone.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">
                                    <span>تخصیص شماره به کارشناس</span>
                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید شماره‌های واقع در مخزن تماس را به کارشناس موردنظر تخصیص دهید تا پیگیری و برقراری تماس توسط همان کارشناس انجام شود."
                                          aria-label="راهنمای تخصیص شماره به کارشناس"
                                          onclick="event.preventDefault(); event.stopPropagation();">
                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                                </div>

                            </a>
                        </div>
                        <!--                    --><?php } ?>

                    <!-- ورودی (permision_addcall) -->
                    <!--                    --><?php //if (Permision('permision_addcall')) { ?>
                    <!--                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                    <!--                            <a href="--><?php //echo url('admin/Tel/importtel.php') ?><!--" class="safe-call-card">-->
                    <!--                                <div class="safe-call-card-title">ورودی</div>-->
                    <!--                                <div class="safe-call-card-desc">-->
                    <!--         ورود و ایمپورت مخاطبین به سیستم      -->
                    <!--                                </div>-->
                    <!--                            </a>-->
                    <!--                        </div>-->
                    <!--                    --><?php //} ?>

                    <!-- گزارش تماس و فایل صوتی (reportcall_permission) -->
                    <?php if (Permision('reportcall_permission')) { ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/Tel/reportcall.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">
                                    <span>گزارش تماس و فایل صوتی</span>
                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید گزارش تماس‌های انجام‌شده را مشاهده کرده و اطلاعات تماس، وضعیت تماس و فایل‌های صوتی ضبط‌شده را بررسی و پخش کنید."
                                          aria-label="راهنمای گزارش تماس و فایل صوتی"
                                          onclick="event.preventDefault(); event.stopPropagation();">
                                        <i class="fe fe-info fs-4"></i>
                                    </span>
                                </div>

                            </a>
                        </div>
                    <?php } ?>

                </div>
                <?php if(permision('autodialer')){ ?>
                <p class="p-2" style="font-size: large; font-weight: bold;">auto dialer (تماس خودکار)
                    <a class="text-success"
                       href="<?php echo url('../assets/doc/آموزش کار با تماس گروهی.pdf')?>"
                       download>
                        جهت نحوه عملکرد و کار با بخش تماس خودکار لطفا فایل راهنما را دانلود نمایید.
                    </a> </p>

                <div class="row row-sm safe-call-menu">

                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/autodialer/contact-management.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>1 - contact (مدیریت مخاطب)</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید مخاطبان را ثبت، مشاهده، ویرایش و مدیریت کنید."
                                      aria-label="راهنمای مدیریت مخاطب"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>

                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/autodialer/autodialer-black-list.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>2 - black list (لیست سیاه)</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید شماره‌هایی را مدیریت کنید که سیستم اتودایلر نباید با آن‌ها تماس بگیرد. شماره‌های موجود در لیست سیاه هنگام اجرای تماس‌های خودکار از فرایند شماره‌گیری حذف می‌شوند."
                                      aria-label="راهنمای لیست سیاه اتودایلر"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>


                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/autodialer/group-management.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>3 - groups (مدیریت گروه)</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید گروه‌های تماس اتودایلر را ایجاد و مدیریت کنید. مخاطبان و شماره‌ها در این گروه‌ها دسته‌بندی می‌شوند تا تماس‌های خودکار برای گروه موردنظر اجرا و کنترل شوند."
                                      aria-label="راهنمای مدیریت گروه‌های اتودایلر"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>

                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/autodialer/voice-management.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>4 - announcements (مدیریت صدا)</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید فایل‌های صوتی و پیام‌های مورد استفاده در تماس‌های خودکار اتودایلر را ثبت و مدیریت کنید. این صداها هنگام اجرای کمپین تماس برای مخاطبان پخش می‌شوند."
                                      aria-label="راهنمای مدیریت صدای اتودایلر"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>


                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/autodialer/campaign-management.php') ?>" class="safe-call-card">
                            <div class="safe-call-card-title">

                                <span>5 - campaign (مدیریت کمپین)</span>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید کمپین‌های تماس خودکار اتودایلر را ایجاد و مدیریت کنید. گروه مخاطبان، پیام صوتی، زمان و نحوه اجرای تماس‌ها از این قسمت مشخص می‌شود و می‌توانید تماس کمپین را شروع و کنترل کنید."
                                      aria-label="راهنمای مدیریت کمپین اتودایلر"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>

                            </div>

                        </a>
                    </div>
                    <?php if ($_SESSION['admin']['adminrole']== 0){ ?>
                        <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                            <a href="<?php echo url('admin/autodialer/autodialer-log-report.php') ?>" class="safe-call-card">
                                <div class="safe-call-card-title">

                                    <span>6 - autodialer logs (وقایع)</span>

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید گزارش فعالیت‌ها و وقایع اتودایلر را مشاهده کنید و بررسی نمایید که هر کاربر چه عملیاتی را در چه تاریخی و چه زمانی انجام داده است."
                                          aria-label="راهنمای گزارش وقایع اتودایلر"
                                          onclick="event.preventDefault(); event.stopPropagation();">

                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                                </div>

                            </a>
                        </div>
                    <?php } ?>
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
