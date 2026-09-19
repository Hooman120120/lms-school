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
    unset($_SESSION['edit-student']);
    header('location: ' . url('admin/Student/edit-absolute.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            if (checkCodemeli($codemeli)) {
                if (typeUser($_POST['codemeli']) == 1) {
                    $_SESSION['edit-student'] = array(
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
        if (isset($_POST['fname']) and $_POST['fname'] != '' and isset($_POST['lname']) and $_POST['lname'] != '' and isset($_POST['gender']) and $_POST['gender'] != '' and isset($_POST['melliat']) and $_POST['melliat'] != '' and isset($_POST['tel1']) and $_POST['tel1'] != '' and isset($_POST['dtts']) and $_POST['dtts'] != '' and isset($_POST['mtts']) and $_POST['mtts'] != '' and isset($_POST['ytts']) and $_POST['ytts'] != '' and isset($_POST['type-vip']) and isset($_POST['shift'])) {
            $fname = $_POST['fname'];
            $lname = $_POST['lname'];
            $gender = $_POST['gender'];
            $melliat = $_POST['melliat'];
            $dtts = $_POST['dtts'];
            $mtts = $_POST['mtts'];
            $ytts = $_POST['ytts'];
            $tel1 = $_POST['tel1'];
            $moaref = $_POST['moaref'] ?? '';
            if (isset($_POST['activity-user'])) {
                $upa = 'UPDATE dblogin SET activity=? WHERE username=?';
                $qur = $conn->prepare($upa);
                $qur->execute([1, $_SESSION['edit-student']['codemeli']]);
            } else {
                $upa = 'UPDATE dblogin SET activity=? WHERE username=?';
                $qur = $conn->prepare($upa);
                $qur->execute([0, $_SESSION['edit-student']['codemeli']]);
            }
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
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
            if (isset($_POST['hand']) and $_POST['hand'][0] != '') {
                $hand = $_POST['hand'][0];
            } else {
                $hand = NULL;
            }
            $birthdate = jalali_to_gregorian($_POST['ytts'], $_POST['mtts'], $_POST['dtts']);
            $birthdate = implode('-', $birthdate);
            $personal = retPerson($_SESSION['edit-student']['codemeli']);
            $vips = $_POST['type-vip'];
            if (!isset($_POST['tel-trust'])) {
                if (!checkTellWithoume($tel1, $_SESSION['edit-student']['codemeli'])) {
                    $upd = 'UPDATE dbpersonal SET fname=?,lname=?,tel1=?,birthdate=?,melliat=?,gender=?,shift=?,tel_home=?,email=?,bio=?,hand=?,dateupdate=?,timeupdate=?,updator =?,moaref=? WHERE codemeli=? ';
                    $qup = $conn->prepare($upd);
                    $qup->execute([$fname, $lname, $tel1, $birthdate, $melliat, $gender, $_POST['shift'], $telHouse, $email, $bio, $hand, $datenow, $timenow, $_SESSION['admin']['user'], $moaref, $_SESSION['edit-student']['codemeli']]);
                    $sql = "DELETE FROM dbpersonal_type_vip WHERE dbpersonal_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$personal->idpersonal]);
                    $insertQuery = "INSERT INTO dbpersonal_type_vip (dbpersonal_id,type_vip_id) VALUES (?,?)";
                    $queryString = $conn->prepare($insertQuery);
                    foreach ($vips as $vip) {
                        $queryString->execute([$personal->idpersonal, $vip]);
                    }
                } else {
                    $havetel = 1;
                }
            } else {
                $upd = 'UPDATE dbpersonal SET fname=?,lname=?,tel1=?,birthdate=?,melliat=?,gender=?,shift=?,tel_home=?,email=?,bio=?,hand=?,dateupdate=?,timeupdate=?,updator =?,moaref=? WHERE codemeli=? ';
                $qup = $conn->prepare($upd);
                $qup->execute([$fname, $lname, $tel1, $birthdate, $melliat, $gender, $_POST['shift'], $telHouse, $email, $bio, $hand, $datenow, $timenow, $_SESSION['admin']['user'], $moaref, $_SESSION['edit-student']['codemeli']]);
                $sql = "DELETE FROM dbpersonal_type_vip WHERE dbpersonal_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$personal->idpersonal]);
                $insertQuery = "INSERT INTO dbpersonal_type_vip (dbpersonal_id,type_vip_id) VALUES (?,?)";
                $queryString = $conn->prepare($insertQuery);
                foreach ($vips as $vip) {
                    $queryString->execute([$personal->idpersonal, $vip]);
                }
            }
        }
    }
}

if (isset($_POST['picture-submit'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        if (isset($_FILES['pic']) && $_FILES['pic']['error'] === UPLOAD_ERR_OK) {
            // Use document root to get the absolute path to your web root
            $targetDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads-student/student-profile/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $fileTmpPath = $_FILES['pic']['tmp_name'];
            $originalName = basename($_FILES['pic']['name']);
            $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            $maxFileSize = 500 * 1024; // 500KB

            if (in_array($fileExtension, $allowedExtensions) && $_FILES['pic']['size'] <= $maxFileSize) {
                $newFileName = time() . '_' . $_SESSION['edit-student']['codemeli'] . '.' . $fileExtension;
                $destPath = $targetDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // Store the web‑accessible path (relative to document root)
                    $picPath = '/uploads-student/student-profile/' . $newFileName;

                    $updateSql = "UPDATE dbpersonal SET pic = ? WHERE codemeli = ?";
                    $stmt = $conn->prepare($updateSql);
                    $stmt->execute([$picPath, $_SESSION['edit-student']['codemeli']]);

                    $_SESSION['upload_success'] = "عکس با موفقیت آپلود شد";
                } else {
                    $_SESSION['upload_error'] = "خطا در آپلود فایل";
                }
            } else {
                $_SESSION['upload_error'] = "فرمت یا حجم فایل مجاز نیست (jpg/png و حداکثر 500KB)";
            }
        } else {
            $_SESSION['upload_error'] = "لطفا یک فایل انتخاب کنید";
        }
        header('location: ' . url('admin/Student/edit-absolute.php'));
        exit;
    }
}
if (isset($_POST['parent-valid'])) { // UPDATE Parent User
    if ($_SESSION['token'] == $_POST['token']) {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $name_pedar = $_POST['name-pedar'];
        $famili_pedar = $_POST['famili-pedar'];
        if (isset($_POST['codemeli-pedar'])) {
            $codemeli_pedar = $_POST['codemeli-pedar'];
        } else {
            $codemeli_pedar = NULL;
        }
        if (isset($_POST['shenasname-pedar'])) {
            $shenas_pedar = $_POST['shenasname-pedar'];
        } else {
            $shenas_pedar = NULL;
        }
        if (isset($_POST['dttp'])) {
            $dttp = $_POST['dttp'];
        } else {
            $dttp = NULL;
        }
        if ($_POST['dttp'] != '' && $_POST['mttp'] != '' && $_POST['yttp'] != '') {
            $birthdatepedar = jalali_to_gregorian($_POST['yttp'], $_POST['mttp'], $_POST['dttp']);
            $birthdatepedar = implode('-', $birthdatepedar);
        } else {
            $birthdatepedar = NULL;
        }
        if (isset($_POST['job-pedar'])) {
            $job_pedar = trim($_POST['job-pedar']);
        } else {
            $job_pedar = NULL;
        }
        $edu_pedar = $_POST['edu-pedar'];
        if (isset($_POST['hayat_p'])) {
            $hayat_p = $_POST['hayat_p'][0];
        } else {
            $hayat_p = NULL;
        }
        $tel3 = $_POST['tel3'];
        //mother
        $name_madar = trim($_POST['name-madar']);
        $famili_madar = trim($_POST['famili-madar']);
        if (isset($_POST['codemeli-madar'])) {
            $codemeli_madar = $_POST['codemeli-madar'];
        } else {
            $codemeli_madar = NULL;
        }
        if (isset($_POST['shenasname-madar'])) {
            $shenas_madar = $_POST['shenasname-madar'];
        } else {
            $shenas_madar = NULL;
        }
        if ($_POST['dttm'] != '' && $_POST['mttm'] != '' && $_POST['yttm'] != '') {
            $birthdatemadar = jalali_to_gregorian($_POST['yttm'], $_POST['mttm'], $_POST['dttm']);
            $birthdatemadar = implode('-', $birthdatemadar);
        } else {
            $birthdatemadar = NULL;
        }
        if (isset($_POST['job-madar'])) {
            $job_madar = trim($_POST['job-madar']);
        } else {
            $job_madar = NULL;
        }
        $edu_madar = $_POST['edu-madar'];
        if (isset($_POST['hayat_m'])) {
            $hayat_m = $_POST['hayat_m'][0];
        } else {
            $hayat_m = NULL;
        }
        $tel2 = $_POST['tel3'];
        if (isset($_POST['life'])) {
            $life = $_POST['life'][0];
        } else {
            $life = NULL;
        }
        if (isset($_POST['bime'])) {
            $bime = $_POST['bime'][0];
        } else {
            $bime = NULL;
        }
        if (isset($_POST['baby'])) {
            $baby = $_POST['baby'];
        } else {
            $baby = NULL;
        }
        $upe = 'UPDATE dbpersonal SET fathername=?, famili_pedar=?, codemeli_pedar=?,shenasname_pedar=?,birthday_pedar=?,job_pedar=?,education_pedar=?,hayat_pedar=?,name_madar=?,famili_madar=?,codemeli_madar=?,shenasname_madar=?,birthdate_madar=?,job_madar=?,education_madar=?,hayat_madar=?,living_condition=?,bime_status=?,all_baby=?,dateupdate=?,timeupdate=?,updator=? WHERE codemeli=?';
        $qupe = $conn->prepare($upe);
        $qupe->execute([$name_pedar, $famili_pedar, $codemeli_pedar, $shenas_pedar, $birthdatepedar, $job_pedar, $edu_pedar, $hayat_p, $name_madar, $famili_madar, $codemeli_madar, $shenas_madar, $birthdatemadar, $job_madar, $edu_madar, $hayat_m, $life, $bime, $baby, $datenow, $timenow, $_SESSION['admin']['user'], $_SESSION['edit-student']['codemeli']]);
    }
}
if (isset($_POST['house-submit'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        if ($_POST['school'] != '' and $_POST['type-school'] != '' and $_POST['mantaghe'] != '' and $_POST['reshte'] != '' and $_POST['paye'] != '') {
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $school = trim($_POST['school']);
            $type_school = $_POST['type-school'];
            $mantaghe = $_POST['mantaghe'];
            $paye = $_POST['paye'];
            $reshte = $_POST['reshte'];
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
            if ($_POST['modir-name'] != '') {
                $modir_name = trim($_POST['modir-name']);
            } else {
                $modir_name = NULL;
            }
            $update = 'UPDATE dbpersonal SET life_rosta=?,address=?,zipcode=?,type_life=?,life_status=?,life_baft=?,life_metr=?,nameschool=?,modir_name=?,typeschool=?,area=?,paye=?,reshtestudent=?,dateupdate=?,timeupdate=?,updator=? WHERE codemeli=?';
            $query = $conn->prepare($update);
            $query->execute([$rosta, $address, $zipcode, $type_life, $status_life, $life_baft, $metr, $school, $modir_name, $type_school, $mantaghe, $paye, $reshte, $datenow, $timenow, $_SESSION['admin']['user'], $_SESSION['edit-student']['codemeli']]);
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
            $qupcard->execute([$sheba, $name_sheba, $bank_sheba, $numcard, $namecard, $_SESSION['edit-student']['codemeli']]);
        } else {
            $erorvalidcard = 1;
        }
    }
}
if (isset($_GET['againcode'])) {
    $data = retPerson($_SESSION['edit-student']['codemeli']);
    $text = 'کد ملی' . ';' . passUser($data->codemeli);
    SendSms($data->tel1, $text, 171697, $_SESSION['edit-student']['codemeli']);
    $_SESSION['againsms'] = $data->tel1;
    header('location: ' . url('admin/Student/edit-absolute.php'));
    exit;
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
    <title> ویرایش اطلاعات دانش آموز |<?php echo __TITLE ?></title>
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
                if (Permision('permision_editstudent')) {
                ?>
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    ویرایش اطلاعات دانش‌آموز
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید اطلاعات حساب کاربری دانش‌آموز، مشخصات فردی، اطلاعات تماس و سایر اطلاعات ثبت‌شده او را مشاهده و ویرایش کنید."
                                                      aria-label="راهنمای ویرایش اطلاعات دانش‌آموز">

                                                                <i class="fe fe-info fs-4"></i>
                                                            </span>

                                            </div>
                                            <p class="text-muted card-sub-title">
                                                ویرایش اطلاعات حساب کاربری دانش‌آموز
                                            </p>


                                        </div>
                                    </div>
                                    <div class="col-2 text-left">
                                        <a href="?newcode" class="btn btn-sm btn-primary"><i
                                                    class="fe fe-refresh-cw"></i> کد ملی جدید</a>
                                    </div>

                                </div>
                                <?php if (isset($collague) and $collague == 1) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کد ملی متعلق به همکار آموزشی می باشد
                                    </div>
                                <?php } ?>
                                <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        خطای غیر منتظره رخ داده است
                                    </div>
                                <?php } ?>
                                <?php if (isset($havemeli) and $havemeli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کد ملی در سیستم موجود نیست
                                    </div>
                                <?php } ?>
                                <?php if (isset($_SESSION['againsms'])) { ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        پیامک با موفقیت به شماره : <?php echo $_SESSION['againsms'] ?> ارسال شد
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php }
                                unset($_SESSION['againsms']);
                                ?>
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <?php if (!isset($_SESSION['edit-student'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="sens" class="form-label mg-t-15">کدملی
                                                                دانش آموز: <span style="color: red">*</span></label>
                                                            <input class="form-control" name="codemeli" type="text"
                                                                   placeholder="با اعداد انگلیسی وارد شود" required>
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
                                                                name="search-melli"><i class="fe fe-search"></i>
                                                            جستجو دانش آموز
                                                        </button>
                                                    </div>
                                                </form>
                                                <!--                                        end step 1-->
                                            <?php } ?>
                                            <?php if (isset($_SESSION['edit-student'])) { ?>
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
                                                <nav
                                                        class="nav main-nav-line p-3 tabs-menu profile-nav-line bg-gray-100">
                                                    <a class="nav-link active" data-bs-toggle="tab"
                                                       href="#edit">اطلاعات حساب
                                                        کاربری </a>
                                                    <a class="nav-link" data-bs-toggle="tab" href="#Parent">اطلاعات
                                                        خانواده </a>
                                                    <a class="nav-link" data-bs-toggle="tab" href="#house">اطلاعات
                                                        سکونت و
                                                        تحصیلی </a>
                                                    <a class="nav-link" data-bs-toggle="tab" href="#picture">تصویر
                                                        پروفایل </a>
                                                    <a class="nav-link" data-bs-toggle="tab" href="#basij">اطلاعات
                                                        بسیج دانش
                                                        آموزی </a>
                                                    <a class="nav-link" data-bs-toggle="tab" href="#wallet">اطلاعات
                                                        حساب بانکی</a>

                                                </nav>
                                            </div>
                                            <?php if (isset($havetel) and $havetel == 1) { ?>
                                                <div class="alert alert-danger" role="alert">
                                                                    <span class="alert-inner--icon"><i
                                                                                class=" mdi mdi-asterisk"></i></span>
                                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert"
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
                                                            <?php $data = retPerson($_SESSION['edit-student']['codemeli']); ?>
                                                            <div class="main-content-body tab-pane p-4 border-top-0 active"
                                                                 id="edit"> <!-- اطلاعات حساب کاربری -->

                                                                <div class="card-body border">
                                                                    <div class="mb-4 main-content-label">
                                                                        اطلاعات شخصی
                                                                    </div>
                                                                    <form class="form-horizontal" method="post"
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
                                                                                           name="fname" required>
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
                                                                                           name="lname" required>
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
                                                                                <div class="col-xl-2 ">
                                                                                    <label class="custom-switch">
                                                                                        <input type="checkbox"
                                                                                               name="activity-user"
                                                                                               class="custom-switch-input"
                                                                                            <?php if (actUser($data->codemeli) == 1) {
                                                                                                echo 'checked';
                                                                                            } ?>>
                                                                                        <span
                                                                                                class="custom-switch-indicator"></span>
                                                                                        <span class="">فعال سازی
                                                                                                            کاربر</span>
                                                                                    </label>
                                                                                </div>
                                                                                <div class="col-xl-3 ">
                                                                                    <a href="?againcode"
                                                                                       class="btn  btn-success"><i
                                                                                                class="fe fe-message-square"></i>
                                                                                        ارسال مجدد پیامک
                                                                                        اطلاعات کاربری</a>
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
                                                                                <div class="col-xl-3">
                                                                                    کد فعالسازی حساب: <span
                                                                                            class="text-danger"><?php if (keyUser($data->codemeli) == NULL) {
                                                                                            echo 'فاقد کد';
                                                                                        } else {
                                                                                            echo keyUser($data->codemeli);
                                                                                        } ?> </span>
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
                                                                                    <div
                                                                                            class="input-group-prepend">
                                                                                        <div
                                                                                                class="input-group-text">
                                                                                            روز:
                                                                                        </div>
                                                                                        <?php
                                                                                        if ($data->birthdate != NULL) {
                                                                                            $date = $data->birthdate;
                                                                                            $date = explode('-', $date);
                                                                                            $date = gregorian_to_jalali($date[0], $date[1], $date[2]);
                                                                                        }
                                                                                        ?>
                                                                                        <select
                                                                                                class="form-control select2"
                                                                                                name="dtts" required>
                                                                                            <option
                                                                                                    label="روز تولد ">
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 1) {
                                                                                                echo 'selected';
                                                                                            } ?>>01
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 2) {
                                                                                                echo 'selected';
                                                                                            } ?>>02
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 3) {
                                                                                                echo 'selected';
                                                                                            } ?>>03
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 4) {
                                                                                                echo 'selected';
                                                                                            } ?>>04
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 5) {
                                                                                                echo 'selected';
                                                                                            } ?>>05
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 6) {
                                                                                                echo 'selected';
                                                                                            } ?>>06
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 7) {
                                                                                                echo 'selected';
                                                                                            } ?>>07
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 8) {
                                                                                                echo 'selected';
                                                                                            } ?>>08
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 9) {
                                                                                                echo 'selected';
                                                                                            } ?>>09
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 10) {
                                                                                                echo 'selected';
                                                                                            } ?>>10
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 11) {
                                                                                                echo 'selected';
                                                                                            } ?>>11
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 12) {
                                                                                                echo 'selected';
                                                                                            } ?>>12
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 13) {
                                                                                                echo 'selected';
                                                                                            } ?>>13
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 14) {
                                                                                                echo 'selected';
                                                                                            } ?>>14
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 15) {
                                                                                                echo 'selected';
                                                                                            } ?>>15
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 16) {
                                                                                                echo 'selected';
                                                                                            } ?>>16
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 17) {
                                                                                                echo 'selected';
                                                                                            } ?>>17
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 18) {
                                                                                                echo 'selected';
                                                                                            } ?>>18
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 19) {
                                                                                                echo 'selected';
                                                                                            } ?>>19
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 20) {
                                                                                                echo 'selected';
                                                                                            } ?>>20
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 21) {
                                                                                                echo 'selected';
                                                                                            } ?>>21
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 22) {
                                                                                                echo 'selected';
                                                                                            } ?>>22
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 23) {
                                                                                                echo 'selected';
                                                                                            } ?>>23
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 24) {
                                                                                                echo 'selected';
                                                                                            } ?>>24
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 25) {
                                                                                                echo 'selected';
                                                                                            } ?>>25
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 26) {
                                                                                                echo 'selected';
                                                                                            } ?>>26
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 27) {
                                                                                                echo 'selected';
                                                                                            } ?>>27
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 28) {
                                                                                                echo 'selected';
                                                                                            } ?>>28
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 29) {
                                                                                                echo 'selected';
                                                                                            } ?>>29
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 30) {
                                                                                                echo 'selected';
                                                                                            } ?>>30
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[2] == 31) {
                                                                                                echo 'selected';
                                                                                            } ?>>31
                                                                                            </option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-xl-3">
                                                                                    <div
                                                                                            class="input-group-prepend">
                                                                                        <div
                                                                                                class="input-group-text">
                                                                                            ماه:
                                                                                        </div>
                                                                                        <select
                                                                                                class="form-control select2"
                                                                                                name="mtts" required>
                                                                                            <option
                                                                                                    label="ماه تولد ">
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 1) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="1">
                                                                                                فروردین
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 2) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="2">
                                                                                                اردیبهشت
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 3) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="3">
                                                                                                خرداد
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 4) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="4">
                                                                                                تیر
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 5) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="5">
                                                                                                مرداد
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 6) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="6">
                                                                                                شهریور
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 7) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="7">
                                                                                                مهر
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 8) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="8">
                                                                                                آبان
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 9) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="9">
                                                                                                آذر
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 10) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="10">
                                                                                                دی
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 11) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="11">
                                                                                                بهمن
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[1] == 12) {
                                                                                                echo 'selected';
                                                                                            } ?>
                                                                                                    value="12">
                                                                                                اسفند
                                                                                            </option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class=" col-xl-3 col-lg-3">
                                                                                    <div
                                                                                            class="input-group-prepend">
                                                                                        <div
                                                                                                class="input-group-text">
                                                                                            سال:
                                                                                        </div>
                                                                                        <select
                                                                                                class="form-control select2"
                                                                                                name="ytts" required>
                                                                                            <option
                                                                                                    label="سال تولد ">
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1395) {
                                                                                                echo 'selected';
                                                                                            } ?>>1395
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1394) {
                                                                                                echo 'selected';
                                                                                            } ?>>1394
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1393) {
                                                                                                echo 'selected';
                                                                                            } ?>>1393
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1392) {
                                                                                                echo 'selected';
                                                                                            } ?>>1392
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1391) {
                                                                                                echo 'selected';
                                                                                            } ?>>1391
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1390) {
                                                                                                echo 'selected';
                                                                                            } ?>>1390
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1389) {
                                                                                                echo 'selected';
                                                                                            } ?>>1389
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1388) {
                                                                                                echo 'selected';
                                                                                            } ?>>1388
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1387) {
                                                                                                echo 'selected';
                                                                                            } ?>>1387
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1386) {
                                                                                                echo 'selected';
                                                                                            } ?>>1386
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1385) {
                                                                                                echo 'selected';
                                                                                            } ?>>1385
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1354) {
                                                                                                echo 'selected';
                                                                                            } ?>>1384
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1383) {
                                                                                                echo 'selected';
                                                                                            } ?>>1383
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1382) {
                                                                                                echo 'selected';
                                                                                            } ?>>1382
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1381) {
                                                                                                echo 'selected';
                                                                                            } ?>>1381
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1380) {
                                                                                                echo 'selected';
                                                                                            } ?>>1380
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1379) {
                                                                                                echo 'selected';
                                                                                            } ?>>1379
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1378) {
                                                                                                echo 'selected';
                                                                                            } ?>>1378
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1377) {
                                                                                                echo 'selected';
                                                                                            } ?>>1377
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1376) {
                                                                                                echo 'selected';
                                                                                            } ?>>1376
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1375) {
                                                                                                echo 'selected';
                                                                                            } ?>>1375
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1374) {
                                                                                                echo 'selected';
                                                                                            } ?>>1374
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1373) {
                                                                                                echo 'selected';
                                                                                            } ?>>1373
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1372) {
                                                                                                echo 'selected';
                                                                                            } ?>>1372
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1371) {
                                                                                                echo 'selected';
                                                                                            } ?>>1371
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1370) {
                                                                                                echo 'selected';
                                                                                            } ?>>1370
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1369) {
                                                                                                echo 'selected';
                                                                                            } ?>>1369
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1368) {
                                                                                                echo 'selected';
                                                                                            } ?>>1368
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1367) {
                                                                                                echo 'selected';
                                                                                            } ?>>1367
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1366) {
                                                                                                echo 'selected';
                                                                                            } ?>>1366
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1365) {
                                                                                                echo 'selected';
                                                                                            } ?>>1365
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1364) {
                                                                                                echo 'selected';
                                                                                            } ?>>1364
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1363) {
                                                                                                echo 'selected';
                                                                                            } ?>>1363
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1362) {
                                                                                                echo 'selected';
                                                                                            } ?>>1362
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1361) {
                                                                                                echo 'selected';
                                                                                            } ?>>1361
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1360) {
                                                                                                echo 'selected';
                                                                                            } ?>>1360
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1359) {
                                                                                                echo 'selected';
                                                                                            } ?>>1359
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1358) {
                                                                                                echo 'selected';
                                                                                            } ?>>1358
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1357) {
                                                                                                echo 'selected';
                                                                                            } ?>>1357
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1356) {
                                                                                                echo 'selected';
                                                                                            } ?>>1356
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1355) {
                                                                                                echo 'selected';
                                                                                            } ?>>1355
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1354) {
                                                                                                echo 'selected';
                                                                                            } ?>>1354
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1353) {
                                                                                                echo 'selected';
                                                                                            } ?>>1353
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1352) {
                                                                                                echo 'selected';
                                                                                            } ?>>1352
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1351) {
                                                                                                echo 'selected';
                                                                                            } ?>>1351
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1350) {
                                                                                                echo 'selected';
                                                                                            } ?>>1350
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1349) {
                                                                                                echo 'selected';
                                                                                            } ?>>1349
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1348) {
                                                                                                echo 'selected';
                                                                                            } ?>>1348
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1347) {
                                                                                                echo 'selected';
                                                                                            } ?>>1347
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1346) {
                                                                                                echo 'selected';
                                                                                            } ?>>1346
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1345) {
                                                                                                echo 'selected';
                                                                                            } ?>>1345
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1344) {
                                                                                                echo 'selected';
                                                                                            } ?>>1344
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1343) {
                                                                                                echo 'selected';
                                                                                            } ?>>1343
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1342) {
                                                                                                echo 'selected';
                                                                                            } ?>>1342
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1341) {
                                                                                                echo 'selected';
                                                                                            } ?>>1341
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1340) {
                                                                                                echo 'selected';
                                                                                            } ?>>1340
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1339) {
                                                                                                echo 'selected';
                                                                                            } ?>>1339
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1338) {
                                                                                                echo 'selected';
                                                                                            } ?>>1338
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1337) {
                                                                                                echo 'selected';
                                                                                            } ?>>1337
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1336) {
                                                                                                echo 'selected';
                                                                                            } ?>>1336
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1335) {
                                                                                                echo 'selected';
                                                                                            } ?>>1335
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1334) {
                                                                                                echo 'selected';
                                                                                            } ?>>1334
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1333) {
                                                                                                echo 'selected';
                                                                                            } ?>>1333
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1332) {
                                                                                                echo 'selected';
                                                                                            } ?>>1332
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1331) {
                                                                                                echo 'selected';
                                                                                            } ?>>1331
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1330) {
                                                                                                echo 'selected';
                                                                                            } ?>>1330
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1329) {
                                                                                                echo 'selected';
                                                                                            } ?>>1329
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1328) {
                                                                                                echo 'selected';
                                                                                            } ?>>1328
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1327) {
                                                                                                echo 'selected';
                                                                                            } ?>>1327
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1326) {
                                                                                                echo 'selected';
                                                                                            } ?>>1326
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1325) {
                                                                                                echo 'selected';
                                                                                            } ?>>1325
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1324) {
                                                                                                echo 'selected';
                                                                                            } ?>>1324
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1323) {
                                                                                                echo 'selected';
                                                                                            } ?>>1323
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1322) {
                                                                                                echo 'selected';
                                                                                            } ?>>1322
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1321) {
                                                                                                echo 'selected';
                                                                                            } ?>>1321
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1320) {
                                                                                                echo 'selected';
                                                                                            } ?>>1320
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1319) {
                                                                                                echo 'selected';
                                                                                            } ?>>1319
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1318) {
                                                                                                echo 'selected';
                                                                                            } ?>>1318
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1317) {
                                                                                                echo 'selected';
                                                                                            } ?>>1317
                                                                                            </option>
                                                                                            <option <?php if (isset($date) and $date[0] == 1316) {
                                                                                                echo 'selected';
                                                                                            } ?>>1316
                                                                                            </option>

                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="form-group ">
                                                                            <div class="row row-sm">
                                                                                <div class="col-xl-3">
                                                                                    <label
                                                                                            class="form-label">جنسیت<span
                                                                                                style="color: red">*</span></label>
                                                                                </div>
                                                                                <div class="col-xl-3">
                                                                                    <select
                                                                                            class="form-control select2"
                                                                                            name="gender" required>
                                                                                        <option label="جنسیت">
                                                                                        </option>
                                                                                        <option <?php if ($data->gender != NULL and $data->gender == 'خانم') {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            خانم
                                                                                        </option>
                                                                                        <option <?php if ($data->gender != NULL and $data->gender == 'آقا') {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            آقا
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="form-group ">
                                                                            <div class="row row-sm">
                                                                                <div class="col-xl-3">
                                                                                    <label
                                                                                            class="form-label">ملیت<span
                                                                                                class="text-danger">*</span></label>
                                                                                </div>
                                                                                <div class="col-xl-3">
                                                                                    <select
                                                                                            class="form-control select2"
                                                                                            name="melliat" required>
                                                                                        <option label="ملیت">
                                                                                        </option>
                                                                                        <option <?php if ($data->melliat != NULL and $data->melliat == 'ایرانی') {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            ایرانی
                                                                                        </option>
                                                                                        <option <?php if ($data->melliat != NULL and $data->melliat == 'اتباع') {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            اتباع
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
                                                                                           placeholder=" " name="tel1"
                                                                                           value="<?php echo htmlentities($data->tel1) ?>"
                                                                                           required>
                                                                                </div>
                                                                                <div class="col-xl-4 ">
                                                                                    <label class="custom-switch">
                                                                                        <input type="checkbox"
                                                                                               name="tel-trust"
                                                                                               class="custom-switch-input">
                                                                                        <span
                                                                                                class="custom-switch-indicator"></span>
                                                                                        <span class="">پذیرش شماره
                                                                                                            تکراری</span>
                                                                                    </label>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <?php
                                                                        $iran_area_codes = [
                                                                            '021' => 'تهران',
                                                                            '026' => 'البرز (کرج)',
                                                                            '025' => 'قم',
                                                                            '031' => 'اصفهان',
                                                                            '041' => 'آذربایجان شرقی (تبریز)',
                                                                            '044' => 'آذربایجان غربی (ارومیه)',
                                                                            '045' => 'اردبیل',
                                                                            '024' => 'زنجان',
                                                                            '028' => 'قزوین',
                                                                            '023' => 'سمنان',
                                                                            '011' => 'مازندران',
                                                                            '013' => 'گیلان (رشت)',
                                                                            '017' => 'گلستان (گرگان)',
                                                                            '051' => 'خراسان رضوی (مشهد)',
                                                                            '056' => 'خراسان جنوبی (بیرجند)',
                                                                            '058' => 'خراسان شمالی (بجنورد)',
                                                                            '034' => 'کرمان',
                                                                            '035' => 'یزد',
                                                                            '038' => 'چهارمحال و بختیاری (شهرکرد)',
                                                                            '061' => 'خوزستان (اهواز)',
                                                                            '066' => 'لرستان (خرم‌آباد)',
                                                                            '086' => 'مرکزی (اراک)',
                                                                            '081' => 'همدان',
                                                                            '083' => 'کرمانشاه',
                                                                            '087' => 'کردستان (سنندج)',
                                                                            '084' => 'ایلام',
                                                                            '071' => 'فارس (شیراز)',
                                                                            '077' => 'بوشهر',
                                                                            '076' => 'هرمزگان (بندرعباس)',
                                                                            '074' => 'کهگیلویه و بویراحمد (یاسوج)',
                                                                            '054' => 'سیستان و بلوچستان (زاهدان)',
                                                                        ];
                                                                        // Prefill from existing tel_home if present (e.g., "02832825352")
                                                                        $tel_home_existing = isset($data->tel_home) ? preg_replace('/\D/','', $data->tel_home) : '';
                                                                        $pref_area  = substr($tel_home_existing, 0, 3);
                                                                        $pref_local = substr($tel_home_existing, 3);
                                                                        ?>
                                                                        <div class="form-group">
                                                                            <div class="row row-sm align-items-center">
                                                                                <div class="col-xl-3">
                                                                                    <label class="form-label">تلفن منزل</label>
                                                                                </div>

                                                                                <div class="col-xl-9">
                                                                                    <div class="input-group" style="max-width:440px;">
                                                                                        <!-- پیش‌شماره -->
                                                                                        <span class="input-group-text p-0" style="overflow:hidden;">
                                                                                                          <select name="area_code" class="form-select form-select-sm" style="width:100px; border:0; box-shadow:none; font-size:12px; padding:.25rem .5rem;">
                                                                                                            <?php foreach ($iran_area_codes as $code => $label): ?>
                                                                                                                <option value="<?php echo $code; ?>" <?php echo ($code === $pref_area ? 'selected' : ''); ?>>
                                                                                                                <?php echo $code . ' — ' . $label; ?>
                                                                                                              </option>
                                                                                                            <?php endforeach; ?>
                                                                                                          </select>
                                                                                                        </span>

                                                                                        <!-- بخش بدون پیش‌شماره (با فیلتر فقط عدد) -->
                                                                                        <input
                                                                                                type="tel"
                                                                                                class="form-control"
                                                                                                placeholder="شماره بدون پیش‌شماره (مثال: 32828282)"
                                                                                                aria-label="شماره ثابت بدون پیش‌شماره"
                                                                                                name="landline_local_edit"
                                                                                                value="<?php echo htmlspecialchars($pref_local); ?>"
                                                                                                pattern="^\d{7,8}$"
                                                                                                inputmode="numeric"
                                                                                                maxlength="8"
                                                                                                oninput="this.value=this.value.replace(/\D/g,'')"
                                                                                                style="text-align:center; min-width:0; font-size:16px; line-height:1.4;"
                                                                                        >

                                                                                        <!-- مقدار نهایی برای ارسال به PHP (همان tel_home) -->
                                                                                        <input type="hidden" name="tel-house" id="tel_house_full" value="<?php echo htmlspecialchars($tel_home_existing); ?>">
                                                                                    </div>
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
                                                                            درباره خودتان
                                                                        </div>
                                                                        <div class="form-group ">
                                                                            <div class="row row-sm">
                                                                                <div class="col-xl-3">
                                                                                    <label
                                                                                            class="form-label">اطلاعاتی
                                                                                        در خصوص وضعیت جسمانی
                                                                                        ،
                                                                                        علایق
                                                                                        و ...</label>
                                                                                </div>
                                                                                <div class="col-xl-9">
                                                                                                    <textarea class="form-control"
                                                                                                              name="bio" rows="4"
                                                                                                              placeholder="" <?php if ($data->bio != NULL) {
                                                                                                        echo htmlentities($data->bio);
                                                                                                    } ?>></textarea>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-4 main-content-label">
                                                                            دست غالب
                                                                        </div>
                                                                        <div class="form-group mb-0">
                                                                            <div class="row row-sm">
                                                                                <div class="col-xl-3">
                                                                                    <label class="form-label">دستی
                                                                                        که با آن می
                                                                                        نویسید</label>
                                                                                </div>
                                                                                <div class="col-xl-4">
                                                                                    <div
                                                                                            class="custom-controls-stacked">
                                                                                        <label
                                                                                                class="bx-radio mg-b-10-f"><input
                                                                                                    type="radio"
                                                                                                    id="hand-left"
                                                                                                    name="hand[]"
                                                                                                    value="چپ دست" <?php if ($data->hand != NULL and $data->hand == 'چپ دست') {
                                                                                                echo 'checked ';
                                                                                            } ?>><span>چپ
                                                                                                                دست</span></label>
                                                                                        <label class=""><input
                                                                                                    type="radio"
                                                                                                    value="راست دست"
                                                                                                    name="hand[]" <?php if ($data->hand != NULL and $data->hand == 'راست دست') {
                                                                                                echo 'checked  ';
                                                                                            } ?>><span>راست
                                                                                                                دست</span></label>
                                                                                    </div>

                                                                                    <input type="hidden"
                                                                                           name="token"
                                                                                           value="<?php echo $hash ?>">
                                                                                </div>
                                                                                <div class="row">
                                                                                    <!-- <div class="col-xl-4 ">
                                                                                                        <label
                                                                                                            class="custom-switch">
                                                                                                            <input type="checkbox"
                                                                                                                name="vip"
                                                                                                                class="custom-switch-input"
                                                                                                                <? //php if ($data->vip == 1) {
                                                                                    // echo 'checked';
                                                                                    // }
                                                                                    ?>>
                                                                                                            <span
                                                                                                                class="custom-switch-indicator"></span>
                                                                                                            <span class=""> دانش
                                                                                                                آموز VIP </span>
                                                                                                        </label>
                                                                                                    </div> -->
                                                                                    <div class="form-group ">
                                                                                        <div class="row row-sm">
                                                                                            <div class="col-xl-3">
                                                                                                <label class="form-label">نام معرف
                                                                                                </label>
                                                                                            </div>
                                                                                            <div class="col-xl-6">
                                                                                                <input type="text"
                                                                                                       class="form-control"
                                                                                                       placeholder="نام "
                                                                                                       value="<?php echo htmlentities($data->moaref ?? '') ?>"
                                                                                                       name="moaref">
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="form-group ">
                                                                                        <div class="row row-sm">
                                                                                            <div class="col-xl-3">
                                                                                                <label
                                                                                                        class="form-label">نوع
                                                                                                    دانش آموز<span
                                                                                                            style="color: red">*</span></label>
                                                                                            </div>
                                                                                            <div class="col-xl-3">
                                                                                                <select
                                                                                                        class="form-control select2"
                                                                                                        name="type-vip[]"
                                                                                                        multiple
                                                                                                        required>
                                                                                                    <option
                                                                                                            label="نوع دانش آموز">
                                                                                                    </option>
                                                                                                    <?php
                                                                                                    $sql = "SELECT * FROM type_vip";
                                                                                                    $stmt = $conn->prepare($sql);
                                                                                                    $stmt->execute();
                                                                                                    $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                                                                    foreach ($vips as $vip) {
                                                                                                        $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN type_vip ON dbpersonal_type_vip.type_vip_id = type_vip.id WHERE dbpersonal_type_vip.dbpersonal_id = ? AND dbpersonal_type_vip.type_vip_id = ?";
                                                                                                        $stmt = $conn->prepare($sql);
                                                                                                        $stmt->execute([$data->idpersonal, $vip->id]);
                                                                                                        $userVip = $stmt->fetch(PDO::FETCH_OBJ);
                                                                                                        if ($stmt->rowCount() > 0) {
                                                                                                            ?>

                                                                                                            <option
                                                                                                                <?= $vip->id == $userVip->type_vip_id ? 'selected' : '' ?>
                                                                                                                    value="<?= $vip->id ?>">
                                                                                                                <?= $vip->name ?>
                                                                                                            </option>
                                                                                                        <?php } else { ?>
                                                                                                            <option
                                                                                                                    value="<?= $vip->id ?>">
                                                                                                                <?= $vip->name ?>
                                                                                                            </option>
                                                                                                        <?php }
                                                                                                    } ?>
                                                                                                </select>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="form-group ">
                                                                                        <div class="row row-sm">
                                                                                            <div class="col-xl-3">
                                                                                                <label
                                                                                                        class="form-label">شیفت
                                                                                                    دانش آموز<span
                                                                                                            style="color: red">*</span></label>
                                                                                            </div>
                                                                                            <div class="col-xl-3">
                                                                                                <select
                                                                                                        class="form-control select2"
                                                                                                        name="shift"
                                                                                                        required>
                                                                                                    <option
                                                                                                            label="نوع دانش آموز">
                                                                                                    </option>
                                                                                                    <option <?php if ($data->shift != NULL and $data->shift == 0) {
                                                                                                        echo 'selected';
                                                                                                    } ?>
                                                                                                            value="0">
                                                                                                        صبح
                                                                                                    </option>
                                                                                                    <option <?php if ($data->shift != NULL and $data->shift == 1) {
                                                                                                        echo 'selected';
                                                                                                    } ?>
                                                                                                            value="1">
                                                                                                        بعدازظهر
                                                                                                    </option>
                                                                                                    <option <?php if ($data->shift != NULL and $data->shift == 2) {
                                                                                                        echo 'selected';
                                                                                                    } ?>
                                                                                                            value="2">
                                                                                                        گردشی
                                                                                                    </option>
                                                                                            </div>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
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
                                                        <div class="main-content-body  tab-pane p-4 border-top-0"
                                                             id="Parent"> <!--  اطلاعات خانواده-->

                                                            <div class="card-body border">
                                                                <div class="mb-4 main-content-label">
                                                                    اطلاعات پدر
                                                                </div>
                                                                <form class="form-horizontal " method="post"
                                                                      data-parsley-validate="">

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">نام
                                                                                    پدر <span
                                                                                            class="text-danger">*</span></label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="نام "
                                                                                       value="<?php echo htmlentities($data->fathername) ?>"
                                                                                       name="name-pedar" required>
                                                                                <input type="hidden" name="token"
                                                                                       value="<?php echo $hash ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">نام
                                                                                    خانوادگی پدر<span
                                                                                            class="text-danger">*</span></label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control" required
                                                                                       placeholder=" نام خانوادگی پدر"
                                                                                       name="famili-pedar" value="<?php if ($data->famili_pedar != NULL) {
                                                                                    echo htmlentities($data->famili_pedar);
                                                                                } ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">کد
                                                                                    ملی پدر</label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       pattern="[0-9]{10}"
                                                                                       placeholder=" کد ملی پدر"
                                                                                       name="codemeli-pedar" value="<?php if ($data->codemeli_pedar != NULL) {
                                                                                    echo htmlentities($data->codemeli_pedar);
                                                                                } ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">شماره
                                                                                    شناسنامه پدر</label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       name="shenasname-pedar"
                                                                                       placeholder="شماره شناسنامه پدر "
                                                                                       value="<?php if ($data->shenasname_pedar != NULL) {
                                                                                           echo htmlentities($data->shenasname_pedar);
                                                                                       } ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3 ">
                                                                                <label class="form-label">تاریخ
                                                                                    تولد پدر</label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        روز:
                                                                                    </div>
                                                                                    <?php
                                                                                    if ($data->birthday_pedar != NULL) {
                                                                                        $date_pedar = $data->birthday_pedar;
                                                                                        $date_pedar = explode('-', $date_pedar);
                                                                                        $date_pedar = gregorian_to_jalali($date_pedar[0], $date_pedar[1], $date_pedar[2]);
                                                                                    }
                                                                                    ?>
                                                                                    <select
                                                                                            class="form-control select2"
                                                                                            name="dttp">
                                                                                        <option
                                                                                                label="روز تولد پدر">
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 1) {
                                                                                            echo 'selected';
                                                                                        } ?>>01
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 2) {
                                                                                            echo 'selected';
                                                                                        } ?>>02
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 3) {
                                                                                            echo 'selected';
                                                                                        } ?>>03
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 4) {
                                                                                            echo 'selected';
                                                                                        } ?>>04
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 5) {
                                                                                            echo 'selected';
                                                                                        } ?>>05
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 6) {
                                                                                            echo 'selected';
                                                                                        } ?>>06
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 7) {
                                                                                            echo 'selected';
                                                                                        } ?>>07
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 8) {
                                                                                            echo 'selected';
                                                                                        } ?>>08
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 9) {
                                                                                            echo 'selected';
                                                                                        } ?>>09
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 10) {
                                                                                            echo 'selected';
                                                                                        } ?>>10
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 11) {
                                                                                            echo 'selected';
                                                                                        } ?>>11
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 12) {
                                                                                            echo 'selected';
                                                                                        } ?>>12
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 13) {
                                                                                            echo 'selected';
                                                                                        } ?>>13
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 14) {
                                                                                            echo 'selected';
                                                                                        } ?>>14
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 15) {
                                                                                            echo 'selected';
                                                                                        } ?>>15
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 16) {
                                                                                            echo 'selected';
                                                                                        } ?>>16
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 17) {
                                                                                            echo 'selected';
                                                                                        } ?>>17
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 18) {
                                                                                            echo 'selected';
                                                                                        } ?>>18
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 19) {
                                                                                            echo 'selected';
                                                                                        } ?>>19
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 20) {
                                                                                            echo 'selected';
                                                                                        } ?>>20
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 21) {
                                                                                            echo 'selected';
                                                                                        } ?>>21
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 22) {
                                                                                            echo 'selected';
                                                                                        } ?>>22
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 23) {
                                                                                            echo 'selected';
                                                                                        } ?>>23
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 24) {
                                                                                            echo 'selected';
                                                                                        } ?>>24
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 25) {
                                                                                            echo 'selected';
                                                                                        } ?>>25
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 26) {
                                                                                            echo 'selected';
                                                                                        } ?>>26
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 27) {
                                                                                            echo 'selected';
                                                                                        } ?>>27
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 28) {
                                                                                            echo 'selected';
                                                                                        } ?>>28
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 29) {
                                                                                            echo 'selected';
                                                                                        } ?>>29
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 30) {
                                                                                            echo 'selected';
                                                                                        } ?>>30
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[2] == 31) {
                                                                                            echo 'selected';
                                                                                        } ?>>31
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        ماه:
                                                                                    </div>
                                                                                    <select
                                                                                            class="form-control select2"
                                                                                            name="mttp">
                                                                                        <option label="ماه تولد ">
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 1) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="1">
                                                                                            فروردین
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 2) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="2">
                                                                                            اردیبهشت
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 3) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="3">
                                                                                            خرداد
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 4) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="4">
                                                                                            تیر
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 5) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="5">
                                                                                            مرداد
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 6) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="6">
                                                                                            شهریور
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 7) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="7">
                                                                                            مهر
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 8) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="8">
                                                                                            آبان
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 9) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="9">
                                                                                            آذر
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 10) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="10">
                                                                                            دی
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 11) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="11">
                                                                                            بهمن
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[1] == 12) {
                                                                                            echo 'selected';
                                                                                        } ?>
                                                                                                value="12">
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
                                                                                    <select
                                                                                            class="form-control select2"
                                                                                            name="yttp">
                                                                                        <option label="سال تولد ">
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1393) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1393
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1392) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1392
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1391) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1391
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1390) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1390
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1389) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1389
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1388) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1388
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1387) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1387
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1386) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1386
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1385) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1385
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1354) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1384
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1383) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1383
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1382) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1382
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1381) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1381
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1380) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1380
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1379) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1379
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1378) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1378
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1377) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1377
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1376) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1376
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1375) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1375
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1374) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1374
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1373) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1373
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1372) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1372
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1371) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1371
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1370) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1370
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1369) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1369
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1368) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1368
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1367) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1367
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1366) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1366
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1365) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1365
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1364) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1364
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1363) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1363
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1362) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1362
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1361) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1361
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1360) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1360
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1359) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1359
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1358) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1358
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1357) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1357
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1356) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1356
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1355) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1355
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1354) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1354
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1353) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1353
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1352) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1352
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1351) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1351
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1350) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1350
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1349) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1349
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1348) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1348
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1347) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1347
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1346) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1346
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1345) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1345
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1344) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1344
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1343) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1343
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1342) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1342
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1341) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1341
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1340) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1340
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1339) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1339
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1338) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1338
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1337) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1337
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1336) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1336
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1335) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1335
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1334) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1334
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1333) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1333
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1332) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1332
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1331) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1331
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1330) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1330
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1329) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1329
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1328) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1328
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1327) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1327
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1326) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1326
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1325) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1325
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1324) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1324
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1323) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1323
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1322) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1322
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1321) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1321
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1320) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1320
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1319) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1319
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1318) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1318
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1317) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1317
                                                                                        </option>
                                                                                        <option <?php if (isset($date_pedar) and $date_pedar[0] == 1316) {
                                                                                            echo 'selected';
                                                                                        } ?>>
                                                                                            1316
                                                                                        </option>

                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label
                                                                                        class="form-label">شغل</label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="شغل پدر"
                                                                                       name="job-pedar" value="<?php if ($data->job_pedar != NULL) {
                                                                                    echo htmlentities($data->job_pedar);
                                                                                } ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">

                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">تحصیلات
                                                                                    پدر</label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <select
                                                                                        class="form-control select2 "
                                                                                        name="edu-pedar"
                                                                                        aria-describedby="basic-addon2">

                                                                                    <option></option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'بی سواد') {
                                                                                        echo 'selected';
                                                                                    } ?>>بی سواد
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'زیر دیپلم') {
                                                                                        echo 'selected';
                                                                                    } ?>>زیر
                                                                                        دیپلم
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'دیپلم') {
                                                                                        echo 'selected';
                                                                                    } ?>>
                                                                                        دیپلم
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'کاردانی') {
                                                                                        echo 'selected';
                                                                                    } ?>>
                                                                                        کاردانی
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'کارشناسی') {
                                                                                        echo 'selected';
                                                                                    } ?>>
                                                                                        کارشناسی
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'کارشناسی ارشد') {
                                                                                        echo 'selected';
                                                                                    } ?>>
                                                                                        کارشناسی ارشد
                                                                                    </option>
                                                                                    <option <?php if ($data->education_pedar != NULL and $data->education_pedar == 'دکتری') {
                                                                                        echo 'selected';
                                                                                    } ?>>
                                                                                        دکتری
                                                                                    </option>

                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">وضعیت
                                                                                    حیات پدر</label>
                                                                            </div>
                                                                            <div class="col-xl-5">
                                                                                <div
                                                                                        class="custom-controls-stacked">
                                                                                    <label
                                                                                            class="bx-radio mg-b-10-f">
                                                                                        <input type="radio"
                                                                                               name="hayat_p[]"
                                                                                               value="در قید حیات"
                                                                                            <?php if ($data->hayat_pedar != NULL and $data->hayat_pedar == 'در قید حیات') {
                                                                                                echo 'checked';
                                                                                            } ?>><span>در
                                                                                                            قید
                                                                                                            حیات</span></label>
                                                                                    <label class=""><input
                                                                                                type="radio"
                                                                                                name="hayat_p[]"
                                                                                                value="فوت شده" <?php if ($data->hayat_pedar != NULL and $data->hayat_pedar == 'فوت شده') {
                                                                                            echo 'checked';
                                                                                        } ?>><span>فوت
                                                                                                            شده</span></label>
                                                                                    <label class=""><input
                                                                                                type="radio"
                                                                                                name="hayat_p[]"
                                                                                                value="شهید شده" <?php if ($data->hayat_pedar != NULL and $data->hayat_pedar == 'شهید شده') {
                                                                                            echo 'checked';
                                                                                        } ?>><span>شهید
                                                                                                            شده</span></label>
                                                                                    <label class=""><input
                                                                                                type="radio"
                                                                                                name="hayat_p[]"
                                                                                                value="سایر" <?php if ($data->hayat_pedar != NULL and $data->hayat_pedar == 'سایر') {
                                                                                            echo 'checked';
                                                                                        } ?>><span>سایر</span></label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">شماره
                                                                                    همراه پدر<span
                                                                                            class="text-danger">*</span></label>
                                                                            </div>
                                                                            <div class="col-xl-3">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="شماره همراه پدر "
                                                                                       name="tel3"
                                                                                       pattern="^(?:9)?(0\d{10})$"
                                                                                       required value="<?php if ($data->tel3 != NULL) {
                                                                                    echo htmlentities($data->tel3);
                                                                                } ?>">
                                                                                <span class="text-muted"> ممکن
                                                                                                    است پشتیبان بخواهد با پدر
                                                                                                    ارتباط برقرار نماید</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>


                                                                    <div class="mb-4 main-content-label">
                                                                        اطلاعات مادر
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">نام
                                                                                            مادر </label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               placeholder="نام مادر "
                                                                                               name="name-madar"
                                                                                               value="<?php if ($data->name_madar != NULL) {
                                                                                                   echo htmlentities($data->name_madar);
                                                                                               } ?>">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">نام
                                                                                            خانوادگی
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               name="famili-madar"
                                                                                               placeholder=" نام خانوادگی مادر"
                                                                                               value="<?php if ($data->famili_madar != NULL) {
                                                                                                   echo htmlentities($data->famili_madar);
                                                                                               } ?>">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label class="form-label">کد
                                                                                            ملی
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               placeholder=" کد ملی مادر"
                                                                                               name="codemeli-madar"
                                                                                               pattern="[0-9]{10}"
                                                                                               value="<?php if ($data->codemeli_madar != NULL) {
                                                                                                   echo htmlentities($data->codemeli_madar);
                                                                                               } ?>">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">شماره
                                                                                            شناسنامه
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               placeholder="شماره شناسنامه مادر "
                                                                                               name="shenasname-madar"
                                                                                               value="<?php if ($data->shenasname_madar != NULL) {
                                                                                                   echo htmlentities($data->shenasname_madar);
                                                                                               } ?>">
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3 ">
                                                                                        <label
                                                                                                class="form-label">تاریخ
                                                                                            تولد
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <div
                                                                                                class="input-group-prepend">
                                                                                            <div
                                                                                                    class="input-group-text">
                                                                                                روز:
                                                                                            </div>
                                                                                            <?php
                                                                                            if ($data->birthdate_madar != NULL) {
                                                                                                $date_madar = $data->birthdate_madar;
                                                                                                $date_madar = explode('-', $date_madar);
                                                                                                $date_madar = gregorian_to_jalali($date_madar[0], $date_madar[1], $date_madar[2]);
                                                                                            }
                                                                                            ?>
                                                                                            <select
                                                                                                    class="form-control select2"
                                                                                                    name="dttm">
                                                                                                <option
                                                                                                        label="تاریخ تولد مادر">
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 1) {
                                                                                                    echo 'selected';
                                                                                                } ?>>01
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 2) {
                                                                                                    echo 'selected';
                                                                                                } ?>>02
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 3) {
                                                                                                    echo 'selected';
                                                                                                } ?>>03
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 4) {
                                                                                                    echo 'selected';
                                                                                                } ?>>04
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 5) {
                                                                                                    echo 'selected';
                                                                                                } ?>>05
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 6) {
                                                                                                    echo 'selected';
                                                                                                } ?>>06
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 7) {
                                                                                                    echo 'selected';
                                                                                                } ?>>07
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 8) {
                                                                                                    echo 'selected';
                                                                                                } ?>>08
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 9) {
                                                                                                    echo 'selected';
                                                                                                } ?>>09
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 10) {
                                                                                                    echo 'selected';
                                                                                                } ?>>10
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 11) {
                                                                                                    echo 'selected';
                                                                                                } ?>>11
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 12) {
                                                                                                    echo 'selected';
                                                                                                } ?>>12
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 13) {
                                                                                                    echo 'selected';
                                                                                                } ?>>13
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 14) {
                                                                                                    echo 'selected';
                                                                                                } ?>>14
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 15) {
                                                                                                    echo 'selected';
                                                                                                } ?>>15
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 16) {
                                                                                                    echo 'selected';
                                                                                                } ?>>16
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 17) {
                                                                                                    echo 'selected';
                                                                                                } ?>>17
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 18) {
                                                                                                    echo 'selected';
                                                                                                } ?>>18
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 19) {
                                                                                                    echo 'selected';
                                                                                                } ?>>19
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 20) {
                                                                                                    echo 'selected';
                                                                                                } ?>>20
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 21) {
                                                                                                    echo 'selected';
                                                                                                } ?>>21
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 22) {
                                                                                                    echo 'selected';
                                                                                                } ?>>22
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 23) {
                                                                                                    echo 'selected';
                                                                                                } ?>>23
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 24) {
                                                                                                    echo 'selected';
                                                                                                } ?>>24
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 25) {
                                                                                                    echo 'selected';
                                                                                                } ?>>25
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 26) {
                                                                                                    echo 'selected';
                                                                                                } ?>>26
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 27) {
                                                                                                    echo 'selected';
                                                                                                } ?>>27
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 28) {
                                                                                                    echo 'selected';
                                                                                                } ?>>28
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 29) {
                                                                                                    echo 'selected';
                                                                                                } ?>>29
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 30) {
                                                                                                    echo 'selected';
                                                                                                } ?>>30
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[2] == 31) {
                                                                                                    echo 'selected';
                                                                                                } ?>>31
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <div
                                                                                                class="input-group-prepend">
                                                                                            <div
                                                                                                    class="input-group-text">
                                                                                                ماه:
                                                                                            </div>
                                                                                            <select
                                                                                                    class="form-control select2"
                                                                                                    name="mttm">
                                                                                                <option
                                                                                                        label="ماه تولد ">
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 1) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="1">
                                                                                                    فروردین
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 2) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="2">
                                                                                                    اردیبهشت
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 3) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="3">
                                                                                                    خرداد
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 4) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="4">
                                                                                                    تیر
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 5) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="5">
                                                                                                    مرداد
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 6) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="6">
                                                                                                    شهریور
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 7) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="7">
                                                                                                    مهر
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 8) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="8">
                                                                                                    آبان
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 9) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="9">
                                                                                                    آذر
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 10) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="10">
                                                                                                    دی
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 11) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="11">
                                                                                                    بهمن
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[1] == 12) {
                                                                                                    echo 'selected';
                                                                                                } ?>
                                                                                                        value="12">
                                                                                                    اسفند
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class=" col-xl-3 col-lg-3">
                                                                                        <div
                                                                                                class="input-group-prepend">
                                                                                            <div
                                                                                                    class="input-group-text">
                                                                                                سال:
                                                                                            </div>
                                                                                            <select
                                                                                                    class="form-control select2"
                                                                                                    name="yttm">
                                                                                                <option
                                                                                                        label="سال تولد ">
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1393) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1393
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1392) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1392
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1391) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1391
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1390) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1390
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1389) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1389
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1388) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1388
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1387) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1387
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1386) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1386
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1385) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1385
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1354) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1384
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1383) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1383
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1382) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1382
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1381) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1381
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1380) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1380
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1379) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1379
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1378) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1378
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1377) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1377
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1376) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1376
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1375) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1375
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1374) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1374
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1373) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1373
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1372) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1372
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1371) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1371
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1370) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1370
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1369) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1369
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1368) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1368
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1367) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1367
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1366) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1366
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1365) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1365
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1364) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1364
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1363) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1363
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1362) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1362
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1361) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1361
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1360) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1360
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1359) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1359
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1358) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1358
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1357) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1357
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1356) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1356
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1355) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1355
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1354) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1354
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1353) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1353
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1352) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1352
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1351) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1351
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1350) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1350
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1349) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1349
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1348) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1348
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1347) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1347
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1346) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1346
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1345) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1345
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1344) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1344
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1343) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1343
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1342) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1342
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1341) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1341
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1340) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1340
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1339) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1339
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1338) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1338
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1337) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1337
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1336) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1336
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1335) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1335
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1334) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1334
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1333) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1333
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1332) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1332
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1331) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1331
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1330) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1330
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1329) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1329
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1328) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1328
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1327) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1327
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1326) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1326
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1325) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1325
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1324) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1324
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1323) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1323
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1322) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1322
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1321) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1321
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1320) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1320
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1319) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1319
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1318) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1318
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1317) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1317
                                                                                                </option>
                                                                                                <option <?php if (isset($date_madar) and $date_madar[0] == 1316) {
                                                                                                    echo 'selected';
                                                                                                } ?>>
                                                                                                    1316
                                                                                                </option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">شغل</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               placeholder="شغل مادر"
                                                                                               name="job-madar" value="<?php if ($data->job_madar != NULL) {
                                                                                            echo htmlentities($data->job_madar);
                                                                                        } ?>">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">

                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">تحصیلات
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <select
                                                                                                class="form-control select2 "
                                                                                                name="edu-madar"
                                                                                                aria-describedby="basic-addon2"
                                                                                        >

                                                                                            <option></option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'بی سواد') {
                                                                                                echo 'selected';
                                                                                            } ?>>بی
                                                                                                سواد
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'زیر دیپلم') {
                                                                                                echo 'selected';
                                                                                            } ?>>زیر
                                                                                                دیپلم
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'دیپلم') {
                                                                                                echo 'selected';
                                                                                            } ?>>دیپلم
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'کاردانی') {
                                                                                                echo 'selected';
                                                                                            } ?>>
                                                                                                کاردانی
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'کارشناسی') {
                                                                                                echo 'selected';
                                                                                            } ?>>
                                                                                                کارشناسی
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'کارشناسی ارشد') {
                                                                                                echo 'selected';
                                                                                            } ?>>
                                                                                                کارشناسی
                                                                                                ارشد
                                                                                            </option>
                                                                                            <option <?php if ($data->education_madar != NULL and $data->education_madar == 'دکتری') {
                                                                                                echo 'selected';
                                                                                            } ?>>دکتری
                                                                                            </option>

                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">وضعیت
                                                                                            حیات
                                                                                            مادر</label>
                                                                                    </div>
                                                                                    <div class="col-xl-5">
                                                                                        <div
                                                                                                class="custom-controls-stacked">
                                                                                            <label
                                                                                                    class="bx-radio mg-b-10-f">
                                                                                                <input type="radio"
                                                                                                       name="hayat_m[]"
                                                                                                       value="در قید حیات"
                                                                                                    <?php if ($data->hayat_madar != NULL and $data->hayat_madar == 'در قید حیات') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>در
                                                                                                                    قید
                                                                                                                    حیات</span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="hayat_m[]"
                                                                                                        value="فوت شده"
                                                                                                    <?php if ($data->hayat_madar != NULL and $data->hayat_madar == 'فوت شده') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>فوت
                                                                                                                    شده</span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="hayat_m[]"
                                                                                                        value="شهید شده"
                                                                                                    <?php if ($data->hayat_madar != NULL and $data->hayat_madar == 'شهید شده') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>شهید
                                                                                                                    شده</span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="hayat_m[]"
                                                                                                        value="سایر"
                                                                                                    <?php if ($data->hayat_madar != NULL and $data->hayat_madar == 'سایر') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>سایر</span></label>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">شماره
                                                                                            همراه
                                                                                            مادر<span
                                                                                                    class="text-danger">*</span></label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="text"
                                                                                               class="form-control"
                                                                                               name="tel2"
                                                                                               pattern="^(?:9)?(0\d{10})$"
                                                                                               required=""
                                                                                               placeholder="شماره همراه مادر "
                                                                                               value="<?php if ($data->tel2 != NULL) {
                                                                                                   echo htmlentities($data->tel2);
                                                                                               } ?>">
                                                                                        <span class="text-muted">
                                                                                                            ممکن است پشتیبان
                                                                                                            بخواهد با مادر
                                                                                                            ارتباط برقرار
                                                                                                            نماید</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="mb-4 main-content-label">
                                                                                درباره خانواده
                                                                            </div>

                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">وضعیت
                                                                                            زندگی</label>
                                                                                    </div>
                                                                                    <div class="col-xl-9">
                                                                                        <div
                                                                                                class="custom-controls-stacked">
                                                                                            <label
                                                                                                    class="bx-radio mg-b-10-f">
                                                                                                <input type="radio"
                                                                                                       name="life[]"
                                                                                                       value="زندگی با پدر و مادر"
                                                                                                    <?php if ($data->living_condition != NULL and $data->living_condition == 'زندگی با پدر و مادر') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>
                                                                                                                    زندگی با پدر
                                                                                                                    و مادر
                                                                                                                </span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="life[]"
                                                                                                        value="زندگی با پدر یا خانواده پدری"
                                                                                                    <?php if ($data->living_condition != NULL and $data->living_condition == 'زندگی با پدر یا خانواده پدری') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>
                                                                                                                    زندگی با پدر
                                                                                                                    یا خانواده
                                                                                                                    پدری
                                                                                                                </span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="life[]"
                                                                                                        value="زندگی با مادر یا خانواده مادری"
                                                                                                    <?php if ($data->living_condition != NULL and $data->living_condition == 'زندگی با مادر یا خانواده مادری') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>
                                                                                                                    زندگی با
                                                                                                                    مادر یا
                                                                                                                    خانواده
                                                                                                                    مادری
                                                                                                                </span></label>
                                                                                        </div>

                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">وضعیت
                                                                                            بیمه
                                                                                            درمان</label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <div
                                                                                                class="custom-controls-stacked">
                                                                                            <label
                                                                                                    class="bx-radio mg-b-10-f">
                                                                                                <input type="radio"
                                                                                                       name="bime[]"
                                                                                                       value="دارم"
                                                                                                    <?php if ($data->bime_status != NULL and $data->bime_status == 'دارم') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>
                                                                                                                    دارم
                                                                                                                </span></label>
                                                                                            <label class=""><input
                                                                                                        type="radio"
                                                                                                        name="bime[]"
                                                                                                        value="ندارم"
                                                                                                    <?php if ($data->bime_status != NULL and $data->bime_status == 'ندارم') {
                                                                                                        echo 'checked';
                                                                                                    } ?>><span>
                                                                                                                    ندارم
                                                                                                                </span></label>

                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="form-group ">
                                                                                <div class="row row-sm">
                                                                                    <div class="col-xl-3">
                                                                                        <label
                                                                                                class="form-label">تعداد
                                                                                            فرزند در
                                                                                            خانواده </label>
                                                                                    </div>
                                                                                    <div class="col-xl-3">
                                                                                        <input type="number" min="1"
                                                                                               max="20" name="baby"
                                                                                               class="form-control"
                                                                                               value="<?php if ($data->all_baby != NULL) {
                                                                                                   echo htmlentities($data->all_baby);
                                                                                               } ?>">


                                                                                        <div class="mt-3">
                                                                                            <button type="submit"
                                                                                                    name="parent-valid"
                                                                                                    class="btn btn-primary btn-block mr-1">
                                                                                                ذخیره
                                                                                                اطلاعات
                                                                                            </button>
                                                                                        </div>
                                                                                    </div>
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
                                                                <form class="form-horizontal " method="post"
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
                                                                                       placeholder="" name="state"
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
                                                                                <label class="form-label">شهر
                                                                                    محل سکونت <span
                                                                                            class="text-danger">*</span></label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="" name="city"
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
                                                                                                <textarea class="form-control"
                                                                                                          name="address" rows="4"
                                                                                                          placeholder=""><?php if ($data->address != NULL) {
                                                                                                        echo htmlentities($data->address);
                                                                                                    } ?></textarea>
                                                                                <span class="text-muted"> ممکن
                                                                                                    است حسب ضرورت ، برای شما ،
                                                                                                    بسته پستی ارسال شود</span>
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
                                                                                       placeholder="کدپستی" value="<?php if ($data->zipcode != NULL) {
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
                                                                                    <option label="نوع محل سکونت">
                                                                                    </option>
                                                                                    <option <?php if ($data->type_life != NULL) {
                                                                                        if ($data->type_life == 'آپارتمان') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        آپارتمان
                                                                                    </option>
                                                                                    <option <?php if ($data->type_life != NULL) {
                                                                                        if ($data->type_life == 'خانه ویلایی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>خانه
                                                                                        ویلایی
                                                                                    </option>
                                                                                    <option <?php if ($data->type_life != NULL) {
                                                                                        if ($data->type_life == 'کوچ نشین') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>کوچ
                                                                                        نشین
                                                                                    </option>
                                                                                    <option <?php if ($data->type_life != NULL) {
                                                                                        if ($data->type_life == 'باغ') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        باغ
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
                                                                                    <option label="وضعیت سکونت">
                                                                                    </option>
                                                                                    <option <?php if ($data->life_status != NULL) {
                                                                                        if ($data->life_status == 'شخصی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        شخصی
                                                                                    </option>
                                                                                    <option <?php if ($data->life_status != NULL) {
                                                                                        if ($data->life_status == 'استیجاری') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        استیجاری
                                                                                    </option>
                                                                                    <option <?php if ($data->life_status != NULL) {
                                                                                        if ($data->life_status == 'سازمانی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        سازمانی
                                                                                    </option>
                                                                                    <option <?php if ($data->life_status != NULL) {
                                                                                        if ($data->life_status == 'سایر') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        سایر
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
                                                                                    <option label="نوع بافت">
                                                                                    </option>
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
                                                                                    } ?>>
                                                                                        روستایی
                                                                                    </option>
                                                                                    <option <?php if ($data->life_baft != NULL) {
                                                                                        if ($data->life_baft == 'عشایر') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        عشایر
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
                                                                                       placeholder="مترمربع" value="<?php if ($data->life_metr != NULL) {
                                                                                    echo htmlentities($data->life_metr);
                                                                                } ?>" name="metr">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="mb-4 main-content-label">
                                                                        اطلاعات تحصیلی
                                                                    </div>
                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">نام
                                                                                    مدرسه <span
                                                                                            class="text-danger">*</span>
                                                                                </label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="نام مدرسه"
                                                                                       value="<?php echo htmlentities($data->nameschool) ?>"
                                                                                       name="school" required>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">نام
                                                                                    مدیر مدرسه </label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       placeholder="نام مدیر مدرسه"
                                                                                       value="<?php if ($data->modir_name != NULL) {
                                                                                           echo htmlentities($data->modir_name);
                                                                                       } ?>"
                                                                                       name="modir-name">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">نوع
                                                                                    مدرسه <span
                                                                                            class="text-danger">*</span>
                                                                                </label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <select class="form-control select2"
                                                                                        name="type-school" required>
                                                                                    <option label="نوع مدرسه">
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'دولتی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        دولتی
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'هیئت امنایی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>هیئت
                                                                                        امنایی
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'غیرانتفاعی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        غیرانتفاعی
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'نمونه دولتی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>نمونه
                                                                                        دولتی
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'تیزهوشان') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        تیزهوشان
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'شاهد') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        شاهد
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'آزاد') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        آزاد
                                                                                    </option>
                                                                                    <option <?php if ($data->typeschool != NULL) {
                                                                                        if ($data->typeschool == 'مدارس اقلیت های مذهبی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>مدارس
                                                                                        اقلیت های
                                                                                        مذهبی
                                                                                    </option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">منطقه
                                                                                    آموزش و پرورش <span
                                                                                            class="text-danger">*</span>
                                                                                </label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <select class="form-control select2"
                                                                                        name="mantaghe" required>
                                                                                    <option label="منطقه">
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '01') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        01
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '02') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        02
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '03') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        03
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '04') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        04
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '05') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        05
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '06') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        06
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '07') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        07
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '08') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        08
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '09') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        09
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '10') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        10
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '11') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        11
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '12') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        12
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '13') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        13
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '14') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        14
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '15') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        15
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '16') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        16
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '17') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        17
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '18') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        18
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '19') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        19
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '20') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        20
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '21') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        21
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '22') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        22
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '23') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        23
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '24') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        24
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '25') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        25
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '26') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        26
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '27') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        27
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '28') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        28
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '29') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        29
                                                                                    </option>
                                                                                    <option <?php if ($data->area != NULL) {
                                                                                        if ($data->area == '30') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        30
                                                                                    </option>

                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-group ">
                                                                        <div class="row row-sm">
                                                                            <div class="col-xl-3">
                                                                                <label class="form-label">پایه
                                                                                    تحصیلی <span
                                                                                            class="text-danger">*</span>
                                                                                </label>
                                                                            </div>
                                                                            <div class="col-xl-4">
                                                                                <select class="form-control select2"
                                                                                        name="paye" required>
                                                                                    <option label="پایه تحصیلی">
                                                                                    </option>
                                                                                    <option value="5" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '5') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        پنجم
                                                                                    </option>
                                                                                    <option value="6" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '6') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        ششم
                                                                                    </option>
                                                                                    <option value="7" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '7') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        هفتم
                                                                                    </option>
                                                                                    <option value="8" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '8') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        هشتم
                                                                                    </option>
                                                                                    <option value="9" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '9') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        نهم
                                                                                    </option>
                                                                                    <option value="10" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '10') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        دهم
                                                                                    </option>
                                                                                    <option value="11" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '11') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        یازدهم
                                                                                    </option>
                                                                                    <option value="12" <?php if ($data->paye != NULL) {
                                                                                        if ($data->paye == '12') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        دوازدهم
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
                                                                                <select class="form-control select2"
                                                                                        required="" name="reshte"
                                                                                        aria-describedby="basic-addon2">
                                                                                    <option <?php if ($data->reshtestudent != NULL) {
                                                                                        if ($data->reshtestudent == 'ندارد') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        ندارد
                                                                                    </option>
                                                                                    <option <?php if ($data->reshtestudent != NULL) {
                                                                                        if ($data->reshtestudent == 'ریاضی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        ریاضی
                                                                                    </option>
                                                                                    <option <?php if ($data->reshtestudent != NULL) {
                                                                                        if ($data->reshtestudent == 'تجربی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        تجربی
                                                                                    </option>
                                                                                    <option <?php if ($data->reshtestudent != NULL) {
                                                                                        if ($data->reshtestudent == 'انسانی') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        انسانی
                                                                                    </option>
                                                                                    <option <?php if ($data->reshtestudent != NULL) {
                                                                                        if ($data->reshtestudent == 'فنی و حرفه ای') {
                                                                                            echo 'selected';
                                                                                        }
                                                                                    } ?>>
                                                                                        فنی و حرفه ای
                                                                                    </option>
                                                                                </select>

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
                                                        <div class="main-content-body tab-pane p-4 border-top-0" id="picture">
                                                            <?php if (isset($_SESSION['upload_success'])): ?>
                                                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                                    <?php echo $_SESSION['upload_success']; ?>
                                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <?php unset($_SESSION['upload_success']); ?>
                                                            <?php endif; ?>

                                                            <?php if (isset($_SESSION['upload_error'])): ?>
                                                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                                    <?php echo $_SESSION['upload_error']; ?>
                                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <?php unset($_SESSION['upload_error']); ?>
                                                            <?php endif; ?>
                                                            <div class="card-body border">
                                                                <div class="col-xl-6">
                                                                    <form method="post" enctype="multipart/form-data" class="form-horizontal">
                                                                        <div class="input-group file-browser">
                                                                            <input type="text" class="form-control border-left-0 browse-file"
                                                                                   placeholder="عکس خود را بارگزاری نمایید" readonly
                                                                                   id="selected_filename">
                                                                            <label class="input-group-btn">
                                                                            <span class="btn btn-primary">
                                                                                انتخاب فایل عکس
                                                                                <input type="file" name="pic" style="display: none;"
                                                                                       accept="image/png, image/jpeg" required>
                                                                            </span>
                                                                            </label>
                                                                        </div>
                                                                        <div class="text-muted">
                                                                            تصویر عکس با فرمت های png،jpg با حداکثر سایز 500KB مجاز می باشد
                                                                        </div>
                                                                        <div class="mt-3 text-center">
                                                                            <button type="submit" name="picture-submit" class="btn btn-warning">
                                                                                بارگذاری عکس
                                                                            </button>
                                                                        </div>
                                                                        <input type="hidden" name="token" value="<?php echo $hash; ?>">
                                                                    </form>

                                                                    <!-- نمایش عکس فعلی (اختیاری) -->
                                                                    <div class="mt-3 text-center">
                                                                        <?php if (!empty($data->pic)): ?>
                                                                            <img src="<?php echo htmlspecialchars($data->pic); ?>"
                                                                                 style="max-width:150px;" class="img-thumbnail">
                                                                            <br>
                                                                            <small>عکس فعلی</small>
                                                                        <?php else: ?>
                                                                            <small>هیچ عکسی آپلود نشده است</small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="main-content-body tab-pane p-4 border-top-0"
                                                             id="basij">
                                                            <div class="card-body border ">

                                                            </div>
                                                        </div>
                                                        <div class="main-content-body tab-pane p-4 border-top-0"
                                                             id="wallet">
                                                            <div class="card-body border">
                                                                <form method="post" data-parsley-validate="">
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
                                                                                           name="sheba" value="<?php if ($data->sheba != NULL) {
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
                                                                                       name="name-sheba" value="<?php if ($data->name_sheba != NULL) {
                                                                                    echo htmlentities($data->name_sheba);
                                                                                } ?>" required="">
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
                                                                                       placeholder="" required
                                                                                       name="bank-sheba" value="<?php if ($data->bank_sheba != NULL) {
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
                                                                                       name="num-card" value="<?php if ($data->card_bank != NULL) {
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
                                                                                       name="name-card" value="<?php if ($data->name_card != NULL) {
                                                                                    echo htmlentities($data->name_card);
                                                                                } ?>" required>
                                                                                <input type="hidden" name="token"
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
            } ?>
        </div>
    </div>
</div>
<!-- End Row -->


<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->

<script>
    (function () {
        const area  = document.querySelector('select[name="area_code"]');
        const local = document.querySelector('input[name="landline_local_edit"]');
        const full  = document.getElementById('tel_house_full');

        function digits(s){ return (s||'').replace(/\D/g,''); }
        function update(){ full.value = (area?.value || '') + digits(local?.value || ''); }

        area?.addEventListener('change', update);
        local?.addEventListener('input', update);
        update(); // init
    })();
</script>

</body>

</html>