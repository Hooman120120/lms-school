<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if (isset($_POST['account-submit'])) { // UPDATE Account User
    if ($_SESSION['token'] == $_POST['token']) {

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
            $upd = 'UPDATE dbpersonal SET tel_home=?,email=?,bio=?,tel2=?,dateupdate=?,timeupdate=?  WHERE codemeli=? ';
            $qupdate = $conn->prepare($upd);
            $qupdate->execute([$telHouse, $email, $bio, $tel2, $datenow, $timenow, $_SESSION['auth']['user']]);
        }
    }
}
// اطلاعات سکونت
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
            $update = 'UPDATE dbpersonal SET life_rosta=?,address=?,zipcode=?,type_life=?,life_status=?,life_baft=?,life_metr=?,reshtehamkar=?,maghta=?,semat=?,workname=?,dateupdate=?,timeupdate=? WHERE codemeli=?';
            $query = $conn->prepare($update);
            $query->execute([$rosta, $address, $zipcode, $type_life, $status_life, $life_baft, $metr,$reshtehamkar,$maghta,$semat,$workname, $datenow, $timenow, $_SESSION['auth']['user']]);

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
            $qupcard->execute([$sheba, $name_sheba, $bank_sheba, $numcard, $namecard, $_SESSION['auth']['user']]);

        } else {
            $erorvalidcard = 1;
        }

    }
}

//* End Check Session JUST ONE Session
$data = retPerson($_SESSION['auth']['user']);
include_once '../temp.html';

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
    <title> ویرایش حساب کاربری | <?php echo __TITLE ?></title>
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
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- Row -->
                <div class="row square">
                    <div class="col-lg-12 col-md-12">
                        <div class="card custom-card">
                            <div class="card-body">

                                <div class="panel profile-cover">
                                    <div class="profile-cover__img">
                                        <img src="../assets/img/users/1.jpg" alt="img">
                                        <h3 class="h3"><?php echo $_SESSION['auth']['name'] . " " . $_SESSION['auth']['famili'] ?></h3>
                                    </div>

                                    <div class="profile-cover__action bg-img"></div>
                                    <div class="profile-cover__info">
                                        <ul class="nav">

                                            <li>
                                                <strong class="badge <?php if (isset($courseregister) and $courseregister == 1) { ?>  badge-danger ">
                                                    غیرفعال <?php }
                                                    else { ?>
                                                        badge-success-light "> فعال
                                                    <?php } ?>
                                                </strong> وضعیت حساب
                                                کاربری
                                            </li>
                                            <li>
                                                <strong class="badge badge-success-light"> <?php if ($_SESSION['auth']['role'] == 1) {
                                                        echo t('student');
                                                    } else {
                                                        echo 'همکار آموزشی';
                                                    } ?></strong> نوع حساب
                                                کاربری

                                            </li>

                                        </ul>
                                    </div>
                                </div>
                                <?php
                                $hash = bin2hex(random_bytes(32));
                                $_SESSION['token'] = $hash;
                                ?>
                                <div class="profile-tab tab-menu-heading">
                                    <nav class="nav main-nav-line p-3 tabs-menu profile-nav-line bg-gray-100">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#edit">اطلاعات حساب
                                            کاربری </a>

                                        <a class="nav-link" data-bs-toggle="tab" href="#house">اطلاعات سکونت و
                                            تحصیلی </a>
                                        <a class="nav-link" data-bs-toggle="tab" href="#picture">تصویر پروفایل </a>

                                        <a class="nav-link" data-bs-toggle="tab" href="#wallet">اطلاعات حساب بانکی</a>

                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <div class="col-lg-12 col-md-12">
                        <div class="card custom-card main-content-body-profile">
                            <div class="tab-content">
                                <?php if (isset($erorstrlensheba) and $erorstrlensheba == 1) { ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        شماره شبا می بایست 24 رقم باشد
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php } ?>
                                <?php if (isset($erorvalidcard) and $erorvalidcard == 1) { ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        لطفا یک شماره کارت معتبر وارد کنید
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php } ?>


                                <div class="main-content-body tab-pane p-4 border-top-0 active" id="edit">
                                    <div class="card-body border">
                                        <div class="mb-4 main-content-label">اطلاعات شخصی</div>
                                        <form class="form-horizontal" method="post" data-parsley-validate="">

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام </label>
                                                    </div>
                                                    <div class="col-xl-6">
                                                        <input type="text" class="form-control" placeholder="نام "
                                                               value="<?php echo htmlentities($data->fname) ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام خانوادگی</label>
                                                    </div>
                                                    <div class="col-xl-6">
                                                        <input type="text" class="form-control"
                                                               placeholder="نام خانوادگی"
                                                               value="<?php echo htmlentities($data->lname) ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام پدر</label>
                                                    </div>
                                                    <div class="col-xl-6">
                                                        <input type="text" class="form-control"
                                                               placeholder="نام خانوادگی"
                                                               value="<?php echo htmlentities($data->fathername) ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">کد ملی</label>
                                                    </div>
                                                    <div class="col-xl-3">
                                                        <input type="text" class="form-control" placeholder="کد ملی"
                                                               value="<?php echo htmlentities($data->codemeli) ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3 ">
                                                        <label class="form-label">تاریخ تولد</label>
                                                    </div>
                                                    <?php
                                                    $date = $data->birthdate;

                                                    $date = explode('-', $date);
                                                    $date = gregorian_to_jalali($date[0], $date[1], $date[2]);
                                                    ?>
                                                    <div class="col-3">
                                                        <input type="text" class="form-control" placeholder=" "
                                                               value="<?php echo $date[2] ?>" disabled>
                                                    </div>
                                                    <div class="col-3">
                                                        <input type="text" class="form-control" placeholder=" "
                                                               value="<?php echo $date[1] ?>" disabled>
                                                    </div>
                                                    <div class="col-4 col-xl-3 col-lg-3">
                                                        <input type="text" class="form-control" placeholder=" "
                                                               value="<?php echo $date[0] ?>" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">جنسیت</label>
                                                    </div>
                                                    <div class="col-xl-2">
                                                        <input type="text" class="form-control" placeholder="جنسیت"
                                                               value="<?php echo htmlentities($data->gender) ?>"
                                                               disabled>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4 main-content-label">اطلاعات تماس</div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">شماره همراه <i>(اصلی)</i></label>
                                                    </div>
                                                    <div class="col-xl-3">
                                                        <input type="text" class="form-control" placeholder=" "
                                                               value="<?php echo htmlentities($data->tel1) ?>" disabled>
                                                    </div>
                                                    <div class="col-xl-4 text-muted">ما به این شماره پیامک ارسال می کنیم
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">شماره همراه <i>(رابط)</i><span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-3">
                                                        <input type="text" class="form-control"
                                                               placeholder="24***091146 " required
                                                               value="<?php echo htmlentities($data->tel2) ?>"
                                                               name="tel2">
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">تلفن منزل</label>
                                                    </div>
                                                    <div class="col-xl-3">
                                                        <input type="text" class="form-control"
                                                               placeholder=" 65****02188" name="tel-house"
                                                               value="<?php if ($data->tel_home != NULL) {
                                                                   echo htmlentities($data->tel_home);
                                                               } ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label"> پست الکترونیکی</label>
                                                    </div>
                                                    <div class="col-xl-3">
                                                        <input type="email" class="form-control" name="email"
                                                               placeholder="پست الکترونیکی "
                                                               value="<?php if ($data->email != NULL) {
                                                                   echo htmlentities($data->email);
                                                               } ?>">
                                                    </div>
                                                </div>
                                            </div>


                                            <div class="mb-4 main-content-label">درباره خودتان</div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">اطلاعاتی در خصوص وضعیت جسمانی ،
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
                                                    <div class="col-xl-3">
                                                    </div>
                                                    <div class="col-xl-4">


                                                        <input type="hidden" name="token"
                                                               value="<?php echo $hash ?>">
                                                        <div class="mt-3">
                                                            <button type="submit" name="account-submit"
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
                                <div class="main-content-body p-4 border tab-pane border-top-0" id="house">
                                    <div class="card-body border">
                                        <div class="mb-4 main-content-label">اطلاعات سکونت</div>
                                        <form class="form-horizontal " method="post" data-parsley-validate="">
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">استان محل سکونت <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control" placeholder=""
                                                               name="state"
                                                               value="<?php echo htmlentities($data->state) ?>"
                                                               disabled>
                                                    </div>
                                                    <input type="hidden" name="token"
                                                           value="<?php echo $hash ?>">
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">شهر محل سکونت <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control" placeholder=""
                                                               name="city"
                                                               value="<?php echo htmlentities($data->city) ?>" disabled>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">روستا محل سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control" name="rosta"
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
                                                        <label class="form-label">آدرس محل سکونت</label>
                                                    </div>
                                                    <div class="col-xl-9">
                                                        <textarea class="form-control" name="address"
                                                                  rows="4" placeholder=""
                                                        ><?php if ($data->address != NULL) {
                                                                echo htmlentities($data->address);
                                                            } ?></textarea>
                                                        <span class="text-muted">  ممکن است حسب ضرورت ، برای شما ، بسته پستی ارسال شود</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">کد پستی محل سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control" placeholder="کدپستی"
                                                               value="<?php if ($data->zipcode != NULL) {
                                                                   echo htmlentities($data->zipcode);
                                                               } ?>" pattern="^[0-9]{10}$" name="zipcode">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نوع محل سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <select class="form-control select2" name="malek-house">
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
                                                        <label class="form-label">وضعیت سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <select class="form-control select2" name="status-house">
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
                                                        <label class="form-label">نوع بافت سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <select class="form-control select2" name="baft-house">
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
                                                        <label class="form-label">متراژ محل سکونت </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="number" class="form-control" placeholder="مترمربع"
                                                               value="<?php if ($data->life_metr != NULL) {
                                                                   echo htmlentities($data->life_metr);
                                                               } ?>" name="metr">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4 main-content-label">اطلاعات تحصیلی و کاری</div>
                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام محل کار </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
                                                               placeholder="نام محل کار"
                                                               value="<?php echo htmlentities($data->workname) ?>"
                                                               name="workname">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">سمت شغلی </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
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
                                                        <label class="form-label">مقطع تحصیلی <span
                                                                    class="text-danger">*</span> </label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <select class="form-control select2" name="maghta"
                                                                required>
                                                            <option label="مقطع تحصیلی"></option>
                                                            <option <?php if ($data->maghta != NULL) {
                                                                if ($data->maghta == 'زیر دیپلم') {
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
                                                        <label class="form-label">رشته تحصیلی <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
                                                               placeholder="رشته تحصیلی"
                                                               value="<?php if ($data->reshtehamkar != NULL) {
                                                                   echo htmlentities($data->reshtehamkar);
                                                               } ?>" name="reshtehamkar" required>


                                                        <div class="mt-3">
                                                            <button type="submit" name="house-submit"
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
                                <div class="main-content-body tab-pane p-4 border-top-0" id="picture">
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
                                                تصویر عکس با فرمت های png،jpg با حداکثر سایز 500KB مجاز می باشد
                                            </div>
                                            <div class="mt-3 text-center">
                                                <button type="button" class="btn btn-warning  mr-1">
                                                    بارگذاری
                                                    عکس
                                                </button>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <div class="main-content-body tab-pane p-4 border-top-0" id="wallet">
                                    <div class="card-body border">
                                        <form method="post" data-parsley-validate="">
                                            <div class="mb-4 main-content-label">اطلاعات حساب بانکی</div>
                                            <div class="form-group ">

                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">شماره شبای بانکی <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <div class="input-group-prepend">

                                                            <input type="text" class="form-control" placeholder="24 رقم"
                                                                   name="sheba"
                                                                   value="<?php if ($data->sheba != NULL) {
                                                                       echo htmlentities($data->sheba);
                                                                   } ?>" required="">
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
                                                        <label class="form-label">نام صاحب حساب <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
                                                               placeholder="نام صاحب حساب شبا" name="name-sheba"
                                                               value="<?php if ($data->name_sheba != NULL) {
                                                                   echo htmlentities($data->name_sheba);
                                                               } ?>" required="">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام بانک عامل <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control" placeholder="" required
                                                               name="bank-sheba"
                                                               value="<?php if ($data->bank_sheba != NULL) {
                                                                   echo htmlentities($data->bank_sheba);
                                                               } ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4 main-content-label">اطلاعات عابربانک</div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">شماره کارت <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
                                                               placeholder="شماره عابربانک معتبر" name="num-card"
                                                               value="<?php if ($data->card_bank != NULL) {
                                                                   echo htmlentities($data->card_bank);
                                                               } ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group ">
                                                <div class="row row-sm">
                                                    <div class="col-xl-3">
                                                        <label class="form-label">نام صاحب کارت <span
                                                                    class="text-danger">*</span></label>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <input type="text" class="form-control"
                                                               placeholder="نام صاحب شماره کارت" name="name-card"
                                                               value="<?php if ($data->name_card != NULL) {
                                                                   echo htmlentities($data->name_card);
                                                               } ?>" required>
                                                        <input type="hidden" name="token"
                                                               value="<?php echo $hash ?>">

                                                        <div class="mt-3">
                                                            <button type="submit" class="btn btn-primary btn-block mr-1"
                                                                    name="acounting-info">
                                                                ذخیره اطلاعات حساب
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
                <!-- End Row -->
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
