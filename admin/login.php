<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!ActiveAdmin()) {
    include_once '../543.html';
    exit;
}
if (isset($_SESSION['admin'])){
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (isset($_POST['loginadmin'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {  // Token UnFailed
        if ($_POST['user'] != '' and $_POST['pass'] != '') {                     // Input Field Not empty
            if ($_POST['user'] != NULL and $_POST['pass'] != NULL) {                  // Input Field Not Null
                if (GetAdminUsername($_POST['user'])) {                                //Check Username Valid
                    if (CheckAdminAcount($_POST['user'], $_POST['pass'])) {             //Check Username And Password Login
                        $sql1 = 'SELECT * FROM dbadmin WHERE username=? AND password=?';
                        $query1 = $conn->prepare($sql1);
                        $query1->execute([$_POST['user'], $_POST['pass']]);
                        $res = $query1->fetch();
                        if ($res['adminactive'] == 0) {
                            $erorlock = 1;
                        } else {
                            $ip = GetIp();
                            $os = GetOs();
                            $sql2 = 'SELECT * FROM dbadmin WHERE username=?';
                            $query2 = $conn->prepare($sql2);
                            $query2->execute([$_POST['user']]);
                            $result = $query2->fetch();
                            $session_id = session_id();
                            $_SESSION['admin'] = array(
                                "idadmin" => $result['idadmin'],
                                "name" => $result['fname'],
                                "famili" => $result['lname'],
                                "tel" => $result['mobile'],
                                "dakheli" => $result['dakheli'],
                                "user" => $_POST['user'],
                                "adminrole" => $result['adminrole'],
                                "can_impersonate" => (int)($result['can_impersonate'] ?? 0), 
                                "ip" => $ip,
                                "os" => $os
                            );
                            date_default_timezone_set("Iran");
                            $datenow = date("Y-m-d");
                            $timenow = date("H:i:s");

                            $sql3 = 'INSERT INTO dbh_admin(username,datelogin,timelogin,session_id,ip,os) VALUES (?,?,?,?,?,?)';
                            $query3 = $conn->prepare($sql3);
                            $query3->execute([$_POST['user'], $datenow, $timenow, $session_id, $ip, $os]);
                            header('location: index.php');
                            // header('location: index.php?' . $result['fname'] . '+' . $result['lname']);
                            exit;
                        }
                    }else {
                        $Notvaliduser = 1;
                        //                lock user
                        if (isset($_SESSION['login'])) {
                            $_SESSION['login'] = $_SESSION['login'] + 1;
                        } else {
                            $_SESSION['login'] = 1;
                        }
                        if ($_SESSION['login'] > 5) {
                            $inactive = 'UPDATE dbadmin SET adminactive=? WHERE username=?';
                            $qu_update = $conn->prepare($inactive);
                            $qu_update->execute([0, $_POST['user']]);
                        }
                    }
                }else {
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
            }else {
                $tokeneror = 1;
            }
        }else {
            $tokeneror = 1;
        }
    }else {
        $tokeneror = 1;
    }
}
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>

    <title>  ورود پشتیبانان | <?php echo __TITLE ?></title>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="">
    <meta name="author" content="Morteza Belali Powered By hamsepar.ir">
    <meta name="keywords" content="">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo url('assets/img/media/loader.gif') ?>" type="image/x-icon"/>
    <!-- Bootstrap css-->
    <link href="../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
    <!-- Icons css-->
    <link href="../assets/plugins/web-fonts/icons.css" rel="stylesheet"/>
    <link href="../assets/plugins/web-fonts/font-awesome/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/plugins/web-fonts/plugin.css" rel="stylesheet"/>
    <!-- Style css-->
    <link href="../assets/css-rtl/style/style.css" rel="stylesheet">
    <link href="../assets/css-rtl/skins.css" rel="stylesheet">
    <link href="../assets/css-rtl/dark-style.css" rel="stylesheet">
    <link href="../assets/css-rtl/colors/default.css" rel="stylesheet">


</head>

<body>
<div class="page main-signin-wrapper">
    <!-- Row -->
    <div class="row signpages text-center">
        <div class="col-md-12">
            <div class="card">
                <div class="row row-sm">

                    <div class="col-lg-6 col-xl-5 d-none d-lg-block text-center bg-info details">

                        <div class="mt-5 pt-4 p-2 pos-absolute">
                            <img src="../assets/img/logo-white.png" class="header-brand-img mb-4" alt="logo">
                            <div class="clearfix"></div>
                            <img src="../assets/img/svgs/user.svg" class="ht-100 mb-0" alt="user">

                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-7 col-xs-12 col-sm-12 login_form ">
                        <div class="container-fluid">
                            <div class="row row-sm">
                                <div class="card-body mt-2 mb-2">
                                    <div class="clearfix"></div>
                                    <form method="post">
                                        <h5 class="text-right mb-2">به حساب خود وارد شوید</h5>
                                        <p class="mb-4 text-muted tx-13 ml-0 text-right"></p>
                                        <div class="form-group text-right">
                                            <label>نام کاربری</label>
                                            <input class="form-control"
                                                   placeholder="نام کاربری را وارد کنید"
                                                   type="text" required="" name="user">
                                        </div>
                                        <div class="form-group text-right">
                                            <label>رمز عبور</label>
                                            <input class="form-control"
                                                   placeholder="رمز عبور را وارد کنید"
                                                   type="password" required="" name="pass">
                                        </div>
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input type="hidden" name="token" value="<?php echo $hash ?>">
                                        <button name="loginadmin"
                                                class="btn ripple btn-main-primary btn-block">ورود</button>
                                    </form>
                                    <div class="text-center mt-5 ml-0 text-warning">
                                        <?php
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include_once '../footer.php'; ?>
    </div>

    <!-- End Row -->


</div>


<script src="../assets/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap js-->
<script src="../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<!-- Select2 js-->
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<!-- Custom js -->
<script src="../assets/js/custom.js"></script>

</body>
</html>
