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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!Permision('permision_reportstudent')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $factorId = queryFactor($_GET['call']);
    if ($factorId->useradder != NULL) {
        $user = $factorId->useradder;
        $semat = 'همکار آموزشی';
    } else {
        $user = $factorId->username;
        $semat = 'دانش آموز';
    }
    $qau = retPerson($user);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: students.php');
    die();
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_GET['call-student']) and $_GET['call-student'] != '') {
    $semat = 'دانش آموز';
    $qau = retPerson($_GET['call-student']);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: students.php');
    die();
}
if (isset($_POST['search'])) {

    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case 'one':
            $swr = 'SELECT * FROM dbcourse WHERE username =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive1 = $wer->fetchAll(PDO::FETCH_OBJ);
            $swr2 = 'SELECT * FROM dbcourse_demo WHERE username = ?';
            $wer2 = $conn->prepare($swr2);
            $wer2->execute([$txt]);
            $recive2 = $wer2->fetchAll(PDO::FETCH_OBJ);
            $recive = array_merge($recive1, $recive2);
            break;
        case 'two':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbcourse INNER JOIN dbpersonal ON dbcourse.username=dbpersonal.codemeli WHERE dbpersonal.fname LIKE :name ORDER BY idcourse DESC';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive1 = $wer->fetchAll(PDO::FETCH_OBJ);
            $swr2 = 'SELECT * FROM dbcourse_demo INNER JOIN dbpersonal ON dbcourse_demo.username=dbpersonal.codemeli WHERE dbpersonal.fname LIKE :name ORDER BY id DESC';
            $wer2 = $conn->prepare($swr2);
            $wer2->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer2->execute();
            $recive2 = $wer2->fetchAll(PDO::FETCH_OBJ);
            $recive = array_merge($recive1, $recive2);
            break;
        case 'three':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbcourse INNER JOIN dbpersonal ON dbcourse.username=dbpersonal.codemeli WHERE dbpersonal.lname LIKE :name ORDER BY idcourse DESC';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive1 = $wer->fetchAll(PDO::FETCH_OBJ);
            $swr2 = 'SELECT * FROM dbcourse_demo INNER JOIN dbpersonal ON dbcourse_demo.username=dbpersonal.codemeli WHERE dbpersonal.lname LIKE :name ORDER BY id DESC';
            $wer2 = $conn->prepare($swr2);
            $wer2->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer2->execute();
            $recive2 = $wer2->fetchAll(PDO::FETCH_OBJ);
            $recive = array_merge($recive1, $recive2);
            break;
        default:
            $sql = "SELECT * FROM dbcourse INNER JOIN (SELECT * FROM dbpersonal INNER JOIN dbpersonal_type_vip ON dbpersonal_type_vip.dbpersonal_id = dbpersonal.idpersonal WHERE dbpersonal_type_vip.type_vip_id = ?) AS vip ON dbcourse.username = vip.codemeli WHERE vip.shift = ?";
            $wer = $conn->prepare($sql);
            $wer->execute([$category, $txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
    }
}
if (isset($_GET['call-student'])) {
    $ret = retPerson($_GET['call-student']);
    $tel = $ret->tel1;
    $name = ' دانش آموز ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: students.php');
    die();
}
if (isset($_GET['call-mother'])) {
    $ret = retPerson($_GET['call-mother']);
    $tel = $ret->tel2;
    $name = '  مادر دانش آموز   ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: students.php');
    die();
}
if (isset($_GET['call-father'])) {
    $ret = retPerson($_GET['call-father']);
    $tel = $ret->tel3;
    $name = ' پدر دانش آموز  ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: students.php');
    die();
}
if (isset($_GET['disable'])) {
    $disable = intval($_GET['disable']);
    $update = 'UPDATE dbcourse SET activityuser=? WHERE idcourse=?';
    $qu = $conn->prepare($update);
    $qu->execute([0, $disable]);
    $_SESSION['massege'] = 'دسترسی به پایه برای دانش آموز غیرفعال شد';
    header('location: students.php');
    die();
}
if (isset($_GET['enable'])) {
    $disable = intval($_GET['enable']);

    $check = 'SELECT * FROM dbcourse WHERE idcourse=?';
    $ch = $conn->prepare($check);
    $ch->execute([$disable]);
    $result = $ch->fetch();
    $act = queryFactor($result['numfactor']);
    if ($act->status == 'موفق') {
        $update = 'UPDATE dbcourse SET activityuser=? WHERE idcourse=?';
        $qu = $conn->prepare($update);
        $qu->execute([1, $disable]);
        $_SESSION['massege'] = 'دسترسی به پایه برای دانش آموز فعال شد';
        header('location: students.php');
        die();
    } else {
        $_SESSION['massege'] = 'فاکتور هزینه دانش آموز برای این پایه هنوز پرداخت / تایید نشده است';
        header('location: students.php');
        die();
    }
}
if (isset($_POST['typereport']) && isset($_POST['startdate']) && isset($_POST['enddate'])) {
    // دریافت گزینه‌های اکسل
    $excel_options = [];
    if (isset($_POST['excel_options']) && !empty($_POST['excel_options'])) {
        $excel_options = json_decode($_POST['excel_options'], true);
    }
    $_SESSION['excelCoursereport'] = array(
        "type" => $_POST['typereport'],
        "codecourse" => $_POST['courses'],
        "status" => $_POST['status'],
        "start" => $_POST['startdate'],
        "end" => $_POST['enddate'],
        "excel_options" => $excel_options,
        "active_events" => $_POST['active_events'] ?? [],
        "school_years"  => $_POST['school_years'] ?? []
    );

    header('location:../Bill/execel.php');
    exit;
}
date_default_timezone_set("Iran");
$dateno = date("Y-m-d");
$dateno = explode('-', $dateno);
$dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
$dateno = implode("/", $dateno);
// Fetch active events (event_type = 1, target_type in ('user','both'))
$activeEventsSql = "SELECT id, title FROM calendar_events WHERE event_type = 1 ORDER BY id ASC";
$activeEventsStmt = $conn->prepare($activeEventsSql);
$activeEventsStmt->execute();
$activeEventsList = $activeEventsStmt->fetchAll(PDO::FETCH_OBJ);

// Calculate current Jalali year to list school years from 1400 up to current year
$currentJalaliYear = (int)jdate('Y', time(), '', '', 'en');
$currentJalaliMonth = (int)jdate('m', time(), '', '', 'en');
$endJalaliYear = ($currentJalaliMonth < 4) ? ($currentJalaliYear - 1) : $currentJalaliYear;

$schoolYearsList = [];
for ($y = 1400; $y <= $endJalaliYear; $y++) {
    $nextY = $y + 1;
    $approxGregorianDate = ($y + 621) . '-06-22';

    // Call your existing function directly
    list($startDate, $endDate) = getJalaliYearWindowApprox($approxGregorianDate);

    $schoolYearsList[] = [
        'label'      => "{$y}-{$nextY}",
        'start_date' => $startDate,
        'end_date'   => $endDate
    ];
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
    <title> گزارش آماری دانش آموزان | <?php echo __TITLE ?></title>
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
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                    $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin WHERE role=? ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div class="d-flex align-items-center" style="gap:12px;">

                                        <label class="main-content-label mb-3 pt-1">
                                            همه دانش آموزان
                                        </label>
                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید گزارش اکسل تمامی دانش آموزان را دریافت کنید. فایل خروجی شامل اطلاعات ثبت‌شده دانش آموزان بوده و برای تهیه گزارش، بایگانی اطلاعات و انجام تحلیل‌های مدیریتی قابل استفاده است."
                                              aria-label="راهنمای گزارش همه دانش آموزان">
                                            <i class="fe fe-info fs-4"></i>
                                        </span>
                                    </div>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left">
                                        <button class="btn btn-sm btn-primary export-btn" data-report-type="all">دریافت
                                            لیست
                                        </button>
                                    </p>
                                    <p class="mb-0 mt-4 text-muted">لیست همه دانش آموزان</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                    $co_student = ' SELECT COUNT(iduser) as countstudent FROM dblogin LEFT JOIN dbcourse ON dblogin.username=dbcourse.username RIGHT JOIN dbpersonal ON dbpersonal.codemeli=dblogin.username WHERE dblogin.role=? AND dbcourse.username IS NULL  ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <label class="main-content-label mb-3 pt-1">بدون پایه</label>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که تاکنون در هیچ پایه‌ای ثبت‌نام نکرده‌اند. با انتخاب گزینه «دریافت لیست»، گزارش این دانش آموزان به‌صورت فایل اکسل (Excel) تولید و دانلود می‌شود تا برای بررسی، پیگیری یا تحلیل از آن استفاده کنید."
                                              aria-label="راهنمای گزارش دانش آموزان بدون پایه"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                    </div>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left">
                                        <button class="btn btn-sm btn-primary export-btn" data-report-type="uncourse">
                                            دریافت لیست
                                        </button>
                                    </p>
                                    <p class="mb-0 mt-4 text-muted">تا کنون هیچ پایه ای ثبت نام نکرده</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                    $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username  INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE dblogin.role=? AND dbcourse.activityuser=? AND codtbl_course.courseactive=?; ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1, 0, 1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);;
                                    ?>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <label class="main-content-label mb-3 pt-1">منتظر پرداخت فاکتور</label>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش دانش آموزانی که دارای ثبت‌نام ناقص هستند نشان داده میشود. دانش آموزانی که پایه را انتخاب کرده‌اند و منتظر تکمیل تراکنش مالی هستند.این افراد تا زمان نهایی شدن پرداخت، امکان ورود به کلاس را ندارند. با انتخاب گزینه «دریافت لیست»، گزارش این دانش آموزان به‌صورت فایل اکسل (Excel) دریافت می‌شود."
                                              aria-label="راهنمای گزارش منتظر پرداخت فاکتور"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                    </div>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left">
                                        <button class="btn btn-sm btn-primary export-btn" data-report-type="away">دریافت
                                            لیست
                                        </button>
                                    </p>
                                    <p class="mb-0 mt-4 text-muted">تعداد نفر منتظر ورود به کلاس</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <?php if (isset($_SESSION['call'])) { ?>
                        <div class="alert alert-outline-success  text-center " role="alert">
                            <div class="mg-t-10">
                                <img style="width: 75px; height: 75px;"
                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                با
                                <?php echo $_SESSION['call']['name'];
                                unset($_SESSION['call']);
                                ?>

                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                        </div>
                    <?php } ?>
                    <div class="col-lg-8">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">
                                                گزارش دانش آموزان بر اساس پایه‌های فعال / غیرفعال
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید بر اساس وضعیت فعال یا غیرفعال پایه‌ها، گزارش دانش آموزان را تهیه کنید. با تعیین فیلترهای موردنظر، سامانه فهرست دانش آموزان مرتبط را تولید می‌کند تا برای بررسی وضعیت ثبت‌نام، پیگیری آموزشی، تحلیل آماری یا تهیه گزارش‌های مدیریتی از آن استفاده شود."
                                                  aria-label="راهنمای گزارش دانش آموزان بر اساس پایه‌های فعال و غیرفعال"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>
                                        <p class="text-muted card-sub-title">لطفا اطلاعات خواسته شده را جهت تولید گزارش
                                            وارد کنید
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="" id="excel-course">
                                    <!-- Row 1: Type, Status, Start, End -->
                                    <div class="row">
                                        <div class="col-xl-3 col-lg-3 col-md-12 mt-n1">
                                            <label class="form-label mg-t-15">نوع گزارش:<span style="color: red">*</span></label>
                                            <select class="form-control select2" name="typereport" id="type" required>
                                                <option value="" label="اقدام لازم"></option>
                                                <option value="1">1- نفر پایه فعال</option>
                                                <option value="2">2- نفر بایگانی سال‌های قبل</option>
                                                <option value="3">3- نفر پایه تجمیعی فعال</option>
                                                <option value="4">4- نفر پایه تجمیعی غیرفعال</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-12 mt-n1">
                                            <label class="form-label mg-t-15" id="student">وضعیت دانش آموز:<span style="color: red">*</span></label>
                                            <select class="form-control select2" name="status" id="status" required>
                                                <option value=""></option>
                                                <option value="all">همه دانش آموزان</option>
                                                <option value="active">دانش آموزان فعال</option>
                                                <option value="inactive">دانش آموزان غیرفعال</option>
                                                <option value="suspended">دانش آموزان تعلیقی</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-12 mt-n1">
                                            <label class="form-label mg-t-15">از تاریخ ثبت پایه :<span style="color: red">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></div>
                                                </div>
                                                <input class="form-control fc-datepicker" placeholder="1402/09/10" required type="text" name="startdate">
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-3 col-md-12 mt-n1">
                                            <label class="form-label mg-t-15">تا تاریخ :<span style="color: red">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></div>
                                                </div>
                                                <input class="form-control fc-datepicker" value="<?php echo $dateno ?>" required type="text" name="enddate">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Row 2: انتخاب پایه (full width) -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label class="form-label">انتخاب پایه:<span style="color: red">*</span></label>
                                            <select class="form-control select2" name="courses[]" id="course" multiple required></select>
                                        </div>
                                    </div>

                                    <!-- Row 3: رویداد عملی (full width) -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label class="form-label">رویداد عملی :</label>
                                            <select class="form-control select2" name="active_events[]" id="active_events" multiple>
                                                <?php foreach ($activeEventsList as $event): ?>
                                                    <option value="<?= $event->id ?>"><?= htmlspecialchars($event->title) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Row: انتخاب سال تحصیلی -->
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label class="form-label">انتخاب سال تحصیلی :</label>
                                            <select class="form-control select2" name="school_years[]" id="school_years" multiple>
                                                <?php foreach ($schoolYearsList as $sy): ?>
                                                    <option value="<?= $sy['start_date'] ?>|<?= $sy['end_date'] ?>">
                                                        <?= htmlspecialchars($sy['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Row 4: دکمه دانلود -->
                                    <div class="row mt-4">
                                        <div class="col-12 text-left">
                                            <button type="submit" name="sesave" class="btn btn-sm btn-success"><i class="fe fe-save"></i> دانلود اکسل</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card custom-card">
                            <div class="card-body d-flex">
                                <div class="col-6">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">جستجوی دانش آموز</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید دانش آموز موردنظر را جستجو کرده و وضعیت ثبت‌نام فعال یا غیرفعال او در پایه‌ها را بررسی کنید."
                                                  aria-label="راهنمای جستجوی دانش آموز"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>
                                        <p class="text-muted card-sub-title"> برحسب نفرپایه </p>
                                    </div>

                                    <form method="post" data-parsley-validate="">
                                        <div class="d-flex align-items-center flex-nowrap" style="width: 100%;">
                                            <!-- دسته‌بندی -->
                                            <div style="width: 200px;">
                                                <select class="form-control select2" name="category" required
                                                        style="border-radius: 5px 0 0 5px; border-right: none;">
                                                    <option label="دسته بندی ها"></option>
                                                    <option value="one">کد ملی دانش آموز</option>
                                                    <option value="two">نام</option>
                                                    <option value="three">نام خانوادگی</option>
                                                </select>
                                            </div>

                                            <!-- فیلد جستجو -->
                                            <div style="flex: 1;">
                                                <input type="text" name="txt" class="form-control"
                                                       placeholder="جستجوی دانش آموز" required
                                                       style="border-radius: 0; border-left: none; border-right: none;">
                                            </div>

                                            <!-- دکمه جستجو -->
                                            <button type="submit" name="search" class="btn btn-primary"
                                                    style="border-radius: 5px 0 0 5px; height: 38px;">
                                                <i class="fe fe-search"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            </div>


                            <div class="card-body">
                                <?php if (isset($_SESSION['massege'])) { ?>
                                    <div class="alert alert-info" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $_SESSION['massege'];
                                        unset($_SESSION['massege']); ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($call) and $call === 1) { ?>
                                    <div class="alert alert-outline-success  text-center " role="alert">
                                        <div class="mg-t-10">
                                            <img style="width: 75px; height: 75px;" src="../../assets/img/call1.gif">در
                                            حال
                                            برقراری ارتباط
                                            با
                                            <?php echo $name ?>

                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </div>

                                    </div>
                                <?php } ?>
                                <div class="table-responsive">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th class="wd-30">اقدامات</th>
                                            <th class="wd-100">نام و نام خانوادگی</th>
                                            <th>پایه</th>
                                            <th class="wd-30">وضعیت فردی</th>
                                            <th class="wd-30">وضعیت پایه</th>
                                            <th class="wd-30">پشتیبان</th>
                                            <th class="wd-30">اولین ورود</th>
                                            <th class="wd-30">همکارآموزشی</th>
                                            <th class="wd-30">نوع دانش آموز</th>
                                            <th class="wd-30">شیفت دانش آموز</th>


                                        </tr>
                                        </thead>

                                        <tbody>
                                        <?php
                                        if (isset($recive)) {
                                            foreach ($recive as $ret) {
                                                ?>
                                                <tr>
                                                    <td class="wd-30">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <div class="dropdown-item  "
                                                                             onclick="window.open('../Student/viewProfile.php?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات کاربری'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fa fa-address-card"></i>
                                                                            مشاهده
                                                                            اطلاعات
                                                                            کاربری

                                                                        </div>
                                                                        <a class="dropdown-item"
                                                                           href="?call-student=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i>
                                                                            تماس
                                                                            با
                                                                            دانش آموز
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="?call-mother=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i>
                                                                            تماس
                                                                            با
                                                                            مادر
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="?call-father=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i>
                                                                            تماس
                                                                            با
                                                                            پدر
                                                                        </a>
                                                                        <div class="dropdown-item"
                                                                             onclick="window.open('../Student/log-viewFilm.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-video"></i> فیلم های
                                                                            مشاهده نموده

                                                                        </div>
                                                                        <div class="dropdown-item"
                                                                             onclick="window.open('../Student/log-viewdoc.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-book-open"></i> جزوات
                                                                            دانلودی

                                                                        </div>
                                                                        <a class="dropdown-item text-info"
                                                                           onclick="window.open('../Student/log-viewquiz.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                   ,'width=1450,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )"><i
                                                                                    class="fa fa-question"></i>
                                                                            مشاهده آزمون ها

                                                                        </a>
                                                                        <a class="dropdown-item disabled"
                                                                           href="?takalif=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-book"></i> گزارش
                                                                            تکالیف ارسالی

                                                                        </a>
                                                                        <a class="dropdown-item text-secondary"
                                                                           onclick="window.open('../Student/log-viewattendance.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                   ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )"><i
                                                                                    class="fe fe-check-square"></i>
                                                                            مشاهده حضور
                                                                            غیاب

                                                                        </a>
                                                                        <?php if (isset($ret->idcourse)) { ?>
                                                                            <hr>
                                                                            <?php if ($ret->activityuser == 1) { ?>
                                                                                <a class="dropdown-item text-danger"
                                                                                   href="?disable=<?php echo $ret->idcourse ?>"><i
                                                                                            class="fe fe-eye-off"></i>غیرفعال
                                                                                    سازی
                                                                                    فردی
                                                                                </a>
                                                                            <?php } ?>
                                                                            <?php if ($ret->activityuser == 0) { ?>
                                                                                <a class="dropdown-item text-success"
                                                                                   href="?enable=<?php echo $ret->idcourse ?>"><i
                                                                                            class="fe fe-eye"></i>فعال
                                                                                    سازی فردی
                                                                                </a>
                                                                            <?php }
                                                                        } ?>

                                                    </td>
                                                    <td class="wd-30"><?php $person = retPerson($ret->username);
                                                        echo $person->fname . ' ' . $person->lname; ?></td>
                                                    <td><?php if (isset($ret->idcourse)) {
                                                            $course = returnCourse($ret->codecourse);
                                                            echo $course->coursename;
                                                        } else {
                                                            $sql = 'SELECT coursename FROM course_demo WHERE codecourse = ?';
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute([$ret->codecourse]);
                                                            $coursename = $stmt->fetchColumn();
                                                            echo $coursename;
                                                        }
                                                        ?></td>
                                                    <td class="wd-30"><?php if (isset($ret->activityuser)) {
                                                            if ($ret->activityuser == 1) {
                                                                echo '<span class="badge badge-success-light">فعال</span>';
                                                            } else {
                                                                echo '<span class="badge badge-warning-light">غیرفعال</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="badge badge-info-light">---</span>';
                                                        }
                                                        ?></td>
                                                    <td class="wd-30"><?php if (isset($ret->idcourse)) {
                                                            if ($course->activecourse == 1) {
                                                                echo '<span class="badge badge-success-light">فعال</span>';
                                                            } else {
                                                                echo '<span class="badge badge-warning-light">غیرفعال</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="badge badge-info-light">---</span>';
                                                        }
                                                        ?></td>
                                                    <td class="wd-30"><?php if (isset($ret->idcourse)) {
                                                            if ($ret->suporter != NULL) {
                                                                echo $ret->suporter;
                                                            } else {
                                                                echo 'ندارد';
                                                            }
                                                        } else {
                                                            echo '<span class="badge badge-info-light">---</span>';
                                                        } ?> </td>

                                                    <td class="wd-30">
                                                        <?php
                                                        $first = retdblogin($ret->username);
                                                        if ($first->firstlogin == 1) {
                                                            echo '<span class="badge badge-success-light">بـله</span>';
                                                        } else {
                                                            echo '<span class="badge badge-warning-light">خــیر</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-30">
                                                        <?php
                                                        if ($person->useradder != NULL) {
                                                            $adder = retPerson($person->useradder);
                                                            echo $adder->fname . ' ' . $adder->lname;
                                                        } else {
                                                            echo 'ندارد';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-30">
                                                        <?php
                                                        $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN dbpersonal ON dbpersonal_type_vip.dbpersonal_id = dbpersonal.idpersonal WHERE dbpersonal.codemeli = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$person->codemeli]);
                                                        $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                        $typeVips = array();
                                                        if ($stmt->rowCount() > 0) {
                                                            foreach ($vips as $vip) {
                                                                $sql = "SELECT * FROM type_vip WHERE id = ?";
                                                                $stmt = $conn->prepare($sql);
                                                                $stmt->execute([$vip->type_vip_id]);
                                                                $type = $stmt->fetch(PDO::FETCH_OBJ);
                                                                array_push($typeVips, $type->name);
                                                            }
                                                            echo implode('، ', $typeVips);
                                                        } else {
                                                            echo 'تعریف نشده';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!isset($person->shift)) {
                                                            echo 'تعریف نشده';
                                                        } else {
                                                            switch ($person->shift) {
                                                                case '0':
                                                                    echo 'صبح';
                                                                    break;
                                                                case '1':
                                                                    echo 'بعدازظهر';
                                                                    break;
                                                                case '2':
                                                                    echo 'گردشی';
                                                                    break;
                                                            }
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">گزارش تعداد استانی دانش آموزان</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش گزارش دانش آموزان به تفکیک استان ها نمایش داده می شود.این گزارش به شما این امکان را میدهد که پراکندگی جغرافیایی دانش آموزان را بررسی کرده و تعداد ثبت‌نام‌شده‌ها را در هر استان مشاهده و تحلیل کنید."
                                                  aria-label="راهنمای گزارش تعداد استانی دانش آموزان"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>
                                        <p class="text-muted card-sub-title">آمار تعداد دانش آموزان به تفکیک استان
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>نام استان</th>
                                            <th>تعداد</th>
                                            <th>خروجی</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT `state`,COUNT(state) as costate FROM `dbpersonal` INNER JOIN `dblogin` ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=?  GROUP BY `dbpersonal`.`state`';
                                        $query = $conn->prepare($sql);
                                        $query->execute([1]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach (
                                                $result
                                                as $ret
                                            ) {
                                                ?>
                                                <tr>
                                                    <td><?php echo $ret->state ?></td>
                                                    <td class="wd-20"><?php echo number_format($ret->costate) ?></td>
                                                    <td class="wd-20">
                                                        <button class="btn btn-sm btn-outline-success state-export-btn"
                                                                data-state="<?php echo htmlspecialchars($ret->state) ?>">
                                                            <i class="fe fe-download"></i>
                                                        </button>
                                                    </td>

                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>
                                        </tbody>
                                    </table>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Excel Options Modal -->
    <div class="modal fade" id="excelOptionsModal" tabindex="-1" aria-labelledby="excelOptionsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="excelOptionsLabel">انتخاب گزینه‌های گزارش اکسل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="excelOptionsText"></p>
                    <div id="excelOptionsContainer" class="row"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="confirmExcelOptions">تایید و ادامه</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->
    <script>
        $(document).ready(function() {
            $('#type').select2({
                placeholder: 'انتخاب گزارش',
            });

            $('#course').select2({
                placeholder: ' پایه فعال/غیرفعال',
                language: {
                    noResults: function() {
                        return "پایه ای یافت نشد";
                    }
                }
            });
            $('#active_events').select2({
                placeholder: 'رویداد عملی را انتخاب کنید',
                language: {
                    noResults: function() {
                        return "رویدادی یافت نشد";
                    }
                }
            });
            $('#school_years').select2({
                placeholder: 'سال تحصیلی را انتخاب کنید',
                language: {
                    noResults: function() {
                        return "سال تحصیلی یافت نشد";
                    }
                }
            });
            $('#status').select2({
                placeholder: 'انتخاب وضعیت',
            });

            // وقتی گزینه تغییر کرد
            $('#type').on('change', function() {
                const selectedType = $(this).val();

                // پاک کردن سلکت پایه‌ها
                $('#course').empty().trigger('change');

                if (!selectedType) return; // اگر چیزی انتخاب نشده، کاری نکن

                // افزودن گزینه‌های پیش‌فرض برای هر نوع گزارش
                if (selectedType == '1' || selectedType == '3') {
                    const allOption = new Option('همه پایه‌های فعال', 'all_active', false, false);
                    $('#course').append(allOption);
                } else if (selectedType == '2' || selectedType == '4') {
                    const allOption = new Option('همه بایگانی سال‌های قبل', 'all_inactive', false, false);
                    $('#course').append(allOption);
                } else if (selectedType == '5') {
                    const allOption = new Option('همه پایه‌ها', 'all_courses', false, false);
                    $('#course').append(allOption);
                }

                // درخواست AJAX برای گرفتن پایه‌ها
                $.ajax({
                    url: '../course/get_courses_by_type.php',
                    method: 'GET',
                    data: {
                        type: selectedType
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (!data || data.length === 0) {
                            const newOption = new Option('موردی یافت نشد', '', false, false);
                            $('#course').append(newOption).trigger('change');
                            return;
                        }

                        // افزودن پایه‌ها به سلکت
                        data.forEach(item => {
                            const newOption = new Option(item.coursename, item.codecourse, false, false);
                            $('#course').append(newOption);
                        });

                        $('#course').trigger('change');
                    },
                    error: function(err) {
                        console.error('خطا در گرفتن داده‌ها: ', err);
                    }
                });
            });

            const inputHtml = '<input type="text" name="txt" class="form-control" placeholder="جستجوی دانش آموز" style="border-radius: 0; border-left: none; border-right: none;" required>';
            const selectHtml = `
                <select name="txt" class="form-control select2" required>
                    <option value="" disabled selected hidden>انتخاب زمان</option>
                    <option value="0">صبح</option>
                    <option value="1">بعدازظهر</option>
                    <option value="2">گردشی</option>
                </select>`;

            $('select[name="category"]').on('change', function() {
                const selectedValue = this.value;
                if ($.isNumeric(selectedValue)) {
                    // اگر قبلا select بود destroy کن
                    if ($('select[name="txt"]').hasClass('select2-hidden-accessible')) {
                        $('select[name="txt"]').select2('destroy');
                    }
                    $('input[name="txt"]').replaceWith(selectHtml);
                    $('select[name="txt"]').select2({
                        placeholder: 'انتخاب زمان'
                    });
                } else {
                    // اگر قبلا select بود destroy کن
                    if ($('select[name="txt"]').hasClass('select2-hidden-accessible')) {
                        $('select[name="txt"]').select2('destroy');
                    }
                    $('select[name="txt"]').replaceWith(inputHtml);
                }
            });

            const optionsSingle = [{
                label: 'استان',
                value: 'state'
            },
                {
                    label: 'شهر',
                    value: 'city'
                },
                {
                    label: 'جنسیت',
                    value: 'gender'
                },
                {
                    label: 'تلفن دانش آموز',
                    value: 'student_phone'
                },
                {
                    label: 'تلفن پدر',
                    value: 'father_phone'
                },
                {
                    label: 'تلفن مادر',
                    value: 'mother_phone'
                },
                {
                    label: 'تعداد اس‌ام‌اس',
                    value: 'sms_count'
                },
                {
                    label: 'مدت زمان مکالمه',
                    value: 'talk_time'
                },

                {
                    label: 'وضعیت دانش آموز',
                    value: 'student_status'
                },
                {
                    label: 'نوع دانش آموز',
                    value: 'student_type'
                },
                {
                    label: 'شیفت دانش آموز',
                    value: 'shift'
                },
                {
                    label: 'تاریخ تولد دانش آموز',
                    value: 'birthdate'
                },
                {
                    label: 'نام مدرسه',
                    value: 'school'
                },
                {
                    label: 'نوع مدرسه',
                    value: 'typeschool'
                },
                {
                    label: 'منطقه آموزش و پرورش',
                    value: 'area'
                },
                {
                    label: 'رشته دانش آموز',
                    value: 'reshte'
                },
                {
                    label: 'نام پایه',
                    value: 'course_name'
                },
                {
                    label: 'ایجاد کننده',
                    value: 'creator'
                },
                {
                    label: 'پشتیبان',
                    value: 'supporter'
                },
                // {
                //     label: 'شماره فاکتور',
                //     value: 'factor_number'
                // },
                {
                    label: 'اولین ورود',
                    value: 'first_login'
                },
                {
                    label: 'تاریخ ثبت پروفایل',
                    value: 'datecreate'
                },
                {
                    label: 'نوع آزمون کشوری',
                    value: 'exam_type'
                },
                {
                    label: 'وضعیت ثبت نام آزمون کشوری',
                    value: 'registered'
                },
                {
                    label: 'وضعیت شرکت در آزمون کشوری',
                    value: 'attended'
                },
                {
                    label: 'نتیجه آزمون کشوری',
                    value: 'result'
                },
                {
                    label: 'توضیحات چرایی عدم ثبت نام در آزمون کشوری',
                    value: 'not_registered_reason'
                },
                {
                    label: 'نظر دانش آموز',
                    value: 'student_comment'
                },
                {
                    label: 'نظر پشتیبان',
                    value: 'supporter_comment'
                },
                {
                    label: 'معدل ترم اول',
                    value: 'avg_first_semester'
                },
                {
                    label: 'معدل ترم دوم',
                    value: 'avg_second_semester'
                },
                {
                    label: 'آخرین تراز آزمون تک درس',
                    value: 'last_balance_single_test'
                },
                {
                    label: 'میانگین تراز آزمون های تک درس',
                    value: 'avg_balance_single_test'
                },
                {
                    label: 'آخرین تراز آزمون جامع',
                    value: 'last_balance_multi_test'
                },
                {
                    label: 'میانگین تراز آزمون های جامع',
                    value: 'avg_balance_multi_test'
                },
                {
                    label: 'شرح حال',
                    value: 'about'
                },
                {
                    label: 'وضعیت عملکرد(رنگ بندی)',
                    value: 'coloring'
                }
            ];

            const optionsAggregate = [{
                label: 'استان',
                value: 'state'
            },
                {
                    label: 'شهر',
                    value: 'city'
                },
                {
                    label: 'جنسیت',
                    value: 'gender'
                },
                {
                    label: 'تلفن دانش آموز',
                    value: 'student_phone'
                },
                {
                    label: 'تلفن پدر',
                    value: 'father_phone'
                },
                {
                    label: 'تلفن مادر',
                    value: 'mother_phone'
                },
                {
                    label: 'تعداد اس‌ام‌اس',
                    value: 'sms_count'
                },
                {
                    label: 'مدت زمان مکالمه',
                    value: 'talk_time'
                },
                {
                    label: 'نوع دانش آموز',
                    value: 'student_type'
                },
                {
                    label: 'شیفت دانش آموز',
                    value: 'shift'
                },
                {
                    label: 'تاریخ تولد دانش آموز',
                    value: 'birthdate'
                },
                {
                    label: 'نام مدرسه',
                    value: 'school'
                },
                {
                    label: 'نوع مدرسه',
                    value: 'typeschool'
                },
                {
                    label: 'منطقه آموزش و پرورش',
                    value: 'area'
                },
                {
                    label: 'رشته دانش آموز',
                    value: 'reshte'
                },
                {
                    label: 'نام پایه‌ها',
                    value: 'course_names'
                },
                {
                    label: 'تعداد پایه ثبت نامی',
                    value: 'course_count'
                },
                {
                    label: 'جمع پرداختی',
                    value: 'total_payment'
                },
                {
                    label: 'اولین ورود',
                    value: 'first_login'
                },
                {
                    label: 'ایجاد کننده',
                    value: 'creator'
                },
                // {
                //     label: 'تاییدکننده فاکتور',
                //     value: 'approver'
                // },
                {
                    label: 'پشتیبان',
                    value: 'supporter'
                },
                // {
                //     label: 'شماره فاکتور',
                //     value: 'factor_number'
                // },
                {
                    label: 'تاریخ ثبت پروفایل',
                    value: 'datecreate'
                },
                {
                    label: 'نوع آزمون کشوری',
                    value: 'exam_type'
                },
                {
                    label: 'وضعیت ثبت نام آزمون کشوری',
                    value: 'registered'
                },
                {
                    label: 'وضعیت شرکت در آزمون کشوری',
                    value: 'attended'
                },
                {
                    label: 'نتیجه آزمون کشوری',
                    value: 'result'
                },
                {
                    label: 'توضیحات چرایی عدم ثبت نام در آزمون کشوری',
                    value: 'not_registered_reason'
                },
                {
                    label: 'نظر دانش آموز',
                    value: 'student_comment'
                },
                {
                    label: 'نظر پشتیبان',
                    value: 'supporter_comment'
                },
                {
                    label: 'معدل ترم اول',
                    value: 'avg_first_semester'
                },
                {
                    label: 'معدل ترم دوم',
                    value: 'avg_second_semester'
                },
                {
                    label: 'شرح حال',
                    value: 'about'
                },
                {
                    label: 'وضعیت عملکرد(رنگ بندی)',
                    value: 'coloring'
                }
            ];

            // گزینه‌های ستون‌ها برای گزارش‌های دانش‌آموزی (مشترک برای همه دانش‌آموزان، بدون پایه، منتظر پرداخت)
            const optionsStudent = [{
                label: 'استان',
                value: 'state'
            },
                {
                    label: 'شهر',
                    value: 'city'
                },
                {
                    label: 'تلفن دانش آموز',
                    value: 'student_phone'
                },
                {
                    label: 'تلفن پدر',
                    value: 'father_phone'
                },
                {
                    label: 'تلفن مادر',
                    value: 'mother_phone'
                },
                {
                    label: 'وضعیت دانش آموز',
                    value: 'student_status'
                },
                {
                    label: 'نوع دانش آموز',
                    value: 'student_type'
                },
                {
                    label: 'شیفت دانش آموز',
                    value: 'shift'
                },
                {
                    label: 'تاریخ تولد دانش آموز',
                    value: 'birthdate'
                },
                {
                    label: 'نام مدرسه',
                    value: 'school'
                },
                {
                    label: 'نوع مدرسه',
                    value: 'typeschool'
                },
                {
                    label: 'منطقه آموزش و پرورش',
                    value: 'area'
                },
                {
                    label: 'رشته دانش آموز',
                    value: 'reshte'
                },
                {
                    label: 'تاریخ ثبت نام',
                    value: 'datecreate'
                },
                {
                    label: 'اولین ورود',
                    value: 'first_login'
                }
            ];

            // دکمه‌های دریافت لیست
            // دکمه‌های دریافت لیست (همه دانش‌آموزان، بدون پایه، منتظر پرداخت)
            $('.export-btn').on('click', function(e) {
                e.preventDefault();
                const reportType = $(this).data('report-type'); // all, uncourse, away

                $('#excelOptionsContainer').empty();
                $('#excelOptionsText').text('لطفا گزینه‌های زیر را برای نمایش گزارش در اکسل مشخص کنید:');
                $('#excelOptionsContainer').append(`
                    <div class="col-12 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select_all_options">
                            <label class="form-check-label fw-bold" for="select_all_options">انتخاب همه</label>
                        </div>
                    </div>
                `);
                // استفاده از گزینه‌های مشترک برای گزارش‌های دانش‌آموزی
                optionsAggregate.forEach(opt => {
                    $('#excelOptionsContainer').append(`
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input excel-option" type="checkbox" name="excel_options[]" value="${opt.value}" id="opt_${opt.value}">
                            <label class="form-check-label" for="opt_${opt.value}"
                              ${opt.value === 'talk_time' ? 'style="color:#e74c3c;font-weight:600;"' : ''}>
                              ${opt.label}
                            </label>
                            </div>
                        </div>
                    `);
                });

                // ذخیره نوع گزارش
                $('#excelOptionsModal').data('report-type', 'student');
                $('#excelOptionsModal').data('student-report', reportType); // all, uncourse, away
                $('#excelOptionsModal').modal('show');
            });

            // رویداد انتخاب همه
            $(document).on('change', '#select_all_options', function() {
                const isChecked = $(this).prop('checked');
                $('.excel-option').prop('checked', isChecked);
            });

            // دکمه‌های خروجی استانی
            $('.state-export-btn').on('click', function(e) {
                e.preventDefault();
                const state = $(this).data('state');

                $('#excelOptionsContainer').empty();
                $('#excelOptionsText').text('لطفا گزینه‌های زیر را برای نمایش گزارش استانی در اکسل مشخص کنید:');

                // برای گزارش استانی از گزینه‌های تجمیعی استفاده می‌کنیم (چون ممکن است چند پایه داشته باشند)
                optionsAggregate.forEach(opt => {
                    $('#excelOptionsContainer').append(`
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input excel-option" type="checkbox" name="excel_options[]" value="${opt.value}" id="opt_${opt.value}">
              <label class="form-check-label" for="opt_${opt.value}"
                                          ${opt.value === 'talk_time' ? 'style="color:#e74c3c;font-weight:600;"' : ''}>
                                          ${opt.label}
                                        </label>
                            </div>
                        </div>
                    `);
                });

                $('#excelOptionsModal').data('report-type', 'state');
                $('#excelOptionsModal').data('state', state);
                $('#excelOptionsModal').modal('show');
            });

            // وقتی کاربر روی دکمه دانلود اکسل زد
            $('button[name="sesave"]').on('click', function(e) {
                e.preventDefault();

                // نوع گزارش انتخاب شده
                const selectedType = $('#type').val();

                // خالی کردن مودال
                $('#excelOptionsContainer').empty();

                if (selectedType == '1' || selectedType == '2') {
                    $('#excelOptionsText').text('لطفا گزینه های زیر را برای نمایش گزارش در اکسل مشخص کنید:');
                    $('#excelOptionsContainer').append(`
                        <div class="col-12 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_all_options">
                                <label class="form-check-label fw-bold" for="select_all_options">انتخاب همه</label>
                            </div>
                        </div>
                    `);
                    optionsSingle.forEach(opt => {
                        $('#excelOptionsContainer').append(`
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input excel-option" type="checkbox" name="excel_options[]" value="${opt.value}" id="opt_${opt.value}">
          <label class="form-check-label" for="opt_${opt.value}"
                                      ${opt.value === 'talk_time' ? 'style="color:#e74c3c;font-weight:600;"' : ''}>
                                      ${opt.label}
                                    </label>
                                </div>
                            </div>
                        `);
                    });
                } else if (selectedType == '3' || selectedType == '4') {
                    $('#excelOptionsText').text('لطفا گزینه های زیر را برای نمایش گزارش در اکسل مشخص کنید:');
                    $('#excelOptionsContainer').append(`
                        <div class="col-12 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_all_options">
                                <label class="form-check-label fw-bold" for="select_all_options">انتخاب همه</label>
                            </div>
                        </div>
                    `);
                    optionsAggregate.forEach(opt => {
                        $('#excelOptionsContainer').append(`
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input excel-option" type="checkbox" name="excel_options[]" value="${opt.value}" id="opt_${opt.value}">
          <label class="form-check-label" for="opt_${opt.value}"
                                      ${opt.value === 'talk_time' ? 'style="color:#e74c3c;font-weight:600;"' : ''}>
                                      ${opt.label}
                                    </label>
                                </div>
                            </div>
                        `);
                    });
                }
                $('#excelOptionsModal').data('report-type', 'course');
                // نمایش مودال
                $('#excelOptionsModal').modal('show');
            });

            // وقتی تایید مودال زده شد
            $('#confirmExcelOptions').on('click', function() {
                $('#excelOptionsModal').modal('hide');

                const selectedOptions = [];
                $('#excelOptionsContainer input:checked.excel-option').each(function() {
                    selectedOptions.push($(this).val());
                });

                const jsonOptions = JSON.stringify(selectedOptions);
                const reportType = $('#excelOptionsModal').data('report-type');

                let form;

                if (reportType === 'course') {
                    // گزارش دوره‌ای (قبلاً داشتید)
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'excel_options',
                        value: jsonOptions
                    }).appendTo('#excel-course');
                    $('#excel-course').submit();

                } else if (reportType === 'student') {
                    // گزارش‌های: همه دانش‌آموزان، بدون پایه، منتظر پرداخت
                    const studentReport = $('#excelOptionsModal').data('student-report');

                    form = $('<form>', {
                        method: 'POST',
                        action: '../Student/export-student.php'
                    });

                    $('<input>').attr({
                        type: 'hidden',
                        name: studentReport,
                        value: '1'
                    }).appendTo(form);
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'excel_options',
                        value: jsonOptions
                    }).appendTo(form);

                    form.appendTo('body').submit();

                } else if (reportType === 'state') {
                    // گزارش استانی
                    const state = $('#excelOptionsModal').data('state');

                    form = $('<form>', {
                        method: 'POST',
                        action: '../Student/export-student.php'
                    });

                    $('<input>').attr({
                        type: 'hidden',
                        name: 'state',
                        value: state
                    }).appendTo(form);
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'excel_options',
                        value: jsonOptions
                    }).appendTo(form);

                    form.appendTo('body').submit();
                }
            });
        });
    </script>

</body>

</html>