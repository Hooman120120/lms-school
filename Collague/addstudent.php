<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
//Protecting against SESSION HIJACKING
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
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
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            if (check_national_code($_POST['codemeli'])) {
                if (!checkCodemeli($_POST['codemeli'])) {
                    $_SESSION['add-student'] = array(
                        "codemeli" => $_POST['codemeli'],
                        "expire" => time() + 900
                    );
                } else {
                    $havemeli = 1;
                }
            } else {
                $erormeli = 1;
            }
        }
    } else {
        $erortoken = 1;
    }
}
if (isset($_POST['search-tel']) and isset($_SESSION['add-student'])) {
    if ($_SESSION['add-student']['expire'] > time()) {

        if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
            if ($_POST['tel'] != '') {
                if (!checkTell($_POST['tel'])) {        // Check Tell
                    if (strlen($_POST['tel']) == 11) {
                        $_SESSION['add-student'] =
                            array(
                                "codemeli" => $_SESSION['add-student']['codemeli'],
                                "expire" => $_SESSION['add-student']['expire'],
                                "tel" => $_POST['tel']

                            );


                    }
                } else {
                    $havetel = 1;
                }

            }
        }
    } else {
        unset($_SESSION['add-student']);
        header('location: ' . url('Collague/addstudent.php'));
        die();
    }
}
if (isset($_GET['change'])) {
    unset($_SESSION['add-student']);
    header('location: ' . url('Collague/addstudent.php'));
    die();
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
if (isset($_POST['submit-student']) AND isset($_SESSION['add-student']) AND $_SESSION['add-student']['expire'] > time() ) {
    if ($_POST['fname-student'] != NULL and $_POST['lname-student'] != NULL and $_POST['parent-student'] != NULL and $_POST['tel-mother'] != NULL and $_POST['tel-father'] != NULL
        and $_POST['roz-student'] != NULL and $_POST['mah-student'] != NULL and $_POST['sal-student'] != NULL and $_POST['state-student'] != NULL and $_POST['city-student'] != NULL
        and $_POST['school'] != NULL and $_POST['typeschool'] != NULL and $_POST['paye-student'] != NULL and $_POST['reshte-student'] != NULL and $_POST['mantaghe'] != NULL and
        $_POST['gender-student'] != NULL) {
        $birthdate = jalali_to_gregorian($_POST['sal-student'], $_POST['mah-student'], $_POST['roz-student']);
        $birthdate = implode('-', $birthdate);
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
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
            }
        }
        $fnameStudent = trim($_POST['fname-student']);
        $lnameStudent = trim($_POST['lname-student']);
        $parentStudent = trim($_POST['parent-student']);
        $school = trim($_POST['school']);
        $state_user = retstate($_POST['state-student']);
        if (!checkCodemeli($_SESSION['add-student']['codemeli'])) {
            hf_control_require_seat($conn, hf_control_login_id($_SESSION['add-student']['codemeli']), 'student');
            $insert = 'INSERT INTO dbpersonal (codemeli,fname,lname,fathername,tel1,tel2,tel3,birthdate,`state`,city,nameschool,typeschool,paye,reshtestudent,area,gender,datecreated,timecreated,ipcreated,oscreated,useradder ) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $insert_student = $conn->prepare($insert);
            $insert_student->execute([$_SESSION['add-student']['codemeli'], $fnameStudent, $lnameStudent, $parentStudent, $_SESSION['add-student']['tel'], $_POST['tel-mother'], $_POST['tel-father'], $birthdate, $state_user, $_POST['city-student'], $school, $_POST['typeschool'], $_POST['paye-student'], $_POST['reshte-student'], $_POST['mantaghe'], $_POST['gender-student'], $datenow, $timenow, $ip, $os,$_SESSION['auth']['user']]);

            $rand1 = rand(1, 10);
            switch ($rand1) {
                case 1:
                    $ch = 'sp';
                    break;
                case 2:
                    $ch = 'wn';

                    break;
                case 3:
                    $ch = 'pc';
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
                    $ch = 'dd';
                    break;
                case 8:
                    $ch = 'yg';
                    break;
                case 9:
                    $ch = 'mi';
                    break;
                case 10:
                    $ch = 'nk';
                    break;
            }
            $rand2 = rand(1000, 9999);
            $pass = $ch . $rand2;
            $key=rand(2000,9000);
            $insert_user = 'INSERT INTO dblogin (username,password,`role`,keyactive) VALUES (?,?,?,?)';
            $query_user = $conn->prepare($insert_user);
            $query_user->execute([$_SESSION['add-student']['codemeli'], $pass, 1,$key]);
            $text='کدملی '.';'. $pass;
            SendSms($_SESSION['add-student']['tel'],$text,171697,$_SESSION['add-student']['codemeli']);
            unset($_SESSION['add-student']);
            $verify=100;

        }
    }
}
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> <?php echo m('page.add_student') ?> | <?php echo __TITLE ?></title>
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
    include_once '../inc/menu-collague.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="col-lg-12 col-xl-12 col-md-12">

                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-3 font-weight-bold tx-14"><?php echo m('label.register_student_info') ?></h5>
                        </div>

                        <div class="card-body">
                            <?php if (isset($verify) and $verify == 100) { ?>
                            <div class="alert alert-success alert-dismissible fade show"
                                 role="alert">
                                <h5 class="alert-heading"><?php echo m('msg.student_saved_sms2') ?></h5>
                                <hr>
                                <p class="mb-0"><?php echo m('help.ask_student_activation_code') ?>
                                   </p>
                                <p class="mb-2"><?php echo m('help.print_guide_for_student') ?>
                                </p>
                                <p class="mb-0"><a href="../assets/doc/s1.pdf" target="_blank" class="btn btn-primary"><?php echo m('action.student_guide_file') ?></a>
                                </p>
                                <button type="button" class="close" data-bs-dismiss="alert"
                                        aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <?php } ?>
                            <?php if (isset($erormeli) and $erormeli == 1) { ?>
                                <div class="alert alert-danger alert-dismissible fade show"
                                     role="alert">
                                    کد ملی معتبر نیست
                                    <button type="button" class="close" data-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
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
                                <div class="alert alert-danger alert-dismissible fade show"
                                     role="alert">
                                    شماره تلفن متعلق به فرد دیگری است
                                    <button type="button" class="close" data-bs-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>
                            <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                <div class="alert alert-danger alert-dismissible fade show"
                                     role="alert">
                                    خطای غیرمنتظره رخ داده است
                                    <button type="button" class="close" data-bs-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>
                            <?php if (isset($havemeli) and $havemeli == 1) { ?>
                                <div class="alert alert-warning alert-dismissible fade show"
                                     role="alert">
                                    این کدملی در سیستم موجود می باشد
                                    <button type="button" class="close" data-bs-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>
                            <section class="section">
                                <div class="row">

                                    <div class="card">
                                        <!--step 1-->
                                        <?php if (!isset($_SESSION['add-student'])) { ?>
                                            <form method="post" data-parsley-validate="">
<!--                                                <div class="card-body">-->

                                                    <div class="col-xl-6">
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
                                                    <div class="text-muted pd-t-10">
                                                        <?php echo m('help.max_time_for_student') ?>
                                                    </div>
                                                    <div class="text-muted pd-t-10">
                                                        <?php echo m('help.prepare_info') ?>

                                                        <ol>
                                                            <li><?php echo m('li.student_nid_mobile') ?></li>
                                                            <li>نام پدر و شماره تماس پدر و مادر</li>
                                                            <li>پایه تحصیلی و رشته</li>
                                                            <li>استان و شهر محل سکونت</li>
                                                            <li>نام مدرسه و نوع مدرسه</li>
                                                        </ol>
                                                    </div>
                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success "
                                                                name="search-melli"><i class="fe fe-arrow-left"></i>
                                                            ثبت کد ملی و ادامه
                                                        </button>
                                                    </div>
<!--                                                </div>-->
                                            </form>
                                            <!--                                        end step 1-->
                                        <?php } ?>

                                        <!--step 2-->
                                        <?php if (isset($_SESSION['add-student']) and !isset($_SESSION['add-student']['tel'])) { ?>

<!--                                            <div class="card-body">-->
                                                <div class="pd-b-5">
                                                    اطلاعات برای کدملی : <span
                                                            class="text-danger"> <?php echo $_SESSION['add-student']['codemeli']; ?></span>
                                                </div>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="col-xl-6">

                                                        <label for="tel-student" class="form-label"><?php echo m('form.student_phone_short') ?>
                                                            <span
                                                                    style="color: red">*</span></label>
                                                        <input type="number" name="tel" class="form-control"
                                                               placeholder="11 رقم کامل-مثال :13***091244"
                                                               pattern="^(?:9)?(0\d{10})$" required>
                                                    </div>
                                                    <div class="text-muted pd-t-10">
                                                        <ol>
                                                            <li>در وارد کردن شماره موبایل دقت کنید - با حروف انگلیسی
                                                                وارد شود
                                                            </li>

                                                            <li> <?php echo m('help.duplicate_phone') ?></li>


                                                            <li> اطلاعات حساب کاربری ، رمزعبور و ... به این شماره ارسال
                                                                می شود
                                                            </li>

                                                        </ol>
                                                    </div>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                    <div class="text-center mg-t-10">
                                                        <button type="submit" class="btn btn-success "
                                                                name="search-tel">
                                                            <i class="fe fe-arrow-left"></i> ثبت شماره تلفن و ادامه
                                                        </button>


                                                    </div>
                                                </form>
<!--                                            </div>-->
                                            <div class="text-center mg-t-10">
                                                <a href="?change">
                                                    <button class="btn btn-warning " name="change">
                                                        تغییر کدملی
                                                    </button>
                                                </a>
                                            </div>

                                        <?php } ?>
                                        <!--  End STEP 2-->
                                        <!--  Start STEP 3-->
                                        <?php if (isset($_SESSION['add-student']) and isset($_SESSION['add-student']['tel'])) { ?>
                                            <div class="col-md-12" style="margin-top: 10px;">
                                                <div class="card custom-card ">
                                                    <form action="" method="post" data-parsley-validate="">
                                                        <div class="form-group">
                                                            <div class="row row-sm">
                                                                <div class="col-lg-3 ">
                                                                    <div class="input-group mb-3">
                                                                        <div class="input-group-prepend">
                                                                            <div class="input-group-text">
                                                                                نام:<span
                                                                                        class="tx-danger">*</span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="form-control" name="fname-student"
                                                                               placeholder="نام کوچک را وارد کنید"
                                                                               required=""
                                                                               type="text" data-placement="bottom"
                                                                               data-bs-toggle="tooltip"
                                                                               title="فارسی بنویسید">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-3 mg-t-auto">
                                                                    <div class="input-group mb-3">
                                                                        <div class="input-group-prepend">
                                                                            <div class="input-group-text">
                                                                                نام خانوادگی:<span
                                                                                        class="tx-danger">*</span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="form-control" name="lname-student"
                                                                               placeholder="نام خانوادگی را وارد کنید"
                                                                               required="" type="text"
                                                                               data-placement="bottom"
                                                                               data-bs-toggle="tooltip"
                                                                               title="فارسی بنویسید">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-3 mg-t-auto">
                                                                    <div class="input-group mb-3">
                                                                        <div class="input-group-prepend">
                                                                            <div class="input-group-text">
                                                                                نام پدر:<span
                                                                                        class="tx-danger">*</span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="form-control"
                                                                               name="parent-student"
                                                                               placeholder="نام پدر خود را وارد کنید"
                                                                               required="" type="text">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-3">
                                                                    <div class="input-group mb-3">

                                                                        <div class="input-group-prepend">
                                                                            <div class="input-group-text">
                                                                                کد ملی:
                                                                            </div>
                                                                        </div>
                                                                        <input class="form-control" name="codemeli"
                                                                               type="text"
                                                                               value="<?php echo $_SESSION['add-student']['codemeli']; ?>"
                                                                               disabled>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-4 ">
                                                                    <label class="form-label">شماره همراه دانش
                                                                        آموز: </label>
                                                                    <div class="input-group mb-3">

                                                                        <input type="text" style="text-align: center"
                                                                               name="tel1"

                                                                               aria-describedby="basic-addon2"
                                                                               aria-label="<?php echo m('aria.student_mobile') ?>"
                                                                               class="form-control"
                                                                               value="<?php echo $_SESSION['add-student']['tel']; ?>"
                                                                               disabled>

                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-4 ">
                                                                    <label class="form-label">شماره همراه مادر: <span
                                                                                class="tx-danger">*</span></label>
                                                                    <div class="input-group mb-3">

                                                                        <input style="text-align: center"
                                                                               name="tel-mother"
                                                                               aria-describedby="basic-addon2"
                                                                               aria-label="شماره همراه مادر"
                                                                               class="form-control"
                                                                               placeholder="11 رقم کامل- با 0 شروع شود"
                                                                               required="" type="number"
                                                                               pattern="^(?:9)?(0\d{10})$"
                                                                               data-placement="bottom"
                                                                               data-bs-toggle="tooltip"
                                                                               title="کیبرد گوشی انگلیسی باشد ">

                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-4 ">
                                                                    <label class="form-label">شماره همراه پدر: <span
                                                                                class="tx-danger">*</span></label>
                                                                    <div class="input-group mb-3">

                                                                        <input style="text-align: center"
                                                                               name="tel-father"
                                                                               aria-describedby="basic-addon2"
                                                                               aria-label="شماره همراه پدر"
                                                                               class="form-control"
                                                                               placeholder="11 رقم کامل- با 0 شروع شود"
                                                                               required="" type="number"
                                                                               pattern="^(?:9)?(0\d{10})$"
                                                                               data-placement="bottom"
                                                                               data-bs-toggle="tooltip"
                                                                               title="کیبرد گوشی انگلیسی باشد ">

                                                                    </div>
                                                                </div>

                                                                <div class="col-lg-2 mg-t-auto">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            روز تولد:<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>


                                                                        <select class="form-control select2 "
                                                                                name="roz-student"
                                                                                aria-describedby="basic-addon2">
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
                                                                <div class="col-lg-2 mg-t-auto">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            ماه تولد:<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2 "
                                                                                name="mah-student"
                                                                                aria-describedby="basic-addon2">
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
                                                                <div class="col-lg-2 mg-t-auto">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            سال تولد:<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2 "
                                                                                name="sal-student"
                                                                                aria-describedby="basic-addon2">
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

                                                                        </select>
                                                                    </div>


                                                                </div>

                                                                <div class="col-lg-3 mg-t-20 ">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            استان محل سکونت:<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control  " required=""
                                                                                name="state-student" id="state"
                                                                                aria-describedby="basic-addon2">
                                                                            <option selected></option>
                                                                            <?php foreach ($states as $state) { ?>
                                                                                <option value="<?php echo $state['id'] ?>"><?php echo $state['name'] ?></option>
                                                                            <?php } ?>
                                                                        </select>


                                                                    </div>

                                                                </div><!-- col-4 -->
                                                                <div class="col-lg-3 mg-t-20 ">
                                                                    <!--                                            <div class="input-group">-->
                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            شهر محل سکونت:<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control  select2"
                                                                                required=""
                                                                                name="city-student"
                                                                                id="city"
                                                                                aria-describedby="basic-addon2">
                                                                        </select>
                                                                    </div>


                                                                    <script src="<?php echo url('assets/plugins/jquery/jquery-3.2.1.min.js') ?>"></script>

                                                                    <script>
                                                                        $('#state').on('change', function (e) {
                                                                            // console.log('errors');
                                                                            e.preventDefault();
                                                                            var selectData = 'state=' + $(this).val();
//         console.log(selectData);

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
                                                                    <!--                                            </div>-->
                                                                </div>
                                                                <div class="col-lg-4 mg-t-20 ">
                                                                    <div class="input-group">
                                                                        <div class="input-group-prepend">
                                                                            <div class="input-group-text">
                                                                                نام مدرسه:<span
                                                                                        class="tx-danger">*</span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="form-control" name="school"
                                                                               aria-describedby="basic-addon2"
                                                                               type="text" required="">
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-4 mg-t-20  ">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            نوع مدرسه: <span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2" required=""
                                                                                name="typeschool"
                                                                                aria-describedby="basic-addon2">
                                                                            <option label="نوع مدرسه">
                                                                            </option>
                                                                            <option>دولتی</option>
                                                                            <option>هیئت امنایی</option>
                                                                            <option>غیرانتفاعی</option>
                                                                            <option>نمونه دولتی</option>
                                                                            <option>تیزهوشان</option>
                                                                            <option>شاهد</option>
                                                                            <option>مدارس اقلیت های دینی</option>
                                                                            <option>آزاد</option>


                                                                        </select>

                                                                    </div>

                                                                </div><!-- col-4 -->
                                                                <div class="col-lg-4 mg-t-20">

                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            پایه تحصیلی: <span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2" required=""
                                                                                name="paye-student"
                                                                                aria-describedby="basic-addon2" id="car"
                                                                                onchange="ChangeCarList()">
<option label="<?php echo htmlspecialchars(m('form.grade_placeholder'), ENT_QUOTES, "UTF-8") ?>"></option>
<?php echo inst_level_options() ?>
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
                                                                    carsAndModels['10'] = ['ریاضی', 'تجربی', 'انسانی'];
                                                                    carsAndModels['11'] = ['ریاضی', 'تجربی', 'انسانی'];
                                                                    carsAndModels['12'] = ['ریاضی', 'تجربی', 'انسانی'];


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
                                                                            منطقه آموزش و پرورش<span
                                                                                    class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2" required=""
                                                                                name="mantaghe"
                                                                                aria-describedby="basic-addon2">
                                                                            <option label="منطقه آموزش و پرورش ">
                                                                            </option>
                                                                            <option value="1">منطقه یک</option>
                                                                            <option value="2">منطقه دو</option>
                                                                            <option value="3">منطقه سه</option>
                                                                            <option value="4">منطقه چهار</option>
                                                                            <option value="5">منطقه پنج</option>
                                                                            <option value="6">منطقه شش</option>
                                                                            <option value="7">منطقه هفت</option>
                                                                            <option value="8">منطقه هشت</option>
                                                                            <option value="9">منطقه نه</option>
                                                                            <option value="10">منطقه ده</option>
                                                                            <option value="11">منطقه یازده</option>
                                                                            <option value="12">منطقه دوازده</option>
                                                                            <option value="13">منطقه سیزده</option>
                                                                            <option value="14">منطقه چهارده</option>
                                                                            <option value="15">منطقه پانزده</option>
                                                                            <option value="16">منطقه شانزده</option>
                                                                            <option value="17">منطقه هفده</option>
                                                                            <option value="18">منطقه هجده</option>
                                                                            <option value="19">منطقه نوزده</option>
                                                                            <option value="20">منطقه بیست</option>
                                                                            <option value="21">منطقه بیست و یک</option>
                                                                            <option value="22">منطقه بیست و دو</option>
                                                                            <option value="23">منطقه بیست و سه</option>
                                                                            <option value="24">منطقه بیست و چهار
                                                                            </option>
                                                                            <option value="25">منطقه بیست و پنج</option>
                                                                            <option value="26">منطقه بیست و شش</option>
                                                                            <option value="27">منطقه بیست و هفت</option>
                                                                            <option value="28">منطقه بیست و هشت</option>
                                                                            <option value="29">منطقه بیست و نه</option>
                                                                            <option value="30">منطقه سی</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-4 mg-t-20 ">
                                                                    <div class="input-group-prepend">
                                                                        <div class="input-group-text">
                                                                            جنسیت:
                                                                            <span class="tx-danger">*</span>
                                                                        </div>
                                                                        <select class="form-control select2" required=""
                                                                                name="gender-student"
                                                                                aria-describedby="basic-addon2">
                                                                            <option label="جنسیت">
                                                                            </option>
                                                                            <option>خانم</option>
                                                                            <option>آقا</option>
                                                                        </select>
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
                                                            تغییر کدملی
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


            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
