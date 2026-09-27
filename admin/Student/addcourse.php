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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
$canBulkEnrol = AdminCanUsePermissionScope('students.definitive.enrolment.bulk', ['permision_addcourse_student', 'excel_permission']);
if (!$canBulkEnrol && (isset($_POST['confirm_invoice']) || isset($_POST['course_signup']))) {
    http_response_code(403);
    exit('مجوز ثبت گروهی پایه یا دوره برای شما فعال نشده است.');
}
include_once '../../inc/bulk_upload.php';

if (isset($_SESSION['factorId'])) {
    $idf = $_SESSION['factorId'];
    unset($_SESSION['factorId']);
}
if (isset($_GET['change'])) {
    unset($_SESSION['add-course']);
    header('location: ' . url('admin/Student/addcourse.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            // if (check_national_code($codemeli)) {
            if (checkCodemeli($codemeli)) {
                if (typeUser($codemeli) == 1) {

                    $_SESSION['add-course'] = array(
                        "codemeli" => $_POST['codemeli']
                    );
                } else {
                    $erorstudent = 1;
                }
            } else {
                $notmelli = 1;
            }

            // } else {
            //     $erormeli = 1;
            // }

        }
    } else {
        $erortoken = 1;
    }
}

//first_suporter_assigned_at  will be set only when its the users first course else its gonna be null/untouched. dont delete this comment.

if (isset($_POST['sales-course'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === ($_POST['token'] ?? '')) {
        if (!empty($_POST['course']) && is_array($_POST['course'])) {

            $username = $_SESSION['add-course']['codemeli'];
            $selectedCourses = array_values(array_unique(array_map('strval', $_POST['course'])));
            $priceModels = isset($_POST['price_model']) && is_array($_POST['price_model'])
                ? $_POST['price_model']
                : [];
            $supporters = isset($_POST['suporter']) && is_array($_POST['suporter'])
                ? $_POST['suporter']
                : [];

            $salesCourseError = null;
            $coursePrices = [];
            $courseSupporters = [];

            /*
             * Price model is PER COURSE.
             * with_supporter    => existing supporter price (getfee)
             * without_supporter => codtbl_course.coursefee_without
             *
             * The class is also PER COURSE and remains independent from the price model.
             */
            foreach ($selectedCourses as $code) {
                $model = isset($priceModels[$code]) ? trim((string)$priceModels[$code]) : '';

                if (!in_array($model, ['with_supporter', 'without_supporter'], true)) {
                    $salesCourseError = m('course.price_model_required');
                    break;
                }

                if ($model === 'with_supporter') {
                    $supporter = isset($supporters[$code]) ? trim((string)$supporters[$code]) : '';
                    if ($supporter === '') {
                        $salesCourseError = m('course.supporter_required');
                        break;
                    }

                    $courseSupporters[$code] = $supporter;
                    $coursePrices[$code] = (int)getfee($code);
                } else {
                    // No supporter is accepted/stored for this course.
                    $courseSupporters[$code] = null;

                    $feeStmt = $conn->prepare(
                        'SELECT coursefee_without 
                                 FROM codtbl_course 
                                 WHERE codecourse = ? 
                                   AND courseactive = 1 
                                 LIMIT 1'
                    );
                    $feeStmt->execute([$code]);
                    $fee = $feeStmt->fetchColumn();

                    if ($fee === false) {
                        $salesCourseError = m('course.invalid');
                        break;
                    }

                    $fee = (int)$fee;

                    // Without-supporter price must be greater than zero.
                    if ($fee <= 0) {
                        $salesCourseError = m('course.price_missing');
                        break;
                    }

                    $coursePrices[$code] = $fee;
                }
            }

            if ($salesCourseError === null) {
                $total = array_sum($coursePrices);

                date_default_timezone_set("Iran");
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');

                // Check whether this student already has any course.
                $check_sql = "SELECT COUNT(*) FROM dbcourse WHERE username = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->execute([$username]);
                $course_count = (int)$check_stmt->fetchColumn();

                // Only a course WITH a supporter can create the first supporter assignment timestamp.
                $firstAssignedAt = null;
                if ($course_count == 0) {
                    foreach ($selectedCourses as $code) {
                        if ($courseSupporters[$code] !== null) {
                            $firstAssignedAt = $datenow . ' ' . $timenow;
                            break;
                        }
                    }
                }

                $ip = GetIp();
                $os = GetOs();

                // Insert Factor.
                $add = 'INSERT INTO dbfactor (username,sumitem,subtotal,factorfee,wallet,datecreate,timecreate,factoradder,ip ,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
                $inadd = $conn->prepare($add);
                $inadd->execute([$username, $total, $total, $total, 0, $datenow, $timenow, $_SESSION['admin']['user'], $ip, $os]);
                $factorId = $conn->lastInsertId();
                $_SESSION['factorId'] = $factorId;

                foreach ($selectedCourses as $code) {
                    $class_id = (isset($_POST['class_id'][$code]) && !empty($_POST['class_id'][$code]))
                        ? $_POST['class_id'][$code]
                        : null;

                    if ($courseSupporters[$code] !== null) {
                        // Course WITH supporter.
                        $inc = 'INSERT INTO dbcourse (username, codecourse, class_id, numfactor, suporter, first_suporter_assigned_at)
                                VALUES (?,?,?,?,?,?)';
                        $inp = $conn->prepare($inc);
                        $inp->execute([
                            $username,
                            $code,
                            $class_id,
                            $factorId,
                            $courseSupporters[$code],
                            $firstAssignedAt
                        ]);
                    } else {
                        // Course WITHOUT supporter.
                        $inc = 'INSERT INTO dbcourse (username, codecourse, class_id, numfactor, has_operator)
                                VALUES (?,?,?,?,?)';
                        $inp = $conn->prepare($inc);
                        $inp->execute([$username, $code, $class_id, $factorId, 0]);
                        $newCourseRecordId = (string)$conn->lastInsertId();
                    }
                    $studentInfo = retPerson($username);
                    $studentFullName = $studentInfo ? trim($studentInfo->fname . ' ' . $studentInfo->lname) : $username;
                    CreateSchoolNotification(
                        'new_student',
                        m('log.new_student_enroll') . $studentFullName,
                        m('log.enrolled_new_course'),
                        (int)($_SESSION['admin']['idadmin'] ?? 0),
                        (string)$_SESSION['admin']['user'],
                        (int)$code,
                        $class_id ? (int)$class_id : null,
                        $username,
                        'admin/panel/suporting.php?user=' . rawurlencode($username) . '&course=' . rawurlencode((string)$code),
                        'dbcourse',
                        $newCourseRecordId
                    );
                }

                unset($_SESSION['add-course']);

                header('location: addcourse.php');
                exit;
            }
        } else {
            $salesCourseError = m('course.select_at_least_one');
        }
    }
}

$bulk_records = 0;
$bulk_inserted = 0;
$bulk_skipped  = 0;
$bulk_errors   = [];

if (!empty($_SESSION['bulk_course_report'])) {
    $bulk_records = $_SESSION['bulk_course_report']['records'] ?? 0;
    $bulk_inserted = $_SESSION['bulk_course_report']['inserted'] ?? 0;
    $bulk_skipped  = $_SESSION['bulk_course_report']['skipped'] ?? 0;
    $bulk_errors   = $_SESSION['bulk_course_report']['errors'] ?? [];

    unset($_SESSION['bulk_course_report']); // clear after reading
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
    <title> <?php echo m('page.create_course_for_student') ?> | <?php echo __TITLE ?></title>
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
                                                    <?php echo m('page.create_course_for_student') ?>
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="<?php echo m('tooltip.create_course_for_student') ?>"
                                                      aria-label="<?php echo m('aria.create_course_help') ?>">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>

                                            </div>

                                            <p class="text-muted card-sub-title">
                                                <?php echo m('page.new_invoice_for_student') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
                                <?php if (isset($idf)) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo m('invoice.issued_success', [':n' => $idf]) ?>

                                    </div>
                                <?php } ?>
                                <?php if (isset($erorstudent) and $erorstudent == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo m('error.nid_is_collague') ?>

                                    </div>
                                <?php } ?>
                                <?php if (isset($notmelli) and $notmelli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کدملی در سیستم وجود ندارد

                                    </div>
                                <?php } ?>
                                <?php if (!empty($salesCourseError)) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo htmlspecialchars($salesCourseError, ENT_QUOTES, 'UTF-8'); ?>
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
                                            <?php if (!isset($_SESSION['add-course'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nid_or_phone') ?> <span style="color: red">*</span></label>
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
                                                                name="search-melli"><i class="fa fa-search"></i>
                                                            <?php echo m('action.search_student') ?>
                                                        </button>
                                                    </div>
                                                </form>
                                                <?php if ($canBulkEnrol) { ?>
                                                    <div class="card-body">
                                                        <form method="post" enctype="multipart/form-data" action="">
                                                            <div class="d-flex flex-column mb-3">
                                                                <div class="d-flex align-items-center mg-t-15"
                                                                     style="gap: 12px;">
                                                                    <label for="excel_file" class="form-label mb-0"><?php echo m('action.bulk_create_course') ?></label>
                                                                    <span
                                                                            class="menu-info-trigger"
                                                                            data-tooltip="<?php echo m('tooltip.bulk_create_course') ?>"
                                                                            aria-label="<?php echo m('aria.bulk_create_course_help') ?>"> <i
                                                                                class="fe fe-info fs-4"></i> </span>
                                                                </div>
                                                                <div class="input-group file-browser mt-3">
                                                                    <input type="text" class="form-control border-left-0 browse-file"
                                                                           placeholder=" فایل اکسل را بارگذاری کنید*" readonly="">
                                                                    <label class="input-group-btn">
                                                                            <span class="btn btn-primary">
                                                                                انتخاب<input type="file" name="excel_file" id="excel_file"
                                                                                             style="display: none;" required>
                                                                            </span>
                                                                    </label>
                                                                </div>
                                                                <br>
                                                                <button type="button" name="course_signup"
                                                                        class="btn btn-success w-25 align-self-center"
                                                                        id="openModalBtn">
                                                                    <?php echo m('action.bulk_create_course') ?>
                                                                </button>
                                                                <br>
                                                                <p class="header-message">
                                                                    طبق فرمت ذیل، فایل خود را آپلود نمایید
                                                                </p>
                                                                <ol style="text-align: right; direction: rtl;">
                                                                    <li>بعد از دانلود فایل، راست کلیک کنید و بر روی گزینه <strong>Properties</strong> کلیک نمایید.</li>
                                                                    <li>در تب <strong>General</strong> پایین پنجره، بخش <strong>Unblock</strong> را تیک بزنید.</li>
                                                                    <li>دکمه <strong>OK</strong> را کلیک کنید و پنجره را ببندید.</li>
                                                                    <li>فایل اکسل را باز کنید و روی <strong>Enable Content</strong> کلیک نمایید</li>
                                                                    <li><strong>ALT+F8</strong> را بزنید و در پنجره ای که باز میشود روی اولین فایل دوبار کلیک نمایید</li>
                                                                    <li><?php echo m('help.excel_step3') ?></li>
                                                                </ol>
                                                                <p>
                                                                    <a href="<?php echo url('assets/doc/addcourse.xlsm') ?>" class="btn btn-info ripple"> دانلود فایل نمونه</a>
                                                                </p>
                                                            </div>
                                                        </form>
                                                        <?php if ($bulk_records || $bulk_inserted || $bulk_skipped || !empty($bulk_errors)): ?>
                                                            <div class="card mt-3">
                                                                <div class="card-body">
                                                                    <h5 class="card-title">گزارش ثبت‌نام گروهی</h5>
                                                                    <p>کل ردیف ها: <?= $bulk_records ?></p>
                                                                    <p>ثبت موفق: <?= $bulk_inserted ?></p>
                                                                    <p>ردیف‌های تکراری/رد شده: <?= $bulk_skipped ?></p>

                                                                    <?php if (!empty($bulk_errors)): ?>
                                                                        <div class="alert alert-danger">
                                                                            <ul>
                                                                                <?php foreach ($bulk_errors as $error): ?>
                                                                                    <li><?= htmlspecialchars($error) ?></li>
                                                                                <?php endforeach; ?>
                                                                            </ul>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php } ?>
                                                <!--                                        end step 1-->
                                            <?php }
                                            if (isset($_SESSION['add-course'])) {
                                                ?>
                                                <div class="row row-sm">
                                                    <div class="col-xl-8">
                                                        <div class="card-header border-bottom-0 pb-1">
                                                            <label class="main-content-label mb-2 pt-1"><?php echo m('label.courses_open_enrollment') ?></label>
                                                            <p class="tx-12 mb-0 text-muted"><?php echo m('label.select_courses_for_student') ?></p>
                                                        </div>

                                                        <?php
                                                        $rest = retPerson($_SESSION['add-course']['codemeli']);
                                                        ?>
                                                        <label class="main-content-label mb-2 pt-1">
                                                            <?php echo m('label.student_name') ?>
                                                            <span class="text-warning">
                                                                    <?php echo htmlspecialchars($rest->fname . ' ' . $rest->lname, ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                        </label>
                                                        <hr>

                                                        <!-- 🔎 فیلتر پایه‌ها بر اساس پایه -->
                                                        <div class="row mb-3 align-items-center">

                                                            <div class="col-12 mb-2">
                                                                <h6 class="main-content-label mb-1"><?php echo m('label.filter_active_courses') ?></h6>
                                                            </div>

                                                            <div class="col-12 col-lg-1 mb-2">
                                                                <a href="#"
                                                                   class="btn btn-info shadow rounded btn-course-filter active"
                                                                   data-filter="all">
                                                                    همه
                                                                </a>
                                                            </div>

                                                            <div class="col-12 col-lg-8">
                                                                <!-- ابتدایی -->
                                                                <div class="btn-group dropdown me-2 mb-2">
                                                                    <a href="#"
                                                                       class="btn btn-info shadow rounded btn-course-filter"
                                                                       data-filter="ابتدایی">
                                                                        <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                                                    </a>
                                                                    <button type="button"
                                                                            class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split"
                                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="اول">کلاس اول دبستان</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوم">کلاس دوم دبستان</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="چهارم">کلاس چهارم دبستان</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="پنجم">کلاس پنجم دبستان</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="ششم">کلاس ششم دبستان</a></li>
                                                                    </ul>
                                                                </div>

                                                                <!-- متوسطه اول -->
                                                                <div class="btn-group dropdown me-2 mb-2">
                                                                    <a href="#"
                                                                       class="btn btn-success shadow rounded btn-course-filter"
                                                                       data-filter="متوسطه_اول">
                                                                        <i class="fa fa-book"></i> متوسطه اول
                                                                    </a>
                                                                    <button type="button"
                                                                            class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split"
                                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="هفتم">هفتم</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="هشتم">هشتم</a></li>
                                                                        <li><a class="dropdown-item btn-course-filter" href="#" data-filter="نهم">نهم</a></li>
                                                                    </ul>
                                                                </div>

                                                                <!-- متوسطه دوم -->
                                                                <div class="btn-group dropdown me-2 mb-2">
                                                                    <a href="#"
                                                                       class="btn btn-warning shadow rounded btn-course-filter"
                                                                       data-filter="متوسطه_دوم">
                                                                        <i class="fa fa-star"></i> متوسطه دوم
                                                                    </a>
                                                                    <button type="button"
                                                                            class="btn btn-warning shadow rounded dropdown-toggle dropdown-toggle-split"
                                                                            data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                                                            aria-expanded="false">
                                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <!-- دهم -->
                                                                        <li class="dropstart d-flex flex-row-reverse">
                                                                            <a class="dropdown-item btn-course-filter" href="#" data-filter="دهم">دهم</a>
                                                                            <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                               href="#" role="button" data-bs-toggle="dropdown"
                                                                               aria-expanded="false"></a>
                                                                            <ul class="dropdown-menu">
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دهم,ریاضی">ریاضی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دهم,تجربی">تجربی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دهم,انسانی">انسانی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دهم,فنی">فنی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دهم,کارودانش">کارودانش</a></li>
                                                                            </ul>
                                                                        </li>

                                                                        <!-- یازدهم -->
                                                                        <li class="dropstart d-flex flex-row-reverse">
                                                                            <a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم">یازدهم</a>
                                                                            <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                               href="#" role="button" data-bs-toggle="dropdown"
                                                                               aria-expanded="false"></a>
                                                                            <ul class="dropdown-menu">
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم,ریاضی">ریاضی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم,تجربی">تجربی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم,انسانی">انسانی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم,فنی">فنی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="یازدهم,کارودانش">کارودانش</a></li>
                                                                            </ul>
                                                                        </li>

                                                                        <!-- دوازدهم -->
                                                                        <li class="dropstart d-flex flex-row-reverse">
                                                                            <a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم">دوازدهم</a>
                                                                            <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                               href="#" role="button" data-bs-toggle="dropdown"
                                                                               aria-expanded="false"></a>
                                                                            <ul class="dropdown-menu">
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم,ریاضی">ریاضی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم,تجربی">تجربی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم,انسانی">انسانی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم,فنی">فنی</a></li>
                                                                                <li><a class="dropdown-item btn-course-filter" href="#" data-filter="دوازدهم,کارودانش">کارودانش</a></li>
                                                                            </ul>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-lg-3 mb-2">
                                                                <label for="courseTypeFilter" class="form-label mb-1"></label>
                                                                <select id="courseTypeFilter"
                                                                        class="form-control bg-primary text-white border-0">
                                                                    <option value="all"><?php echo m('label.all_courses') ?></option>
                                                                    <option value="0">آموزشی</option>
                                                                    <option value="2">المپیاد</option>
                                                                    <option value="1">مهارتی</option>
                                                                </select>
                                                            </div>

                                                        </div>
                                                        <!-- /فیلتر -->

                                                        <div class="card-body pt-0">
                                                            <form method="post" data-parsley-validate="">
                                                                <ul class="top-selling-products pb-0 mb-0 pr-0">
                                                                    <?php
                                                                    // ⚠️ فرض: جدول codtbl_course ستون های paye و reshte دارد
                                                                    // اگر نام ستون‌ها چیز دیگری است، اینجا را تغییر بده.
                                                                    $sql = 'SELECT * FROM codtbl_course WHERE courseactive = ?';
                                                                    $query = $conn->prepare($sql);
                                                                    $query->execute([1]);
                                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);

                                                                    if ($query->rowCount() > 0) {
                                                                        foreach ($result as $results) {
                                                                            if (checkactCourse($_SESSION['add-course']['codemeli'], $results->codecourse)) {
                                                                                $registercourse = 1;
                                                                            } else {
                                                                                $registercourse = 0;
                                                                            }

                                                                            // امن‌سازی مقادیر
                                                                            $courseName  = htmlentities($results->coursename, ENT_QUOTES, 'UTF-8');
                                                                            $courseMuted = htmlentities($results->coursemuted, ENT_QUOTES, 'UTF-8');
                                                                            $courseFee   = (int)$results->coursefee;
                                                                            $courseFeeWithout = isset($results->coursefee_without) ? (int)$results->coursefee_without : 0;

                                                                            // ⚠️ این دو خط را با نام واقعی ستون‌های پایه/رشته در دیتابیست هماهنگ کن
                                                                            $courseGrade = isset($results->paye)   ? htmlentities($results->paye,   ENT_QUOTES, 'UTF-8') : '';
                                                                            $courseField = isset($results->reshte) ? htmlentities($results->reshte, ENT_QUOTES, 'UTF-8') : '';
                                                                            ?>
                                                                            <?php
                                                                            $courseGrade = isset($results->paye)        ? htmlentities($results->paye,   ENT_QUOTES, 'UTF-8') : '';
                                                                            $courseField = isset($results->reshte)      ? htmlentities($results->reshte, ENT_QUOTES, 'UTF-8') : '';
                                                                            $courseType  = isset($results->course_type) ? (int)$results->course_type : null;
                                                                            ?>
                                                                            <li class="product-item"
                                                                                data-grade="<?php echo $courseGrade; ?>"
                                                                                data-field="<?php echo $courseField; ?>"
                                                                                data-type="<?php echo $courseType; ?>">

                                                                                <div class="product-img">
                                                                                    <input type="checkbox"
                                                                                           name="course[]"
                                                                                           class="course-checkbox"
                                                                                           value="<?php echo $results->codecourse; ?>"
                                                                                        <?php echo ($registercourse == 1) ? 'disabled' : ''; ?>>
                                                                                </div>
                                                                                <div class="product-info">
                                                                                    <div class="product-name">
                                                                                        <?php
                                                                                        if ($registercourse == 1) {
                                                                                            echo '<del>' . $courseName . '</del>';
                                                                                        } else {
                                                                                            echo $courseName;
                                                                                        }
                                                                                        ?>
                                                                                    </div>
                                                                                    <div class="price"><?php echo $courseMuted; ?></div>
                                                                                </div>
                                                                                <div class="product-amount">
                                                                                    <div class="product-price">
                                                                                        <?php echo number_format($courseFee); ?> تومان
                                                                                    </div>
                                                                                    <div class="items-sold">
                                                                                        <?php echo ($registercourse == 1) ? 'غیرفعال' : 'فعال'; ?>
                                                                                    </div>
                                                                                </div>
                                                                            </li>
                                                                            <div class="course-settings-container mt-2" id="course-settings-<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>" style="display: none;">
                                                                                <div class="border rounded p-3 bg-light">
                                                                                    <div class="mb-2">
                                                                                        <label class="form-label mb-2 fw-bold"><?php echo m('label.price_model_this_course') ?></label>
                                                                                        <div class="d-flex flex-wrap gap-3">
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input price-model-radio"
                                                                                                       type="radio"
                                                                                                       name="price_model[<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>]"
                                                                                                       value="with_supporter"
                                                                                                       data-code="<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>">
                                                                                                <label class="form-check-label">
                                                                                                    با پشتیبان — <?php echo number_format($courseFee); ?> تومان
                                                                                                </label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input price-model-radio"
                                                                                                       type="radio"
                                                                                                       name="price_model[<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>]"
                                                                                                       value="without_supporter"
                                                                                                       data-code="<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>"
                                                                                                    <?php echo ($courseFeeWithout <= 0) ? 'disabled' : ''; ?>>
                                                                                                <label class="form-check-label <?php echo ($courseFeeWithout <= 0) ? 'text-muted' : ''; ?>">
                                                                                                    بدون پشتیبان —
                                                                                                    <?php if ($courseFeeWithout > 0): ?>
                                                                                                        <?php echo number_format($courseFeeWithout); ?> تومان
                                                                                                    <?php else: ?>
                                                                                                        <span class="text-danger">غیرفعال</span>
                                                                                                    <?php endif; ?>
                                                                                                </label>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="supporter-wrapper mt-3" id="supporter-wrapper-<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>" style="display: none;">
                                                                                        <label class="form-label mb-1">پشتیبان <span class="tx-danger">*</span></label>
                                                                                        <select class="form-control select2 supporter-select"
                                                                                                name="suporter[<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>]"
                                                                                                id="supporter-select-<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>">
                                                                                            <option value="">انتخاب پشتیبان</option>
                                                                                            <?php
                                                                                            $sqlSupporter = 'SELECT username FROM dbadmin WHERE simple_operator = ?';
                                                                                            $querySupporter = $conn->prepare($sqlSupporter);
                                                                                            $querySupporter->execute([1]);
                                                                                            $supportersResult = $querySupporter->fetchAll(PDO::FETCH_OBJ);
                                                                                            foreach ($supportersResult as $admin) { ?>
                                                                                                <option value="<?php echo htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8'); ?>">
                                                                                                    <?php echo htmlspecialchars($admin->username, ENT_QUOTES, 'UTF-8'); ?>
                                                                                                </option>
                                                                                            <?php } ?>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="class-container mt-2" id="class-container-<?php echo htmlspecialchars($results->codecourse, ENT_QUOTES, 'UTF-8'); ?>" style="display: none;"></div>
                                                                            <hr>
                                                                            <?php
                                                                        }
                                                                    }
                                                                    ?>
                                                                </ul>

                                                                <?php
                                                                $hash = bin2hex(random_bytes(32));
                                                                $_SESSION['token'] = $hash;
                                                                ?>
                                                                <input class="form-control" name="token" type="hidden" value="<?php echo $hash; ?>">
                                                                <input type="hidden" name="user"
                                                                       value="<?php echo htmlspecialchars($_SESSION['add-course']['codemeli'], ENT_QUOTES, 'UTF-8'); ?>">

                                                                <div class="text-center mt-3">
                                                                    <button class="btn btn-success ripple" name="sales-course">
                                                                        تایید و صدور فاکتور
                                                                    </button>
                                                                </div>
                                                            </form>

                                                            <div class="mg-t-10">
                                                                <a href="?change">
                                                                    <button class="btn btn-warning" type="button" name="change">
                                                                        تغییر کدملی
                                                                    </button>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4">
                                                        <div class="table">
                                                            <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                                <thead>
                                                                <tr>
                                                                    <th>ردیف</th>
                                                                    <th>پشتیبان</th>
                                                                    <th><?php echo m('label.count_person_course') ?></th>
                                                                </tr>
                                                                </thead>
                                                                <tbody>
                                                                <?php
                                                                $sql = 'SELECT COUNT(*) as coreg , dbadmin.username FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse RIGHT JOIN dbadmin ON dbadmin.username=dbcourse.suporter  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? GROUP BY dbcourse.suporter ORDER BY coreg DESC ';
                                                                $query = $conn->prepare($sql);
                                                                $query->execute(['موفق']);
                                                                $refrence = $query->fetchAll(PDO::FETCH_OBJ);
                                                                if ($query->rowCount() > 0) {
                                                                    $count = 1;
                                                                    foreach ($refrence as $ted) {

                                                                        ?>
                                                                        <tr>
                                                                            <td class="wd-15"><?php echo $count ?></td>
                                                                            <td><?php echo $ted->username ?></td>
                                                                            <td class="wd-100"><?php echo $ted->coreg ?></td>
                                                                        </tr>
                                                                        <?php
                                                                        $count++;
                                                                    }
                                                                }
                                                                ?>
                                                                </tbody>
                                                            </table>


                                                        </div>
                                                    </div>
                                                </div>

                                            <?php } ?>
                                            <!-- Confirmation Modal -->
                                            <div class="modal fade" id="confirmModal" tabindex="-1"
                                                 aria-labelledby="confirmModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="confirmModalLabel">تأیید
                                                                عملیات</h5>
                                                            <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="بستن"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            آیا فاکتورهای صادر شده تأیید واریزی شوند؟
                                                        </div>
                                                        <div class="modal-footer justify-content-center">
                                                            <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">لغو عملیات
                                                            </button>
                                                            <button type="button" class="btn btn-danger" id="noBtn">
                                                                خیر
                                                            </button>
                                                            <button type="button" class="btn btn-success" id="yesBtn">
                                                                بله
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

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
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const openModalBtn = document.getElementById("openModalBtn");
            const yesBtn = document.getElementById("yesBtn");
            const noBtn = document.getElementById("noBtn");
            const excelForm = document.querySelector('form[enctype="multipart/form-data"]');
            if (!openModalBtn || !yesBtn || !noBtn || !excelForm) return;
            const fileInput = excelForm.querySelector('input[type="file"]');
            const confirmModal = new bootstrap.Modal(document.getElementById("confirmModal"));

            // 🚦 وقتی روی دکمه "ثبت پایه گروهی" کلیک شد
            openModalBtn.addEventListener("click", function() {
                if (!fileInput.value) {
                    // اگر فایل انتخاب نشده بود
                    alert("لطفاً ابتدا فایل اکسل را انتخاب کنید.");
                    fileInput.focus();
                    return;
                }
                // اگر فایل انتخاب شده بود، مودال باز شود
                confirmModal.show();
            });

            // 🚀 تابع ارسال فرم با مقدار انتخابی
            function submitForm(confirmValue) {
                let oldInput = document.querySelector('input[name="confirm_invoice"]');
                if (oldInput) oldInput.remove();

                let hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = "confirm_invoice";
                hiddenInput.value = confirmValue;
                excelForm.appendChild(hiddenInput);

                excelForm.submit();
            }

            yesBtn.addEventListener("click", function() {
                submitForm("yes");
            });

            noBtn.addEventListener("click", function() {
                submitForm("no");
            });
        });
        //here
        document.addEventListener("DOMContentLoaded", function() {
            // ----- فیلتر پایه ها بر اساس پایه / رشته + نوع پایه -----
            const filterButtons = document.querySelectorAll(".btn-course-filter");
            const courseItems = document.querySelectorAll(".top-selling-products .product-item");
            const typeFilterSelect = document.getElementById("courseTypeFilter");

            // اگر هنوز فیلتر یا لیست پایه‌ها روی صفحه نیست، کاری نکن
            if (!filterButtons.length || !courseItems.length) {
                return;
            }

            let currentGradeFilter = "all";

            function matchesFilter(item, gradeFilter, typeFilter) {
                if (!gradeFilter) gradeFilter = "all";
                if (!typeFilter) typeFilter = "all";

                const grade = (item.dataset.grade || "").trim();
                const field = (item.dataset.field || "").trim();
                const type = (item.dataset.type || "").trim();

                // ---- فیلتر نوع پایه (course_type) ----
                // typeFilter: "all" | "0" | "1" | "2"
                if (typeFilter !== "all") {
                    if (type !== typeFilter) {
                        return false;
                    }
                }

                // ---- فیلتر پایه / رشته (قبلی) ----
                if (gradeFilter === "all") {
                    return true;
                }

                const parts = gradeFilter.split(",");
                const baseGrade = parts[0]; // e.g. "دهم" یا "متوسطه_اول" یا "ابتدایی"
                const fieldFilter = parts[1] || ""; // e.g. "ریاضی"

                // گروه‌های کلی
                if (baseGrade === "ابتدایی") {
                    return ["اول", "دوم", "سوم", "چهارم", "پنجم", "ششم"].includes(grade);
                }
                if (baseGrade === "متوسطه_اول") {
                    return ["هفتم", "هشتم", "نهم"].includes(grade);
                }
                if (baseGrade === "متوسطه_دوم") {
                    return ["دهم", "یازدهم", "دوازدهم"].includes(grade);
                }

                // فیلتر پایه مشخص
                if (fieldFilter) {
                    // پایه + رشته
                    return grade === baseGrade && field === fieldFilter;
                } else {
                    // فقط پایه
                    return grade === baseGrade;
                }
            }

            function applyFilter() {
                const typeFilter = typeFilterSelect ? typeFilterSelect.value : "all";

                courseItems.forEach(function(item) {
                    if (matchesFilter(item, currentGradeFilter, typeFilter)) {
                        item.style.display = "";
                        const hr = item.nextElementSibling;
                        if (hr && hr.tagName === "HR") {
                            hr.style.display = "";
                        }
                    } else {
                        item.style.display = "none";
                        const hr = item.nextElementSibling;
                        if (hr && hr.tagName === "HR") {
                            hr.style.display = "none";
                        }
                    }
                });
            }

            // کلیک روی دکمه‌های پایه / رشته
            filterButtons.forEach(function(btn) {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();

                    const filter = this.dataset.filter || "all";
                    currentGradeFilter = filter;

                    // فعال کردن ظاهر دکمه انتخاب‌شده
                    filterButtons.forEach(function(b) {
                        b.classList.remove("active");
                    });
                    this.classList.add("active");

                    applyFilter();
                });
            });

            // تغییر نوع پایه (course_type)
            if (typeFilterSelect) {
                typeFilterSelect.addEventListener("change", function() {
                    applyFilter();
                });
            }

            // فیلتر اولیه (نمایش همه)
            applyFilter();
        });
        $(document).ready(function() {
            function getCourseContainer(code) {
                return $('#course-settings-' + code);
            }

            function getClassContainer(code) {
                return $('#class-container-' + code);
            }

            function updateCourseSettings(code) {
                const settings = getCourseContainer(code);
                const selectedModel = settings.find('.price-model-radio:checked').val();
                const supporterWrapper = settings.find('.supporter-wrapper');
                const supporterSelect = settings.find('.supporter-select');

                if (selectedModel === 'with_supporter') {
                    supporterWrapper.stop(true, true).slideDown();
                    supporterSelect.prop('required', true);
                } else {
                    supporterWrapper.stop(true, true).slideUp();
                    supporterSelect.prop('required', false).val('').trigger('change');
                }
            }

            $('.price-model-radio').on('change', function() {
                updateCourseSettings($(this).data('code'));
            });

            $('.course-checkbox').on('change', function() {
                let checkbox = $(this);
                let code = checkbox.val();
                let settings = getCourseContainer(code);
                let container = getClassContainer(code);

                if (checkbox.is(':checked')) {
                    // Show the price model selector for THIS course only.
                    settings.stop(true, true).slideDown();

                    // Class selection remains independent and per-course.
                    container.html('<span class="text-muted small">در حال بارگذاری کلاس‌ها...</span>').slideDown();

                    $.ajax({
                        url: 'get_classes.php',
                        type: 'POST',
                        data: {
                            codecourse: code
                        },
                        success: function(response) {
                            let html = `
                        <label class="form-label small text-primary mb-1"><?php echo m('label.choose_class_for_course') ?></label>
                        <select name="class_id[${code}]" class="form-control form-control-sm border-primary" required>
                            <option value="">(انتخاب کلاس الزامى است)</option>
                            ${response}
                        </select>
                    `;
                            container.html(html);
                        },
                        error: function() {
                            container.html('<span class="text-danger small">خطا در بارگذاری کلاس‌ها</span>');
                        }
                    });
                } else {
                    // Clear the course's price model and supporter when the course is unchecked.
                    settings.find('.price-model-radio').prop('checked', false);
                    settings.find('.supporter-select').prop('required', false).val('').trigger('change');
                    settings.stop(true, true).slideUp();

                    container.slideUp(function() {
                        $(this).empty();
                    });
                }
            });

            $('button[name="sales-course"]').on('click', function(e) {
                let valid = true;
                let errorMessage = '';

                $('.course-checkbox:checked').each(function() {
                    const code = $(this).val();
                    const settings = getCourseContainer(code);
                    const model = settings.find('.price-model-radio:checked').val();

                    if (!model) {
                        valid = false;
                        errorMessage = <?php echo json_encode(m('course.price_model_required'), JSON_UNESCAPED_UNICODE) ?>;
                        settings.stop(true, true).slideDown();
                        return false;
                    }

                    if (model === 'with_supporter') {
                        const supporter = settings.find('.supporter-select').val();
                        if (!supporter) {
                            valid = false;
                            errorMessage = <?php echo json_encode(m('course.supporter_required_js'), JSON_UNESCAPED_UNICODE) ?>;
                            settings.find('.supporter-wrapper').stop(true, true).slideDown();
                            settings.find('.supporter-select').prop('required', true);
                            return false;
                        }
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    alert(errorMessage);
                    return false;
                }
            });
        });
    </script>


</body>

</html>
