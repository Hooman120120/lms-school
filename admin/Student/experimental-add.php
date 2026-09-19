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
if (isset($_GET['change'])) {
    unset($_SESSION['add-student2']);
    header('location: ' . url('admin/Student/experimental-add.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            $melliat = $_POST['melliat'];
            if ($melliat == '01') {
                if (check_national_code($codemeli)) {
                    if (!checkCodemeli($codemeli)) {
                        $_SESSION['add-student2'] = array(
                            "codemeli" => $_POST['codemeli'],
                            "melliat" => 'ایرانی'
                        );
                    } else {
                        $havemeli = 1;
                    }
                } else {
                    $erormeli = 1;
                }
            } else {
                if (!checkCodemeli($codemeli)) {
                    $_SESSION['add-student2'] = array(
                        "codemeli" => $_POST['codemeli'],
                        "melliat" => 'اتباع'
                    );
                } else {
                    $havemeli = 1;
                }
            }
        }
    } else {
        $erortoken = 1;
    }
}
if (isset($_POST['search-tel'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['tel'] != '') {
            if (isset($_POST['tel-trust'])) {
                if (strlen($_POST['tel']) == 11) {
                    $_SESSION['add-student2'] =
                        array(
                            "tel" => $_POST['tel'],
                            "tel-trust" => 1
                        );
                } else {
                    $erormeli = 1;
                }
            } else {
                if (!checkTell2($_POST['tel'])) {        // Check Tell
                    if (strlen($_POST['tel']) == 11) {
                        $_SESSION['add-student2'] =
                            array(
                                "tel" => $_POST['tel']
                            );
                    }
                } else {
                    $havetel = 1;
                }
            }
        }
    }
}
$cities = null;
if (isset($_POST['state'])) {
    $state = $_POST['state'];
    $res = $conn->query("SELECT * FROM city WHERE state_id=$state");
    $cities = array();
    while ($row = $res->fetch()) {
        $cities[] = $row;
    }
    $final = '';
    foreach ($cities as $city) {
        $id = $city['id'];
        $name = $city['name'];
        $final .= "<option value=$name>$name</option>";
    }
    die($final);
}


$result = $conn->query("SELECT * FROM `state`");

$states = array();
while ($row = $result->fetch()) {
    $states[] = $row;
}
if (isset($_POST['submit-student']) and isset($_SESSION['add-student2'])) {
    if (
        $_POST['fname-student'] != NULL and $_POST['lname-student'] != NULL and $_POST['paye-student'] != NULL and $_POST['reshte-student'] != NULL and $_POST['gender-student'] != NULL
    ) {
        $ip = GetIp();
        $os = GetOs();
        if ($_POST['paye-student'] == '5' or $_POST['paye-student'] == '6' or $_POST['paye-student'] == '7' or $_POST['paye-student'] == '8' or $_POST['paye-student'] == '9') {
            $_POST['reshte-student'] = 'ندارد';
        } else {
            switch ($_POST['reshte-student']) {
                case '0':
                    $_POST['reshte-student'] = "ریاضی";
                    break;
                case '1':
                    $_POST['reshte-student'] = "تجربی";
                    break;
                case '2':
                    $_POST['reshte-student'] = "انسانی";
                    break;
                case "3":
                    $_POST['reshte-student'] = 'فنی و حرفه ای';
                    break;
            }
        }
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $fnameStudent = trim($_POST['fname-student']);
        $lnameStudent = trim($_POST['lname-student']);
        $genderStudent = trim($_POST['gender-student']);
        if (!checkTell2($_SESSION['add-student2']['tel']) || isset($_SESSION['add-student2']['tel-trust'])) {
            $codemeliValue = $_SESSION['add-student2']['tel'];

                // If checking against dbpersonal shows codemeli exists, append a unique timestamp/rand suffix
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = ?");
            $checkStmt->execute([$codemeliValue]);
            if ($checkStmt->fetchColumn() > 0) {
                // If it exists and starts with '09', change the prefix '09' to '90'
                if (strpos($_SESSION['add-student2']['tel'], '09') === 0) {
                    $codemeliValue = '90' . substr($_SESSION['add-student2']['tel'], 2);
                } else {
                    // Fallback prefix swap if it starts with '0'
                    $codemeliValue = '9' . substr($_SESSION['add-student2']['tel'], 1);
                }
            }
            $insert = 'INSERT INTO dbpersonal (codemeli,fname,lname,gender,tel1,paye,reshtestudent,datecreated,timecreated,ipcreated,oscreated,creator) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
            $insert_student = $conn->prepare($insert);
            $insert_student->execute([$codemeliValue, $fnameStudent, $lnameStudent, $genderStudent, $_SESSION['add-student2']['tel'], $_POST['paye-student'], $_POST['reshte-student'], $datenow, $timenow, $ip, $os, $_SESSION['admin']['user']]);

            $rand1 = rand(1, 10);
            switch ($rand1) {
                case 1:
                    $ch = 'ug';
                    break;
                case 2:
                    $ch = 'af';

                    break;
                case 3:
                    $ch = 'wv';
                    break;
                case 4:
                    $ch = 'qb';
                    break;
                case 5:
                    $ch = 'je';
                    break;
                case 6:
                    $ch = 'ad';
                    break;
                case 7:
                    $ch = 'df';
                    break;
                case 8:
                    $ch = 'yg';
                    break;
                case 9:
                    $ch = 'iu';
                    break;
                case 10:
                    $ch = 'nk';
                    break;
            }
            $rand2 = rand(1000, 9999);
            $pass = $ch . $rand2;
            $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
            $query_user = $conn->prepare($insert_user);
            $query_user->execute([$codemeliValue, $codemeliValue, 1]);
            $text = $codemeliValue . ';' . $codemeliValue;
            SendSms($_SESSION['add-student2']['tel'], $text, 171697, $codemeliValue);
            unset($_SESSION['add-student2']);
            $verify = 100;
        }
    }

}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> ثبت دانش آموز | <?php echo __TITLE ?></title>
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
                <?php
                if (Permision('permision_viewstudent')) {
                    if (Permision('permision_addstudent')) {
                        ?>
                        <!-- Row -->
                        <div class="row row-sm">
                            <div class="col-lg-12">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                                                <i class="fe fe-alert-triangle fs-4 me-2"></i>
                                                <div>
                                                    <strong>توجه:</strong>
                                                    مدیر محترم، برای ثبت‌نام موقت یا قطعی دانش آموز، لطفاً از شماره تماسی استفاده نمایید که به پیامک‌های ارسالی آن دسترسی دارید؛ زیرا کدهای تأیید و اطلاع‌رسانی به همین شماره ارسال خواهند شد.
                                                </div>
                                            </div>

                                            <div class="col-9">
                                                <div>
                                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                        <h6 class="main-content-label mb-0">
                                                            افزودن دانش آموز موقت
                                                        </h6>

                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش می‌توانید اطلاعات دانش آموز موقت را ثبت کرده و برای او حساب کاربری آزمایشی ایجاد کنید. این نوع ثبت‌نام برای دانش آموزانی است که هنوز ثبت‌نام قطعی نشده‌اند و به‌صورت موقت از خدمات یا دوره‌های فوق برنامه استفاده می‌کنند."
                                                              aria-label="راهنمای افزودن دانش آموز موقت">

                                                                <i class="fe fe-info fs-4"></i>
                                                            </span>

                                                    </div>

                                                    <p class="text-muted card-sub-title">
                                                        ثبت اطلاعات و ایجاد حساب کاربری موقت برای دانش آموز
                                                    </p>
                                                </div>
                                            </div>

                                        </div>
                                        <?php if (isset($verify) and $verify == 100) { ?>
                                            <div class="alert alert-success" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                اطلاعات دانش آموز با موفقیت ثبت و رمز عبور برایشان پیامک شد.
                                                <hr>
                                                <p class="mb-0">نام کاربری : شماره تماس ثبت شده
                                                </p>
                                                <p class="mb-0">رمز عبور : <?php echo 'شماره تماس ثبت شده' ?>
                                                </p>
                                            </div>
                                        <?php } ?>
                                        <?php if (isset($erormeli) and $erormeli == 1) { ?>
                                            <div class="alert alert-danger" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                شماره تماس معتبر نیست

                                            </div>
                                        <?php } ?>
                                        <?php if (isset($timeout) and $timeout == 1) { ?>
                                            <div class="alert alert-danger alert-dismissible fade show"
                                                 role="alert">
                                                مدت زمان انجام به پایان رسیده است
                                                <button type="button" class="close" data-bs-dismiss="alert"
                                                        aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        <?php } ?>
                                        <?php if (isset($havetel) and $havetel == 1) { ?>
                                            <div class="alert alert-warning" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                شماره تلفن متعلق به فرد دیگری است

                                            </div>
                                        <?php } ?>
                                        <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                            <div class="alert alert-warning" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                خطای غیر منتظره رخ داده است
                                            </div>
                                        <?php } ?>
                                        <?php if (isset($havemeli) and $havemeli == 1) { ?>
                                            <div class="alert alert-danger" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                این کد ملی در سیستم موجود می باشد
                                            </div>
                                        <?php } ?>
                                        <section class="section">
                                            <div class="row">
                                                <div class="card">
                                                    <?php if (!isset($_SESSION['add-student2']) and 1 == 0) { ?>
                                                        <form method="post" data-parsley-validate="">
                                                            <div class="row">
                                                                <div class="col-xl-6 mb-2">
                                                                    <label for="sens" class="form-label mg-t-15">کدملی
                                                                        دانش آموز: <span
                                                                                style="color: red">*</span></label>
                                                                    <input class="form-control" name="codemeli"
                                                                           type="text"
                                                                           placeholder="با اعداد انگلیسی وارد شود"
                                                                           required>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <label for="sens" class="form-label mg-t-15">ملیت
                                                                        دانش آموز: <span
                                                                                style="color: red">*</span></label>
                                                                    <select class="form-control select2 "
                                                                            name="melliat"
                                                                            aria-describedby="basic-addon2" required>
                                                                        <option label="ملیت ">
                                                                        </option>
                                                                        <option value="01">ایرانی</option>
                                                                        <option value="02">اتباع</option>
                                                                    </select>

                                                                </div>
                                                            </div>
                                                            <?php
                                                            $hash = bin2hex(random_bytes(32));
                                                            $_SESSION['token'] = $hash;
                                                            ?>
                                                            <input class="form-control" name="token" type="hidden"
                                                                   value="<?php echo $hash ?>">


                                                            <div class="text-center mg-10">
                                                                <button type="submit" class="btn btn-success "
                                                                        name="search-melli"><i
                                                                            class="fe fe-arrow-left"></i>
                                                                    ثبت کد ملی و ادامه
                                                                </button>
                                                            </div>
                                                        </form>
                                                        <!--                                        end step 1-->
                                                    <?php } ?>
                                                    <?php if (!isset($_SESSION['add-student2'])) { ?>
                                                        <form method="post" data-parsley-validate="">
                                                            <div class="col-xl-6">

                                                                <label for="tel-student" class="form-label">شماره دانش
                                                                    آموز:
                                                                    <span
                                                                            style="color: red">*</span></label>
                                                                <input type="number" name="tel" class="form-control"
                                                                       placeholder="11 رقم کامل-مثال :13***091244"
                                                                       pattern="^(?:9)?(0\d{10})$" required>
                                                            </div>
                                                            <div class="text-muted pd-t-10">

                                                                <input type="checkbox" name="tel-trust">
                                                                پذیرش شماره تکراری


                                                            </div>
                                                            <?php
                                                            $hash = bin2hex(random_bytes(32));
                                                            $_SESSION['token'] = $hash;
                                                            ?>
                                                            <input class="form-control" name="token" type="hidden"
                                                                   value="<?php echo $hash ?>">
                                                            <div class="text-center mg-t-10 mb-3">
                                                                <button type="submit" class="btn btn-success "
                                                                        name="search-tel">
                                                                    <i class="fe fe-arrow-left"></i> ثبت شماره تلفن و
                                                                    ادامه
                                                                </button>


                                                            </div>
                                                        </form>
                                                    <?php } ?>
                                                    <!--  End STEP 2-->
                                                    <!--  Start STEP 3-->
                                                    <?php if (isset($_SESSION['add-student2']) and isset($_SESSION['add-student2']['tel'])) { ?>
                                                        <div class="col-md-12" style="margin-top: 10px;">
                                                            <div class="card custom-card ">
                                                                <form action="" method="post" data-parsley-validate="">
                                                                    <div class="form-group">
                                                                        <div class="row row-sm">
                                                                            <div class="col-lg-4 mg-t-auto">
                                                                                <div class="input-group mb-3">
                                                                                    <div class="input-group-prepend">
                                                                                        <div class="input-group-text">
                                                                                            نام:<span
                                                                                                    class="tx-danger">*</span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <input class="form-control"
                                                                                           name="fname-student"
                                                                                           placeholder="نام کوچک را وارد کنید"
                                                                                           required=""
                                                                                           type="text"
                                                                                           data-placement="bottom"
                                                                                           data-bs-toggle="tooltip"
                                                                                           title="فارسی بنویسید">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-auto">
                                                                                <div class="input-group mb-3">
                                                                                    <div class="input-group-prepend">
                                                                                        <div class="input-group-text">
                                                                                            نام خانوادگی:<span
                                                                                                    class="tx-danger">*</span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <input class="form-control"
                                                                                           name="lname-student"
                                                                                           placeholder="نام خانوادگی را وارد کنید"
                                                                                           required="" type="text"
                                                                                           data-placement="bottom"
                                                                                           data-bs-toggle="tooltip"
                                                                                           title="فارسی بنویسید">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-auto">
                                                                                <label class="form-label text-center">شماره همراه دانش آموز (مقداردهی شده از بخش قبل) </label>
                                                                                <div class="input-group mb-3">

                                                                                    <input type="text"
                                                                                           style="text-align: center"
                                                                                           name="tel1"

                                                                                           aria-describedby="basic-addon2"
                                                                                           aria-label="شماره همراه دانش آموز"
                                                                                           class="form-control"
                                                                                           value="<?php echo $_SESSION['add-student2']['tel']; ?>"
                                                                                           disabled>

                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-20">

                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        پایه تحصیلی: <span
                                                                                                class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control select2"
                                                                                            required=""
                                                                                            name="paye-student"
                                                                                            aria-describedby="basic-addon2"
                                                                                            id="car"
                                                                                            onchange="ChangeCarList()">
                                                                                        <option label="پایه تحصیلی ">
                                                                                        </option>
                                                                                        <option value="5">پنجم</option>
                                                                                        <option value="6">ششم</option>
                                                                                        <option value="7">هفتم</option>
                                                                                        <option value="8">هشتم</option>
                                                                                        <option value="9">نهم</option>
                                                                                        <option value="10">دهم</option>
                                                                                        <option value="11">یازدهم
                                                                                        </option>
                                                                                        <option value="12">دوازدهم
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div><!-- col-4 -->
                                                                            <div class="col-lg-4 mg-t-20 ">

                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        رشته تحصیلی: <span
                                                                                                class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select id="carmodel"
                                                                                            class="form-control select2"
                                                                                            required=""
                                                                                            name="reshte-student"
                                                                                            aria-describedby="basic-addon2">
                                                                                    </select>
                                                                                </div>
                                                                            </div><!-- col-4 -->
                                                                            <script>
                                                                                var carsAndModels = {};
                                                                                carsAndModels['5'] = ['ندارد'];
                                                                                carsAndModels['6'] = ['ندارد'];
                                                                                carsAndModels['7'] = ['ندارد'];
                                                                                carsAndModels['8'] = ['ندارد'];
                                                                                carsAndModels['9'] = ['ندارد'];
                                                                                carsAndModels['10'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];
                                                                                carsAndModels['11'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];
                                                                                carsAndModels['12'] = ['ریاضی', 'تجربی', 'انسانی', 'فنی و حرفه ای'];


                                                                                function ChangeCarList() {
                                                                                    var carList = document.getElementById("car");
                                                                                    var modelList = document.getElementById("carmodel");
                                                                                    var selCar = carList.options[carList.selectedIndex].value;
                                                                                    while (modelList.options.length) {
                                                                                        modelList.remove(0);
                                                                                    }
                                                                                    var cars = carsAndModels[selCar];
                                                                                    if (cars) {
                                                                                        var i;
                                                                                        for (i = 0; i < cars.length; i++) {
                                                                                            var car = new Option(cars[i], i);
                                                                                            modelList.options.add(car);
                                                                                        }
                                                                                    }
                                                                                }
                                                                            </script>
                                                                            <div class="col-lg-4 mg-t-20 ">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        جنسیت:
                                                                                        <span class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control select2"
                                                                                            required=""
                                                                                            name="gender-student"
                                                                                            aria-describedby="basic-addon2">
                                                                                        <option label="جنسیت">
                                                                                        </option>
                                                                                        <option>خانم</option>
                                                                                        <option>آقا</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="text-center mg-t-20">
                                                                            <button class="btn ripple btn-main-primary pd-x-30 mg-l-5"
                                                                                    name="submit-student"
                                                                                    type="submit">تایید و ارسال
                                                                            </button>
                                                                            <button class="btn ripple btn-light pd-x-30"
                                                                                    type="reset">
                                                                                بازنشانی
                                                                            </button>

                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                            <div class="text-center mg-t-10">
                                                                <a href="?change">
                                                                    <button class="btn btn-warning " name="change">
                                                                        تغییر شماره تماس
                                                                    </button>
                                                                </a>
                                                            </div>
                                                        </div>


                                                    <?php } ?>
                                                    <!--  End STEP 3-->

                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Row -->
                    <?php } else { ?>
                        <div class="card-body">

                            <div class="alert alert-danger" role="alert">
                                <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                                شما مجوز لازم برای مشاهده این بخش را ندارید، با مدیر سامانه تماس بگیرید
                            </div>
                            <div style="text-align: center; padding-top: 20px">
                                <img src="../../assets/img/media/alert.png">

                            </div>
                        </div>
                    <?php }
                } ?>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        (function() {
            const area = document.querySelector('select[name="area_code"]');
            const local = document.querySelector('input[name="landline_local"]');
            const full = document.getElementById('landline_full');

            function digits(s) {
                return (s || '').replace(/\D/g, '');
            }

            function update() {
                full.value = (area?.value || '') + digits(local?.value || '');
            }

            area?.addEventListener('change', update);
            local?.addEventListener('input', update);
            update();
        })();
    </script>

</body>

</html>