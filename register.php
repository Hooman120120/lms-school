<?php
include_once 'dbconfig.php';
include_once 'inc/function_login.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}

if (isset($_GET['course']) and $_GET['course'] != '') {
    $_SESSION['course'] = intval($_GET['course']);
}
if (!isset($_GET['op'])) {
    header('location:' . url("register.php?op=student"));
}
$ip = GetIp();
$os=GetOs();
if(isset($_SESSION['count-sms']) AND $_SESSION['count-sms']>6 ) {
    $ip = GetIp();
    $today = date("Y-m-d H:i:s" );
    $ipblock = 'INSERT INTO blacklist_ip(ip_blackList_ip,os_blackList_ip,date_blacklist_ip) VALUES (?,?)';
    $quip = $conn->prepare($ipblock);
    $quip->execute([$ip, $os,$today]);
}


// Start Receive Register Form Register Student
if (isset($_POST['validation'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) { // Token UnFailed
        if (isset($_POST['codemeli-student']) and $_POST['codemeli-student'] != '' and isset($_POST['tel-student']) and $_POST['tel-student'] != '') { // check data Not empty
            if (check_national_code($_POST['codemeli-student'])) { // check codemeli Valid
                if (!checkCodemeli($_POST['codemeli-student'])) { // Check codemeli user
                    if (!checkTell($_POST['tel-student'])) {        // Check Tell Student
                        if (strlen($_POST['tel-student']) == 11) {

                            $otp = rand(1111, 9999);
                            $_SESSION['OTP'] = $otp;
                            $_SESSION['personal'] = array(
                                "codemeli" => $_POST['codemeli-student'],
                                "tel1" => $_POST['tel-student'],
                                "course"=> 0,
                                "ip"=> $ip,
                                "os"=> $os,
                                "role"=>1
                        );
                            if(!isset($_SESSION['count-sms'])){
                                $_SESSION['count-sms']=1;
                            }else{
                                $_SESSION['count-sms']=$_SESSION['count-sms']+1;
                            }

                            SendSMS($_POST['tel-student'],$otp,171696,$_POST['codemeli-student']);

                            header('location: smsVerification.php');
                            exit;

                        } else {
                            $erortel = 2;
                        }

                    } else {
                        $erortel = 1;
                    }

                } else {
                    $erorcodemeliStudent = 2;
                }

            } else {
                $erorcodemeliStudent = 1;  // codemeli not valid
            }

        } else {
            $tokeneror = 1;  //Data empty or not set
        }

    } else {
        $tokeneror = 1; // token empty or not set
    }
}
// End Receive Form Login
if (isset($_POST['validation-collague'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['tokenc']) { // Token UnFailed
        if (isset($_POST['codemeli-collague']) and $_POST['codemeli-collague'] != '' and isset($_POST['tel-collague']) and $_POST['tel-collague'] != '') { // check data Not empty
            if (check_national_code($_POST['codemeli-collague'])) { // check codemeli Valid
                if (!checkCodemeli($_POST['codemeli-collague'])) { // Check codemeli collague
                    if (!checkTell($_POST['tel-collague'])) {        // Check Tell collague
                        if (strlen($_POST['tel-collague']) == 11) {

                            $otp = rand(1111, 9999);
                            $_SESSION['OTP'] = $otp;
                            $_SESSION['personal'] = array(
                                "codemeli" => $_POST['codemeli-collague'],
                                "tel1" => $_POST['tel-collague'],
                                "course"=>0,
                                "ip"=> $ip,
                                "os"=> $os,
                                "role"=>2
                            );
                            if(!isset($_SESSION['count-sms'])){
                                $_SESSION['count-sms']=1;
                            }else{
                                $_SESSION['count-sms']=$_SESSION['count-sms']+1;
                            }

                            SendSMS($_POST['tel-collague'],$otp,171696,$_POST['codemeli-collague']);

                            header('location: smsVerification.php');
                            exit;

                        } else {
                            $erortelc = 2;
                        }

                    } else {
                        $erortelc = 1;
                    }

                } else {
                    $erorcodemelic = 2;
                }

            } else {
                $erorcodemelic = 1;  // codemeli not valid
            }

        } else {
            $tokenerorc = 1;  //Data empty or not set
        }

    } else {
        $tokenerorc = 1; // token empty or not set
    }
}

?>

<?php if (isset($_GET['op']) and $_GET['op'] == 'student' and $_GET['op'] != 'colleague'){
    if(!ActiveRegister_Student())
    {
        include_once '500.html';
        exit;
    }
    if (!ActiveRegister_Student_two()) {
    include_once '543.html';
    exit;
}
    ?>
<!DOCTYPE html>
<?php include_once 'temp.html'; ?>
<html lang="fa" dir="rtl">
<head>
    <title>عضویت دانش آموز | <?php echo __TITLE ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Morteza Belali Powered By hamsepar.ir">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
    <meta name="keywords" content="">
    <link rel="stylesheet" href="<?php echo url('assets/login/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="<?php echo url('assets/login/css/style.css') ?>">

    <!-- Internal Prism css-->
    <link href="<?php echo url('assets/plugins/prism/prism.css') ?>" rel="stylesheet">


</head>
<body>
<section class="ftco-section">
    <div class="container">
        <div class="row justify-content-center">

        </div>
        <div class="row justify-content-center">
            <div class="col-md-12 col-lg-10">
                <div class="wrap d-md-flex">
                    <div class="img" style="background-image: url(assets/login/images/bg-9.jpg);">


                    </div>

                    <div class="login-wrap p-4 p-md-5">
                        <?php if (isset($tokeneror) and $tokeneror === 1) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                خطای غیر منتظره رخ داده است
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        ?>
                        <?php if (isset($erorcodemeliStudent) and $erorcodemeliStudent === 1) { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                کدملی معتبر وارد کنید
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($erortel) and $erortel === 1) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <small>شماره تلفن وارد شده برای فرد دیگری ثبت شده است</small>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($erortel) and $erortel === 2) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <small>شماره تلفن وارد شده صحیح نیست</small>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($erorcodemeliStudent) and $erorcodemeliStudent === 2) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <small>شما دارای حساب کاربری می باشید، لطفا وارد حساب کاربری خود شوید</small>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
<!--                            <script>window.location.href = "login.php"</script>-->
                        <?php } ?>

                        <div class="d-flex">
                            <div class="w-100">
                                <h5 class="mb-4">عضویت در موسسه هنرآموزش</h5>


                            </div>

                        </div>
                        <form method="post" data-parsley-validate="">
                            <div class="form-group mb-3">
                                <label for="name"> کد ملی دانش آموز:</label>
                                <input type="number" name="codemeli-student" class="form-control" placeholder="کدملی "
                                       pattern="[0-9]{10}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="tel-student">شماره همراه دانش آموز:</label>
                                <input type="number" name="tel-student" class="form-control" placeholder="11 رقم کامل"
                                       pattern="^(?:9)?(0\d{10})$" required>
                            </div>
                            <?php
                            $hash = bin2hex(random_bytes(32));
                            $_SESSION['token'] = $hash;
                            ?>
                            <input type="hidden" name="token" value="<?php echo $hash ?>" required>
                            <div class="form-group">
                                <button type="submit" name="validation"
                                        class="form-control btn btn-primary rounded submit px-3"> بررسی و ارسال کد تاببد
                                </button>
                        </form>
                    </div>
                    <div class="form-group d-md-flex">
                        <div class="w-100 text-right">
                            <label class="checkbox-wrap checkbox-primary mb-0">


                            </label>
                            <div class="w-100 text-md-right text-black-50">
                                <a href="<?php echo url('login.php') ?>">ورود به حساب کاربری</a>
                            </div>
                        </div>
                    </div>

                    <p class="text-center">همکار آموزشی هستید؟ <a href="<?php echo url('register.php?op=colleague') ?>">عضویت
                            بصورت همکار</a></p>
                </div>

            </div>

        </div>
        <?php include_once 'footer.php'; ?>

    </div>
    </div>

</section>
<?php }
if (isset($_GET['op']) and $_GET['op'] == 'colleague' and $_GET['op'] != 'student'){
if(!ActiveRegister_Collague())
{
    include_once '500.html';
    exit;
}
if (!ActiveRegister_Collague_two()) {
    include_once '543.html';
    exit;
}
?>
<!DOCTYPE html>
<!-- =======================================================
    * Template Name: Register Form Programming V1.2.2
    * Template URL: https://honaramoozesh.ir
    * Admin Holder: DR. N.Keyvan
    * Author: Morteza belali , Msc University of Tehran
    * License: http://hamsepar.ir
     * Contact: elenetw@gmail.com
======================================================== -->
<html lang="fa" dir="rtl">
<head>
    <title>ثبت نام همکار | موسسه هنرآموزش</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Morteza Belali Powered By hamsepar.ir">
    <meta name="keywords" content="">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
    <link rel="stylesheet" href="<?php echo url('assets/login/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="<?php echo url('assets/login/css/style.css') ?>">
    <!-- Internal Prism css-->
    <link href="<?php echo url('assets/plugins/prism/prism.css') ?>" rel="stylesheet">

</head>
<body>
<section class="ftco-section">
    <div class="container">
        <div class="row justify-content-center">

        </div>
        <div class="row justify-content-center">
            <div class="col-md-12 col-lg-10">
                <div class="wrap d-md-flex">
                    <div class="img" style="background-image: url(assets/login/images/bg-8.jpg);">


                    </div>

                    <div class="login-wrap p-4 p-md-5">

                            <?php if (isset($tokenerorc) and $tokenerorc === 1) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    خطای غیر منتظره رخ داده است
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            ?>
                            <?php if (isset($erorcodemelic) and $erorcodemelic === 1) { ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    کدملی معتبر وارد کنید
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($erortelc) and $erortelc === 1) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <small>شماره تلفن وارد شده برای فرد دیگری ثبت شده است</small>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($erortelc) and $erortelc === 2) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <small>شماره تلفن وارد شده صحیح نیست</small>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($erorcodemelic) and $erorcodemelic === 2) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <small>شما دارای حساب کاربری می باشید، لطفا وارد حساب کاربری خود شوید</small>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
<!--                                <script>window.location.href = "login.php"</script>-->
                            <?php } ?>
                        <div class="d-flex">
                            <div class="w-100">
                                <h5 class="mb-4">عضویت در موسسه هنرآموزش</h5>

                            </div>

                        </div>
                        <form class="signin-form" method="post" data-parsley-validate="">
                            <div class="form-group mb-3">
                                <label for="name"> کد ملی همکارآموزشی</label>
                                <input type="number" name="codemeli-collague" class="form-control" placeholder="کدملی "
                                       pattern="[0-9]{10}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="password">شماره همراه همکارآموزشی</label>
                                <input type="number" name="tel-collague" class="form-control" placeholder="11 رقم کامل"
                                       pattern="^(?:9)?(0\d{10})$" required>
                            </div>
                            <?php
                            $hashc = bin2hex(random_bytes(32));
                            $_SESSION['token'] = $hashc;
                            ?>
                            <input type="hidden" name="tokenc" value="<?php echo $hashc ?>" required>
                            <div class="form-group">
                                <button type="submit" name="validation-collague"
                                        class="form-control btn btn-primary rounded submit px-3"> بررسی و ارسال کد تاببد
                                </button>
                            </div>
                            <div class="form-group d-md-flex">
                                <div class="w-100 text-right">

                                    <label class="checkbox-wrap checkbox-primary mb-0">


                                    </label>
                                    <div class="w-100 text-md-right text-black-50">
                                        <a href="<?php echo url('login.php') ?>">ورود به حساب کاربری</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <p class="text-center">دانش آموز هستید؟ <a href="<?php echo url('register.php?op=student') ?>">عضویت
                                بصورت دانش آموز</a></p>
                    </div>

                </div>

            </div>
            <?php include_once 'footer.php'; ?>

        </div>
    </div>

</section>
<?php } ?>

<script src="<?php echo url('assets/login/js/jquery.min.js') ?>"></script>
<script src="<?php echo url('assets/login/js/popper.js') ?> "></script>
<script src="<?php echo url('assets/login/js/bootstrap.min.js') ?> "></script>
<script src="<?php echo url('assets/login/js/main.js') ?>"></script>
<script src="<?php echo url('assets/plugins/prism/prism.js') ?>"></script>
<script src="<?php echo url('assets/js/form-validation.js') ?>"></script>
<script src="<?php echo url('assets/plugins/parsleyjs/parsley.min.js') ?>"></script>

</body>
</html>

