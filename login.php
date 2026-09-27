<?php
include_once 'dbconfig.php';
include_once 'inc/function_login.php';
include_once 'inc/function.php';
include_once 'inc/jdf.php';


if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if (!ActiveLogin()) {
    include_once '500.html';
    exit;
}
if (!ActiveLogintwo()) {
    include_once '543.html';
    exit;
}
// Start Receive Form Login
if (isset($_POST['loginuser'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {  // Token UnFailed
        if ($_POST['user'] != '' and $_POST['pass'] != '') {                     // Input Field Not empty
            if ($_POST['user'] != NULL and $_POST['pass'] != NULL) {                  // Input Field Not Null
                if (GetUsername($_POST['user'])) {                                //Check Username Valid
                    if (CheckAcount($_POST['user'], $_POST['pass'])) {             //Check Username And Password Login
                        $sql1 = 'SELECT * FROM dblogin WHERE username=? AND password=?';
                        $query1 = $conn->prepare($sql1);
                        $query1->execute([$_POST['user'], $_POST['pass']]);
                        $res = $query1->fetch();
                        if ($res['activity'] == 0) {
                            $erorlock = 1;
                        } else {
                            try {
                                hf_control_require_panel_access($conn, true);
                            } catch (Throwable $e) {
                                $hfAccessError = $e->getMessage();
                                $res = null;
                            }
                            if ($res === null) {
                                goto login_user_finished;
                            }
                            $ip = GetIp();
                            $os = GetOs();
                            $sql2 = 'SELECT * FROM dbpersonal WHERE codemeli=?';
                            $query2 = $conn->prepare($sql2);
                            $query2->execute([$_POST['user']]);
                            $result = $query2->fetch();
                            $session_id = session_id();
                            $_SESSION['auth'] = array(
                                "codemeli" => $result['codemeli'],
                                "name" => $result['fname'],
                                "famili" => $result['lname'],
                                "pic" => $result['pic'],
                                "tel1" => $result['tel1'],
                                "paye" => $result['paye'],
                                "suspension_mode" => $result['suspension_mode'],
                                "course" => 0,
                                "user" => $_POST['user'],
                                "role" => $res['role'],
                                "keyactive" => $res['keyactive'],
                                "truekey" => $res['true_key'],
                                "ip" => $ip,
                                "os" => $os
                            );
                            date_default_timezone_set("Iran");
                            $datenow = date("Y-m-d");
                            $timenow = date("H:i:s");

                            $sql3 = 'INSERT INTO dbh_login(username,datelogin,timelogin,session_id,ip,os) VALUES (?,?,?,?,?,?)';
                            $query3 = $conn->prepare($sql3);
                            $query3->execute([$_POST['user'], $datenow, $timenow, $session_id, $ip, $os]);
                            $updating='UPDATE dblogin SET firstlogin=? WHERE username=?';
                            $query4=$conn->prepare($updating);
                            $query4->execute([1,$_POST['user']]);
                            // header('location: index.php?' . $result['fname'] . '+' . $result['lname']);
                            header('location: index.php');
                            exit;

                        }


                    } else {
                        $Notvaliduser = 1;
                        //                lock user
                        if (isset($_SESSION['login'])) {
                            $_SESSION['login'] = $_SESSION['login'] + 1;
                        } else {
                            $_SESSION['login'] = 1;
                        }
                        if ($_SESSION['login'] > 5) {
                            $inactive = 'UPDATE dblogin SET activity=? WHERE username=?';
                            $qu_update = $conn->prepare($inactive);
                            $qu_update->execute([0, $_POST['user']]);
                        }
                    }
                } else {
                    $Notvaliduser = 1;
                    //            IP blacklist
                    if (isset($_SESSION['user'])) {
                        $_SESSION['user'] = $_SESSION['user'] + 1;
                    } else {
                        $_SESSION['user'] = 1;
                    }
                    if ($_SESSION['user'] > 10) {
                        $ip = GetIp();
                        $os = GetOs();
                        date_default_timezone_set("Iran");
                        $today = date("Y-m-d H:i:s");
                        $incheck_login = 'INSERT INTO blacklist_ip(ip_blackList_ip,os_blackList_ip,date_blacklist_ip) VALUES (?,?,?)';
                        $query_incheck = $conn->prepare($incheck_login);
                        $query_incheck->execute([$ip, $os, $today]);
                        include_once 'blackList.html';
                        exit;
                    }
                }
            } else {
                $tokeneror = 1;
            }

        } else {
            $tokeneror = 1;
        }
    } else {
        $tokeneror = 1;
    }
}
login_user_finished:
// End Receive Form Login

//* Start Receive Forget password
if (isset($_POST['forget-user'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (isset($_SESSION['forget-sms']) and $_SESSION['forget-sms'] > 3) {
            $erorMaxsms = 1;
        } else {
            if (!empty($_POST['codemeli']) and $_POST['codemeli'] != '') {
                if (GetUsername($_POST['codemeli'])) {
                    $tel = $_POST['tel'];
                    $birthdate = jalali_to_gregorian($_POST['sal'], $_POST['mah'], $_POST['roz']);
                    $birthdate = implode('-', $birthdate);
                    if (forgetPass($_POST['codemeli'], $tel, $birthdate)) {
                        $sql = 'SELECT * FROM dblogin WHERE username=?';
                        $query = $conn->prepare($sql);
                        $query->execute([$_POST['codemeli']]);
                        $respon = $query->fetch(PDO::FETCH_OBJ);
                        if ($respon->activity == 1) {
                            $pass = $respon->password;

                            //* Start Send SMS Password
                             $text='کد ملی'.';'.$pass;
                            SendSms($tel, $text, 171698, $_POST['codemeli']);


                            //* End Send SMS Password
                            $versms = 1;
                            if (!isset($_SESSION['forget-sms'])) {
                                $_SESSION['forget-sms'] = 1;
                            } else {
                                $_SESSION['forget-sms'] = $_SESSION['forget-sms'] + 1;
                            }


                        } else {
                            $erorlock = 1;
                        }

                    } else {
                        $Notvaliduser = 1;
                        //            IP blacklist
                        if (isset($_SESSION['user'])) {
                            $_SESSION['user'] = $_SESSION['user'] + 1;
                        } else {
                            $_SESSION['user'] = 1;
                        }
                        if ($_SESSION['user'] > 10) {
                            $ip = GetIp();
                            $os = GetOs();
                            date_default_timezone_set("Iran");
                            $today = date("Y-m-d H:i:s");
                            $incheck_login = 'INSERT INTO blacklist_ip(ip_blackList_ip,os_blackList_ip,date_blacklist_ip) VALUES (?,?,?)';
                            $query_incheck = $conn->prepare($incheck_login);
                            $query_incheck->execute([$ip, $os, $today]);
                            include_once 'blackList.html';
                            exit;
                        }
                    }


                } else {
                    $Notvaliduser = 1;
                    //            IP blacklist
                    if (isset($_SESSION['user'])) {
                        $_SESSION['user'] = $_SESSION['user'] + 1;
                    } else {
                        $_SESSION['user'] = 1;
                    }
                    if ($_SESSION['user'] > 10) {
                        $ip = GetIp();
                        $os = GetOs();
                        date_default_timezone_set("Iran");
                        $today = date("Y-m-d H:i:s");
                        $incheck_login = 'INSERT INTO blacklist_ip(ip_blackList_ip,os_blackList_ip,date_blacklist_ip) VALUES (?,?,?)';
                        $query_incheck = $conn->prepare($incheck_login);
                        $query_incheck->execute([$ip, $os, $today]);
                        include_once 'blackList.html';
                        exit;
                    }
                }

            } else {
                $tokeneror = 1;
            }
        }

    } else {
        $tokeneror = 1; // token empty or not set
    }
}
?>

<!doctype html>
<?php include_once 'temp.html'; ?>
<?php
if (isset($_GET['forget'])){ ?>
<html lang="fa" dir="rtl">
<head>
    <title>فراموشی رمز عبور | <?php echo __TITLE ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="Morteza Belali Powered By hamsepar.ir">
    <meta name="keywords" content="">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
    <link rel="stylesheet" href="<?php echo url('assets/login/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="assets/login/css/style.css">
    <!-- Select2 css -->
    <link href="<?php echo url('assets/plugins/select2/css/select2.min.css') ?>" rel="stylesheet">
    <!-- Internal Prism css-->
    <link href="<?php echo url('assets/plugins/prism/prism.css') ?>" rel="stylesheet">


<script type="text/javascript">
if (document.layers) {
    //Capture the MouseDown event.
    document.captureEvents(Event.MOUSEDOWN);
 
    //Disable the OnMouseDown event handler.
    document.onmousedown = function () {
        return false;
    };
}
else {
    //Disable the OnMouseUp event handler.
    document.onmouseup = function (e) {
        if (e != null && e.type == "mouseup") {
            //Check the Mouse Button which is clicked.
            if (e.which == 2 || e.which == 3) {
                //If the Button is middle or right then disable.
                return false;
            }
        }
    };
}
 
//Disable the Context Menu event.
document.oncontextmenu = function () {
    return false;
};
</script>
</head>
<html>
<body>

<section class="ftco-section">
    <div class="container">

        <div class="row justify-content-center">
            <div class="col-md-12 col-lg-8">
                <div class="wrap d-md-flex">


                    <div class="login-wrap p-4 p-md-5">
                        <?php if (isset($erorMaxsms) and $erorMaxsms === 1) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                سقف ارسال پیامک برای شما به پایان رسیده است. لطفاً ساعتی بعد امتحان کنید
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($hfAccessError)) { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($hfAccessError, ENT_QUOTES, 'UTF-8') ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($tokeneror) and $tokeneror === 1) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>متاسفم!</strong> خطای غیر منتظره رخ داده است
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($Notvaliduser) and $Notvaliduser === 1) { ?>

                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>خطا! </strong> اطلاعات وارد شده صحیح نمی باشد
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($erorlock) and $erorlock === 1) { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong> خطا! </strong>نام کاربری غیرفعال شده است
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php }
                        if (isset($versms) and $versms === 1) { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                رمز عبورتان به شماره <?php echo $_POST['tel'] ?> پیامک شد
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php } ?>

                        <!--                        <div class="d-flex">-->
                        <div class="w-100">
                            <h3 class="mb-4">فراموشی رمز عبور</h3>
                        </div>

                        <!--                        </div>-->
                        <form class="signin-form" method="post" data-parsley-validate="">
                            <div class="form-group mb-3">
                                <label for="code">کد ملی:</label><span style="color: red;">*</span>
                                <input type="text" name="codemeli" class="form-control" placeholder=" کدملی "
                                       pattern="[0-9]{10}" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="tel">شماره همراه :</label><span style="color: red;">*</span>
                                <input type="number" name="tel" class="form-control" placeholder="11 رقم کامل"
                                       pattern="^(?:9)?(0\d{10})$" required>
                            </div>


                            <?php
                            $hash = bin2hex(random_bytes(32));
                            $_SESSION['token'] = $hash;
                            ?>
                            <input type="hidden" name="token" value="<?php echo $hash ?>" required>

                            <div class="form-group mb-3">
                                <label for="">تاریخ تولد:</label><span style="color: red;">*</span>
                                <div class="row row-sm">

                                    <div class="col-lg-4 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    روز:
                                                </div>

                                                <select class="form-control select2 " name="roz"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="روز تولد ">
                                                    </option>
                                                    <option>01</option>
                                                    <option>02</option>
                                                    <option>03</option>
                                                    <option>04</option>
                                                    <option>05</option>
                                                    <option>06</option>
                                                    <option>07</option>
                                                    <option>08</option>
                                                    <option>09</option>
                                                    <option>10</option>
                                                    <option>11</option>
                                                    <option>12</option>
                                                    <option>13</option>
                                                    <option>14</option>
                                                    <option>15</option>
                                                    <option>16</option>
                                                    <option>17</option>
                                                    <option>18</option>
                                                    <option>19</option>
                                                    <option>20</option>
                                                    <option>21</option>
                                                    <option>22</option>
                                                    <option>23</option>
                                                    <option>24</option>
                                                    <option>25</option>
                                                    <option>26</option>
                                                    <option>27</option>
                                                    <option>28</option>
                                                    <option>29</option>
                                                    <option>30</option>
                                                    <option>31</option>


                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mg-t-auto">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    ماه:
                                                </div>

                                                <select class="form-control select2 " name="mah"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="ماه تولد ">
                                                    </option>
                                                    <option value="1">فروردین</option>
                                                    <option value="2">اردیبهشت</option>
                                                    <option value="3">خرداد</option>
                                                    <option value="4">تیر</option>
                                                    <option value="5">مرداد</option>
                                                    <option value="6">شهریور</option>
                                                    <option value="7">مهر</option>
                                                    <option value="8">آبان</option>
                                                    <option value="9">آذر</option>
                                                    <option value="10">دی</option>
                                                    <option value="11">بهمن</option>
                                                    <option value="12">اسفند</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 mg-t-auto">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    سال:
                                                </div>

                                                <select class="form-control select2 "
                                                        aria-describedby="basic-addon2"
                                                        required="" name="sal">
                                                    <option label="سال تولد ">
                                                    </option>
                                                    <option>1393</option>
                                                    <option>1392</option>
                                                    <option>1391</option>
                                                    <option>1390</option>
                                                    <option>1389</option>
                                                    <option>1388</option>
                                                    <option>1387</option>
                                                    <option>1386</option>
                                                    <option>1385</option>
                                                    <option>1384</option>
                                                    <option>1383</option>
                                                    <option>1382</option>
                                                    <option>1381</option>
                                                    <option>1380</option>
                                                    <option>1379</option>
                                                    <option>1378</option>
                                                    <option>1377</option>
                                                    <option>1376</option>
                                                    <option>1375</option>
                                                    <option>1374</option>
                                                    <option>1373</option>
                                                    <option>1372</option>
                                                    <option>1371</option>
                                                    <option>1370</option>
                                                    <option>1369</option>
                                                    <option>1368</option>
                                                    <option>1367</option>
                                                    <option>1366</option>
                                                    <option>1365</option>
                                                    <option>1364</option>
                                                    <option>1363</option>
                                                    <option>1362</option>
                                                    <option>1361</option>
                                                    <option>1360</option>
                                                    <option>1359</option>
                                                    <option>1358</option>
                                                    <option>1357</option>
                                                    <option>1356</option>
                                                    <option>1355</option>
                                                    <option>1354</option>
                                                    <option>1353</option>
                                                    <option>1352</option>
                                                    <option>1351</option>
                                                    <option>1350</option>
                                                    <option>1349</option>
                                                    <option>1348</option>
                                                    <option>1347</option>
                                                    <option>1346</option>
                                                    <option>1345</option>
                                                    <option>1344</option>
                                                    <option>1343</option>
                                                    <option>1342</option>
                                                    <option>1341</option>
                                                    <option>1340</option>
                                                    <option>1339</option>
                                                    <option>1338</option>
                                                    <option>1337</option>
                                                    <option>1336</option>
                                                    <option>1335</option>
                                                    <option>1334</option>
                                                    <option>1333</option>
                                                    <option>1332</option>
                                                    <option>1331</option>
                                                    <option>1330</option>
                                                    <option>1329</option>
                                                    <option>1328</option>
                                                    <option>1327</option>
                                                    <option>1326</option>
                                                    <option>1325</option>
                                                    <option>1324</option>
                                                    <option>1323</option>
                                                    <option>1322</option>
                                                    <option>1321</option>
                                                    <option>1320</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div>
                            <div class="form-group">
                                <button type="submit" name="forget-user"
                                        class="form-control btn btn-primary rounded submit px-3">بررسی و ارسال
                                </button>
                            </div>

                        </form>
                        <div class="w-100 text-md-right">
                            <a href="<?php echo url('login.php') ?>" class="text-black-50">ورود به حساب کاربری</a>
                        </div>
                        <p class="text-center">حساب کاربری ندارید؟ <a
                                    href="<?php echo url('register.php?op=student') ?>">عضو شوید</a></p>
                    </div>

                </div>

            </div>
          

        </div>
         <div class="text-center">
          <?php include_once 'footer.php'; ?>
            </div>
    </div>

</section>

<script src="<?php echo url('assets/login/js/jquery.min.js') ?>"></script>
<script src="<?php echo url('assets/login/js/popper.js') ?> "></script>
<script src="<?php echo url('assets/login/js/bootstrap.min.js') ?> "></script>
<script src="<?php echo url('assets/login/js/main.js') ?>"></script>
<!-- Select2 js-->
<script src="<?php echo url('assets/plugins/select2/js/select2.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/prism/prism.js') ?>"></script>
<script src="<?php echo url('assets/js/form-validation.js') ?>"></script>
<script src="<?php echo url('assets/plugins/parsleyjs/parsley.min.js') ?>"></script>

</body>
</html>

<?php
} else {
    ?>
    <html lang="fa" dir="rtl">
    <head>
        <title>ورود به حساب کاربری | <?php echo __TITLE ?></title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="Morteza Belali Powered By hamsepar.ir">
        <meta name="keywords" content="">
        <!-- Favicon -->
        <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
        <link rel="stylesheet" href="<?php echo url('assets/login/css/font-awesome.min.css') ?>">
        <link rel="stylesheet" href="<?php echo url('assets/login/css/style.css') ?>">


    </head>
    <body>

    <section class="ftco-section">
        <div class="container">
            <div class="row justify-content-center">

            </div>
            <div class="row justify-content-center">
                <div class="col-md-12 col-lg-10" >
                    <div class="wrap d-md-flex" style="border-radius: 20px;">
                        <div class="img" style="background-image: url(assets/login/images/login.jpg);">


                        </div>

                        <div class="login-wrap p-4 p-md-5">
                            <?php if (isset($hfAccessError)) { ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($hfAccessError, ENT_QUOTES, 'UTF-8') ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($tokeneror) and $tokeneror === 1) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <strong>متاسفم!</strong> خطای غیر منتظره رخ داده است
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($Notvaliduser) and $Notvaliduser === 1) { ?>

                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <strong>خطا! </strong> نام کاربری یا رمز عبور نادرست
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php }
                            if (isset($erorlock) and $erorlock === 1) { ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <strong> خطا! </strong>نام کاربری غیرفعال شده است
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>

                            <div class="d-flex">
                                <div class="w-100">
                                    <h3 class="mb-4">اطلاعات کاربری</h3>
                                </div>

                            </div>
                            <form class="signin-form" method="post">
                                <div class="form-group mb-3">
                                    <label for="name">نام کاربری</label>
                                    <input type="text" name="user" class="form-control" placeholder="نام کاربری"
                                           required>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="password">رمز عبور</label>
                                    <input type="password" name="pass" class="form-control" placeholder="گذرواژه"
                                           required>
                                </div>
                                <?php
                                $hash = bin2hex(random_bytes(32));
                                $_SESSION['token'] = $hash;
                                ?>
                                <input type="hidden" name="token" value="<?php echo $hash ?>" required>
                                <div class="form-group">
                                    <button type="submit" name="loginuser"
                                            class="form-control btn btn-primary rounded submit px-3"> ورود
                                    </button>
                                </div>
                                <div class="form-group d-md-flex">
                                    <div class="w-100 text-right">
                                        <input type="checkbox" checked>
                                        مرا بخاطر بسپار
                                        <label class="checkbox-wrap checkbox-primary mb-0">


                                        </label>
                                        <div class="w-100 text-md-left text-black-50">
                                            <a href="?forget">فراموشی رمز عبور</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <p class="text-center">حساب کاربری ندارید؟ <a
                                        href="<?php echo url('register-experimental.php?op=student') ?>">عضو شوید</a></p>
                        </div>

                    </div>

                </div>
                <?php include_once 'footer.php'; ?>
            </div>
           

        </div>
         

    </section>

    <script src="<?php echo url('assets/login/js/jquery.min.js') ?>"></script>
    <script src="<?php echo url('assets/login/js/popper.js') ?> "></script>
    <script src="<?php echo url('assets/login/js/bootstrap.min.js') ?> "></script>
    <script src="<?php echo url('assets/login/js/main.js') ?>"></script>
    <script src="<?php echo url('assets/plugins/prism/prism.js') ?>"></script>
    <script src="<?php echo url('assets/js/form-validation.js') ?>"></script>
    <script src="<?php echo url('assets/plugins/parsleyjs/parsley.min.js') ?>"></script>

    </body>
    </html>
<?php } ?>

