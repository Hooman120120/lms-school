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

// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// 1. Validate and get the class_id from the URL
if (!isset($_GET['class_id']) || empty($_GET['class_id'])) {
    echo "<script>alert('شناسه کلاس نامعتبر است.'); window.location.href='classes_list.php';</script>";
    exit;
}
$class_id = intval($_GET['class_id']);
$isTeacher = (int)($_SESSION['admin']['adminrole'] ?? -1) === 3;
$teacherCanContent = TeacherPermission('teacher_perm_content');
$teacherCanStudents = TeacherPermission('teacher_perm_students');
$teacherCanHomework = TeacherPermission('teacher_perm_homework');
$teacherCanAttendance = TeacherPermission('teacher_perm_attendance');

if ($isTeacher && !$teacherCanContent && !$teacherCanStudents && !$teacherCanHomework && !$teacherCanAttendance) {
    http_response_code(403);
    exit('شما مجوز مشاهده اطلاعات این کلاس را ندارید.');
}
if ($isTeacher && !TeacherHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به محتوای این کلاس دسترسی ندارید.');
}

// class-course
$classSql = "
    SELECT c.*, cr.coursename 
    FROM classes c
    LEFT JOIN codtbl_course cr ON c.codecourse = cr.codecourse
    WHERE c.id = ?
";
$classQuery = $conn->prepare($classSql);
$classQuery->execute([$class_id]);
$classDetails = $classQuery->fetch(PDO::FETCH_OBJ);

if (!$classDetails) {
    echo "<script>alert('کلاس مورد نظر یافت نشد.'); window.location.href='classes_list.php';</script>";
    exit;
}
if (!$isTeacher && StaffCourseScopeRequired() && !StaffHasCourseAccess((int)$classDetails->codecourse)) {
    http_response_code(403);
    exit('شما به محتوای این پایه و کلاس دسترسی ندارید.');
}

$groupedSyllabus = [];

$classDarsStmt = $conn->prepare('SELECT d.id, d.dars FROM class_dars cd INNER JOIN dbdars d ON d.id = cd.dars_id WHERE cd.class_id = ? ORDER BY d.dars ASC');
$classDarsStmt->execute([$class_id]);
foreach ($classDarsStmt->fetchAll(PDO::FETCH_OBJ) as $classDars) {
    $groupedSyllabus[(int)$classDars->id] = [
        'dars_name' => $classDars->dars,
        'lessons' => [],
        'movies' => [],
        'addons' => [],
    ];
}

function groupItemsByDars(&$groupedArray, $data, $itemType) {
    foreach ($data as $row) {
        if (!isset($groupedArray[$row->dars_id])) {
            $groupedArray[$row->dars_id] = [
                'dars_name' => $row->dars_name,
                'lessons' => [],
                'movies' => [],
                'addons' => []
            ];
        }
        $groupedArray[$row->dars_id][$itemType][] = [
            'id' => $row->item_id,
            'name' => $row->item_name,
            'lesson_id' => isset($row->lesson_id) ? (int)$row->lesson_id : 0,
            'lesson_name' => $row->lesson_name ?? 'بدون سرفصل'
        ];
    }
}

// Teachers only receive assigned movies and handouts. Administrative users
// keep the complete syllabus view.
if (!$isTeacher) {
    $lessonsSql = "
        SELECT d.id AS dars_id, d.dars AS dars_name, l.id AS item_id, l.name AS item_name
        FROM class_lesson cl
        JOIN dblesson l ON cl.lesson_id = l.id
        JOIN dbdars d ON l.dars_id = d.id
        JOIN class_dars cd ON cd.class_id = cl.class_id AND cd.dars_id = d.id
        WHERE cl.class_id = ?
    ";
    $lessonsQuery = $conn->prepare($lessonsSql);
    $lessonsQuery->execute([$class_id]);
    groupItemsByDars($groupedSyllabus, $lessonsQuery->fetchAll(PDO::FETCH_OBJ), 'lessons');
}

if (!$isTeacher || $teacherCanContent) {
// film ---
$moviesSql = "
    SELECT DISTINCT d.id AS dars_id, d.dars AS dars_name,
           m.id AS item_id, m.filmname AS item_name,
           COALESCE(l.id, 0) AS lesson_id, COALESCE(l.name, 'بدون سرفصل') AS lesson_name
    FROM class_movie cm
    JOIN classes c ON c.id = cm.class_id
    JOIN dbmovie m ON cm.movie_id = m.id AND m.codecourse = c.codecourse
    JOIN dbdars d ON m.dars_id = d.id
    JOIN class_dars cd ON cd.class_id = cm.class_id AND cd.dars_id = d.id
    LEFT JOIN dblesson l ON l.id = m.lesson_id AND l.dars_id = m.dars_id AND l.codecourse = c.codecourse
    WHERE cm.class_id = ?
";
$moviesQuery = $conn->prepare($moviesSql);
$moviesQuery->execute([$class_id]);
groupItemsByDars($groupedSyllabus, $moviesQuery->fetchAll(PDO::FETCH_OBJ), 'movies');

// jozve ---
$addonsSql = "
    SELECT DISTINCT d.id AS dars_id, d.dars AS dars_name,
           a.id AS item_id, a.docname AS item_name,
           COALESCE(l.id, 0) AS lesson_id, COALESCE(l.name, 'بدون سرفصل') AS lesson_name
    FROM addon_class ac
    JOIN classes c ON c.id = ac.class_id
    JOIN dbaddon a ON ac.addon_id = a.id AND a.codecourse = c.codecourse
    JOIN dbdars d ON a.dars_id = d.id
    JOIN class_dars cd ON cd.class_id = ac.class_id AND cd.dars_id = d.id
    LEFT JOIN dblesson l ON l.id = a.lesson_id AND l.dars_id = a.dars_id AND l.codecourse = c.codecourse
    WHERE ac.class_id = ?
";
$addonsQuery = $conn->prepare($addonsSql);
$addonsQuery->execute([$class_id]);
groupItemsByDars($groupedSyllabus, $addonsQuery->fetchAll(PDO::FETCH_OBJ), 'addons');
}

if ($isTeacher) {
    $assignedDarsIds = TeacherAssignedDarsIds($class_id);
    if ($assignedDarsIds !== []) {
        foreach (array_keys($groupedSyllabus) as $darsId) {
            if (!in_array((int)$darsId, $assignedDarsIds, true)) {
                unset($groupedSyllabus[$darsId]);
            }
        }
    }
}

ksort($groupedSyllabus);

$teacherContentHierarchy = [];
if ($isTeacher && $teacherCanContent) {
    $teacherDarsStmt = $conn->prepare('SELECT d.id, d.dars FROM class_dars cd INNER JOIN dbdars d ON d.id = cd.dars_id WHERE cd.class_id = ? AND d.codecourse = ? ORDER BY d.id ASC');
    $teacherDarsStmt->execute([$class_id, (int)$classDetails->codecourse]);
    foreach ($teacherDarsStmt->fetchAll(PDO::FETCH_OBJ) as $teacherDars) {
        if (isset($assignedDarsIds) && $assignedDarsIds !== [] && !in_array((int)$teacherDars->id, $assignedDarsIds, true)) {
            continue;
        }
        $teacherContentHierarchy[(int)$teacherDars->id] = [
            'name' => $teacherDars->dars,
            'lessons' => [],
        ];
    }

    $teacherLessonStmt = $conn->prepare('
        SELECT l.id, l.dars_id, l.name
        FROM class_lesson cl
        INNER JOIN dblesson l ON l.id = cl.lesson_id
        WHERE cl.class_id = ? AND l.codecourse = ?
        ORDER BY l.id ASC
    ');
    $teacherLessonStmt->execute([$class_id, (int)$classDetails->codecourse]);
    foreach ($teacherLessonStmt->fetchAll(PDO::FETCH_OBJ) as $teacherLesson) {
        $darsId = (int)$teacherLesson->dars_id;
        if (!isset($teacherContentHierarchy[$darsId])) {
            continue;
        }
        $teacherContentHierarchy[$darsId]['lessons'][(int)$teacherLesson->id] = [
            'name' => $teacherLesson->name,
            'movies' => [],
            'addons' => [],
        ];
    }

    foreach ($groupedSyllabus as $darsId => $darsData) {
        foreach (['movies', 'addons'] as $contentType) {
            foreach ($darsData[$contentType] as $item) {
                $lessonId = (int)($item['lesson_id'] ?? 0);
                if (!isset($teacherContentHierarchy[$darsId])) {
                    $teacherContentHierarchy[$darsId] = [
                        'name' => $darsData['dars_name'],
                        'lessons' => [],
                    ];
                }
                if (!isset($teacherContentHierarchy[$darsId]['lessons'][$lessonId])) {
                    $teacherContentHierarchy[$darsId]['lessons'][$lessonId] = [
                        'name' => $item['lesson_name'] ?: 'بدون سرفصل',
                        'movies' => [],
                        'addons' => [],
                    ];
                }
                $teacherContentHierarchy[$darsId]['lessons'][$lessonId][$contentType][] = $item;
            }
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
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>اطلاعات کلاس | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .inner-table th { background-color: #f8f9fa; font-weight: 500; }
        .inner-table td, .inner-table th { border-color: #e8e8f7 !important; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; padding-right: 10px; border-right: 3px solid; }
        .title-lessons { border-color: #0162e8; color: #0162e8; }
        .title-movies { border-color: #f10075; color: #f10075; }
        .title-addons { border-color: #22c03c; color: #22c03c; }

        /* Minimal CSS for rotating the arrow on open */
        .btn[aria-expanded="true"] .fe-chevron-down { transform: rotate(180deg); }
        .fe-chevron-down { transition: transform 0.3s ease; }
    </style>
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

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <div class="d-flex align-items-center mg-b-5" style="gap: 12px;">

                            <h2 class="main-content-title tx-24 mb-0">
                                جزئیات کلاس:
                                <?= htmlspecialchars($classDetails->name); ?>
                            </h2>

                            <span class="menu-info-trigger"
                                  data-tooltip="<?= $isTeacher
                                      ? 'در این بخش می‌توانید سرفصل‌های کلاس را تعریف کنید و فیلم‌ها یا جزوه‌های همین کلاس را با لینک یا بارگذاری فایل ثبت و مشاهده کنید.'
                                      : 'در این بخش می‌توانید اطلاعات جامع کلاس انتخاب‌شده را مشاهده و مدیریت کنید. مشخصات کلاس، اطلاعات پایه، دانش آموزان، جلسات، درس‌ها و سایر تنظیمات مرتبط با کلاس از این قسمت قابل بررسی است.' ?>"
                                  aria-label="راهنمای جزئیات کلاس">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </div>

                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="#">مدیریت کلاس‌ها</a>
                            </li>

                            <li class="breadcrumb-item active" aria-current="page">
                                اطلاعات جامع کلاس
                            </li>
                        </ol>
                    </div>
                </div>
                <!-- End Page Header -->

                <div class="row row-sm">
                    <!-- General Class Details Card -->
                    <div class="col-lg-12 col-md-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div>
                                    <h6 class="main-content-label mb-1">مشخصات کلی </h6>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-bordered table-striped text-md-nowrap mg-b-0">
                                        <tbody>
                                        <tr>
                                            <th class="wd-20p bg-light">نام پایه اصلی </th>
                                            <td><span class="badge badge-primary tx-14"><?= htmlspecialchars($classDetails->coursename ?? 'نامشخص') ?></span> (کد: <?= htmlspecialchars($classDetails->codecourse) ?>)</td>
                                        </tr>
                                        <tr>
                                            <th class="wd-20p bg-light">نام کلاس</th>
                                            <td><strong class="tx-15"><?= htmlspecialchars($classDetails->name) ?></strong></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isTeacher): ?>
                        <div class="col-lg-12 col-md-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
                                        <div>
                                            <h6 class="main-content-label mb-1">بخش‌های کلاس</h6>
                                            <small class="text-muted">دسترسی‌های فعال شما برای همین کلاس</small>
                                        </div>
                                        <div class="d-flex flex-wrap" style="gap: 8px;">
                                            <?php if ($teacherCanStudents): ?>
                                                <a class="btn btn-outline-primary" href="assign-student.php?class_id=<?= (int)$class_id ?>">
                                                    <i class="fe fe-users"></i> مشاهده دانش‌آموزان
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($teacherCanHomework): ?>
                                                <a class="btn btn-outline-success" href="homeworks.php?class_id=<?= (int)$class_id ?>">
                                                    <i class="fe fe-check-square"></i> مدیریت تکالیف
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($teacherCanAttendance): ?>
                                                <a class="btn btn-outline-warning" href="attendance.php?id=<?= (int)$classDetails->codecourse ?>&amp;class=<?= (int)$class_id ?>">
                                                    <i class="fe fe-user-check"></i> حضور و غیاب
                                                </a>
                                                <a class="btn btn-outline-dark" href="class-register.php?class=<?= (int)$class_id ?>">
                                                    <i class="fe fe-book"></i> دفتر کلاسی
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Assigned Content Accordion -->
                    <div class="col-lg-12 col-md-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            محتوای آموزشی اختصاص‌یافته
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="<?= $isTeacher
                                                  ? 'محتوا فقط برای همین کلاس ثبت می‌شود؛ ابتدا درس و سرفصل را انتخاب کنید و سپس فیلم یا جزوه را مشاهده یا اضافه کنید.'
                                                  : 'در این بخش می‌توانید محتوای آموزشی اختصاص‌یافته به این کلاس را به تفکیک هر درس مشاهده کنید. با کلیک روی نام هر درس، جزئیات آن شامل سرفصل‌های آموزشی، فیلم‌ها و جزوات مرتبط نمایش داده می‌شود. اگر محتوایی برای کلاس ثبت نشده باشد، پیام مربوط به نبود محتوا نمایش داده خواهد شد.' ?>"
                                              aria-label="راهنمای محتوای آموزشی اختصاص‌یافته">

                                            <i class="fe fe-info fs-4"></i>
                                        </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        <?= $isTeacher
                                            ? 'برای مشاهده فیلم‌ها و جزوه‌های تخصیص‌یافته، ابتدا درس و سپس سرفصل را انتخاب کنید.'
                                            : 'تفکیک سرفصل‌ها، فیلم‌ها و جزوات بر اساس هر درس (جهت مشاهده جزئیات روی هر درس کلیک کنید).' ?>
                                    </p>
                                </div>

                                <?php if ($isTeacher): ?>
                                    <?php if (!$teacherCanContent): ?>
                                        <div class="alert alert-light border text-center mg-b-0 mt-3">
                                            مجوز مشاهده و مدیریت محتوای آموزشی برای این حساب فعال نیست.
                                        </div>
                                    <?php elseif (!empty($teacherContentHierarchy)): ?>
                                        <div class="d-flex flex-wrap mb-3" style="gap: 8px;">
                                            <a class="btn btn-success" href="teacher-upload-content.php?class_id=<?= (int)$class_id ?>">
                                                <i class="fe fe-upload"></i> افزودن فیلم یا جزوه
                                            </a>
                                            <a class="btn btn-outline-primary disabled" id="teacher-lesson-manage-link" href="#" aria-disabled="true">
                                                <i class="fe fe-list"></i> تعریف و مشاهده سرفصل
                                            </a>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="col-lg-6 col-md-6 mb-3">
                                                <label for="teacher-dars-select" class="font-weight-bold">۱. انتخاب درس</label>
                                                <select id="teacher-dars-select" class="form-control">
                                                    <option value="">یک درس را انتخاب کنید</option>
                                                    <?php foreach ($teacherContentHierarchy as $darsId => $darsData): ?>
                                                        <option value="<?= (int)$darsId ?>"><?= htmlspecialchars($darsData['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-lg-6 col-md-6 mb-3">
                                                <label for="teacher-lesson-select" class="font-weight-bold">۲. انتخاب سرفصل</label>
                                                <select id="teacher-lesson-select" class="form-control" disabled>
                                                    <option value="">ابتدا درس را انتخاب کنید</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div id="teacher-content-guide" class="alert alert-info text-center mb-0">
                                            برای مشاهده فیلم‌ها و جزوه‌ها، ابتدا درس و سپس سرفصل را انتخاب کنید.
                                        </div>

                                        <div id="teacher-content-results" class="mt-3">
                                            <?php foreach ($teacherContentHierarchy as $darsId => $darsData): ?>
                                                <?php foreach ($darsData['lessons'] as $lessonId => $lessonData): ?>
                                                    <div class="teacher-content-panel d-none"
                                                         data-dars-id="<?= (int)$darsId ?>"
                                                         data-lesson-id="<?= (int)$lessonId ?>">
                                                        <div class="alert alert-light border">
                                                            <strong>درس:</strong> <?= htmlspecialchars($darsData['name']) ?>
                                                            <span class="mx-2">|</span>
                                                            <strong>سرفصل:</strong> <?= htmlspecialchars($lessonData['name']) ?>
                                                        </div>

                                                        <?php if (!empty($lessonData['movies'])): ?>
                                                            <div class="section-title title-movies mt-3">فیلم‌های این سرفصل</div>
                                                            <div class="table-responsive mb-4">
                                                                <table class="table inner-table table-bordered text-center mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="wd-10p">ردیف</th>
                                                                        <th>عنوان فیلم</th>
                                                                        <th class="wd-20p">عملیات</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php $movieRow = 1; foreach ($lessonData['movies'] as $movie): ?>
                                                                        <tr>
                                                                            <td><?= $movieRow++ ?></td>
                                                                            <td class="text-right pd-r-20"><i class="fe fe-video text-danger me-2"></i> <?= htmlspecialchars($movie['name']) ?></td>
                                                                            <td>
                                                                                <a class="btn btn-sm btn-danger"
                                                                                   href="teacher-content.php?class_id=<?= $class_id ?>&amp;type=movie&amp;id=<?= (int)$movie['id'] ?>"
                                                                                   target="_blank" rel="noopener noreferrer">
                                                                                    <i class="fe fe-play-circle"></i> مشاهده فیلم
                                                                                </a>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($lessonData['addons'])): ?>
                                                            <div class="section-title title-addons mt-3">جزوه‌های این سرفصل</div>
                                                            <div class="table-responsive mb-2">
                                                                <table class="table inner-table table-bordered text-center mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="wd-10p">ردیف</th>
                                                                        <th>عنوان جزوه</th>
                                                                        <th class="wd-20p">عملیات</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php $addonRow = 1; foreach ($lessonData['addons'] as $addon): ?>
                                                                        <tr>
                                                                            <td><?= $addonRow++ ?></td>
                                                                            <td class="text-right pd-r-20"><i class="fe fe-file-text text-success me-2"></i> <?= htmlspecialchars($addon['name']) ?></td>
                                                                            <td>
                                                                                <a class="btn btn-sm btn-success"
                                                                                   href="teacher-content.php?class_id=<?= $class_id ?>&amp;type=addon&amp;id=<?= (int)$addon['id'] ?>"
                                                                                   target="_blank" rel="noopener noreferrer">
                                                                                    <i class="fe fe-download"></i> دریافت جزوه
                                                                                </a>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (empty($lessonData['movies']) && empty($lessonData['addons'])): ?>
                                                            <div class="alert alert-warning text-center mb-0">
                                                                هنوز فیلم یا جزوه‌ای برای این سرفصل ثبت نشده است.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning text-center mg-b-0 mt-3" role="alert">
                                            هیچ فیلم یا جزوه‌ای برای این کلاس تخصیص داده نشده است.
                                        </div>
                                    <?php endif; ?>

                                <?php elseif (!empty($groupedSyllabus)): ?>

                                    <!-- Start Accordion Container -->
                                    <div class="accordion mt-4" id="accordionSyllabus">

                                        <?php foreach ($groupedSyllabus as $dars_id => $darsData): ?>
                                            <div class="card shadow-none border mb-3 rounded">

                                                <!-- Accordion Header (Styled as a distinct Button Bar) -->
                                                <div class="card-header p-0 bg-transparent border-bottom-0" id="heading-<?= $dars_id ?>">
                                                    <!-- Using pure Bootstrap classes for the wide button look -->
                                                    <button class="btn btn-light btn-block text-right d-flex justify-content-between align-items-center p-3 m-0 rounded"
                                                            type="button"
                                                            data-toggle="collapse"
                                                            data-bs-toggle="collapse"
                                                            data-target="#collapse-<?= $dars_id ?>"
                                                            data-bs-target="#collapse-<?= $dars_id ?>"
                                                            aria-expanded="false"
                                                            aria-controls="collapse-<?= $dars_id ?>">
                                                        <span class="tx-16 font-weight-bold text-dark">
                                                            <i class="fe fe-book-open text-primary me-2 mr-2"></i>
                                                            درس: <?= htmlspecialchars($darsData['dars_name']) ?>
                                                        </span>
                                                        <i class="fe fe-chevron-down tx-20 text-muted"></i>
                                                    </button>
                                                </div>

                                                <!-- Accordion Body (Collapsible) -->
                                                <div id="collapse-<?= $dars_id ?>" class="collapse" aria-labelledby="heading-<?= $dars_id ?>" data-parent="#accordionSyllabus" data-bs-parent="#accordionSyllabus">
                                                    <div class="card-body p-4 border-top mt-2 bg-white rounded-bottom">

                                                        <!-- Lessons Table -->
                                                        <?php if (!empty($darsData['lessons'])): ?>
                                                            <div class="section-title title-lessons mt-2"> سرفصل‌های این درس در <?= htmlspecialchars($classDetails->name) ?></div>
                                                            <div class="table-responsive mb-4">
                                                                <table class="table inner-table table-bordered text-center mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="wd-10p">ردیف</th>
                                                                        <th>نام سرفصل</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php $c = 1; foreach ($darsData['lessons'] as $lesson): ?>
                                                                        <tr>
                                                                            <td><?= $c++ ?></td>
                                                                            <td class="text-right pd-r-20"><?= htmlspecialchars($lesson['name']) ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Movies Table -->
                                                        <?php if (!empty($darsData['movies'])): ?>
                                                            <div class="section-title title-movies mt-2">فیلم‌های این درس در <?= htmlspecialchars($classDetails->name) ?></div>
                                                            <div class="table-responsive mb-4">
                                                                <table class="table inner-table table-bordered text-center mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="wd-10p">ردیف</th>
                                                                        <th>عنوان فیلم</th>
                                                                        <?php if ($isTeacher): ?>
                                                                            <th class="wd-20p">عملیات</th>
                                                                        <?php endif; ?>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php $c = 1; foreach ($darsData['movies'] as $movie): ?>
                                                                        <tr>
                                                                            <td><?= $c++ ?></td>
                                                                            <td class="text-right pd-r-20"><i class="fe fe-video text-danger me-2"></i> <?= htmlspecialchars($movie['name']) ?></td>
                                                                            <?php if ($isTeacher): ?>
                                                                                <td>
                                                                                    <a class="btn btn-sm btn-danger"
                                                                                       href="teacher-content.php?class_id=<?= $class_id ?>&amp;type=movie&amp;id=<?= (int)$movie['id'] ?>"
                                                                                       target="_blank" rel="noopener noreferrer">
                                                                                        <i class="fe fe-play-circle"></i> مشاهده فیلم
                                                                                    </a>
                                                                                </td>
                                                                            <?php endif; ?>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Addons Table -->
                                                        <?php if (!empty($darsData['addons'])): ?>
                                                            <div class="section-title title-addons mt-2">جزوات این درس در <?= htmlspecialchars($classDetails->name) ?></div>
                                                            <div class="table-responsive mb-2">
                                                                <table class="table inner-table table-bordered text-center mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th class="wd-10p">ردیف</th>
                                                                        <th>عنوان جزوه</th>
                                                                        <?php if ($isTeacher): ?>
                                                                            <th class="wd-20p">عملیات</th>
                                                                        <?php endif; ?>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php $c = 1; foreach ($darsData['addons'] as $addon): ?>
                                                                        <tr>
                                                                            <td><?= $c++ ?></td>
                                                                            <td class="text-right pd-r-20"><i class="fe fe-file-text text-success me-2"></i> <?= htmlspecialchars($addon['name']) ?></td>
                                                                            <?php if ($isTeacher): ?>
                                                                                <td>
                                                                                    <a class="btn btn-sm btn-success"
                                                                                       href="teacher-content.php?class_id=<?= $class_id ?>&amp;type=addon&amp;id=<?= (int)$addon['id'] ?>"
                                                                                       target="_blank" rel="noopener noreferrer">
                                                                                        <i class="fe fe-download"></i> دریافت جزوه
                                                                                    </a>
                                                                                </td>
                                                                            <?php endif; ?>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php endif; ?>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>
                                    <!-- End Accordion Container -->

                                <?php else: ?>
                                    <div class="alert alert-warning text-center mg-b-0 mt-3" role="alert">
                                        هیچ سرفصل، فیلم یا جزوه‌ای برای این کلاس ثبت نشده است.
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
<!--                    end here-->
                </div>
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <?php if ($isTeacher && $teacherCanContent && !empty($teacherContentHierarchy)): ?>
        <?php
        $teacherLessonOptions = [];
        foreach ($teacherContentHierarchy as $darsId => $darsData) {
            $teacherLessonOptions[(string)$darsId] = [];
            foreach ($darsData['lessons'] as $lessonId => $lessonData) {
                $teacherLessonOptions[(string)$darsId][(string)$lessonId] = $lessonData['name'];
            }
        }
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const lessonsByDars = <?= json_encode($teacherLessonOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const darsSelect = document.getElementById('teacher-dars-select');
                const lessonSelect = document.getElementById('teacher-lesson-select');
                const lessonManageLink = document.getElementById('teacher-lesson-manage-link');
                const guide = document.getElementById('teacher-content-guide');
                const panels = document.querySelectorAll('.teacher-content-panel');

                function hidePanels() {
                    panels.forEach(function (panel) {
                        panel.classList.add('d-none');
                    });
                }

                darsSelect.addEventListener('change', function () {
                    const darsId = this.value;
                    const lessons = lessonsByDars[darsId] || {};
                    hidePanels();
                    lessonSelect.innerHTML = '<option value="">یک سرفصل را انتخاب کنید</option>';

                    Object.keys(lessons).forEach(function (lessonId) {
                        const option = document.createElement('option');
                        option.value = lessonId;
                        option.textContent = lessons[lessonId];
                        lessonSelect.appendChild(option);
                    });

                    lessonSelect.disabled = !darsId;
                    if (lessonManageLink) {
                        if (darsId) {
                            lessonManageLink.href = 'lesson.php?code=<?= (int)$classDetails->codecourse ?>&dars=' + encodeURIComponent(darsId) + '&class=<?= (int)$class_id ?>';
                            lessonManageLink.classList.remove('disabled');
                            lessonManageLink.removeAttribute('aria-disabled');
                        } else {
                            lessonManageLink.href = '#';
                            lessonManageLink.classList.add('disabled');
                            lessonManageLink.setAttribute('aria-disabled', 'true');
                        }
                    }
                    guide.classList.remove('d-none');
                });

                lessonSelect.addEventListener('change', function () {
                    const darsId = darsSelect.value;
                    const lessonId = this.value;
                    hidePanels();

                    if (!darsId || lessonId === '') {
                        guide.classList.remove('d-none');
                        return;
                    }

                    const selectedPanel = document.querySelector(
                        '.teacher-content-panel[data-dars-id="' + darsId + '"][data-lesson-id="' + lessonId + '"]'
                    );
                    if (selectedPanel) {
                        selectedPanel.classList.remove('d-none');
                        guide.classList.add('d-none');
                    }
                });
            });
        </script>
    <?php endif; ?>

</div>
</body>
</html>
