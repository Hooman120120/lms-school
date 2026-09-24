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
                <div class="row">
                    <div class="col-lg-6 col-xl-6 col-md-6">

                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14"><?php echo m('label.student_inquiry') ?></h5>
                            </div>
                            <div class="card-body">
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <form method="post" data-parsley-validate="">
                                                <!--                                                <div class="card-body">-->

                                                <div class="col-xl-12">
                                                    <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nid_label') ?> <span
                                                                style="color: red">*</span></label>
                                                    <input class="form-control" name="codemeli" type="text"
                                                           placeholder="با اعداد انگلیسی وارد شود"
                                                           pattern="[0-9]{10}" required>
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">


                                                <div class="text-center mg-10">
                                                    <button type="submit" class="btn btn-success "
                                                            name="search-melli"><i class="fe fe-search"></i>
                                                        بررسی کد ملی
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-6 col-md-6">

                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">استعلام شماره تلفن</h5>
                            </div>
                            <div class="card-body">
                                <section class="section">

                                    <div class="card">
                                        <form method="post" data-parsley-validate="">
                                            <!--                                                <div class="card-body">-->

                                            <div class="col-xl-12">
                                                <label for="sens" class="form-label mg-t-15">شماره تماس: <span
                                                            style="color: red">*</span></label>
                                                <input class="form-control" name="codemeli" type="text"
                                                       placeholder="با اعداد انگلیسی وارد شود"
                                                       pattern="[0-9]{10}" required>
                                            </div>
                                            <?php
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">


                                            <div class="text-center mg-10">
                                                <button type="submit" class="btn btn-success "
                                                        name="search-tel"><i class="fe fe-search"></i>
                                                    بررسی شماره تلفن
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                </section>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>