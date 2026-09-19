<?php
include_once 'dbconfig.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';
include_once 'inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
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
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}
//* End Check Session JUST ONE Session
include_once 'temp.html';

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
    <title> راهنمای ثبت نام |<?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>
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
    include_once 'menu.php';
    include_once 'header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="main-content-body tab-pane p-4 border-top-0 " id="about">
                    <div class="card-body p-0 border p-0 rounded-10">

                        <div class="p-4">
                            <label class="main-content-label tx-13 mg-b-20">راهنمای ثبت نام </label>

                        </div>

                        <div class="custom-card">
                            <div class="card">
                                <div class="card-body">
                                    همکار گرامی ! خواهشمند است جهت ثبت نام و ایجاد دوره برای دانش آموزان ، مراحل ثبت نام
                                    را با دقت مطالعه نمایید
                                    <p><strong>
                                            گام اول - ثبت اطلاعات دانش آموز</strong><br>
                                        از منوی "دانش آموزان من --> ثبت دانش آموز" نسبت به ثبت اطلاعات اولیه دانش آموز
                                        اقدام نمایید ، این اطلاعات شامل اطلاعات هویتی و تحصیلی می باشد
                                    </p>
                                    <p><strong>
                                            گام دوم - تایید حساب کاربری دانش آموز</strong><br>
                                        پس از ثبت اطلاعات دانش آموز، به شماره همراه ثبتی ، پیامک حاوی نام کاربری و رمز
                                        عبور به دانش آموز پیامک می شود، از دانش آموز بخواهید وارد حساب کاربری خود شده و
                                        کد تایید را به شما بدهد. کد تایید را در قسمت فعالسازی برای دانش آموز ثبت کنید
                                    </p>
                                    <p><strong>
                                            گام سوم - انتخاب دوره برای دانش آموز</strong><br>
                                        از طریق منوی دانش آموزان من --> لیست دانش آموزان ، نسبت به انتخاب دانش آموز و
                                        خرید دوره اقدام نمایید. می توانید برای هر دانش آموز دوره های مختلف و متناسب با
                                        نیاز او انتخاب کنید
                                    </p>
                                    <p><strong>
                                            گام چهارم - صدور صورتحساب برای دانش آموز</strong><br>
                                        پس از انتخاب دوره ، پیش فاکتور مورد نظر را مشاهده می کنید، در این مرحله قابلیت
                                        ویرایش نیز دارید، یکی از روش های پرداخت را انتخاب کنید، پیشنهاد ما پرداخت آنلاین
                                        است. اگر قصد پرداخت دسته جمعی دارید، روش کارت به کارت را انتخاب نمایید و ایجاد
                                        صورتحساب را بزنید. <br>
                                        پس از اینکه این کار را برای همه دانش آموزان خود کردید ، از طریق افزایش موجودی
                                        (کیف پول) خود را یکبار از روش های مشخص شده شارژ کرده و صورتحساب های موجود را
                                        پرداخت نمایید
                                    </p>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include 'footers.php'; ?>
    <!--End Footer-->


</body>
</html>
