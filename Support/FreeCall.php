<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
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
if (isset($_POST['submit'])) {
    if ($_SESSION['auth']['role'] == 1) {
        $name = 'دانش آموز: ' . $_SESSION['auth']['name'] . ' ' . $_SESSION['auth']['famili'];
    } else {
        $name = 'همکار آموزشی: ' . $_SESSION['auth']['name'] . ' ' . $_SESSION['auth']['famili'];
    }

    freeCall($_SESSION['auth']['tel1'], $name);
    $resive = 1;
//
}
//* End Check Session JUST ONE Session
include_once '../temp.html';
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
    <title> تماس رایگان با پشتیبان| <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
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


                <!-- Page Header -->


                <div class="col-lg-12 col-xl-12 col-md-12">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-3 font-weight-bold tx-14">تماس رایگان با پشتیبانی</h5>
                            <?php if (isset($resive) and $resive === 1) { ?>
                                <div class="alert alert-outline-success  text-center " role="alert">
                                    <div class="mg-t-10">
                                        <img style="width: 75px; height: 75px;" src="../assets/img/call1.gif">در حال
                                        تماس...

                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>

                                </div>
                            <?php } ?>
                        </div>
                        <div class="card-body">

                            <div class="alert alert-info mb-0" role="alert">
                                <h4 class="alert-heading"></h4>
                                <p>تماس رایگان با پشتیبانی بصورت هوشمند برقرار شده و شما به اپراتور پشتیبانی متصل خواهید
                                    شد. توجه داشته باشید که ممکن است درصورت شلوغی خطوط اپراتورها ، مدتی در صف انتظار
                                    برای پاسخگویی باشید.</p>
                                <hr>
                                <p class="mb-0">مطمئن شوید ، تلفن همراه شما به شماره
                                    <strong> <?php echo $_SESSION['auth']['tel1'] ?> </strong> روشن باشد، به محض درخواست
                                    تماس، با این شماره تماس برقرار می شود</p>
                            </div>
                            <form method="post">
                                <div class="mg-t-20 text-center">
                                    <button type="submit" name="submit" class="btn ripple btn-outline-success "><i
                                                class="fe fe-phone-call"></i> هم اکنون تماس را برقرار کنید
                                    </button>
                                </div>
                            </form>
                            <div class="mg-t-10">
                                <div class="alert alert-warning  text-center " role="alert">

                                    <p>توجه داشته باشید فقط در ساعات کاری موسسه امکان برقراری تماس با اپراتور امکان پذیر
                                        است</p>

                                </div>
                            </div>


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

