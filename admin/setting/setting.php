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
if (!Permision('permision_viewsetting')) {
    header('location:' . url("admin/index.php"));
    die();
}
if(isset($_POST['save'])){
    if(isset($_POST['signup-student'])){
        $singup_student=1;
    }else{
        $singup_student=0;
    }
    if(isset($_POST['signup-collague'])){
        $singup_collague=1;
    }else{
        $singup_collague=0;
    }
    if(isset($_POST['signin'])){
        $signin=1;
    }else{
        $signin=0;
    }
    if(isset($_POST['sms-card'])){
        $sms_card=1;
    }else{
        $sms_card=0;
    }
    if(isset($_POST['sms-epay'])){
        $sms_epay=1;
    }else{
        $sms_epay=0;
    }
    $upset='UPDATE setting_public SET act_register_student=?,act_register_collague=?,act_login=?,actsms_success_pay=?,actsms_success_card=? WHERE id=?';
    $qset=$conn->prepare($upset);
    $qset->execute([$singup_student,$singup_collague,$signin,$sms_epay,$sms_card,1]);
    $message='تنظیمات بروز رسانی شد';
}


// نوع و جنسیت مؤسسه در هنرفناوری تعیین می‌شود و با مجوز پنل به setting_public می‌رسد
// (hf_control_apply_settings در inc/hf_control.php)؛ این صفحه فقط آن را نشان می‌دهد.
$current_inst_type = inst_type();
$current_gender = null;
try {
    $qg = $conn->query('SELECT gender FROM setting_public ORDER BY id LIMIT 1');
    $vg = $qg ? $qg->fetchColumn() : false;
    if ($vg === 'boys' || $vg === 'girls') { $current_gender = $vg; }
} catch (PDOException $e) {
    // ستون هنوز ساخته نشده؛ با اولین دریافت مجوز ساخته می‌شود
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
    <title> تنظیمات | <?php echo __TITLE ?></title>
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
                <div class="row row-sm">
                    <?php if (isset($message)) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                    type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $message ?>
                        </div>
                    <?php } ?>
                 
                    <div class="col-sm-12 col-md-12 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <form method="post">
                                    <div class="col-10">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">تنظیمات</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید تنظیمات عمومی مربوط به ورود و ثبت‌نام کاربران را مدیریت کنید. امکاناتی مانند فعال یا غیرفعال کردن ثبت‌نام، تعیین نحوه ورود کاربران، تنظیمات احراز هویت و سایر گزینه‌های مرتبط با فرآیند ورود و ایجاد حساب کاربری از طریق این قسمت قابل پیکربندی است."
                                                      aria-label="راهنمای تنظیمات ورود و ثبت‌نام"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                تنظیمات ورود و ثبت‌نام
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-xl-12 ">
                                        <label class="custom-switch">
                                            <input type="checkbox"
                                                   name="signup-student" <?php if (ActivatePublic('act_register_student')) { echo 'checked'; } ?>
                                                   class="custom-switch-input" >
                                            <span class="custom-switch-indicator"></span>
                                            <span class=""> فرم ثبت نام دانش آموز </span>
                                        </label>
                                    </div>
                                    <div class="col-xl-12 ">
                                        <label class="custom-switch">
                                            <input type="checkbox"
                                                   name="signup-collague" <?php if (ActivatePublic('act_register_collague')) { echo 'checked';} ?>
                                                   class="custom-switch-input" >
                                            <span class="custom-switch-indicator"></span>
                                            <span class=""> فرم ثبت نام همکار </span>
                                        </label>
                                    </div>
                                    <div class="col-xl-12 ">
                                        <label class="custom-switch">
                                            <input type="checkbox"
                                                   name="signin"  <?php if (ActivatePublic('act_login')) { echo 'checked';} ?>
                                                   class="custom-switch-input" >
                                            <span class="custom-switch-indicator"></span>
                                            <span class=""> فرم ورود </span>
                                        </label>
                                    </div>
                                    <div class="col-xl-12 ">
                                        <label class="custom-switch">
                                            <input type="checkbox"
                                                   name="sms-card"  <?php if (ActivatePublic('actsms_success_card')) { echo 'checked';} ?>
                                                   class="custom-switch-input" >
                                            <span class="custom-switch-indicator"></span>
                                            <span class=""> پیامک کارت به کارت </span>
                                        </label>
                                    </div>
                                    <div class="col-xl-12 ">
                                        <label class="custom-switch">
                                            <input type="checkbox"
                                                   name="sms-epay"  <?php if (ActivatePublic('actsms_success_pay')) { echo 'checked';} ?>
                                                   class="custom-switch-input" >
                                            <span class="custom-switch-indicator"></span>
                                            <span class=""> پیامک پرداخت آنلاین </span>
                                        </label>
                                    </div>
                                    <div class="text-left mg-10">
                                        <button type="submit" class="btn btn-primary "
                                                name="save"><i
                                                    class="fa fa-save"></i>
                                            ذخیره تنظیمات
                                        </button>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
