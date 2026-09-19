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
if (isset($_GET['newcode'])) {
    unset($_SESSION['edit-collague']);
    header('location: ' . url('admin/Collague/edit-collague.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            if (checkCodemeli($codemeli)) {
                if (typeUser($_POST['codemeli']) == 2) {
                    $_SESSION['edit-collague'] = array(
                        "codemeli" => $_POST['codemeli']
                    );
                } else {
                    $collague = 1;
                }
            } else {
                $havemeli = 1;
            }
        } else {
            $erortoken = 1;
        }
    } else {
        $erortoken = 1;
    }
}
if (isset($_POST['account-submit'])) { // UPDATE Account User
    if ($_SESSION['token'] == $_POST['token']) {
        if (isset($_POST['activity-user'])) {
            $upa = 'UPDATE dblogin SET activity=? WHERE username=?';
            $qur = $conn->prepare($upa);
            $qur->execute([1, $_SESSION['edit-collague']['codemeli']]);
        } else {
            $upa = 'UPDATE dblogin SET activity=? WHERE username=?';
            $qur = $conn->prepare($upa);
            $qur->execute([0, $_SESSION['edit-collague']['codemeli']]);
        }
        if (isset($_POST['fname']) and $_POST['fname'] != '' and isset($_POST['lname']) and $_POST['lname'] != '' and isset($_POST['gender']) and $_POST['gender'] != ''  and isset($_POST['tel1']) and $_POST['tel1'] != '' and isset($_POST['dtts']) and $_POST['dtts'] != '' and isset($_POST['mtts']) and $_POST['mtts'] != '' and isset($_POST['ytts']) and $_POST['ytts'] != '') {
            $fname = $_POST['fname'];
            $lname = $_POST['lname'];
            $gender = $_POST['gender'];
            $pedar = $_POST['fathername'];
            $dtts = $_POST['dtts'];
            $mtts = $_POST['mtts'];
            $ytts = $_POST['ytts'];
            $tel1 = $_POST['tel1'];
            if ($_POST['tel-house'] == '' and $_POST['email'] == '' and $_POST['bio'] == '' and $_POST['tel2'] == '') {
                $erorempty = 1;
            } else {
                if ($_POST['tel-house'] != '') {
                    $telHouse = $_POST['tel-house'];
                } else {
                    $telHouse = NULL;
                }
                if ($_POST['email'] != '') {
                    $email = $_POST['email'];
                } else {
                    $email = NULL;
                }
                if ($_POST['bio'] != '') {
                    $bio = $_POST['bio'];
                } else {
                    $bio = NULL;
                }
                if ($_POST['tel2'] != '') {
                    $tel2 = $_POST['tel2'];
                } else {
                    $tel2 = NULL;
                }
                date_default_timezone_set("Iran");
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $birthdate = jalali_to_gregorian($_POST['ytts'], $_POST['mtts'], $_POST['dtts']);
                $birthdate = implode('-', $birthdate);
                if (!isset($_POST['tel-trust'])) {
                    if (!checkTellWithoume($tel1, $_SESSION['edit-collague']['codemeli'])) {
                        $upd = 'UPDATE dbpersonal SET fname=?,lname=?,fathername=?,tel1=?,tel2=?,birthdate=?,gender=?,tel_home=?,email=?,bio=?,dateupdate=?,timeupdate=?,updator =? WHERE codemeli=? ';
                        $qup = $conn->prepare($upd);
                        $qup->execute([$fname, $lname,$pedar, $tel1, $tel2, $birthdate, $gender, $telHouse, $email, $bio, $datenow, $timenow, $_SESSION['admin']['user'], $_SESSION['edit-collague']['codemeli']]);
                    } else {
                        $havetel = 1;
                    }
                } else {
                    $upd = 'UPDATE dbpersonal SET fname=?,lname=?,fathername=?,tel1=?,tel2=?,birthdate=?,gender=?,tel_home=?,email=?,bio=?,dateupdate=?,timeupdate=?,updator =? WHERE codemeli=? ';
                    $qup = $conn->prepare($upd);
                    $qup->execute([$fname, $lname,$pedar, $tel1, $tel2, $birthdate, $gender, $telHouse, $email, $bio, $datenow, $timenow, $_SESSION['admin']['user'], $_SESSION['edit-collague']['codemeli']]);
                }

            }
        }
    }
}
if (isset($_POST['house-submit'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        if (  $_POST['reshtehamkar'] != '' and $_POST['maghta'] != '') {
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $maghta = $_POST['maghta'];
            $reshtehamkar = trim($_POST['reshtehamkar']);

            if ($_POST['rosta'] != '') {
                $rosta = trim($_POST['rosta']);
            } else {
                $rosta = NULL;
            }
            if ($_POST['address'] != '') {
                $address = trim($_POST['address']);
            } else {
                $address = NULL;
            }
            if ($_POST['zipcode'] != '') {
                $zipcode = intval($_POST['zipcode']);
            } else {
                $zipcode = NULL;
            }
            if ($_POST['malek-house'] != '') {
                $type_life = $_POST['malek-house'];
            } else {
                $type_life = NULL;
            }
            if ($_POST['status-house'] != '') {
                $status_life = $_POST['status-house'];
            } else {
                $status_life = NULL;
            }
            if ($_POST['baft-house'] != '') {
                $life_baft = $_POST['baft-house'];
            } else {
                $life_baft = NULL;
            }
            if ($_POST['metr'] != '') {
                $metr = $_POST['metr'];
            } else {
                $metr = NULL;
            }
            if ($_POST['workname'] != '') {
                $workname = trim($_POST['workname']);
            } else {
                $workname = NULL;
            }
            if ($_POST['semat'] != '') {
                $semat = trim($_POST['semat']);
            } else {
                $semat = NULL;
            }
            $update = 'UPDATE dbpersonal SET life_rosta=?,address=?,zipcode=?,type_life=?,life_status=?,life_baft=?,life_metr=?,reshtehamkar=?,maghta=?,semat=?,workname=?,dateupdate=?,timeupdate=?,updator=? WHERE codemeli=?';
            $query = $conn->prepare($update);
            $query->execute([$rosta, $address, $zipcode, $type_life, $status_life, $life_baft, $metr,$reshtehamkar,$maghta,$semat,$workname, $datenow, $timenow,$_SESSION['admin']['user'], $_SESSION['edit-collague']['codemeli']]);

        }
    }
}
if (isset($_POST['acounting-info'])) {
    if (strlen($_POST['sheba']) != 24) {
        $erorstrlensheba = 1;
    } else {
        if (bankCardCheck($_POST['num-card'])) {
            $sheba = $_POST['sheba'];
            $name_sheba = $_POST['name-sheba'];
            $bank_sheba = $_POST['bank-sheba'];
            $numcard = $_POST['num-card'];
            $namecard = $_POST['name-card'];
            $upcard = 'UPDATE dbpersonal SET sheba=?,name_sheba=?,bank_sheba=?,card_bank=?,name_card=? WHERE  codemeli=?';
            $qupcard = $conn->prepare($upcard);
            $qupcard->execute([$sheba, $name_sheba, $bank_sheba, $numcard, $namecard, $_SESSION['edit-collague']['codemeli']]);

        } else {
            $erorvalidcard = 1;
        }

    }
}
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
    <title> ویرایش اطلاعات همکار |<?php echo __TITLE ?></title>
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
                if (Permision('permision_viewcollague')) {

                    ?>
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                    <h6 class="main-content-label mb-0">
                                                        ویرایش اطلاعات معرف برون سازمانی
                                                    </h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش می‌توانید اطلاعات حساب کاربری معرف برون سازمانی، مشخصات فردی، اطلاعات تماس و سایر اطلاعات ثبت‌شده او را مشاهده و ویرایش کنید."
                                                          aria-label="راهنمای ویرایش اطلاعات معرف برون سازمانی">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>

                                                </div>

                                                <p class="text-muted card-sub-title">
                                                    ویرایش اطلاعات حساب کاربری معرف برون سازمانی
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-3 text-left">
                                            <a href="?newcode" class="btn btn-sm btn-primary"><i
                                                        class="fe fe-refresh-cw"></i> کد ملی جدید</a>
                                        </div>

                                    </div>
                                    <?php if (isset($collague) and $collague == 1) { ?>
                                        <div class="alert alert-warning" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            این کد ملی متعلق به دانش آموز می باشد
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
                                            این کد ملی در سیستم موجود نیست
                                        </div>
                                    <?php } ?>
                                    <section class="section">
                                        <div class="row">
                                            <div class="card">
                                                <?php if (!isset($_SESSION['edit-collague'])) { ?>
                                                    <form method="post" data-parsley-validate="">
                                                        <div class="row">
                                                            <div class="col-xl-6 mb-2">
                                                                <label for="sens" class="form-label mg-t-15"> کدملی معرف برون سازمانی: <span
                                                                            style="color: red">*</span></label>
                                                                <input class="form-control" name="codemeli"
                                                                       type="text"
                                                                       placeholder="با اعداد انگلیسی وارد شود"
                                                                       required>
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
                                                                        class="fe fe-search"></i>
                                                                جستجو همکار
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <!--                                        end step 1-->
                                                <?php } ?>
                                                <?php if (isset($_SESSION['edit-collague'])) { ?>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <?php if (isset($erorstrlensheba) and $erorstrlensheba == 1) { ?>
                                                        <div class="alert alert-danger alert-dismissible fade show"
                                                             role="alert">
                                                            شماره شبا می بایست 24 رقم باشد
                                                            <button type="button" class="close" data-dismiss="alert"
                                                                    aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                    <?php } ?>
                                                    <?php if (isset($erorvalidcard) and $erorvalidcard == 1) { ?>
                                                        <div class="alert alert-danger alert-dismissible fade show"
                                                             role="alert">
                                                            لطفا یک شماره کارت معتبر وارد کنید
                                                            <button type="button" class="close" data-dismiss="alert"
                                                                    aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                    <?php } ?>
                                                    <div class="profile-tab tab-menu-heading">
                                                        <nav class="nav main-nav-line p-3 tabs-menu profile-nav-line bg-gray-100">
                                                            <a class="nav-link active" data-bs-toggle="tab"
                                                               href="#edit">اطلاعات حساب
                                                                کاربری </a>

                                                            <a class="nav-link" data-bs-toggle="tab" href="#house">اطلاعات
                                                                سکونت و
                                                                تحصیلی </a>
                                                            <a class="nav-link" data-bs-toggle="tab"
                                                               href="#picture">تصویر پروفایل </a>

                                                            <a class="nav-link" data-bs-toggle="tab" href="#wallet">اطلاعات
                                                                حساب بانکی</a>

                                                        </nav>
                                                    </div>
                                                    <?php if (isset($havetel) and $havetel == 1) { ?>
                                                        <div class="alert alert-danger" role="alert">
                                                                <span class="alert-inner--icon"><i
                                                                            class=" mdi mdi-asterisk"></i></span>
                                                            <button aria-label="بستن" class="close"
                                                                    data-bs-dismiss="alert"
                                                                    type="button">
                                                                <span aria-hidden="true">×</span>
                                                            </button>
                                                            این شماره تلفن تکراری است
                                                        </div>
                                                    <?php } ?>
                                                    <div class="row row-sm">
                                                        <div class="col-lg-12 col-md-12">
                                                            <div class="card custom-card main-content-body-profile">
                                                                <div class="tab-content">
                                                                    <?php $data = retPerson($_SESSION['edit-collague']['codemeli']); ?>
                                                                    <div class="main-content-body tab-pane p-4 border-top-0 active"
                                                                         id="edit"> <!-- اطلاعات حساب کاربری -->

                                                                        <div class="card-body border">
                                                                            <div class="mb-4 main-content-label">
                                                                                اطلاعات شخصی
                                                                            </div>
                                                                            <form class="form-horizontal"
                                                                                  method="post"
                                                                                  data-parsley-validate="">

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                <span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-6">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام "
                                                                                                   value="<?php echo htmlentities($data->fname) ?>"
                                                                                                   name="fname"
                                                                                                   required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                خانوادگی<span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-6">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام خانوادگی"
                                                                                                   value="<?php echo htmlentities($data->lname) ?>"
                                                                                                   name="lname"
                                                                                                   required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                پدر</label>
                                                                                        </div>
                                                                                        <div class="col-xl-6">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام خانوادگی"
                                                                                                   value="<?php echo htmlentities($data->fathername) ?>"
                                                                                                   name="fathername">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">کد
                                                                                                ملی<span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="کد ملی"
                                                                                                   value="<?php echo htmlentities($data->codemeli) ?>"
                                                                                                   disabled>
                                                                                        </div>
                                                                                        <div class="col-xl-4 ">
                                                                                            <label class="custom-switch">
                                                                                                <input type="checkbox"
                                                                                                       name="activity-user"
                                                                                                       class="custom-switch-input" <?php if (actUser($data->codemeli) == 1) {
                                                                                                    echo 'checked';
                                                                                                } ?>>
                                                                                                <span class="custom-switch-indicator"></span>
                                                                                                <span class="">فعال سازی کاربر</span>
                                                                                            </label>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">رمز
                                                                                                عبور<span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   value="<?php echo passUser($data->codemeli); ?>"
                                                                                                   disabled>
                                                                                        </div>

                                                                                    </div>

                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3 ">
                                                                                            <label class="form-label">تاریخ
                                                                                                تولد <span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <div class="input-group-prepend">
                                                                                                <div class="input-group-text">
                                                                                                    روز:
                                                                                                </div>
                                                                                                <?php
                                                                                                if ($data->birthdate != NULL) {
                                                                                                    $date = $data->birthdate;
                                                                                                    $date = explode('-', $date);
                                                                                                    $date = gregorian_to_jalali($date[0], $date[1], $date[2]);
                                                                                                }
                                                                                                ?>
                                                                                                <select class="form-control select2"
                                                                                                        name="dtts"
                                                                                                        required>
                                                                                                    <option label="روز تولد "></option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 1) {
                                                                                                        echo 'selected';
                                                                                                    } ?>>01
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 2) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >02
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 3) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >03
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 4) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >04
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 5) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >05
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 6) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >06
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 7) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >07
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 8) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >08
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 9) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >09
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 10) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >10
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 11) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >11
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 12) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >12
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 13) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >13
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 14) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >14
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 15) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >15
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 16) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >16
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 17) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >17
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 18) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >18
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 19) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >19
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 20) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >20
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 21) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >21
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 22) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >22
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 23) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >23
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 24) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >24
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 25) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >25
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 26) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >26
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 27) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >27
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 28) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >28
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 29) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >29
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 30) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >30
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[2] == 31) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >31
                                                                                                    </option>
                                                                                                </select>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <div class="input-group-prepend">
                                                                                                <div class="input-group-text">
                                                                                                    ماه:
                                                                                                </div>
                                                                                                <select class="form-control select2"
                                                                                                        name="mtts"
                                                                                                        required>
                                                                                                    <option label="ماه تولد ">
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 1) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="1">
                                                                                                        فروردین
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 2) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="2">
                                                                                                        اردیبهشت
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 3) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="3">
                                                                                                        خرداد
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 4) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="4">
                                                                                                        تیر
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 5) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="5">
                                                                                                        مرداد
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 6) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="6">
                                                                                                        شهریور
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 7) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="7">
                                                                                                        مهر
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 8) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="8">
                                                                                                        آبان
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 9) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="9">
                                                                                                        آذر
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 10) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="10">
                                                                                                        دی
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 11) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="11">
                                                                                                        بهمن
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[1] == 12) {
                                                                                                        echo 'selected';
                                                                                                    } ?> value="12">
                                                                                                        اسفند
                                                                                                    </option>
                                                                                                </select>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class=" col-xl-3 col-lg-3">
                                                                                            <div class="input-group-prepend">
                                                                                                <div class="input-group-text">
                                                                                                    سال:
                                                                                                </div>
                                                                                                <select class="form-control select2"
                                                                                                        name="ytts"
                                                                                                        required>
                                                                                                    <option label="سال تولد ">
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1393) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1393
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1392) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1392
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1391) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1391
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1390) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1390
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1389) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1389
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1388) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1388
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1387) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1387
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1386) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1386
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1385) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1385
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1354) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1384
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1383) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1383
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1382) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1382
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1381) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1381
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1380) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1380
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1379) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1379
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1378) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1378
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1377) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1377
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1376) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1376
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1375) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1375
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1374) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1374
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1373) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1373
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1372) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1372
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1371) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1371
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1370) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1370
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1369) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1369
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1368) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1368
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1367) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1367
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1366) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1366
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1365) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1365
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1364) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1364
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1363) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1363
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1362) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1362
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1361) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1361
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1360) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1360
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1359) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1359
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1358) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1358
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1357) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1357
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1356) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1356
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1355) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1355
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1354) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1354
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1353) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1353
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1352) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1352
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1351) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1351
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1350) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1350
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1349) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1349
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1348) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1348
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1347) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1347
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1346) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1346
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1345) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1345
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1344) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1344
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1343) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1343
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1342) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1342
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1341) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1341
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1340) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1340
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1339) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1339
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1338) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1338
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1337) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1337
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1336) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1336
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1335) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1335
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1334) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1334
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1333) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1333
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1332) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1332
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1331) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1331
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1330) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1330
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1329) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1329
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1328) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1328
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1327) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1327
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1326) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1326
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1325) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1325
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1324) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1324
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1323) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1323
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1322) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1322
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1321) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1321
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1320) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1320
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1319) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1319
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1318) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1318
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1317) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1317
                                                                                                    </option>
                                                                                                    <option <?php if (isset($date) and $date[0] == 1316) {
                                                                                                        echo 'selected';
                                                                                                    } ?> >1316
                                                                                                    </option>

                                                                                                </select>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">جنسیت<span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <select class="form-control select2"
                                                                                                    name="gender"
                                                                                                    required>
                                                                                                <option label="جنسیت">
                                                                                                </option>
                                                                                                <option <?php if ($data->gender != NULL and $data->gender == 'خانم') {
                                                                                                    echo 'selected';
                                                                                                } ?> >خانم
                                                                                                </option>
                                                                                                <option <?php if ($data->gender != NULL and $data->gender == 'آقا') {
                                                                                                    echo 'selected';
                                                                                                } ?>>آقا
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="mb-4 main-content-label">
                                                                                    اطلاعات تماس
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">شماره
                                                                                                همراه
                                                                                                <i>(اصلی)</i><span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=" "
                                                                                                   name="tel1"
                                                                                                   value="<?php echo htmlentities($data->tel1) ?>"
                                                                                                   required>
                                                                                        </div>
                                                                                        <div class="col-xl-4 ">
                                                                                            <label class="custom-switch">
                                                                                                <input type="checkbox"
                                                                                                       name="tel-trust"
                                                                                                       class="custom-switch-input">
                                                                                                <span class="custom-switch-indicator"></span>
                                                                                                <span class="">پذیرش شماره تکراری</span>
                                                                                            </label>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">شماره
                                                                                                همراه
                                                                                                <i>(رابط)</i><span
                                                                                                        style="color: red">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=" "
                                                                                                   name="tel2"
                                                                                                   value="<?php echo htmlentities($data->tel2) ?>"
                                                                                                   required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">تلفن
                                                                                                منزل</label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=" شماره تلفن منزل"
                                                                                                   name="tel-house"
                                                                                                   value="<?php if ($data->tel_home != NULL) {
                                                                                                       echo htmlentities($data->tel_home);
                                                                                                   } ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">
                                                                                                پست
                                                                                                الکترونیکی</label>
                                                                                        </div>
                                                                                        <div class="col-xl-3">
                                                                                            <input type="email"
                                                                                                   class="form-control"
                                                                                                   name="email"
                                                                                                   placeholder="پست الکترونیکی "
                                                                                                   value="<?php if ($data->email != NULL) {
                                                                                                       echo htmlentities($data->email);
                                                                                                   } ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>


                                                                                <div class="mb-4 main-content-label">
                                                                                    درباره همکار
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">اطلاعاتی
                                                                                                در خصوص وضعیت جسمانی
                                                                                                ،
                                                                                                علایق
                                                                                                و ...</label>
                                                                                        </div>
                                                                                        <div class="col-xl-9">
                                                        <textarea class="form-control" name="bio"
                                                                  rows="4"
                                                                  placeholder=""><?php if ($data->bio != NULL) {
                                                                echo htmlentities($data->bio);
                                                            } ?></textarea>
                                                                                        </div>

                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group mb-0">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-4">
                                                                                            <input type="hidden"
                                                                                                   name="token"
                                                                                                   value="<?php echo $hash ?>">
                                                                                        </div>
                                                                                        <div class="row">
                                                                                            <div class="col-xl-4 ">

                                                                                            </div>
                                                                                            <div class="mt-3">
                                                                                                <button type="submit"
                                                                                                        name="account-submit"
                                                                                                        class="btn btn-primary  mr-1">
                                                                                                    ذخیره
                                                                                                    اطلاعات
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>

                                                                                    </div>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                    </div>

                                                                    <div class="main-content-body p-4 border tab-pane border-top-0"
                                                                         id="house"> <!--  اطلاعات سکونت-->
                                                                        <div class="card-body border">
                                                                            <div class="mb-4 main-content-label">
                                                                                اطلاعات سکونت
                                                                            </div>
                                                                            <form class="form-horizontal "
                                                                                  method="post"
                                                                                  data-parsley-validate="">
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">استان
                                                                                                محل سکونت <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=""
                                                                                                   name="state"
                                                                                                   value="<?php echo htmlentities($data->state) ?>"
                                                                                                   disabled>
                                                                                        </div>
                                                                                        <input type="hidden"
                                                                                               name="token"
                                                                                               value="<?php echo $hash ?>">
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">شهر
                                                                                                محل سکونت <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=""
                                                                                                   name="city"
                                                                                                   value="<?php echo htmlentities($data->city) ?>"
                                                                                                   disabled>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">روستا
                                                                                                محل سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   name="rosta"
                                                                                                   placeholder="در صورت زندگی در روستا"
                                                                                                   value="<?php if ($data->life_rosta != NULL) {
                                                                                                       echo htmlentities($data->life_rosta);
                                                                                                   } ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">آدرس
                                                                                                محل سکونت</label>
                                                                                        </div>
                                                                                        <div class="col-xl-9">
                                                        <textarea class="form-control" name="address"
                                                                  rows="4" placeholder=""
                                                        ><?php if ($data->address != NULL) {
                                                                echo htmlentities($data->address);
                                                            } ?></textarea>
                                                                                            <span class="text-muted">  ممکن است حسب ضرورت ، برای همکار ، بسته پستی ارسال شود</span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">کد
                                                                                                پستی محل
                                                                                                سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="کدپستی"
                                                                                                   value="<?php if ($data->zipcode != NULL) {
                                                                                                       echo htmlentities($data->zipcode);
                                                                                                   } ?>"
                                                                                                   pattern="^[0-9]{10}$"
                                                                                                   name="zipcode">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نوع
                                                                                                محل سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <select class="form-control select2"
                                                                                                    name="malek-house">
                                                                                                <option label="نوع محل سکونت"></option>
                                                                                                <option <?php if ($data->type_life != NULL) {
                                                                                                    if ($data->type_life == 'آپارتمان') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>آپارتمان
                                                                                                </option>
                                                                                                <option <?php if ($data->type_life != NULL) {
                                                                                                    if ($data->type_life == 'خانه ویلایی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>خانه ویلایی
                                                                                                </option>
                                                                                                <option <?php if ($data->type_life != NULL) {
                                                                                                    if ($data->type_life == 'کوچ نشین') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>کوچ نشین
                                                                                                </option>
                                                                                                <option <?php if ($data->type_life != NULL) {
                                                                                                    if ($data->type_life == 'باغ') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>باغ
                                                                                                </option>
                                                                                                <option <?php if ($data->type_life != NULL) {
                                                                                                    if ($data->type_life == 'سایر') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>سایر
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">وضعیت
                                                                                                سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <select class="form-control select2"
                                                                                                    name="status-house">
                                                                                                <option label="وضعیت سکونت"></option>
                                                                                                <option <?php if ($data->life_status != NULL) {
                                                                                                    if ($data->life_status == 'شخصی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>شخصی
                                                                                                </option>
                                                                                                <option <?php if ($data->life_status != NULL) {
                                                                                                    if ($data->life_status == 'استیجاری') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>استیجاری
                                                                                                </option>
                                                                                                <option <?php if ($data->life_status != NULL) {
                                                                                                    if ($data->life_status == 'سازمانی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>سازمانی
                                                                                                </option>
                                                                                                <option <?php if ($data->life_status != NULL) {
                                                                                                    if ($data->life_status == 'سایر') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>سایر
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نوع
                                                                                                بافت سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <select class="form-control select2"
                                                                                                    name="baft-house">
                                                                                                <option label="نوع بافت"></option>
                                                                                                <option <?php if ($data->life_baft != NULL) {
                                                                                                    if ($data->life_baft == 'شهری') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>شهری
                                                                                                </option>
                                                                                                <option <?php if ($data->life_baft != NULL) {
                                                                                                    if ($data->life_baft == 'روستایی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>روستایی
                                                                                                </option>
                                                                                                <option <?php if ($data->life_baft != NULL) {
                                                                                                    if ($data->life_baft == 'عشایر') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>عشایر
                                                                                                </option>
                                                                                                <option <?php if ($data->life_baft != NULL) {
                                                                                                    if ($data->life_baft == 'سایر') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?>>سایر
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">متراژ
                                                                                                محل سکونت </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="number"
                                                                                                   class="form-control"
                                                                                                   placeholder="مترمربع"
                                                                                                   value="<?php if ($data->life_metr != NULL) {
                                                                                                       echo htmlentities($data->life_metr);
                                                                                                   } ?>"
                                                                                                   name="metr">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="mb-4 main-content-label">
                                                                                    اطلاعات تحصیلی و کاری
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                محل کار </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام محل کار"
                                                                                                   value="<?php echo htmlentities($data->workname) ?>"
                                                                                                   name="workname">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">سمت
                                                                                                شغلی </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="سمت"
                                                                                                   value="<?php if ($data->semat != NULL) {
                                                                                                       echo htmlentities($data->semat);
                                                                                                   } ?>" name="semat">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">مقطع
                                                                                                تحصیلی <span
                                                                                                        class="text-danger">*</span>
                                                                                            </label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <select class="form-control select2"
                                                                                                    name="maghta"
                                                                                                    required>
                                                                                                <option label="مقطع تحصیلی"></option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'زیردیپلم') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >زیر دیپلم
                                                                                                </option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'دیپلم') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >دیپلم
                                                                                                </option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'کاردانی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >کاردانی
                                                                                                </option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'کارشناسی') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >کارشناسی
                                                                                                </option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'کارشناسی ارشد') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >کارشناسی ارشد
                                                                                                </option>
                                                                                                <option <?php if ($data->maghta != NULL) {
                                                                                                    if ($data->maghta == 'دکتری') {
                                                                                                        echo 'selected';
                                                                                                    }
                                                                                                } ?> >دکتری
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">رشته
                                                                                                تحصیلی <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="رشته تحصیلی"
                                                                                                   value="<?php if ($data->reshtehamkar != NULL) {
                                                                                                       echo htmlentities($data->reshtehamkar);
                                                                                                   } ?>"
                                                                                                   name="reshtehamkar"
                                                                                                   required>

                                                                                            <div class="mt-3">
                                                                                                <button type="submit"
                                                                                                        name="house-submit"
                                                                                                        class="btn btn-primary btn-block mr-1">
                                                                                                    ذخیره
                                                                                                    اطلاعات
                                                                                                </button>

                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </form>

                                                                        </div>
                                                                    </div>
                                                                    <div class="main-content-body tab-pane p-4 border-top-0"
                                                                         id="picture">
                                                                        <div class="card-body border ">
                                                                            <div class="col-xl-3">

                                                                            </div>
                                                                            <div class="col-xl-6">

                                                                                <div class="input-group file-browser">
                                                                                    <input type="text"
                                                                                           class="form-control border-left-0 browse-file"
                                                                                           placeholder=" عکس خود را بارگزاری نمایید"
                                                                                           readonly="">
                                                                                    <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب فایل عکس<input type="file"
                                                                                  name="fileToUpload"
                                                                                  style="display: none;">
														</span>
                                                                                    </label>
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    تصویر عکس با فرمت های png،jpg با
                                                                                    حداکثر سایز 500KB مجاز می باشد
                                                                                </div>
                                                                                <div class="mt-3 text-center">
                                                                                    <button type="button"
                                                                                            class="btn btn-warning  mr-1">
                                                                                        بارگذاری
                                                                                        عکس
                                                                                    </button>

                                                                                </div>
                                                                            </div>

                                                                        </div>
                                                                    </div>

                                                                    <div class="main-content-body tab-pane p-4 border-top-0"
                                                                         id="wallet">
                                                                        <div class="card-body border">
                                                                            <form method="post"
                                                                                  data-parsley-validate="">
                                                                                <div class="mb-4 main-content-label">
                                                                                    اطلاعات حساب بانکی
                                                                                </div>
                                                                                <div class="form-group ">

                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">شماره
                                                                                                شبای بانکی <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <div class="input-group-prepend">

                                                                                                <input type="text"
                                                                                                       class="form-control"
                                                                                                       placeholder="24 رقم"
                                                                                                       name="sheba"
                                                                                                       value="<?php if ($data->sheba != NULL) {
                                                                                                           echo htmlentities($data->sheba);
                                                                                                       } ?>"
                                                                                                       required="">
                                                                                                <div class="input-group-text">
                                                                                                    IR
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                صاحب حساب <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام صاحب حساب شبا"
                                                                                                   name="name-sheba"
                                                                                                   value="<?php if ($data->name_sheba != NULL) {
                                                                                                       echo htmlentities($data->name_sheba);
                                                                                                   } ?>"
                                                                                                   required="">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                بانک عامل <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder=""
                                                                                                   required
                                                                                                   name="bank-sheba"
                                                                                                   value="<?php if ($data->bank_sheba != NULL) {
                                                                                                       echo htmlentities($data->bank_sheba);
                                                                                                   } ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="mb-4 main-content-label">
                                                                                    اطلاعات عابربانک
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">شماره
                                                                                                کارت <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="شماره عابربانک معتبر"
                                                                                                   name="num-card"
                                                                                                   value="<?php if ($data->card_bank != NULL) {
                                                                                                       echo htmlentities($data->card_bank);
                                                                                                   } ?>" required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="form-group ">
                                                                                    <div class="row row-sm">
                                                                                        <div class="col-xl-3">
                                                                                            <label class="form-label">نام
                                                                                                صاحب کارت <span
                                                                                                        class="text-danger">*</span></label>
                                                                                        </div>
                                                                                        <div class="col-xl-4">
                                                                                            <input type="text"
                                                                                                   class="form-control"
                                                                                                   placeholder="نام صاحب شماره کارت"
                                                                                                   name="name-card"
                                                                                                   value="<?php if ($data->name_card != NULL) {
                                                                                                       echo htmlentities($data->name_card);
                                                                                                   } ?>" required>
                                                                                            <input type="hidden"
                                                                                                   name="token"
                                                                                                   value="<?php echo $hash ?>">

                                                                                            <div class="mt-3">
                                                                                                <button type="submit"
                                                                                                        class="btn btn-primary btn-block mr-1"
                                                                                                        name="acounting-info">
                                                                                                    ذخیره اطلاعات
                                                                                                    حساب
                                                                                                </button>

                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>

                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
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


                ?>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

