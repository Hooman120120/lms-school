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
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['sesave'])) {
    $_SESSION['excelCoursereport'] = array(
        "type" => $_POST['typereport'],
        "start" => $_POST['startdate'],
        "end" => $_POST['enddate']
    );
    header('location:../Bill/execel.php');
    exit;
}
date_default_timezone_set("Iran");
$dateno = date("Y-m-d");
$dateno = explode('-', $dateno);
$dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
$dateno = implode("/", $dateno);
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
    <title> آمار و گزارشات پایه ها |<?php echo __TITLE ?></title>
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
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <?php if (isset($call) and $call === 1) { ?>
                        <div class="alert alert-outline-success  text-center " role="alert">
                            <div class="mg-t-10">
                                <img style="width: 75px; height: 75px;"
                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط

                                با <?php echo $name ?>

                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                        </div>
                    <?php } ?>
                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                آمار و گزارش‌های پایه‌ها
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید با وارد کردن اطلاعات و انتخاب فیلترهای موردنیاز، گزارش‌های آماری مربوط به پایه‌ها را تولید و بررسی کنید. نتایج گزارش بر اساس پایه‌ها و معیارهای انتخاب‌شده نمایش داده می‌شود."
                                                  aria-label="راهنمای آمار و گزارش‌های پایه‌ها">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <p class="text-muted card-sub-title">
                                            لطفاً اطلاعات و فیلترهای موردنیاز را برای تولید گزارش پایه‌ها وارد کنید.
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-3 mb-2">
                                            <label for="sens" class="form-label mg-t-15">نوع گزارش:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="typereport"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <option value="1">1- نفر پایه</option>
                                                <option value="2">2- نفر پایه تجمیعی</option>
                                                <option value="3">3- گزارش وضعیت دانش آموز</option>

                                            </select>
                                        </div>

                                        <div class="col-xl-3">
                                            <p>از تاریخ ثبت پایه :<span
                                                        style="color: red">*</span></p>

                                            <div class="input-group ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fe fe-calendar lh--9 op-6"></i>
                                                    </div>
                                                </div>
                                                <input class="form-control fc-datepicker"
                                                       placeholder="1402/09/10" required
                                                       type="text" name="startdate">
                                            </div>
                                        </div>
                                        <div class="col-xl-3 ">
                                            <p>تا تاریخ :<span
                                                        style="color: red">*</span></p>

                                            <div class="input-group ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fe fe-calendar lh--9 op-6"></i>
                                                    </div>
                                                </div>
                                                <input class="form-control fc-datepicker"
                                                       value="<?php echo $dateno ?>" required
                                                       type="text" name="enddate">
                                            </div>
                                        </div>
                                    </div>
                                    <div class=" text-left">


                                        <button type="submit" name="sesave" class="btn btn-sm btn-success"><i
                                                    class="fe fe-save"></i> دانلود اکسل
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
</body>
</html>

