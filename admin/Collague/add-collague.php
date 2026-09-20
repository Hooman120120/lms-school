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
if (!Permision('permision_viewcollague')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_GET['change'])) {
    unset($_SESSION['add-collague']);
    header('location: ' . url('admin/Collague/add-collague.php'));
    die();
}

// فقط فیلتر شماره تماس
if (isset($_POST['search-tel'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['tel'] != '') {
            if (strlen($_POST['tel']) == 11) {
                // بررسی شماره تکراری
                if (!checkTell($_POST['tel'])) {
                    $_SESSION['add-collague'] = array(
                        "tel" => $_POST['tel']
                    );
                } else {
                    $havetel = 1;
                }
            }
        }
    } else {
        $erortoken = 1;
    }
}

$cities = null;
if (isset($_POST['state'])) {
    $state = $_POST['state'];
    $res = $conn->query("SELECT * FROM city WHERE state_id=$state");
    $cities = array();
    while ($row = $res->fetch()) {
        $cities [] = $row;
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
    $states [] = $row;
}

//Insert Data to Database For Collague
if (isset($_POST['submit-collague'])) {
    if (isset($_POST['fname-collague']) and $_POST['fname-collague'] != NULL and isset($_POST['lname-collague']) and $_POST['lname-collague'] != NULL
        and $_POST['state-collague'] != NULL and $_POST['city-collague'] != NULL and $_POST['gender-collague'] != NULL) {

        if (isset($_POST['personal-code']) and $_POST['personal-code'] != '') {
            $personalcode = $_POST['personal-code'];
        } else {
            $personalcode = NULL;
        }
        if (isset($_POST['semat']) and $_POST['semat'] != '') {
            $semat = $_POST['semat'];
        } else {
            $semat = NULL;
        }
        if (isset($_POST['ashnaee']) and $_POST['ashnaee'] != '') {
            $ashnaee = $_POST['ashnaee'];
        } else {
            $ashnaee = NULL;
        }

        // تاریخ تولد پیش فرض (برای رعایت NOT NULL در دیتابیس)
        $birthdate = '2000-01-01';

        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $state_user = retstate($_POST['state-collague']);
        $fnameCollague = trim($_POST['fname-collague']);
        $lnameCollague = trim($_POST['lname-collague']);

        // تولید کد ملی مصنوعی برای استفاده در دیتابیس (چون فیلد NOT NULL است)
        $codemeli = $_SESSION['add-collague']['tel'];

        // بررسی عدم تکراری بودن کد ملی مصنوعی
        while (checkCodemeli($codemeli)) {
            $codemeli = date('Ymd') . rand(100, 999);
        }

        hf_control_require_seat($conn, hf_control_login_id($_SESSION['add-collague']['tel']), 'colleague');
        $insertc = 'INSERT INTO dbpersonal (codemeli,fname,lname,fathername,tel1,tel2,personalcode,birthdate,`state`,city,gender,workname,semat,maghta,reshtehamkar,presented,datecreated,timecreated,ipcreated,oscreated,creator) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $query_collague = $conn->prepare($insertc);
        $query_collague->execute([$codemeli, $fnameCollague, $lnameCollague, NULL, $_SESSION['add-collague']['tel'], NULL, $personalcode, $birthdate, $state_user, $_POST['city-collague'], $_POST['gender-collague'], NULL, $semat, NULL, NULL, $_POST['ashnaee'], $datenow, $timenow, $ip, $os, $_SESSION['admin']['user']]);

        // تولید رمز عبور
        $rand1 = rand(1, 7);
        switch ($rand1) {
            case 1:
                $ch = 'F2w';
                break;
            case 2:
                $ch = 'R9c';
                break;
            case 3:
                $ch = 'Q8D';
                break;
            case 4:
                $ch = 'S6e';
                break;
            case 5:
                $ch = 'C2r';
                break;
            case 6:
                $ch = 'LRT';
                break;
            case 7:
                $ch = 'a9B';
                break;
        }
        $rand2 = rand(1000, 9999);
        $pass = $ch . $rand2;

        // نام کاربری با شماره تماس
        $username = $_SESSION['add-collague']['tel'];
        $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
        $query_user = $conn->prepare($insert_user);
        $query_user->execute([$username, $pass, 2]);

        // ارسال پیامک با رمز عبور
        $text=$username . ';' .  $pass;
        SendSms($_SESSION['add-collague']['tel'], $text, 171697, $username);


        $verify=100;
    }
}
//End Data Collague
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
    <title> ثبت همکارآموزشی | <?php echo __TITLE ?></title>
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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    افزودن معرف جدید
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش ابتدا شماره تماس معرف را وارد کرده و سپس اطلاعات کامل را ثبت نمایید"
                                                      aria-label="راهنمای افزودن معرف آموزشی جدید">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                            </div>

                                            <p class="text-muted card-sub-title">
                                                ثبت اطلاعات و ایجاد حساب کاربری برای معرفان برون سازمانی
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
                                        اطلاعات همکار آموزشی با موفقیت ثبت و رمز عبور برایشان پیامک شد.
                                        <hr>
                                        <p class="mb-0">نام کاربری : شماره تماس ثبت شده (<?php echo $_SESSION['add-collague']['tel']; unset($_SESSION['add-collague']); ?>)</p>
                                        <p class="mb-0">رمز عبور : <?php echo $pass ?></p>
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
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <!-- مرحله 1: فقط ورود شماره تماس -->
                                            <?php if (!isset($_SESSION['add-collague'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="tel" class="form-label mg-t-15">شماره تماس معرف برون سازمانی: <span style="color: red">*</span></label>
                                                            <input class="form-control" name="tel" type="text" placeholder="11 رقم کامل - مثال: 09123456789" required pattern="^0\d{10}$">
                                                            <small class="text-muted">شماره تماس را با صفر شروع کنید (مثال: 09123456789)</small>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">
                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success" name="search-tel">
                                                            <i class="fe fe-arrow-left"></i> ثبت شماره تماس و ادامه
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php } ?>

                                            <!-- مرحله 2: ثبت اطلاعات کامل (فیلدهای محدود) -->
                                            <?php if (isset($_SESSION['add-collague']) and isset($_SESSION['add-collague']['tel'])) { ?>
                                                <div class="col-md-12" style="margin-top: 10px;">
                                                    <div class="card custom-card ">
                                                        <div class="pd-b-5">
                                                            اطلاعات برای شماره تماس: <span class="text-danger"><?php echo $_SESSION['add-collague']['tel']; ?></span>
                                                        </div>
                                                        <form action="" method="post" data-parsley-validate="">
                                                            <div class="form-group">
                                                                <div class="row row-sm">
                                                                    <!-- ردیف 1: نام، نام خانوادگی و شماره تماس -->
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    نام:<span class="tx-danger">*</span>
                                                                                </div>
                                                                            </div>
                                                                            <input class="form-control"
                                                                                   name="fname-collague"
                                                                                   placeholder="نام کوچک را وارد کنید"
                                                                                   required=""
                                                                                   type="text" data-placement="bottom"
                                                                                   data-bs-toggle="tooltip"
                                                                                   title="فارسی بنویسید">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    نام خانوادگی:<span class="tx-danger">*</span>
                                                                                </div>
                                                                            </div>
                                                                            <input class="form-control"
                                                                                   name="lname-collague"
                                                                                   placeholder="نام خانوادگی را وارد کنید"
                                                                                   required="" type="text"
                                                                                   data-placement="bottom"
                                                                                   data-bs-toggle="tooltip"
                                                                                   title="فارسی بنویسید">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    شماره تماس:
                                                                                </div>
                                                                            </div>
                                                                            <input class="form-control"
                                                                                   name="tel1"
                                                                                   type="text"
                                                                                   value="<?php echo $_SESSION['add-collague']['tel']; ?>"
                                                                                   disabled>
                                                                        </div>
                                                                    </div>

                                                                    <!-- ردیف 2: کد پرسنلی و سمت -->
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    کد پرسنلی همکار:
                                                                                </div>
                                                                            </div>
                                                                            <input class="form-control"
                                                                                   name="personal-code"
                                                                                   aria-describedby="basic-addon2"
                                                                                   type="text" data-placement="bottom"
                                                                                   data-bs-toggle="tooltip"
                                                                                   title="درصورت نیاز ">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    سمت در محل خدمت:
                                                                                </div>
                                                                            </div>
                                                                            <input class="form-control" name="semat"
                                                                                   aria-describedby="basic-addon2"
                                                                                   type="text"
                                                                                   placeholder="<?php echo m('placeholder.job_title') ?>">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-4 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    جنسیت: <span class="tx-danger">*</span>
                                                                                </div>
                                                                            </div>
                                                                            <select class="form-control"
                                                                                    required=""
                                                                                    name="gender-collague"
                                                                                    aria-describedby="basic-addon2">
                                                                                <option></option>
                                                                                <option>خانم</option>
                                                                                <option>آقا</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>

                                                                    <!-- ردیف 3: استان و شهر -->
                                                                    <div class="col-lg-6 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    استان محل سکونت:<span class="tx-danger">*</span>
                                                                                </div>
                                                                            </div>
                                                                            <select class="form-control" required=""
                                                                                    name="state-collague" id="state"
                                                                                    aria-describedby="basic-addon2">
                                                                                <option selected></option>
                                                                                <?php foreach ($states as $state) { ?>
                                                                                    <option value="<?php echo $state['id'] ?>"><?php echo $state['name'] ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-lg-6 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    شهر محل سکونت:<span class="tx-danger">*</span>
                                                                                </div>
                                                                            </div>
                                                                            <select class="form-control"
                                                                                    required=""
                                                                                    name="city-collague"
                                                                                    id="city"
                                                                                    aria-describedby="basic-addon2">
                                                                            </select>
                                                                        </div>
                                                                        <script src="<?php echo url('assets/plugins/jquery/jquery-3.2.1.min.js') ?>"></script>
                                                                        <script>
                                                                            $('#state').on('change', function (e) {
                                                                                e.preventDefault();
                                                                                var selectData = 'state=' + $(this).val();
                                                                                $.ajax({
                                                                                    type: 'post',
                                                                                    url: $(location).attr('href'),
                                                                                    data: selectData,
                                                                                    success: function (data) {
                                                                                        $('#city').html(data);
                                                                                    },
                                                                                    error: function (data) {
                                                                                        console.log(data);
                                                                                    }
                                                                                });
                                                                            });
                                                                        </script>
                                                                    </div>

                                                                    <!-- ردیف 4: نحوه آشنایی -->
                                                                    <div class="col-lg-12 mg-t-20">
                                                                        <div class="input-group mb-3">
                                                                            <div class="input-group-prepend">
                                                                                <div class="input-group-text">
                                                                                    نحوه آشنایی با موسسه:
                                                                                </div>
                                                                            </div>
                                                                            <select class="form-control"
                                                                                    name="ashnaee"
                                                                                    aria-describedby="basic-addon2">
                                                                                <option></option>
                                                                                <option>طرح های وزارت آموزش و پرورش</option>
                                                                                <option>طرح های سازمان بسیج دانش آموزی</option>
                                                                                <option>طرح های وزارت علوم تحقیقات و فناوری</option>
                                                                                <option>تبلیغات پیامکی</option>
                                                                                <option>معرفی دوستان و آشنایان</option>
                                                                                <option>یافتن از موتورهای جستجو</option>
                                                                                <option>بیلبوردهای سطح شهر</option>
                                                                                <option>معرفی در همایش ها</option>
                                                                                <option>تبلیغات های تلویزیونی</option>
                                                                                <option>معرفی از طریق تیم تماس تلفنی</option>
                                                                                <option>سایر</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>

                                                                    <!-- دکمه‌ها -->
                                                                    <div class="text-center mg-t-30 mg-b-20">
                                                                        <button class="btn ripple btn-main-primary pd-x-30 mg-l-5"
                                                                                name="submit-collague"
                                                                                type="submit">تایید و ارسال
                                                                        </button>
                                                                        <a href="?change">
                                                                            <button class="btn ripple btn-warning pd-x-30" type="button">
                                                                                شروع مجدد
                                                                            </button>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Row -->
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
