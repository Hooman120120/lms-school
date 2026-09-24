<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once '../../inc/bulk_upload.php';
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
    unset($_SESSION['add-student']);
    header('location: ' . url('admin/Student/add.php'));
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
                        $_SESSION['add-student'] = array(
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
                    $_SESSION['add-student'] = array(
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
if (isset($_POST['search-tel']) and isset($_SESSION['add-student'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['tel'] != '') {
            if (isset($_POST['tel-trust'])) {
                if (strlen($_POST['tel']) == 11) {
                    $_SESSION['add-student'] =
                        array(
                            "codemeli" => $_SESSION['add-student']['codemeli'],
                            "melliat" => $_SESSION['add-student']['melliat'],
                            "tel" => $_POST['tel']
                        );
                }
            } else {
                if (!checkTell($_POST['tel'])) {        // Check Tell
                    if (strlen($_POST['tel']) == 11) {
                        $_SESSION['add-student'] =
                            array(
                                "codemeli" => $_SESSION['add-student']['codemeli'],
                                "melliat" => $_SESSION['add-student']['melliat'],
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
if (isset($_POST['submit-student']) and isset($_SESSION['add-student'])) { 
    if (
        $_POST['fname-student'] != NULL and $_POST['lname-student'] != NULL and $_POST['parent-student'] != NULL and $_POST['tel-mother'] != NULL and $_POST['tel-father'] != NULL
        and $_POST['roz-student'] != NULL and $_POST['mah-student'] != NULL and $_POST['sal-student'] != NULL and $_POST['state-student'] != NULL and $_POST['city-student'] != NULL
        and $_POST['school'] != NULL and $_POST['typeschool'] != NULL and $_POST['paye-student'] != NULL and $_POST['reshte-student'] != NULL and $_POST['mantaghe'] != NULL and
        $_POST['gender-student'] != NULL and $_POST['type-vip'] != NULL and $_POST['shift'] != NULL
    ) {
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
                case "3":
                    $_POST['reshte-student'] = 'فنی و حرفه ای';
                    break;
            }
        }
        $moaref = !empty($_POST['moaref']) ? $_POST['moaref'] : '';
        $description = !empty($_POST['description']) ? $_POST['description'] : '';
        $fnameStudent = trim($_POST['fname-student']);
        $lnameStudent = trim($_POST['lname-student']);
        $parentStudent = trim($_POST['parent-student']);
        $school = trim($_POST['school']);
        $state_user = retstate($_POST['state-student']);

        $area  = trim($_POST['area_code'] ?? '');
        $local = trim($_POST['landline_local'] ?? '');
        $area_digits  = preg_replace('/\D/', '', $area);
        $local_digits = preg_replace('/\D/', '', $local);

        // Validate against your hard-coded list + 7–8 digits for local
        $errors = $errors ?? [];
        if (!isset($iran_area_codes[$area_digits])) {
            $errors[] = 'پیش‌شماره معتبر نیست.';
        }
        if (!preg_match('/^\d{7,8}$/', $local_digits)) {
            $errors[] = 'بخش شماره ثابت باید ۷ تا ۸ رقم باشد.';
        }
        $tel_home = ($area_digits !== '' && $local_digits !== '') ? $area_digits . $local_digits : null;
        if (!checkCodemeli($_SESSION['add-student']['codemeli'])) { 
            hf_control_require_seat($conn, hf_control_login_id($_SESSION['add-student']['codemeli']), 'student');
            $insert = 'INSERT INTO dbpersonal (codemeli,fname,lname,fathername,tel1,tel2,tel3,tel_home,melliat,birthdate,`state`,city,nameschool,typeschool,paye,reshtestudent,area,gender,shift,datecreated,timecreated,ipcreated,oscreated,creator,moaref,description ) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $insert_student = $conn->prepare($insert);
            $insert_student->execute([$_SESSION['add-student']['codemeli'], $fnameStudent, $lnameStudent, $parentStudent, $_SESSION['add-student']['tel'], $_POST['tel-mother'], $_POST['tel-father'],   $tel_home,  $_SESSION['add-student']['melliat'], $birthdate, $state_user, $_POST['city-student'], $school, $_POST['typeschool'], $_POST['paye-student'], $_POST['reshte-student'], $_POST['mantaghe'], $_POST['gender-student'], $_POST['shift'], $datenow, $timenow, $ip, $os, $_SESSION['admin']['user'], $moaref, $description]);
            $personalCurrentId = $conn->lastInsertId();
            $typeVips = $_POST['type-vip'];
            foreach ($typeVips as $typeVip) {
                $sql = "INSERT INTO dbpersonal_type_vip (dbpersonal_id,type_vip_id) VALUES (?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$personalCurrentId, $typeVip]);
            } 

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
            $query_user->execute([$_SESSION['add-student']['codemeli'], $pass, 1]);
            $oesSync = cross_panel_sync_oes_student(
                (string)$_SESSION['add-student']['codemeli'],
                $fnameStudent,
                $lnameStudent,
                $pass
            );
            if (empty($oesSync['ok'])) {
                $_SESSION['cross_panel_warning'] = (string)($oesSync['message'] ?? 'ساخت حساب دانش‌آموز در سامانه آزمون انجام نشد');
            }
            $text = 'کد ملی' . ';' . $pass;
            SendSms($_SESSION['add-student']['tel'], $text, 171697, $_SESSION['add-student']['codemeli']);
            unset($_SESSION['add-student']);
            $verify = 100;
        }
    }
}

$stmt = $conn->prepare("
    SELECT bu.id, bu.uploaded_at, bu.uploaded_by, bu.filename,bu.deleted_at,
           COUNT(dp.idpersonal) AS inserted
    FROM bulk_uploads bu
    LEFT JOIN dbpersonal dp ON dp.bulk_upload_id = bu.id
    GROUP BY bu.id
    ORDER BY bu.uploaded_at DESC
");
$stmt->execute();
$bulkUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Read session report into local variables (if exists)
$bulk_inserted = 0;
$bulk_updated  = 0;
$bulk_skipped  = 0;
$bulk_errors   = [];

if (!empty($_SESSION['bulk_signup_report'])) { // <- match the key
    $bulk_inserted = $_SESSION['bulk_signup_report']['inserted'] ?? 0;
    $bulk_updated  = $_SESSION['bulk_signup_report']['updated']  ?? 0;
    $bulk_skipped  = $_SESSION['bulk_signup_report']['skipped']  ?? 0;
    $bulk_errors   = $_SESSION['bulk_signup_report']['errors']   ?? [];
    unset($_SESSION['bulk_signup_report']);
}

if (isset($_GET['download'])) {
    $sql = "SELECT * FROM bulk_uploads WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['download']]);
    $bulk = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$bulk) {
        die("رکورد یافت نشد");
    }

    $file = $_SERVER['DOCUMENT_ROOT'] . $bulk->file_path;
    $path = realpath($file);

    if ($path === false || !file_exists($path)) {
        die("فایل یافت نشد یا مسیر اشتباه است: " . htmlspecialchars($file));
    }

    $mimeType = mime_content_type($path);

    if (!file_exists($path)) {
        die("فایل یافت نشد");
    }

    // هدرهای دانلود
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
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
    <title> <?php echo m('page.add_student') ?> | <?php echo __TITLE ?></title>
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
                                            <div class="alert alert-warning d-flex align-items-center mb-3"
                                                 role="alert">
                                                <i class="fe fe-alert-triangle fs-4 me-2"></i>
                                                <div>
                                                    <strong>توجه:</strong>
                                                    <?php echo m('msg.phone_notice') ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    <?php echo m('action.add_new_student') ?>
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="<?php echo m('tooltip.add_new_student') ?>"
                                                      aria-label="<?php echo m('aria.add_new_student_help') ?>">

                                                                <i class="fe fe-info fs-4"></i>
                                                            </span>

                                            </div>

                                        </div>
                                        <?php if (isset($verify) and $verify == 100) { ?>
                                            <div class="alert alert-success" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                <?php echo m('msg.student_saved_sms') ?>
                                                <hr>
                                                <p class="mb-0">نام کاربری : کد ملی ثبت شده
                                                </p>
                                                <p class="mb-0">رمز عبور : <?php echo $pass ?>
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
                                                کد ملی معتبر نیست

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
                                                    <?php if (!isset($_SESSION['add-student'])) { ?>
                                                        <form method="post" data-parsley-validate="">
                                                            <div class="row">
                                                                <div class="col-xl-6 mb-2">
                                                                    <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nid_label') ?> <span style="color: red">*</span></label>
                                                                    <input class="form-control" name="codemeli"
                                                                           type="text"
                                                                           placeholder="با اعداد انگلیسی وارد شود"
                                                                           required>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nationality') ?> <span style="color: red">*</span></label>
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
                                                        <?php
                                                        if (Permision('excel_permission')) {
                                                            ?>
                                                            <!-- Row -->

                                                            <div class="row row-sm">
                                                                <div class="col-lg-12">
                                                                    <div class="card custom-card">
                                                                        <div class="card-body">
                                                                            <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                                                                                <div class="card custom-card pt-0 pb-0" style="background-color: lightgreen">
                                                                                    <div class="card-header border-bottom-0 pb-1">

                                                                                        <div class="d-flex align-items-center mb-2 pt-1"
                                                                                             style="gap: 12px;">

                                                                                            <label class="main-content-label mb-0">
                                                                                                ثبت نام دسته جمعی
                                                                                            </label>

                                                                                            <span class="menu-info-trigger"
                                                                                                  data-tooltip="<?php echo m('tooltip.bulk_add_students') ?>"
                                                                                                  aria-label="<?php echo m('aria.bulk_add_students_help') ?>">

                                                                                                    <i class="fe fe-info fs-4"></i>
                                                                                                </span>

                                                                                        </div>

                                                                                        <p class="tx-12 mb-0 text-muted font-weight-bolder">
                                                                                            <?php echo m('action.bulk_add_students') ?>
                                                                                            با فایل اکسل
                                                                                        </p>

                                                                                    </div>
                                                                                    <div class="card-body">
                                                                                        <form method="POST" enctype="multipart/form-data">
                                                                                            <div class="form-group">
                                                                                                <label for="fileUpload">انتخاب فایل</label>
                                                                                                <input type="file" name="excel_file" accept=".xlsx,.xlsm" required>
                                                                                                <small class="form-text text-muted font-weight-bolder">(.xlsm)فرمت‌های مجاز: اکسل</small>
                                                                                            </div>
                                                                                            <button type="submit" name="upload" class="btn btn-primary">آپلود و ثبت نام</button>
                                                                                        </form>
                                                                                        <hr>
                                                                                        <p class="header-message">
                                                                                            طبق فرمت ذیل، فایل خود را آپلود نمایید<br>
                                                                                        </p>
                                                                                        <ol style="text-align: right; direction: rtl;">
                                                                                            <li>بعد از دانلود فایل، راست کلیک کنید و بر روی گزینه <strong>Properties</strong> کلیک نمایید.</li>
                                                                                            <li>در تب <strong>General</strong> پایین پنجره، بخش <strong>Unblock</strong> را تیک بزنید.</li>
                                                                                            <li>دکمه <strong>OK</strong> را کلیک کنید و پنجره را ببندید.</li>
                                                                                            <li>حال میتوانید از تنظیمات ماکروی فایل مربوطه استفاده کنید.</li>
                                                                                        </ol>
                                                                                        <p>
                                                                                            <a href="<?php echo url('assets/doc/group-add.xlsm') ?>" class="btn btn-success ripple"> دانلود فایل نمونه</a>
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <!-- REPORT CARD: only visible if session report exists -->
                                                                            <?php if ($bulk_inserted || $bulk_updated || $bulk_skipped || !empty($bulk_errors)): ?>
                                                                                <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                                                                                    <div class="card custom-card bg-light">
                                                                                        <div class="card-header border-bottom-0 pb-1">
                                                                                            <label class="main-content-label mb-2 pt-1">گزارش ثبت نام دسته جمعی</label>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <p>افزوده شده: <?= $bulk_inserted ?></p>
                                                                                            <p>به‌روزرسانی شده: <?= $bulk_updated ?></p>
                                                                                            <p>نادیده گرفته شده: <?= $bulk_skipped ?></p>

                                                                                            <?php if (!empty($bulk_errors)): ?>
                                                                                                <div class="mt-3">
                                                                                                    <strong>خطاها:</strong>
                                                                                                    <ul class="text-danger">
                                                                                                        <?php foreach ($bulk_errors as $err): ?>
                                                                                                            <li><?= $err ?></li>
                                                                                                        <?php endforeach; ?>
                                                                                                    </ul>
                                                                                                </div>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <table class="table table-striped">
                                                                                <thead>
                                                                                <tr>
                                                                                    <th>شناسه</th>
                                                                                    <th>زمان آپلود</th>
                                                                                    <th>آپلود شده توسط</th>
                                                                                    <th>فایل</th>
                                                                                    <th>موفق</th>
                                                                                    <th>اقدامات</th>
                                                                                </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                <?php foreach ($bulkUploads as $upload): ?>
                                                                                    <tr>
                                                                                        <td><?= $upload['id'] ?></td>
                                                                                        <td><?= !empty($upload['uploaded_at']) ? gregorianDateTimeToJalali($upload['uploaded_at']) : '-' ?></td>
                                                                                        <td><?= $upload['uploaded_by'] ?></td>
                                                                                        <td><?= $upload['filename'] ?></td>
                                                                                        <td><?= $upload['inserted'] ?></td>
                                                                                        <td class="d-flex flex-row gap-1">
                                                                                            <?php if ($upload['deleted_at']): ?>
                                                                                                <span class="badge bg-danger">حذف شده</span>
                                                                                            <?php else: ?>
                                                                                                <form method="POST" onsubmit="return confirmDelete(this);">
                                                                                                    <input type="hidden" name="delete_bulk_id" value="<?= $upload['id'] ?>">
                                                                                                    <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                                                                </form>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($upload['errors'])): ?>
                                                                                                <ul>
                                                                                                    <?php foreach ($upload['errors'] as $err): ?>
                                                                                                        <li><?= $err ?></li>
                                                                                                    <?php endforeach; ?>
                                                                                                </ul>
                                                                                            <?php endif; ?>
                                                                                            <a class="btn btn-success btn-sm text-white" href="?download=<?= $upload['id'] ?>">دانلود فایل</a>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- End Row -->
                                                            <?php
                                                        } ?>
                                                        <!--                                        end step 1-->
                                                    <?php } ?>

                                                    <!--step 2-->
                                                    <?php if (isset($_SESSION['add-student']) and !isset($_SESSION['add-student']['tel'])) { ?>

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

                                                                <input type="checkbox" name="tel-trust">
                                                                پذیرش شماره تکراری


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
                                                                    <i class="fe fe-arrow-left"></i> ثبت شماره تلفن و
                                                                    ادامه
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
                                                                            <div class="col-lg-3 mg-t-auto">
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
                                                                                    <input class="form-control"
                                                                                           name="codemeli"
                                                                                           type="text"
                                                                                           value="<?php echo $_SESSION['add-student']['codemeli']; ?>"
                                                                                           disabled>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 ">
                                                                                <label class="form-label"><?php echo m('form.student_mobile_label') ?> </label>
                                                                                <div class="input-group mb-3">

                                                                                    <input type="text"
                                                                                           style="text-align: center"
                                                                                           name="tel1"

                                                                                           aria-describedby="basic-addon2"
                                                                                           aria-label="<?php echo m('aria.student_mobile') ?>"
                                                                                           class="form-control"
                                                                                           value="<?php echo $_SESSION['add-student']['tel']; ?>"
                                                                                           disabled>

                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 ">
                                                                                <label class="form-label">شماره همراه
                                                                                    مادر: <span
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
                                                                                <label class="form-label">شماره همراه
                                                                                    پدر: <span
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
                                                                            ?>
                                                                            <!-- force a new row before landline (optional, if you need it on its own row) -->
                                                                            <div class="col-12 d-flex flex-column" style="align-items:center;">
                                                                                <label class="form-label" style="text-align:center; width:100%;">
                                                                                    تلفن ثابت (خانه): <span class="tx-danger">*</span>
                                                                                </label>

                                                                                <div class="input-group mb-3" style="max-width:440px; width:100%; margin-right:auto; margin-left:auto;">
                                                                                    <!-- پیش‌شماره (area code) -->
                                                                                    <div id="areaCodeWrap" style="position:relative;">
                                                                                        <select name="area_code"
                                                                                                class="form-select form-select-lg select2-area"
                                                                                                style="width:130px; height: 38px; font-size: small;">
                                                                                            <?php foreach ($iran_area_codes as $code => $label): ?>
                                                                                                <option value="<?php echo $code; ?>"><?php echo $code . ' — ' . $label; ?></option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
                                                                                    </div>

                                                                                    <!-- بقیه شماره بدون پیش‌شماره -->
                                                                                    <input
                                                                                            type="tel"
                                                                                            name="landline_local"
                                                                                            class="form-control"
                                                                                            placeholder="شماره بدون پیش‌شماره (مثال: 32828282)"
                                                                                            pattern="^\d{7,8}$"
                                                                                            aria-label="شماره ثابت بدون پیش‌شماره"
                                                                                            style="text-align:center; min-width:0; font-size:16px; line-height:1.4;">

                                                                                    <input type="hidden" name="landline_phone" id="landline_full">
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
                                                                            </div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

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
                                                                                        <option value="1">فروردین
                                                                                        </option>
                                                                                        <option value="2">اردیبهشت
                                                                                        </option>
                                                                                        <option value="3">خرداد</option>
                                                                                        <option value="4">تیر</option>
                                                                                        <option value="5">مرداد</option>
                                                                                        <option value="6">شهریور
                                                                                        </option>
                                                                                        <option value="7">مهر</option>
                                                                                        <option value="8">آبان</option>
                                                                                        <option value="9">آذر</option>
                                                                                        <option value="10">دی</option>
                                                                                        <option value="11">بهمن</option>
                                                                                        <option value="12">اسفند
                                                                                        </option>
                                                                                    </select>
                                                                                </div>


                                                                            </div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
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
                                                                                        <option>1395</option>
                                                                                        <option>1394</option>
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


                                                                            </div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                                                                            <div class="col-lg-3 mg-t-20 ">

                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        استان محل سکونت:<span
                                                                                                class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control  "
                                                                                            required=""
                                                                                            name="state-student"
                                                                                            id="state"
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
                                                                                    $('#state').on('change', function(e) {
                                                                                        // console.log('errors');
                                                                                        e.preventDefault();
                                                                                        var selectData = 'state=' + $(this).val();
                                                                                        //         console.log(selectData);

                                                                                        $.ajax({
                                                                                            type: 'post',
                                                                                            url: $(location).attr('href'),
                                                                                            data: selectData,
                                                                                            success: function(data) {
                                                                                                $('#city').html(data);

                                                                                            },
                                                                                            error: function(data) {
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
                                                                                    <input class="form-control"
                                                                                           name="school"
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
                                                                                    <select class="form-control select2"
                                                                                            required=""
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
                                                                                        <option>مدارس اقلیت های دینی
                                                                                        </option>
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
                                                                                    <select class="form-control select2"
                                                                                            required=""
                                                                                            name="paye-student"
                                                                                            aria-describedby="basic-addon2"
                                                                                            id="car"
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
                                                                                        منطقه آموزش و پرورش<span
                                                                                                class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control select2"
                                                                                            required=""
                                                                                            name="mantaghe"
                                                                                            aria-describedby="basic-addon2">
                                                                                        <option label="منطقه آموزش و پرورش ">
                                                                                        </option>
                                                                                        <option value="1">منطقه یک
                                                                                        </option>
                                                                                        <option value="2">منطقه دو
                                                                                        </option>
                                                                                        <option value="3">منطقه سه
                                                                                        </option>
                                                                                        <option value="4">منطقه چهار
                                                                                        </option>
                                                                                        <option value="5">منطقه پنج
                                                                                        </option>
                                                                                        <option value="6">منطقه شش
                                                                                        </option>
                                                                                        <option value="7">منطقه هفت
                                                                                        </option>
                                                                                        <option value="8">منطقه هشت
                                                                                        </option>
                                                                                        <option value="9">منطقه نه
                                                                                        </option>
                                                                                        <option value="10">منطقه ده
                                                                                        </option>
                                                                                        <option value="11">منطقه یازده
                                                                                        </option>
                                                                                        <option value="12">منطقه
                                                                                            دوازده
                                                                                        </option>
                                                                                        <option value="13">منطقه سیزده
                                                                                        </option>
                                                                                        <option value="14">منطقه
                                                                                            چهارده
                                                                                        </option>
                                                                                        <option value="15">منطقه
                                                                                            پانزده
                                                                                        </option>
                                                                                        <option value="16">منطقه
                                                                                            شانزده
                                                                                        </option>
                                                                                        <option value="17">منطقه هفده
                                                                                        </option>
                                                                                        <option value="18">منطقه هجده
                                                                                        </option>
                                                                                        <option value="19">منطقه نوزده
                                                                                        </option>
                                                                                        <option value="20">منطقه بیست
                                                                                        </option>
                                                                                        <option value="21">منطقه بیست و
                                                                                            یک
                                                                                        </option>
                                                                                        <option value="22">منطقه بیست و
                                                                                            دو
                                                                                        </option>
                                                                                        <option value="23">منطقه بیست و
                                                                                            سه
                                                                                        </option>
                                                                                        <option value="24">منطقه بیست و
                                                                                            چهار
                                                                                        </option>
                                                                                        <option value="25">منطقه بیست و
                                                                                            پنج
                                                                                        </option>
                                                                                        <option value="26">منطقه بیست و
                                                                                            شش
                                                                                        </option>
                                                                                        <option value="27">منطقه بیست و
                                                                                            هفت
                                                                                        </option>
                                                                                        <option value="28">منطقه بیست و
                                                                                            هشت
                                                                                        </option>
                                                                                        <option value="29">منطقه بیست و
                                                                                            نه
                                                                                        </option>
                                                                                        <option value="30">منطقه سی
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
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
                                                                            <div class="col-lg-4 mg-t-20 ">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        <?php echo m('label.student_type_colon') ?>
                                                                                        <span class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control select2" multiple
                                                                                            required=""
                                                                                            name="type-vip[]"
                                                                                            aria-describedby="basic-addon2">
                                                                                        <option label="<?php echo m('col.student_type') ?>">
                                                                                        </option>
                                                                                        <?php
                                                                                        $sql = "SELECT * FROM type_vip";
                                                                                        $stmt = $conn->prepare($sql);
                                                                                        $stmt->execute();
                                                                                        $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                                                        foreach ($vips as $vip) {
                                                                                            ?>
                                                                                            <option value="<?= $vip->id ?>"><?= $vip->name ?></option>
                                                                                        <?php } ?>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-20 ">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        توضیحات
                                                                                    </div>
                                                                                    <textarea class="form-control"
                                                                                              name="description"
                                                                                              placeholder="اختیاری"
                                                                                              type="text"
                                                                                              data-placement="bottom"
                                                                                              data-bs-toggle="tooltip"
                                                                                              title="فارسی بنویسید"></textarea>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-20 ">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        نام معرف:
                                                                                    </div>
                                                                                    <input class="form-control"
                                                                                           name="moaref"
                                                                                           placeholder="نام معرف را وارد کنید"
                                                                                           type="text"
                                                                                           data-placement="bottom"
                                                                                           data-bs-toggle="tooltip"
                                                                                           title="فارسی بنویسید">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-lg-4 mg-t-20 ">
                                                                                <div class="input-group-prepend">
                                                                                    <div class="input-group-text">
                                                                                        <?php echo m('label.student_shift_colon') ?>
                                                                                        <span class="tx-danger">*</span>
                                                                                    </div>
                                                                                    <select class="form-control select2"
                                                                                            required=""
                                                                                            name="shift"
                                                                                            aria-describedby="basic-addon2">
                                                                                        <option label="<?php echo m('col.student_type') ?>">
                                                                                        </option>
                                                                                        <option value="0">صبح</option>
                                                                                        <option value="1">بعدازظهر</option>
                                                                                        <option value="2">گردشی</option>
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
