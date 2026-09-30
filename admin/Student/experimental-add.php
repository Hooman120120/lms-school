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
require_once BASE_PATH . '/inc/temporary_student_registration.php';
$temporaryRegistration = HandleTemporaryStudentRegistration();
if (!empty($temporaryRegistration['forbidden'])) {
    http_response_code(403);
    exit('دسترسی ثبت‌نام موقت برای شما فعال نشده است.');
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> <?php echo m('page.add_student') ?> | <?php echo __TITLE ?></title>
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
                if (AdminCanSee('students.temporary.register')) {
                    if (AdminCanSee('students.temporary.register')) {
                        ?>
                        <?php RenderTemporaryStudentRegistration($temporaryRegistration); ?>
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
                    <?php }
                } ?>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        (function() {
            const area = document.querySelector('select[name="area_code"]');
            const local = document.querySelector('input[name="landline_local"]');
            const full = document.getElementById('landline_full');

            function digits(s) {
                return (s || '').replace(/\D/g, '');
            }

            function update() {
                full.value = (area?.value || '') + digits(local?.value || '');
            }

            area?.addEventListener('change', update);
            local?.addEventListener('input', update);
            update();
        })();
    </script>

</body>

</html>
