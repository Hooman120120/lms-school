<?php
include_once 'inc/jdf.php';
include_once 'dbconfig.php';
include_once 'inc/function_login.php';
include_once 'inc/function.php';


if (!isset($_SESSION['OTP'])) {
    header('location: register.php?FalseCode');
    exit;
}

// if( strlen($_SESSION['OTP'] != 4)) {
//     header('location: register.php?FalseCode');
//     exit;
// }
if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    die();
}

//Protecting against SESSION Hijaking
if ($_SESSION['personal']['ip'] != GetIp() and $_SESSION['personal']['os'] != GetOs()) {
    unset($_SESSION['OTP']);


    header('location: register.php?Hijaking');
    die();
}
//--End of Protecting against SESSION Hijaking

if (isset($_SESSION['count-sms']) and $_SESSION['count-sms'] > 10) {
    $ip = GetIp();
    $today = date("Y-m-d H:i:s");
    $ipblock = 'INSERT INTO blacklist_ip(ip_blackList_ip,date_blacklist_ip) VALUES (?,?)';
    $quip = $conn->prepare($ipblock);
    $quip->execute([$ip, $today]);
}

if (isset($_GET['changnumber'])) {

    if ($_SESSION['personal']['role'] == 1) {
        unset($_SESSION['OTP']);
        unset($_SESSION['token-sms']);
        unset($_SESSION['token']);
        header('location: register.php');
        die();
    } else {
        unset($_SESSION['OTP']);
        unset($_SESSION['token-sms']);
        unset($_SESSION['token']);
        header('location: register.php?op=colleague');
        die();
    }
}
//send again sms
if (isset($_GET['againSms'])) {
    if (!isset($_SESSION['againsms'])) {
        $_SESSION['againsms'] = 1;
    } else {
        $_SESSION['againsms'] = $_SESSION['againsms'] + 1;
    }
    if ($_SESSION['againsms'] > 3) {
        if ($_SESSION['personal']['role'] == 1) {
            unset($_SESSION['OTP']);
            unset($_SESSION['token-sms']);
            unset($_SESSION['token']);
            unset($_SESSION['againsms']);
            header('location: register.php');
            die();
        } else {
            unset($_SESSION['OTP']);
            unset($_SESSION['token-sms']);
            unset($_SESSION['token']);
            unset($_SESSION['againsms']);
            header('location: register.php?op=colleague');
            die();
        }
    }
    SendSMS($_SESSION['personal']['tel1'], $_SESSION['OTP'], 171696, $_SESSION['personal']['codemeli']);
    header('location: smsVerification.php');
    die();
}
//End - Again sms
if (isset($_POST['submit'])) {

    if (isset($_POST['token']) and $_POST['token'] == $_SESSION['token-sms']) {

        $otp = intval($_POST['num1']) . intval($_POST['num2']) . intval($_POST['num3']) . intval($_POST['num4']);

        if ($_SESSION['OTP'] == $otp) {


            unset($_SESSION['token-sms']);
            unset($_SESSION['token']);
            unset($_SESSION['OTP']);
            unset($_SESSION['course']);
            unset($_SESSION['againsms']);
            unset($_SESSION['count-sms']);
            $_SESSION['personal'] = array(
                "tel1" => $_SESSION['personal']['tel1'],
                "course" => $_SESSION['personal']['course'],
                "role" => $_SESSION['personal']['role'],
                "ip" => $_SESSION['personal']['ip'],
                "os" => $_SESSION['personal']['os'],
                "verify" => 100
            );

            header('location: Sendinformation-experimental.php?tel=' . $_SESSION['personal']['tel1']);
            exit;
        } else {
            $erorcode = 1;
        }
    } else {
        $erorunknown = 1;
    }
}


?>
<!DOCTYPE html>
<?php include_once 'temp.html'; ?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>تایید شماره تلفن | <?php echo __TITLE ?></title>

    <link rel='stylesheet' href='assets/plugins/bootstrap/css/bootstrap.min.css'>
    <link rel="stylesheet" href="./style.css">

</head>

<body>
    <!-- partial:index.partial.html -->
    <div id="wrapper" style="padding-top: 50px;">
        <div id="dialog">

            <h6>کد 4 رقمی ارسال شده به شماره زیر را وارد کنید</h6>
            <h6 style="color: #067f25"><?php echo $_SESSION['personal']['tel1']; ?></h6>
            <?php if (isset($erorcode) and $erorcode == 1) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    کد وارد شده صحیح نمی باشد
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php }
            if (isset($erorunknown) and $erorunknown == 1) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    خطای غیرمنتظره رخ داده است
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php }
            if (isset($_SESSION['againsms'])) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    پیامک <?php echo $_SESSION['againsms']; ?> با موفقیت ارسال شد
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php } ?>
            <div id="form">
                <?php
                $hashsms = bin2hex(random_bytes(8));
                $_SESSION['token-sms'] = $hashsms;
                ?>
                <form method="post">
                    <input type="number" maxLength="1" size="1" min="0" max="9" pattern="[0-9]{1}" name="num1" required />
                    <input type="number" maxLength="1" size="1" min="0" max="9" pattern="[0-9]{1}" name="num2" required />
                    <input type="number" maxLength="1" size="1" min="0" max="9" pattern="[0-9]{1}" name="num3" required />
                    <input type="number" maxLength="1" size="1" min="0" max="9" pattern="[0-9]{1}" name="num4" required />
                    <input type="hidden" name="token" value="<?php echo $hashsms ?>">
                    <!--                <hidden>--><?php //echo $_SESSION['OTP']; 
                                                    ?><!-- </hidden>-->
                    <button class="btn btn-primary btn-embossed" type="submit" name="submit"><strong>تایید کد</strong>
                    </button>
                </form>
            </div>

            <div>
                کد دریافت نکردید؟<br />
                <a href="?againSms" style="position: absolute;top: auto;right: 5px;">
                    <button class="btn btn-secondary">ارسال مجدد کد</button>
                </a><br />
                <a href="?changnumber" style="position: absolute;left: 10px;bottom: 0;">
                    <button class="btn btn-secondary">تغییر شماره</button>
                </a>
            </div>

        </div>
        <div class="trademark footer " style="padding: 10px; font-size: 14px; direction: rtl"> &trade; مرکز یادگیری های
            الکترونیکی <a href="https://honaramoozesh.ir" target="_blank"> موسسه هنرفناوری</a>
            &copy; <?php echo jdate('Y', ''); ?>

        </div>
    </div>
    <!-- partial -->

    <script src='assets/plugins/jquery/jquery.min.js'></script>
    <script src="<?php echo url('assets/login/js/bootstrap.min.js') ?> "></script>
    <script src="./script.js"></script>

</body>

</html>