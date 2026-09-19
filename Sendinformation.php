<?php
include_once 'dbconfig.php';
include_once 'inc/function_login.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if (!isset($_SESSION['personal'])){
    header('location:' . url("register.php"));
}
if (!isset($_SESSION['personal']['verify'])){
    header('location:' . url("register.php"));
}
if($_SESSION['personal']['verify'] != 100) {
    header('location:' . url("register.php"));
}
//Protecting against SESSION Hijaking
if($_SESSION['personal']['ip'] != GetIp() and $_SESSION['personal']['os'] != GetOs()) {
    unset($_SESSION['personal']);
    header('location:' . url("register.php"));
    die();
}
//--End of Protecting against SESSION Hijaking
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

//Insert Data to database For Student

if (isset($_POST['submit-student'])) {
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
        if (!checkCodemeli($_SESSION['personal']['codemeli'])) {
            $insert = 'INSERT INTO dbpersonal (codemeli,fname,lname,fathername,tel1,tel2,tel3,birthdate,`state`,city,nameschool,typeschool,paye,reshtestudent,area,gender,datecreated,timecreated,ipcreated,oscreated ) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $insert_student = $conn->prepare($insert);
            $insert_student->execute([$_SESSION['personal']['codemeli'], $fnameStudent, $lnameStudent, $parentStudent, $_SESSION['personal']['tel1'], $_POST['tel-mother'], $_POST['tel-father'], $birthdate, $state_user, $_POST['city-student'], $school, $_POST['typeschool'], $_POST['paye-student'], $_POST['reshte-student'], $_POST['mantaghe'], $_POST['gender-student'], $datenow, $timenow, $ip, $os]);

            $rand1 = rand(1, 10);
            switch ($rand1) {
                case 1:
                    $ch = 'as';
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
                    $ch = 'ur';
                    break;
            }
            $rand2 = rand(1000, 9999);
            $pass = $ch . $rand2;
            $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
            $query_user = $conn->prepare($insert_user);
            $query_user->execute([$_SESSION['personal']['codemeli'], $pass, 1]);
            $_SESSION['verify'] = array(
                "codemeli" => $_SESSION['personal']['codemeli'],
                "course" => $_SESSION['personal']['course'],
                "tel" => $_SESSION['personal']['tel1'],
                "ip" => $_SESSION['personal']['ip'],
                "os" => $_SESSION['personal']['os'],
                "name" => $fnameStudent,
                "famili" => $lnameStudent,
                "role" => 1,
                "pass" => $pass,
                "verify" => 200
            );
            unset($_SESSION['personal']);
            header('location: submit.php');
            die();
        }else{
           header('location: register.php?dublicator');
           exit;
        }
    }
}

//End - Data student

//Insert Data to Database For Collague
if (isset($_POST['submit-collague'])) {
    if (isset($_POST['fname-collague']) and $_POST['fname-collague'] != NULL and isset($_POST['lname-collague']) AND $_POST['lname-collague'] != NULL and $_POST['parent-collague'] != NULL and $_POST['tel2-collague'] != NULL
        and $_POST['roz-collague'] != NULL and $_POST['mah-collague'] != NULL and $_POST['sal-collague'] != NULL and $_POST['state-collague'] != NULL and $_POST['city-collague'] != NULL
        and $_POST['madrak'] != NULL and $_POST['reshte-collague'] != NULL and $_POST['gender-collague'] != NULL) {
        if (isset($_POST['personal-code']) and $_POST['personal-code'] != '') {
            $personalcode = $_POST['personal-code'];
        } else {
            $personalcode = NULL;
        }
        if (isset($_POST['location']) and $_POST['location'] != '') {
            $location = $_POST['location'];
        } else {
            $location = NULL;
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
        $birthdate = jalali_to_gregorian($_POST['sal-collague'], $_POST['mah-collague'], $_POST['roz-collague']);
        $birthdate=implode('-',$birthdate);
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $state_user=retstate($_POST['state-collague']);
        $fnameCollague=trim($_POST['fname-collague']);
        $lnameCollague=trim($_POST['lname-collague']);
        $parentCollague=trim($_POST['parent-collague']);
        $reshteCollague=trim($_POST['reshte-collague']);
        if (!checkCodemeli($_SESSION['personal']['codemeli'])) {
            $insertc = 'INSERT INTO dbpersonal (codemeli,fname,lname,fathername,tel1,tel2,personalcode,birthdate,`state`,city,gender,workname,semat,maghta,reshtehamkar,presented,datecreated,timecreated,ipcreated,oscreated) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $query_collague = $conn->prepare($insertc);
            $query_collague->execute([$_SESSION['personal']['codemeli'], $fnameCollague, $lnameCollague, $parentCollague, $_SESSION['personal']['tel1'], $_POST['tel2-collague'], $personalcode, $birthdate, $state_user, $_POST['city-collague'], $_POST['gender-collague'], $location, $semat, $_POST['madrak'], $reshteCollague, $_POST['ashnaee'], $datenow, $timenow, $ip, $os]);
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
            $insert_user = 'INSERT INTO dblogin (username,password,`role`) VALUES (?,?,?)';
            $query_user = $conn->prepare($insert_user);
            $query_user->execute([$_SESSION['personal']['codemeli'], $pass, 2]);
            $_SESSION['verify'] = array(
                "codemeli" => $_SESSION['personal']['codemeli'],
                "course" => $_SESSION['personal']['course'],
                "tel" => $_SESSION['personal']['tel1'],
                "ip" => $_SESSION['personal']['ip'],
                "os" => $_SESSION['personal']['os'],
                "role" => 2,
                "pass" => $pass,
                "name" => $fnameCollague,
                "famili" => $lnameCollague,
                "verify" => 200
            );
            unset($_SESSION['personal']);
            header('location: submit.php');
            die();
        }else{
            header('location: register.php?dublicator');
            exit;
        }
    }
}
//End Data Collague
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
    <title> ثبت اطلاعات |<?php echo __TITLE ?> </title>
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
<?php if ($_SESSION['personal']['role'] === 1) { ?>
    <div class="page">

        <div class="main-content  pt-0">
            <div class="container-fluid">
                <div class="inner-body">

                    <div class="row row-sm  mt-lg-4">
                        <div class="col-sm-12 col-lg-12 col-xl-12">
                            <div class="card bg-orange custom-card card-box">
                                <div class="card-body p-4">
                                    <div class="row align-items-center">
                                        <div class="col-xl-8 col-sm-6 col-12 img-bg ">
                                            <h4 class="d-flex  mb-3">
                                                <span class="font-weight-bold text-white "><?php echo m('label.dear_student') ?></span>
                                            </h4>
                                            <p class="tx-white mb-1"> اطلاعات خواسته شده را بصورت فارسی تکمیل نمایید
                                            </p></div>
                                        <img src="<?php echo url('assets/img/pngs/work9.png') ?>" alt="user-img">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row row-sm">
                        <div class="text text-center">

                        </div>

                        <div class="col-md-12" style="margin-top: 10px;">
                            <div class="card custom-card card-body">
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
                                                           placeholder="نام کوچک را وارد کنید" required=""
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
                                                           required="" type="text" data-placement="bottom"
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
                                                    <input class="form-control" name="parent-student"
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
                                                           value="<?php echo $_SESSION['personal']['codemeli']; ?>"
                                                           disabled>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 ">
                                                <label class="form-label"><?php echo m('form.student_mobile_label') ?> </label>
                                                <div class="input-group mb-3">

                                                    <input type="text" style="text-align: center" name="tel1"

                                                           aria-describedby="basic-addon2"
                                                           aria-label="<?php echo m('aria.student_mobile') ?>" class="form-control"
                                                           value="<?php echo $_SESSION['personal']['tel1']; ?>"
                                                           disabled>

                                                </div>
                                            </div>
                                            <div class="col-lg-4 ">
                                                <label class="form-label">شماره همراه مادر: <span
                                                            class="tx-danger">*</span></label>
                                                <div class="input-group mb-3">

                                                    <input style="text-align: center" name="tel-mother"
                                                           aria-describedby="basic-addon2"
                                                           aria-label="شماره همراه مادر" class="form-control"
                                                           placeholder="11 رقم کامل- با 0 شروع شود"
                                                           required="" type="number" pattern="^(?:9)?(0\d{10})$"
                                                           data-placement="bottom"
                                                           data-bs-toggle="tooltip"
                                                           title="کیبرد گوشی انگلیسی باشد ">

                                                </div>
                                            </div>
                                            <div class="col-lg-4 ">
                                                <label class="form-label">شماره همراه پدر: <span
                                                            class="tx-danger">*</span></label>
                                                <div class="input-group mb-3">

                                                    <input style="text-align: center" name="tel-father"
                                                           aria-describedby="basic-addon2"
                                                           aria-label="شماره همراه پدر" class="form-control"
                                                           placeholder="11 رقم کامل- با 0 شروع شود"
                                                           required="" type="number" pattern="^(?:9)?(0\d{10})$"
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


                                                    <select class="form-control select2 " name="roz-student"
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
                                                    <select class="form-control select2 " name="mah-student"
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
                                                    <select class="form-control select2 " name="sal-student"
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
                                                    <select class="form-control  select2" required=""
                                                            name="city-student"
                                                            id="city" aria-describedby="basic-addon2">
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
                                                    <select class="form-control select2" required="" name="paye-student"
                                                            aria-describedby="basic-addon2" id="car"
                                                            onchange="ChangeCarList()">
                                                        <option label="پایه تحصیلی ">
                                                        </option>
                                                        <option value="5">پنجم</option>
                                                        <option value="6">ششم</option>
                                                        <option value="7">هفتم</option>
                                                        <option value="8">هشتم</option>
                                                        <option value="9">نهم</option>
                                                        <option value="10">دهم</option>
                                                        <option value="11">یازدهم</option>
                                                        <option value="12">دوازدهم</option>
                                                    </select>
                                                </div>
                                            </div><!-- col-4 -->
                                            <div class="col-lg-4 mg-t-20 ">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        رشته تحصیلی: <span
                                                                class="tx-danger">*</span>
                                                    </div>
                                                    <select id="carmodel" class="form-control select2"
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
                                                        <option value="24">منطقه بیست و چهار</option>
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
                                                <button class="btn ripple btn-light pd-x-30" type="reset">
                                                    بازنشانی
                                                </button>

                                            </div>
                                        </div>
                                </form>

                            </div>
                        </div>
                        <div class="trademark footer text-center " style="padding: 10px; font-size: 14px;"> &trade; مرکز
                            یادگیری های الکترونیکی<a href="https://honaramoozesh.ir" target="_blank"> موسسه هنر
                                آموزش</a> &copy; <?php echo jdate('Y') ?>
                            <img src="assets/img/heart.gif" width="25px" height="25px">
                        </div>
                    </div>
                    <!-- End Row -->

                </div>
            </div>
        </div>


        <!-- End Row -->


    </div>
<?php } ?>
<?php if ($_SESSION['personal']['role'] === 2) { ?>
    <div class="page">

        <div class="main-content  pt-0">
            <div class="container-fluid">
                <div class="inner-body">

                    <div class="row row-sm  mt-lg-4">
                        <div class="col-sm-12 col-lg-12 col-xl-12">
                            <div class="card bg-orange custom-card card-box">
                                <div class="card-body p-4">
                                    <div class="row align-items-center">
                                        <div class="col-xl-8 col-sm-6 col-12 img-bg ">
                                            <h4 class="d-flex  mb-3">
                                                <span class="font-weight-bold text-white ">همکار گرامی!</span>
                                            </h4>
                                            <p class="tx-white mb-1"> اطلاعات خواسته شده خود را بصورت فارسی تکمیل نمایید
                                            </p></div>
                                        <img src="<?php echo url('assets/img/pngs/work9.png') ?>" alt="user-img">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row row-sm">
                        <div class="text text-center">

                        </div>

                        <div class="col-md-12" style="margin-top: 10px;">
                            <div class="card custom-card card-body">
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
                                                    <input class="form-control" name="fname-collague"
                                                           placeholder="نام کوچک را وارد کنید" required=""
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
                                                    <input class="form-control" name="lname-collague"
                                                           placeholder="نام خانوادگی را وارد کنید"
                                                           required="" type="text" data-placement="bottom"
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
                                                    <input class="form-control" name="parent-collague"
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
                                                    <input class="form-control" name="codemeli-collague"
                                                           type="text"
                                                           value="<?php echo $_SESSION['personal']['codemeli']; ?>"
                                                           disabled>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 ">
                                                <label class="form-label">شماره همراه همکار: </label>
                                                <div class="input-group mb-3">

                                                    <input type="text" style="text-align: center" name="tel1"

                                                           aria-describedby="basic-addon2"
                                                           aria-label="شماره همکار" class="form-control"
                                                           value="<?php echo $_SESSION['personal']['tel1']; ?>"
                                                           disabled>


                                                </div>
                                            </div>
                                            <div class="col-lg-4 ">
                                                <label class="form-label">شماره همراه رابط: <span
                                                            class="tx-danger">*</span></label>
                                                <div class="input-group mb-3">

                                                    <input style="text-align: center" name="tel2-collague"
                                                           aria-describedby="basic-addon2"
                                                           aria-label="شماره نماینده" class="form-control"
                                                           placeholder="  11رقم کامل- با 0 شروع شود"
                                                           required="" type="number" pattern="^(?:9)?(0\d{10})$"
                                                           data-placement="bottom"
                                                           data-bs-toggle="tooltip"
                                                           title="کیبرد گوشی انگلیسی باشد ">

                                                </div>
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            کد پرسنلی همکار:
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="personal-code"
                                                           aria-describedby="basic-addon2"
                                                           type="text" data-placement="bottom"
                                                           data-bs-toggle="tooltip"
                                                           title="درصورت نیاز ">
                                                </div>
                                            </div>
                                            <div class="col-lg-2 mg-t-auto">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        روز تولد:<span
                                                                class="tx-danger">*</span>
                                                    </div>


                                                    <select class="form-control select2 "
                                                            aria-describedby="basic-addon2"
                                                            required="" name="roz-collague">
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
                                                            aria-describedby="basic-addon2"
                                                            required="" name="mah-collague">
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
                                                            aria-describedby="basic-addon2"
                                                            required="" name="sal-collague">
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

                                            <div class="col-lg-3 mg-t-20 ">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        استان محل سکونت:<span
                                                                class="tx-danger">*</span>
                                                    </div>
                                                    <select class="form-control  " required=""
                                                            name="state-collague" id="state"
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
                                                    <select class="form-control  select2" required=""
                                                            name="city-collague"
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
                                                            نام محل خدمت:
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="location"
                                                           aria-describedby="basic-addon2"
                                                           type="text" placeholder="<?php echo m('placeholder.target_school') ?>">
                                                </div>
                                            </div>
                                            <div class="col-lg-4 mg-t-20  ">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        سمت در محل خدمت:
                                                    </div>
                                                    <input class="form-control" name="semat"
                                                           aria-describedby="basic-addon2"
                                                           type="text"
                                                           placeholder="<?php echo m('placeholder.job_title') ?>">


                                                </div>

                                            </div><!-- col-4 -->
                                            <div class="col-lg-4 mg-t-20">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        مقطع تحصیلات:<span
                                                                class="tx-danger">*</span>
                                                    </div>
                                                    <select class="form-control select2" required="" name="madrak"
                                                            aria-describedby="basic-addon2">
                                                        <option label="سطح تحصیلات">
                                                        </option>
                                                        <option>زیردیپلم</option>
                                                        <option>دیپلم</option>
                                                        <option>کاردانی</option>
                                                        <option>کارشناسی</option>
                                                        <option>کارشناسی ارشد</option>
                                                        <option>دکتری</option>

                                                    </select>
                                                </div>
                                            </div><!-- col-4 -->
                                            <div class="col-lg-4 mg-t-20 ">

                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        رشته تحصیلی: <span
                                                                class="tx-danger">*</span>
                                                    </div>
                                                    <input class="form-control" name="reshte-collague"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required>
                                                </div>
                                            </div><!-- col-4 -->


                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        جنسیت:
                                                        <span class="tx-danger">*</span>
                                                    </div>
                                                    <select class="form-control select2" required=""
                                                            name="gender-collague"
                                                            aria-describedby="basic-addon2">
                                                        <option label="جنسیت">
                                                        </option>
                                                        <option>خانم</option>
                                                        <option>آقا</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 mg-t-20 ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نحوه آشنایی با موسسه:
                                                    </div>
                                                    <select class="form-control select2" name="ashnaee"
                                                            aria-describedby="basic-addon2">
                                                        <option label="آشنایی با موسسه">
                                                        </option>
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
                                            </div><!-- col-4 -->

                                            <div class="text-center mg-t-20">
                                                <button class="btn ripple btn-main-primary pd-x-30 mg-l-5"
                                                        name="submit-collague"
                                                        type="submit">تایید و ارسال
                                                </button>
                                                <button class="btn ripple btn-light pd-x-30" type="reset">
                                                    بازنشانی
                                                </button>

                                            </div>
                                        </div>
                                </form>

                            </div>
                        </div>
                        <div class="trademark footer text-center " style="padding: 10px; font-size: 14px;"> &trade; مرکز
                            یادگیری های الکترونیکی<a href="https://honaramoozesh.ir" target="_blank"> موسسه هنر
                                آموزش</a>
                            &copy; <?php echo jdate('Y') ?> <img src="assets/img/heart.gif" width="25px" height="25px">
                        </div>
                    </div>
                    <!-- End Row -->

                </div>
            </div>
        </div>


        <!-- End Row -->


    </div>
<?php } ?>
<!-- Back-to-top -->
<a href="#top" id="back-to-top"><i class="fe fe-arrow-up"></i></a>

<!-- Internal Chart.Bundle js-->
<script src="assets/plugins/chart.js/Chart.bundle.min.js"></script>

<!-- Internal Clipboard js-->
<script src="assets/plugins/clipboard/clipboard.min.js"></script>
<script src="assets/plugins/clipboard/clipboard.js"></script>

<!-- Internal Prism js-->
<script src="assets/plugins/prism/prism.js"></script>
<!-- Internal Form-validation js-->
<script src="assets/js/form-validation.js"></script>

<!-- Internal Parsley js-->
<script src="assets/plugins/parsleyjs/parsley.min.js"></script>


<!-- Jquery js-->
<script src="<?php echo url('assets/plugins/jquery/jquery.min.js') ?>"></script>
<!-- Bootstrap js-->
<script src="<?php echo url('assets/plugins/bootstrap/js/popper.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/bootstrap/js/bootstrap-rtl.js') ?>"></script>
<!-- Perfect-scrollbar js -->
<script src="<?php echo url('assets/plugins/perfect-scrollbar/perfect-scrollbar.min-rtl.js') ?>"></script>
<!-- Internal Jquery-Ui js-->
<script src="<?php echo url('assets/plugins/jquery-ui/ui/widgets/datepicker.js') ?> "></script>
<!-- Sidebar js -->
<script src="<?php echo url('assets/plugins/sidebar/sidebar-rtl.js') ?>"></script>
<!-- Peity js-->
<script src="<?php echo url('assets/plugins/peity/jquery.peity.min.js') ?>"></script>
<!-- Internal Ion-rangeslider js-->
<script src="<?php echo url('assets/plugins/ion-rangeslider/js/ion.rangeSlider.min.js') ?>"></script>
<!-- Internal Dashboard js-->
<script src="<?php echo url('assets/js/index.js') ?>"></script>
<!-- Internal Specturm-colorpicker js-->
<script src="<?php echo url('assets/plugins/spectrum-colorpicker/spectrum.js') ?>"></script>
<!-- Sticky js -->
<script src="<?php echo url('assets/js/sticky.js') ?>"></script>
<!-- Custom js -->
<script src="<?php echo url('assets/js/custom.js') ?>"></script>
<script src="<?php echo url('assets/js/table-data.js') ?>"></script>
<!-- Select2 js-->
<script src="<?php echo url('assets/plugins/select2/js/select2.min.js') ?>"></script>
<!-- Internal Form-elements js-->
<script src="<?php echo url('assets/js/advanced-form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/select2.js') ?>"></script>
<!-- Internal Jquery.maskedinput js-->
<script src="<?php echo url('assets/plugins/jquery.maskedinput/jquery.maskedinput.js') ?>"></script>
<!-- InternalSumoselect js-->
<script src="<?php echo url('assets/plugins/sumoselect/jquery.sumoselect.js') ?>"></script>
</body>
</html>
