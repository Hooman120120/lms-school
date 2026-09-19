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
if(isset($_POST['darsad'])){
    $upwage='UPDATE setting_public SET wage=? WHERE id=?';
    $qwage=$conn->prepare($upwage);
    $qwage->execute([$_POST['darsad'],1]);
    $message='تنظیمات بروز رسانی شد';
}

// نوع مؤسسه: مدرسه یا آموزشگاه
if (isset($_POST['save-institution-type'])) {
    $newtype = (isset($_POST['institution_type']) && $_POST['institution_type'] === 'institute') ? 'institute' : 'school';
    $upinst  = 'UPDATE setting_public SET institution_type=? WHERE id=?';
    $qinst   = $conn->prepare($upinst);
    $qinst->execute([$newtype, 1]);
    $message = 'نوع مؤسسه تغییر کرد. برای اعمال کامل واژه‌ها صفحه را تازه کنید.';
}

// مقدار فعلی نوع مؤسسه برای نمایش در فرم
$current_inst_type = 'school';
try {
    $qct = $conn->query('SELECT institution_type FROM setting_public ORDER BY id LIMIT 1');
    $vct = $qct ? $qct->fetchColumn() : false;
    if ($vct === 'institute') { $current_inst_type = 'institute'; }
} catch (PDOException $e) {
    // ستون هنوز ساخته نشده → حالت مدرسه
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
                                                <h6 class="main-content-label mb-1">نوع مؤسسه</h6>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="نوع مؤسسه تعیین می‌کند که سامانه با چه واژه‌هایی صحبت کند. در حالت مدرسه از «دانش‌آموز» و «پایه» و در حالت آموزشگاه از «فراگیر» و «دوره» استفاده می‌شود. ماژول‌هایی مثل کارنامه و ولی دانش‌آموز هم فقط در حالت مدرسه فعال هستند. این تنظیم هیچ داده‌ای را حذف یا تغییر نمی‌دهد."
                                                      aria-label="راهنمای نوع مؤسسه"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                            </div>
                                            <p class="text-muted card-sub-title">
                                                واژه‌نامهٔ سامانه بر اساس این گزینه انتخاب می‌شود
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-xl-12">
                                        <select name="institution_type" class="form-control">
                                            <option value="school"<?php if ($current_inst_type === 'school') { echo ' selected'; } ?>>
                                                مدرسه &mdash; دانش‌آموز / پایه
                                            </option>
                                            <option value="institute"<?php if ($current_inst_type === 'institute') { echo ' selected'; } ?>>
                                                آموزشگاه &mdash; فراگیر / دوره
                                            </option>
                                        </select>
                                    </div>
                                    <div class="text-left mg-10">
                                        <button type="submit" class="btn btn-primary"
                                                name="save-institution-type"><i class="fa fa-save"></i>
                                            ذخیره نوع مؤسسه
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
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
                    <div class="col-sm-12 col-md-12 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <form method="post">
                                    <div class="col-10">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">درصد کارمزد معرف برون سازمانی</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش درصد کارمزد معرفان برون سازمانی را تعیین می‌کنید. هنگام صدور فاکتور برای معرف، درصد تعیین‌شده به‌عنوان کارمزد از مبلغ فاکتور کسر شده و مبلغ نهایی بر اساس این تنظیمات محاسبه می‌شود. هرگونه تغییر در این مقدار، در محاسبات فاکتورهای بعدی اعمال خواهد شد."
                                                      aria-label="راهنمای درصد کارمزد همکار"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                میزان درصدی که از مبلغ فاکتور کسر خواهد شد.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="form-group ">
                                        <div class="row row-sm">
                                            <?php
                                            $darsad='SELECT wage FROM setting_public';
                                            $query=$conn->prepare($darsad);
                                            $query->execute();
                                            $result=$query->fetch(PDO::FETCH_OBJ);
                                            ?>

                                            <div class="col-xl-12 ">
                                                <div class="input-group-prepend">
                                                    <input type="text"
                                                           class="form-control text-left"

                                                           name="darsad"
                                                           value="<?php echo $result->wage ?>"
                                                           required="">
                                                    <div class="input-group-text">
                                                        %
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="mt-3">
                                        <button type="submit"
                                                class="btn btn-light  mr-1"
                                                name="darsad-collague">
                                            ذخیره کارمزد همکار
                                        </button>

                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">

                                <div class="col-12">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">اعتبار سامانه ملی پیامک</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید اعتبار باقی‌مانده سامانه ملی پیامک را مشاهده کنید. این اعتبار برای ارسال پیامک‌های سامانه مانند پیامک‌های اطلاع‌رسانی، احراز هویت، اعلان‌ها و سایر پیام‌های ارسالی استفاده می‌شود. در صورت کاهش یا اتمام اعتبار، لازم است حساب سامانه پیامک شارژ شود تا ارسال پیامک‌ها بدون وقفه ادامه یابد."
                                                  aria-label="راهنمای اعتبار سامانه ملی پیامک"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>

                                        <p class="text-muted card-sub-title">
                                            مشاهده اعتبار باقی‌مانده سامانه ملی پیامک
                                        </p>
                                    </div>
                                </div>
                                <ul class="top-selling-products pb-0 mb-0 pr-0">
                                    <li class="product-item pb-0">
                                        <div class="product-info">
                                            <div class="product-name">اعتبار باقیمانده پیامک</div>
                                            <div class="price">از ملی پیامک</div>
                                        </div>
                                        <?php
                                        $username = __USERSMS;
                                        $password = __PASSSMS;
                                        $data = array('username' => $username, 'password' => $password );
                                        $post_data = http_build_query($data);
                                        $handle = curl_init('https://rest.payamak-panel.com/api/SendSMS/GetCredit');
                                        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
                                            'content-type' => 'application/x-www-form-urlencoded'
                                        ));
                                        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                                        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($handle, CURLOPT_POST, true);
                                        curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
                                        $response = curl_exec($handle);
                                        $response = json_decode($response);
                                        $response = intval($response->Value);


                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"> <?php  echo number_format($response); ?></span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>

                                </ul>
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
