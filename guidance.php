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
                                    <?php echo m('guide.intro') ?>
                                    <p><strong>
                                            <?php echo m('guide.step1_title') ?></strong><br>
                                        <?php echo m('guide.step1_body') ?>
                                    </p>
                                    <p><strong>
                                            <?php echo m('guide.step2_title') ?></strong><br>
                                        <?php echo m('guide.step2_body') ?>
                                    </p>
                                    <p><strong>
                                            <?php echo m('guide.step3_title') ?></strong><br>
                                        <?php echo m('guide.step3_body') ?>
                                    </p>
                                    <p><strong>
                                            <?php echo m('guide.step4_title') ?></strong><br>
                                        <?php echo m('guide.step4_body1') ?> <br>
                                        <?php echo m('guide.step4_body2') ?>
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
