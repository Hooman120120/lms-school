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
if (isset($_POST['darsad'])) {
    AdminRequire('finance.referrer-commission.save');
    $wage = filter_var($_POST['darsad'], FILTER_VALIDATE_FLOAT);
    if ($wage === false || $wage < 0 || $wage > 100) {
        $message = 'درصد کارمزد باید عددی بین صفر و صد باشد';
    } else {
        $qwage = $conn->prepare('UPDATE setting_public SET wage=? WHERE id=?');
        $qwage->execute([$wage, 1]);
        $message = 'کارمزد معرف به‌روزرسانی شد';
    }
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
    <title> کارمزد معرف | <?php echo __TITLE ?></title>
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
                        <div class="alert alert-info" role="alert">
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
                                            $darsad='SELECT wage FROM setting_public WHERE id=1';
                                            $query=$conn->prepare($darsad);
                                            $query->execute();
                                            $result=$query->fetch(PDO::FETCH_OBJ);
                                            ?>

                                            <div class="col-xl-12 ">
                                                <div class="input-group-prepend">
                                                    <input type="number" min="0" max="100" step="any"
                                                           class="form-control text-left"

                                                           name="darsad"
                                                           value="<?php echo htmlspecialchars((string) $result->wage, ENT_QUOTES, 'UTF-8') ?>"
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
                                            ذخیره کارمزد معرف
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
