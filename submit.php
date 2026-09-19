<?php
include_once 'dbconfig.php';
include_once 'inc/function_login.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if(!isset($_SESSION['verify'])){
    header('location: login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['verify']['ip'] !== GetIp() and $_SESSION['verify']['os'] != GetOs()) {
    unset($_SESSION['verify']);
    header('location:' . url("register.php"));
}
//--End of Protecting against SESSION Hijaking

//* Start Send SMS Acount
$text=$_SESSION['verify']['pass'].';'.$_SESSION['verify']['pass'];
SendSms($_SESSION['verify']['tel'],$text,171697,$_SESSION['verify']['tel']);

?>
<!DOCTYPE html>
<?php include_once 'temp.html'; ?>

<html lang="fa" dir="rtl">

<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="Morteza Belali">
    <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
    <title>ایجاد حساب کاربری |<?php echo __TITLE ?> </title>
    <link href="<?php echo url('assets/plugins/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet"/>
    <link href="<?php echo url('assets/plugins/web-fonts/icons.css') ?>" rel="stylesheet"/>
    <link href="<?php echo url('assets/plugins/web-fonts/font-awesome/font-awesome.min.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/web-fonts/plugin.css') ?>" rel="stylesheet"/>
    <link href="<?php echo url('assets/css-rtl/style/style.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/css-rtl/skins.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/css-rtl/dark-style.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/css-rtl/colors/default.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/datatable/dataTables.bootstrap4.min-rtl.css') ?>" rel="stylesheet"/>
    <link href="<?php echo url('assets/plugins/datatable/responsivebootstrap4.min.css') ?>" rel="stylesheet"/>
    <link href="<?php echo url('assets/plugins/datatable/fileexport/buttons.bootstrap4.min.css') ?>" rel="stylesheet"/>
    <link id="theme" type="text/css" media="all" href="<?php echo url('assets/css-rtl/colors/color.css') ?>"
          rel="stylesheet">
    <link href="<?php echo url('assets/plugins/select2/css/select2.min.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/prism/prism.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/css-rtl/sidemenu/sidemenu.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/spectrum-colorpicker/spectrum.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/ion-rangeslider/css/ion.rangeSlider.css') ?>" rel="stylesheet">
    <link href="<?php echo url('assets/plugins/ion-rangeslider/css/ion.rangeSlider.skinFlat.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo url('assets/plugins/multipleselect/multiple-select.css') ?>">
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<!-- End Loader -->
<div class="page">
    <div class="row signpages text-center">
        <div class="col-md-12">
            <div class="card">
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header  border-bottom-0 pb-0">

                                <div>
                                </div>
                                <div>
                                    <div class="alert alert-success mb-0" role="alert">
                                        <h4 class="alert-heading">حساب کاربری شما ایجاد شد!</h4>
                                        <h6 class="alert-heading">به خانواده بزرگ هنرآموزش خوش آمدید!</h6>

                                        <hr>
                                        <p class="mb-0"><?php if($_SESSION['verify']['role']==1) {echo m('label.dear_student_colon') ;} else {echo 'همکار گرامی:';} ?>
                                            :<strong> <?php echo $_SESSION['verify']['name']." ".$_SESSION['verify']['famili'] ?> </strong></p>
                                        <p class="mb-0"> نام کاربری
                                            :<strong><?php echo $_SESSION['verify']['tel'] ?></strong></p>

                                        <p class="mb-0"> رمز عبور: <strong><?php echo $_SESSION['verify']['pass'] ?></strong>
                                        </p>

                                    </div>
                                </div>
                            </div>
                            <div class="row row-sm mg-t-30">
                                <div class="col-sm-12 col-md-12 col-xl-12">
                                    <div class="card custom-card">
                                        <div class="card-body user-card text-center">


                                            <a href="login.php" class="btn ripple btn-info mt-3">ورود به حساب کاربری
                                                </a>
                                            <p class="mt-3">   <span class="text-muted">   کاربر گرامی ، ضمن تشکر و قدردانی از شما برای پیوستن به خانواده بزرگ موسسه هنرآموزش به اطلاع تان می رسانیم کلیه ثبت نام ها، مشاهده کلاس ها و آزمون ها از طریق حساب کاربری ایجاد شده انجام می گردد، لذا می توانید نام کاربری و رمز عبور خود را برای ورود به حساب کاربری ذخیره نمایید </span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Row -->
                    </div>
                </div>
            </div>
<?php include_once 'footer.php';
$_SESSION['course']=$_SESSION['verify']['course'];
unset($_SESSION['verify']);
?>
        </div>
    </div>

    <a href="#top" id="back-to-top"><i class="fe fe-arrow-up"></i></a>

    <!-- Jquery js-->
    <script src="assets/plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap js-->
    <script src="assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap-rtl.js"></script>
    <!-- Perfect-scrollbar js -->
    <script src="assets/plugins/perfect-scrollbar/perfect-scrollbar.min-rtl.js"></script>
    <!-- Sidemenu js -->
    <script src="assets/plugins/sidemenu/sidemenu-rtl.js"></script>
    <!-- Sidebar js -->
    <script src="assets/plugins/sidebar/sidebar-rtl.js"></script>
    <!-- Peity js-->
    <script src="assets/plugins/peity/jquery.peity.min.js"></script>
    <!-- Internal Clipboard js-->
    <script src="assets/plugins/clipboard/clipboard.min.js"></script>
    <script src="assets/plugins/clipboard/clipboard.js"></script>
    <!-- Internal Parsley js-->
    <script src="assets/plugins/parsleyjs/parsley.min.js"></script>
    <!-- Custom js -->
    <script src="assets/js/custom.js"></script>
</body>
</html>