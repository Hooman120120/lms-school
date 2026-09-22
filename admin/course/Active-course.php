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
//if (!Permision('permision_viewcourse')) {
//    header('location: ' . url('admin/index.php'));
//    die();
//}

$currentUser = $_SESSION['admin']['user'];
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
$teacherOnlineEntryEnabled = false;
$teacherCourseTable = 'course_teacher'; // ← your mapping table (id, codecourse, teacher_username)

RequireTeacherPermission('teacher_perm_courses', url('admin/index.php'));

if ($adminRole === 3 && isset($_GET['login'])) {
    http_response_code(403);
    exit(m('perm.teacher_no_online_class'));
}

if ($adminRole === 3) {
    $teacherBlockedActions = [
        'disable', 'enable', 'diactive', 'close', 'act_class', 'act_ostadclass',
        'expire_ostadclass', 'act_general', 'act_quiz', 'expire_class',
        'expire_general', 'expire_quiz'
    ];
    foreach ($teacherBlockedActions as $blockedAction) {
        if (isset($_GET[$blockedAction])) {
            http_response_code(403);
            exit(m('perm.teacher_no_course_settings'));
        }
    }
}

if ($adminRole !== 3 && StaffCourseScopeRequired($currentUser)) {
    $staffCourseRequestKeys = [
        'code', 'disable', 'enable', 'diactive', 'close', 'act_class',
        'act_ostadclass', 'expire_ostadclass', 'act_general', 'act_quiz',
        'expire_class', 'expire_general', 'expire_quiz', 'schedule'
    ];
    foreach ($staffCourseRequestKeys as $requestKey) {
        if (isset($_GET[$requestKey]) && !StaffHasCourseAccess((int)$_GET[$requestKey], $currentUser)) {
            http_response_code(403);
            exit('شما به این پایه دسترسی ندارید.');
        }
    }
}

// ------------------------------------------------------------------
// Join online class for professors (adminrole == 3)
// ------------------------------------------------------------------
// ------------------------------------------------------------------
// Join online class – supports multi‑persona for user 'h10203'
// ------------------------------------------------------------------
if (isset($_GET['login']) && isset($_GET['code'])) {
    $code = (int)$_GET['code'];
    $as   = $_GET['as'] ?? 'default';

    $username = $_SESSION['admin']['user'];
    $isSpecial = ($username === 'h10203');

    // 1) Only professors (adminrole == 3) can use this
    $adminRole = $_SESSION['admin']['adminrole'] ?? 0;
    if ((int)$adminRole !== 3) {
        $_SESSION['message'] = 'دسترسی غیرمجاز.';
        header('location: ' . url('admin/course/Active-course.php'));
        exit;
    }

    if (!TeacherPermission('teacher_perm_online_class') || !TeacherHasCourseAccess($code, $currentUser)) {
        http_response_code(403);
        exit('شما مجوز ورود به کلاس آنلاین این پایه را ندارید.');
    }

    // 2) Check class activity using professor's function
    if (!function_exists('act_ostadSkyroom') || act_ostadSkyroom($code) != 1) {
        $_SESSION['message'] = m('msg.online_class_off_for_teachers');
        header('location: ' . url('admin/course/Active-course.php'));
        exit;
    }

    // 3) Get room_id from the course
    $stmt = $conn->prepare("SELECT link_class FROM codtbl_course WHERE codecourse = ?");
    $stmt->execute([$code]);
    $course = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$course || empty($course->link_class)) {
        $_SESSION['message'] = 'آی دی کلاس skyroom تنظیم نشده است.';
        header('location: ' . url('admin/course/Active-course.php'));
        exit;
    }

    // 4) Determine user_id and nickname based on persona (only for h10203)
    $personas = [
        'ost_h' => ['user' => 'ost_h', 'nick' => 'استاد هادیان'],
        'ost_f' => ['user' => 'ost_f', 'nick' => 'استاد فتوت'],
        'ost_s' => ['user' => 'ost_s', 'nick' => 'استاد ستاری'],
        'ost_a' => ['user' => 'ost_a', 'nick' => 'استاد آقالو'],
        'ost_m' => ['user' => 'ost_m', 'nick' => 'استاد امیر مرادی 1'],      // جدید
        'ost_am' => ['user' => 'ost_am', 'nick' => 'استاد امیر مرادی 2'],      // جدید
    ];
    if ($isSpecial && isset($personas[$as])) {
        $userId   = $personas[$as]['user'];
        $nickname = $personas[$as]['nick'];
    } else {
        // Normal professor – use their own name
        $prof = retAdmin($username);
        $nickname = trim(($prof->name ?? '') . ' ' . ($prof->family ?? ''));
        if ($nickname === '') $nickname = $username;
        $userId = $username;
    }

    // 5) Call Skyroom API
    require_once '../../api/Skyroom.php';
    $api = new Skyroom(__SKY);
    $params = [
        'room_id'    => (int)$course->link_class,
        'user_id'    => $userId,
        'language'   => 'fa',
        'nickname'   => $nickname,
        'access'     => 3,
        'concurrent' => 1,
        'ttl'        => 3600
    ];
    $result = $api->call('createLoginUrl', $params);
    if (Skyroom\HttpError::IsError($result)) {
        $_SESSION['message'] = 'خطا در اتصال به skyroom: ' . $result->getMessage();
        header('location: ' . url('admin/course/Active-course.php'));
        exit;
    }

    $sky = output($result);
    $skytoken = json_decode($sky)->result;

    // 6) Log
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    $log = $conn->prepare("INSERT INTO log_skyroom(username, codecourse, datelogin, timelogin, url, ip, os) VALUES (?,?,?,?,?,?,?)");
    $log->execute([$userId, $code, $datenow, $timenow, $skytoken, $ip, $os]);

    header('Location: ' . $skytoken);
    exit;
}


if (isset($_GET['disable'])) {
    $disable = 'UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([0, $_GET['disable']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['enable'])) {
    $disable = 'UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([1, $_GET['enable']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['diactive'])) {
    $disable = 'UPDATE codtbl_course SET activecourse=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([0, $_GET['diactive']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['close'])) {
    if (Permision('permision_deletcourse')) {
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        if (!checkEntityGroupQuiz($_GET['close'])) {
            $delgroups = 'DELETE FROM `_ugroups` WHERE ugtitle=?';
            $dquiz = $ponn->prepare($delgroups);
            $dquiz->execute([$_GET['close']]);
        }
        //
        $disable = 'DELETE FROM  codtbl_course  WHERE codecourse=?';
        $qupd = $conn->prepare($disable);
        $qupd->execute([$_GET['close']]);
        header('location: ' . url('admin/course/Active-course.php'));
        die();
    }
}
if (isset($_GET['act_class'])) {
    $act = 'UPDATE codtbl_course SET act_class=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_class']]);
    $sql = 'UPDATE classes SET act_class=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_class']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['act_ostadclass'])) {
    $act = 'UPDATE codtbl_course SET act_ostadclass=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_ostadclass']]);
    $sql = 'UPDATE classes SET act_ostadclass=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_ostadclass']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['act_general'])) {
    $act = 'UPDATE codtbl_course SET act_general=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_general']]);
    $sql = 'UPDATE classes SET act_general=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_general']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['act_quiz'])) {
    $act = 'UPDATE codtbl_course SET act_quiz=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_quiz']]);
    $sql = 'UPDATE classes SET act_quiz=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_quiz']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_class'])) {
    $act = 'UPDATE codtbl_course SET act_class=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_class']]);
    $sql = 'UPDATE classes SET act_class=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_class']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_ostadclass'])) {
    $act = 'UPDATE codtbl_course SET act_ostadclass=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_ostadclass']]);
    $sql = 'UPDATE classes SET act_ostadclass=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_ostadclass']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_general'])) {
    $act = 'UPDATE codtbl_course SET act_general=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_general']]);
    $sql = 'UPDATE classes SET act_general=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_general']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_quiz'])) {
    $act = 'UPDATE codtbl_course SET act_quiz=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_quiz']]);
    $sql = 'UPDATE classes SET act_quiz=? WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_quiz']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['loginquiz'])) {
    header('location: startquiz.php?true');

}
if (isset($_GET['schedule'])) {
    $sql = "SELECT * FROM dbschedules WHERE codecourse = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['schedule']]);
    $schedule = $stmt->fetch(PDO::FETCH_OBJ);
    if ($stmt->rowCount() > 0) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $schedule->file_path;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $delete = "DELETE FROM dbschedules WHERE codecourse = ?";
        $query = $conn->prepare($delete);
        $query->execute([$_GET['schedule']]);
        $_SESSION['message'] = "برنامه هفتگی موردنظر حذف شد";
        header('Location: Active-course.php');
        die();
    } else {
        $_SESSION['message'] = "برنامه هفتگی برای این پایه تعریف نشده است";
        header('Location: Active-course.php');
        die();
    }
}

$adminRole = $_SESSION['admin']['adminrole'] ?? null;
if ($adminRole === null) {
    $stmt = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ?");
    $stmt->execute([$_SESSION['admin']['user']]);
    $adminRole = (int)$stmt->fetchColumn();
} else {
    $adminRole = (int)$adminRole;
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
    <title> پایه های فعال | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!-- <div id="global-loader">
    <img src="<//?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div> -->
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
                <div style="padding:10px;">
                    <style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.1);
                        }

                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2);
                        }

                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.25);
                        }
                    </style>
                    <ul class="nav nav-tabs mb-3 custom-tabs" id="courseTab" role="tablist">
                        <?php if ($adminRole !== 2): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="type1-tab" data-bs-toggle="tab" data-bs-target="#type1"
                                        type="button" role="tab">
                                    آموزشی
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php
                        $currentUser = $_SESSION['admin']['user'];
                        if (PermisionUser('skill_permission', $currentUser)): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="type2-tab" data-bs-toggle="tab" data-bs-target="#type2"
                                        type="button" role="tab">
                                    مهارتی
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php if (PermisionUser('olympiad_permission', $currentUser)): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="type3-tab" data-bs-toggle="tab" data-bs-target="#type3"
                                        type="button" role="tab">
                                    المپیاد
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="row row-sm">
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>


                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                        <?php if ($adminRole !== 2): ?>
                            <div class="tab-pane fade show active" id="type1" role="tabpanel">
                                <div class="col-lg-12">
                                    <div class="card custom-card">
                                        <div class="card-body">

                                            <div class="row">
                                                <div class="col-1">
                                                    <div>
                                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                            <h6 class="main-content-label mb-0">
                                                                پایه‌های فعال
                                                            </h6>

                                                            <span class="menu-info-trigger"
                                                                  data-tooltip="در این بخش می توانید لیست پایه های فعال را به صورت کلی یا با فیلتر مورد نظر مشاهده و مدیریت کنید.همچنین علاوه بر مدیریت پایه های فعال
مدیریت کلاس های مرتبط با هر پایه / دانش آموزان ثبت نام شده در هر پایه ، ویرایش و حذف پایه در این بخش قرار دارد."
                                                                  aria-label="راهنمای پایه‌های فعال">

                                                                <i class="fe fe-info fs-4"></i>
                                                            </span>

                                                        </div>

                                                        <p class="text-muted card-sub-title">
                                                            مشاهده و مدیریت پایه‌های فعال
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="col-1">
                                                    <a href="?paye=all"
                                                       class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'all') ? 'active' : ''; ?>">
                                                        همه
                                                    </a>
                                                </div>

                                                <div class="col-4">

                                                    <!-- High School -->
                                                    <div class="btn-group dropdown me-2 mb-3">
                                                        <a href="?paye=متوسطه_دوم"
                                                           class="btn btn-warning shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_دوم') ? 'active' : ''; ?>">
                                                             متوسطه دوم
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
                                                                <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم') ? 'active' : ''; ?>"
                                                                   href="?paye=دهم">دهم</a>
                                                                <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                   href="#" role="button" data-bs-toggle="dropdown"
                                                                   aria-expanded="false"></a>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,ریاضی') ? 'active' : ''; ?>"
                                                                           href="?paye=دهم,ریاضی">ریاضی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,تجربی') ? 'active' : ''; ?>"
                                                                           href="?paye=دهم,تجربی">تجربی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,انسانی') ? 'active' : ''; ?>"
                                                                           href="?paye=دهم,انسانی">انسانی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,فنی') ? 'active' : ''; ?>"
                                                                           href="?paye=دهم,فنی">فنی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,کارودانش') ? 'active' : ''; ?>"
                                                                           href="?paye=دهم,کارودانش">کارودانش</a></li>
                                                                </ul>
                                                            </li>
                                                            <!-- یازدهم -->
                                                            <li class="dropstart d-flex flex-row-reverse">
                                                                <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم') ? 'active' : ''; ?>"
                                                                   href="?paye=یازدهم">یازدهم</a>
                                                                <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                   href="#" role="button" data-bs-toggle="dropdown"
                                                                   aria-expanded="false"></a>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,ریاضی') ? 'active' : ''; ?>"
                                                                           href="?paye=یازدهم,ریاضی">ریاضی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,تجربی') ? 'active' : ''; ?>"
                                                                           href="?paye=یازدهم,تجربی">تجربی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,انسانی') ? 'active' : ''; ?>"
                                                                           href="?paye=یازدهم,انسانی">انسانی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,فنی') ? 'active' : ''; ?>"
                                                                           href="?paye=یازدهم,فنی">فنی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,کارودانش') ? 'active' : ''; ?>"
                                                                           href="?paye=یازدهم,کارودانش">کارودانش</a></li>
                                                                </ul>
                                                            </li>
                                                            <!-- دوازدهم -->
                                                            <li class="dropstart d-flex flex-row-reverse">
                                                                <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم') ? 'active' : ''; ?>"
                                                                   href="?paye=دوازدهم">دوازدهم</a>
                                                                <a class="dropdown-item dropdown-toggle" style="width: 25%;"
                                                                   href="#" role="button" data-bs-toggle="dropdown"
                                                                   aria-expanded="false"></a>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,ریاضی') ? 'active' : ''; ?>"
                                                                           href="?paye=دوازدهم,ریاضی">ریاضی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,تجربی') ? 'active' : ''; ?>"
                                                                           href="?paye=دوازدهم,تجربی">تجربی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,انسانی') ? 'active' : ''; ?>"
                                                                           href="?paye=دوازدهم,انسانی">انسانی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,فنی') ? 'active' : ''; ?>"
                                                                           href="?paye=دوازدهم,فنی">فنی</a></li>
                                                                    <li>
                                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,کارودانش') ? 'active' : ''; ?>"
                                                                           href="?paye=دوازدهم,کارودانش">کارودانش</a></li>
                                                                </ul>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <?php if ($adminRole !== 3 && Permision('permision_viewcourse')) { ?>
                                                    <div class="col-2 text-left mb-3 bg">
                                                        
                                                    </div>
                                                <?php } ?>
                                                <?php
                                                if (Permision('permision_viewtest')) {
                                                    ?>
                                                    <div class="col-2 text-right ">
                                                        
                                                    </div>
                                                    <div class="col-2 text-right ">
                                                       
                                                    </div>
                                                <?php } ?>
                                            </div>


                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <?php if ($adminRole === 3 && $teacherOnlineEntryEnabled): ?>
                                                            <th>ورود به کلاس</th>
                                                        <?php endif; ?>
                                                        <th>اقدامات</th>
                                                        <?php if($adminRole!==3):  ?>
                                                            <th>کلاس</th>
                                                            <th>مشاوره</th>
                                                            <th>آزمون</th>
                                                        <?php endif; ?>
                                                            <th>کد پایه</th>
                                                            <th>نام پایه</th>
                                                            <th> موفق</th>
                                                            <th>منتظر</th>
                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                            <th>وضعیت ثبت نام</th>
                                                            <th>غیرفعالسازی</th>
                                                        <?php } ?>

                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php
                                                    function filter_paye($conn, $adminRole, $currentUser, $teacherCourseTable) {
                                                        $validPayeValues = ['all','ابتدایی','متوسطه_اول','متوسطه_دوم','اول','دوم','سوم','چهارم','پنجم','ششم','هفتم','هشتم','نهم','دهم','یازدهم','دوازدهم','ریاضی','تجربی','انسانی','فنی','کارودانش'];

                                                        $sql = 'SELECT DISTINCT c.* FROM codtbl_course c';
                                                        $params = [];

                                                        // Teacher check: Join course_teacher -> classes -> codtbl_course
                                                        if ((int)$adminRole === 3) {
                                                            $sql .= " INNER JOIN classes cl ON cl.codecourse = c.codecourse";
                                                            $sql .= " INNER JOIN {$teacherCourseTable} t ON t.class_id = cl.id AND t.teacher_username = ?";
                                                            $params[] = $currentUser;
                                                        } elseif (StaffCourseScopeRequired($currentUser)) {
                                                            $sql .= ' INNER JOIN school_staff_courses ssc ON ssc.codecourse = c.codecourse AND ssc.admin_username = ?';
                                                            $params[] = $currentUser;
                                                        }

                                                        $sql .= ' WHERE c.activecourse = ? AND c.course_type = 0';
                                                        $params[] = 1;

                                                        if (isset($_GET['paye']) && $_GET['paye'] !== 'all') {
                                                            $paye = $_GET['paye'];
                                                            $payeParts = explode(',', $paye);
                                                            foreach ($payeParts as $part) {
                                                                if (!in_array($part, $validPayeValues, true)) {
                                                                    return [];
                                                                }
                                                            }
                                                            if (count($payeParts) > 1) {
                                                                $sql .= ' AND (c.paye LIKE ? OR c.paye LIKE ?)';
                                                                $params[] = '%' . $payeParts[0] . '%';
                                                                $params[] = '%' . $payeParts[1] . '%';
                                                            } else {
                                                                $sql .= ' AND c.paye LIKE ?';
                                                                $params[] = '%' . $paye . '%';
                                                            }
                                                        }

                                                        $sql .= ' ORDER BY c.idcourse DESC';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute($params);
                                                        return $query->fetchAll(PDO::FETCH_OBJ);
                                                    }
                                                    $result = filter_paye($conn, $adminRole, $currentUser, $teacherCourseTable);
                                                    if (count($result) > 0) {
                                                        foreach ($result as $res) {     ?>
                                                            <tr>
                                                                <?php if ((int)$adminRole === 3 && $teacherOnlineEntryEnabled) { ?>
                                                                    <td class="wd-15 text-center">
                                                                        <?php if ($_SESSION['admin']['user'] === 'h10203'): ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && function_exists('act_ostadSkyroom') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <div class="dropdown">
                                                                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                                        <i class="fe fe-tv"></i> ورود به عنوان
                                                                                    </button>
                                                                                    <ul class="dropdown-menu">
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_h" target="_blank">استاد هادیان</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_f" target="_blank">استاد فتوت</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_s" target="_blank">استاد ستاری</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_a" target="_blank">استاد آقالو</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_m" target="_blank">استاد امیر مرادی 1</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_am" target="_blank">استاد امیر مرادی 2</a></li>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <a href="?code=<?php echo $res->codecourse ?>&login=true" class="btn btn-sm btn-primary" style="min-width: 120px;" target="_blank">
                                                                                    <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                <?php } ?>
                                                                <td class="wd-20">
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false"
                                                                                        aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown"
                                                                                        type="button">
                                                                                    اقدام <i
                                                                                            class="fas fa-caret-down mr-1"></i>
                                                                                    <span class="menu-info-trigger"
                                                                                          data-tooltip="<?php echo m('tooltip.course_management_menu') ?>"
                                                                                          aria-label="راهنمای منوی اقدام"
                                                                                          onclick="event.preventDefault(); event.stopPropagation();">
                                                                                        <i class="fe fe-info fs-4"></i>
                                                                                </button>
                                                                                <div class="dropdown-menu tx-8">
                                                                                    <?php if ((int)$adminRole === 3): ?>
                                                                                    <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-calendar"></i> مشاهده کلاس‌ها
                                                                                    </a>
                                                                                    <?php else: ?>
<!--                                                                                        <a class="dropdown-item" href="list-student.php?code=--><?php //echo $res->codecourse ?><!--">-->
<!--                                                                                            <i class="fe fe-more-vertical"></i> لیست دانش آموزان-->
<!--                                                                                        </a>-->
                                                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                                                            <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-info" href="?act_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی کلاس
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع کلاس
                                                                                                </a>
                                                                                            <?php } ?>


                                                                                            <?php if (act_ostadSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-info" href="?act_ostadclass=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> <?php echo m('action.activate_class_for_teacher') ?>
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_ostadclass=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i>  <?php echo m('action.stop_class_for_teacher') ?>
                                                                                                </a>
                                                                                            <?php } ?>


                                                                                            <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-primary" href="?act_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی مشاوره
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع مشاوره
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-secondary" href="?act_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-grid"></i> فعالسازی آزمون
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-stop-circle"></i> قطع آزمون
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <a class="dropdown-item text-primary" href="<?php echo 'login_skyroom.php?loginskyroom=' . $res->codecourse ?>" target="_blank">
                                                                                                <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                            </a>
                                                                                            <hr>
                                                                                            <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                                <i class="fe fe-more-vertical"></i> لیست دانش آموزان
                                                                                            </a>
                                                                                        <?php } ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="teacher-assign.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-airplay"></i> <?php echo m('action.assign_teacher') ?>
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="lesson-plans.php?course=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-book-open"></i> <?php echo m('page.lesson_plans') ?>
                                                                                        </a>
                                                                                        <!-- <a class="dropdown-item" href="attendance.php?id=<//?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-check-square"></i> افزودن حضور و غیاب
                                                                                        </a> -->
                                                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                                                            <hr>
                                                                                            <a class="dropdown-item text-success" href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>">
                                                                                                <i class="fe fe-edit"></i> ویرایش پایه
                                                                                            </a>
                                                                                            <?php if (Permision('permision_deletcourse')) { ?>
                                                                                                <a class="dropdown-item text-danger" href="?close=<?php echo $res->codecourse ?>" onclick="return confirm('آیا با حذف این پایه موافقید؟')">
                                                                                                    <i class="fe fe-x-square"></i> حذف دائم پایه
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                        <?php } ?>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                </td>
                                                                <?php if($adminRole!==3):  ?>
                                                                    <td class="wd-15">
                                                                        <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i
                                                                                        class="mdi mdi-adjust"></i>
                                                                            </div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="wd-15">
                                                                        <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i
                                                                                        class="mdi mdi-adjust"></i>
                                                                            </div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="wd-15">
                                                                        <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i
                                                                                        class="mdi mdi-adjust"></i></div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </td>
                                                                <?php  endif; ?>
                                                                <td class="wd-20"><?php echo $res->codecourse ?></td>
                                                                <td><?php echo htmlentities($res->coursename) ?></td>
                                                                <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse, 1) ?></td>
                                                                <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse, 0) ?></td>
                                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                                    <td class="wd-20">
                                                                        <?php if ($res->courseactive == 1) { ?>
                                                                            <a href="?disable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-success btn-icon"><i
                                                                                        class="fe fe-check"></i></a>
                                                                        <?php } else { ?>
                                                                            <a href="?enable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                                        class="fe fe-x"></i></a>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="wd-20">
                                                                        <a href="?diactive=<?php echo $res->codecourse ?>"
                                                                           class="btn btn-sm ripple btn-warning btn-icon"><i
                                                                                    class="si si-minus"></i></a>
                                                                    </td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php }
                                                    } else {
                                                        echo '<tr><td colspan="10" class="text-center">هیچ پایه‌ای یافت نشد.</td></tr>';
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endif; ?>


                        <!-- Type 2 tab -->
                        <?php
                        $currentUser = $_SESSION['admin']['user'];
                        if (PermisionUser('skill_permission', $currentUser)): ?>
                            <div class="tab-pane fade" id="type2" role="tabpanel">

                                <div class="col-lg-12">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-8">
                                                    <div>
                                                        <h6 class="main-content-label mb-1">پایه های فعال </h6>
                                                        <p class="text-muted card-sub-title">
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if ($adminRole !== 3 && Permision('permision_viewcourse')) { ?>
                                                    <div class="col-2 text-left mb-3">
                                                        
                                                    </div>
                                                <?php } ?>
                                                <?php
                                                if (Permision('permision_viewtest')) {
                                                    ?>
                                                    <div class="col-2 text-right ">
                                                        
                                                    </div>
                                                <?php } ?>

                                            </div>
                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <?php if ($adminRole === 3 && $teacherOnlineEntryEnabled): ?>
                                                            <th>ورود به کلاس</th>
                                                        <?php endif; ?>
                                                        <th>اقدامات</th>
                                                        <th>کلاس</th>
                                                        <th>مشاوره</th>
                                                        <th>آزمون</th>
                                                        <th>کد پایه</th>
                                                        <th>نام پایه</th>
                                                        <th> موفق</th>
                                                        <th>منتظر</th>
                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                            <th>وضعیت ثبت نام</th>
                                                            <th>غیرفعالسازی</th>
                                                        <?php } ?>

                                                    </tr>
                                                    </thead>
                                                    <tbody>

                                                    <?php
                                                    if ((int)$adminRole === 3) {
                                                        // Teacher: Join classes -> course_teacher
                                                        $sql = "SELECT DISTINCT c.* FROM codtbl_course c
            INNER JOIN classes cl ON cl.codecourse = c.codecourse
            INNER JOIN {$teacherCourseTable} t ON t.class_id = cl.id AND t.teacher_username = ?
            WHERE c.activecourse = ? AND c.course_type = 1
            ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } elseif (StaffCourseScopeRequired($currentUser)) {
                                                        $sql = "SELECT DISTINCT c.* FROM codtbl_course c
            INNER JOIN school_staff_courses ssc ON ssc.codecourse = c.codecourse AND ssc.admin_username = ?
            WHERE c.activecourse = ? AND c.course_type = 1
            ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } else {
                                                        // Non-Teacher / Admin: output all active courses directly
                                                        $sql = "SELECT * FROM codtbl_course WHERE activecourse = ? AND course_type = 1 ORDER BY idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([1]);
                                                    }
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) { ?>
                                                            <tr>
                                                                <?php if ((int)$adminRole === 3 && $teacherOnlineEntryEnabled): ?>
                                                                    <td class="wd-15 text-center">
                                                                        <?php if ($_SESSION['admin']['user'] === 'h10203'): ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && function_exists('act_ostadSkyroom') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <div class="dropdown">
                                                                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                                        <i class="fe fe-tv"></i> ورود به عنوان
                                                                                    </button>
                                                                                    <ul class="dropdown-menu">
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_h" target="_blank">استاد هادیان</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_f" target="_blank">استاد فتوت</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_s" target="_blank">استاد ستاری</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_a" target="_blank">استاد آقالو</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_m" target="_blank">استاد امیر مرادی 1</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_am" target="_blank">استاد امیر مرادی 2</a></li>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <a href="?code=<?php echo $res->codecourse ?>&login=true" class="btn btn-sm btn-primary" style="min-width: 120px;" target="_blank">
                                                                                    <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                <?php endif; ?>

                                                                <td class="wd-20">
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false" aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown" type="button">
                                                                                    اقدام <i class="fas fa-caret-down mr-1"></i>
                                                                                </button>

                                                                                <div class="dropdown-menu tx-8">
                                                                                    <?php if ((int)$adminRole === 3): ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مشاهده کلاس‌ها
                                                                                        </a>
                                                                                    <?php else: ?>
                                                                                        <?php
                                                                                        if (Permision('permision_viewcourse')) { ?>
                                                                                            <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-info" href="?act_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی کلاس
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع کلاس
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-primary" href="?act_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی مشاوره
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع مشاوره
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-secondary" href="?act_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-grid"></i> فعالسازی آزمون
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-stop-circle"></i> قطع آزمون
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <a class="dropdown-item text-primary" href="<?php echo 'login_skyroom.php?loginskyroom=' . $res->codecourse ?>" target="_blank">
                                                                                                <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                            </a>
                                                                                            <hr>
                                                                                            <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                                <i class="fe fe-more-vertical"></i> لیست دانش آموزان
                                                                                            </a>
                                                                                        <?php } ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="teacher-assign.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-airplay"></i> <?php echo m('action.assign_teacher') ?>
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="lesson-plans.php?course=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-book-open"></i> <?php echo m('page.lesson_plans') ?>
                                                                                        </a>
                                                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                                                            <hr>
                                                                                            <a class="dropdown-item text-success" href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>">
                                                                                                <i class="fe fe-edit"></i> ویرایش پایه
                                                                                            </a>
                                                                                            <?php if (Permision('permision_deletcourse')) { ?>
                                                                                                <a class="dropdown-item text-danger" href="?close=<?php echo $res->codecourse ?>" onclick="return confirm('آیا با حذف این پایه موافقید؟')">
                                                                                                    <i class="fe fe-x-square"></i> حذف دائم پایه
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                        <?php } ?>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="wd-15">
                                                                    <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                        <div class="text-danger"><i
                                                                                    class="mdi mdi-adjust "></i>
                                                                        </div>
                                                                    <?php } else { ?>
                                                                        <div class="text-success"><i
                                                                                    class="fe fe-wifi "></i>
                                                                        </div>
                                                                    <?php } ?>
                                                                </td>
                                                                <td class="wd-15">
                                                                    <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                        <div class="text-danger"><i
                                                                                    class="mdi mdi-adjust "></i>
                                                                        </div>
                                                                    <?php } else { ?>
                                                                        <div class="text-success"><i
                                                                                    class="fe fe-wifi "></i>
                                                                        </div>
                                                                    <?php } ?>
                                                                </td>
                                                                <td class="wd-15">
                                                                    <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                        <div class="text-danger"><i
                                                                                    class="mdi mdi-adjust "></i>
                                                                        </div>
                                                                    <?php } else { ?>
                                                                        <div class="text-success"><i
                                                                                    class="fe fe-wifi "></i>
                                                                        </div>
                                                                    <?php } ?>
                                                                </td>
                                                                <td class="wd-20"><?php echo $res->codecourse ?></td>

                                                                <td><?php echo htmlentities($res->coursename) ?>
                                                                </td>
                                                                <td class="wd-20">
                                                                    <?php echo CountRegisterCourse($res->codecourse, 1) ?>
                                                                </td>
                                                                <td class="wd-20">
                                                                    <?php echo CountRegisterCourse($res->codecourse, 0) ?>
                                                                </td>
                                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                                    <td class="wd-20">
                                                                        <?php if ($res->courseactive == 1) { ?>
                                                                            <a href="?disable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-success btn-icon"><i
                                                                                        class="fe fe-check"></i></a>
                                                                        <?php } else { ?>
                                                                            <a href="?enable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                                        class="fe fe-x"></i></a>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="wd-20">
                                                                        <a href="?diactive=<?php echo $res->codecourse ?>"
                                                                           class="btn btn-sm ripple btn-warning btn-icon"><i
                                                                                    class="si si-minus"></i></a>
                                                                    </td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php }
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endif; ?>

                        <?php if (PermisionUser('olympiad_permission', $currentUser)): ?>
                            <div class="tab-pane fade" id="type3" role="tabpanel">
                                <div class="col-lg-12">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-8">
                                                    <div>
                                                        <h6 class="main-content-label mb-1">پایه های فعال </h6>
                                                        <p class="text-muted card-sub-title"></p>
                                                    </div>
                                                </div>

                                                <?php if ($adminRole !== 3 && Permision('permision_viewcourse')) { ?>
                                                    <div class="col-2 text-left mb-3">
                                                       
                                                    </div>
                                                <?php } ?>

                                                <?php if (Permision('permision_viewtest')) { ?>
                                                    <div class="col-2 text-right">
                                                        
                                                    </div>
                                                <?php } ?>
                                            </div>

                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <?php if ($adminRole === 3 && $teacherOnlineEntryEnabled): ?>
                                                            <th>ورود به کلاس</th>
                                                        <?php endif; ?>
                                                        <th>اقدامات</th>
                                                        <?php if($adminRole!==3):  ?>
                                                        <th>کلاس</th>
                                                        <th>مشاوره</th>
                                                         <th>آزمون</th>
                                                        <?php endif; ?>
                                                        <th>کد پایه</th>
                                                        <th>نام پایه</th>
                                                        <th>موفق</th>
                                                        <th>منتظر</th>
                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                            <th>وضعیت ثبت نام</th>
                                                            <th>غیرفعالسازی</th>
                                                        <?php } ?>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php
                                                    if ((int)$adminRole === 3) {
                                                        $sql = "SELECT DISTINCT c.* FROM codtbl_course c
                                                                INNER JOIN classes cl ON cl.codecourse = c.codecourse
                                                                INNER JOIN {$teacherCourseTable} t ON t.class_id = cl.id AND t.teacher_username = ?
                                                                WHERE c.activecourse = ? AND c.course_type = 2
                                                                ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } elseif (StaffCourseScopeRequired($currentUser)) {
                                                        $sql = "SELECT DISTINCT c.* FROM codtbl_course c
                                                                INNER JOIN school_staff_courses ssc ON ssc.codecourse = c.codecourse AND ssc.admin_username = ?
                                                                WHERE c.activecourse = ? AND c.course_type = 2
                                                                ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } else {
                                                        $sql = "SELECT * FROM codtbl_course WHERE activecourse = ? AND course_type = 2 ORDER BY idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([1]);
                                                    }
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);

                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) { ?>
                                                            <tr>
                                                                <?php if ((int)$adminRole === 3 && $teacherOnlineEntryEnabled): ?>
                                                                    <td class="wd-15 text-center">
                                                                        <?php if ($_SESSION['admin']['user'] === 'h10203'): ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && function_exists('act_ostadSkyroom') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <div class="dropdown">
                                                                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                                        <i class="fe fe-tv"></i> ورود به عنوان
                                                                                    </button>
                                                                                    <ul class="dropdown-menu">
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_h" target="_blank">استاد هادیان</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_f" target="_blank">استاد فتوت</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_s" target="_blank">استاد ستاری</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_a" target="_blank">استاد آقالو</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_m" target="_blank">استاد امیر مرادی 1</a></li>
                                                                                        <li><a class="dropdown-item" href="?code=<?php echo $res->codecourse ?>&login=true&as=ost_am" target="_blank">استاد امیر مرادی 2</a></li>
                                                                                    </ul>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <?php if (TeacherPermission('teacher_perm_online_class') && act_ostadSkyroom($res->codecourse) == 1): ?>
                                                                                <a href="?code=<?php echo $res->codecourse ?>&login=true" class="btn btn-sm btn-primary" style="min-width: 120px;" target="_blank">
                                                                                    <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <button class="btn btn-sm btn-secondary disabled" style="min-width: 120px;">
                                                                                    <i class="fe fe-tv-off"></i> کلاس فعال نیست
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                <?php endif; ?>
                                                                <td class="wd-20">
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false" aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown" type="button">
                                                                                    اقدام <i class="fas fa-caret-down mr-1"></i>
                                                                                </button>

                                                                                <div class="dropdown-menu tx-8">
                                                                                    <?php if ((int)$adminRole === 3): ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مشاهده کلاس‌ها
                                                                                        </a>
                                                                                    <?php else: ?>
                                                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                                                            <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-info" href="?act_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی کلاس
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_class=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع کلاس
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-primary" href="?act_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video"></i> فعالسازی مشاوره
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_general=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-video-off"></i> قطع مشاوره
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                                                <a class="dropdown-item text-secondary" href="?act_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-grid"></i> فعالسازی آزمون
                                                                                                </a>
                                                                                            <?php } else { ?>
                                                                                                <a class="dropdown-item text-warning" href="?expire_quiz=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fe fe-stop-circle"></i> قطع آزمون
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                            <a class="dropdown-item text-primary" href="<?php echo 'login_skyroom.php?loginskyroom=' . $res->codecourse ?>" target="_blank">
                                                                                                <i class="fe fe-tv"></i> ورود به کلاس آنلاین
                                                                                            </a>
                                                                                            <hr>
                                                                                            <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                                <i class="fe fe-more-vertical"></i> لیست دانش آموزان
                                                                                            </a>
                                                                                        <?php } ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="teacher-assign.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-airplay"></i> <?php echo m('action.assign_teacher') ?>
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="lesson-plans.php?course=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-book-open"></i> <?php echo m('page.lesson_plans') ?>
                                                                                        </a>
                                                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                                                            <hr>
                                                                                            <a class="dropdown-item text-success" href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>">
                                                                                                <i class="fe fe-edit"></i> ویرایش پایه
                                                                                            </a>
                                                                                            <?php if (Permision('permision_deletcourse')) { ?>
                                                                                                <a class="dropdown-item text-danger" href="?close=<?php echo $res->codecourse ?>" onclick="return confirm('آیا با حذف این پایه موافقید؟')">
                                                                                                    <i class="fe fe-x-square"></i> حذف دائم پایه
                                                                                                </a>
                                                                                            <?php } ?>
                                                                                        <?php } ?>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <?php if($adminRole!==3):  ?>
                                                                    <td class="wd-15">
                                                                        <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i></div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i></div>
                                                                        <?php } ?>
                                                                    </td>

                                                                    <td class="wd-15">
                                                                        <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i></div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i></div>
                                                                        <?php } ?>
                                                                    </td>

                                                                    <td class="wd-15">
                                                                        <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i></div>
                                                                        <?php } else { ?>
                                                                            <div class="text-success"><i class="fe fe-wifi"></i></div>
                                                                        <?php } ?>
                                                                    </td>
                                                                <?php endif; ?>

                                                                <td class="wd-20"><?php echo $res->codecourse ?></td>
                                                                <td><?php echo htmlentities($res->coursename) ?></td>
                                                                <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse, 1) ?></td>
                                                                <td class="wd-20"><?php echo CountRegisterCourse($res->codecourse, 0) ?></td>

                                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                                    <td class="wd-20">
                                                                        <?php if ($res->courseactive == 1) { ?>
                                                                            <a href="?disable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-success btn-icon"><i class="fe fe-check"></i></a>
                                                                        <?php } else { ?>
                                                                            <a href="?enable=<?php echo $res->codecourse ?>"
                                                                               class="btn btn-sm ripple btn-danger btn-icon"><i class="fe fe-x"></i></a>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="wd-20">
                                                                        <a href="?diactive=<?php echo $res->codecourse ?>"
                                                                           class="btn btn-sm ripple btn-warning btn-icon"><i class="si si-minus"></i></a>
                                                                    </td>
                                                                <?php } ?>
                                                            </tr>
                                                        <?php }
                                                    } else {
                                                        echo '<tr><td colspan="10" class="text-center">هیچ پایه‌ای یافت نشد.</td></tr>';
                                                    }
                                                    ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>


                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Get all tab buttons
        var tabs = document.querySelectorAll('#courseTab button');

        // Check if a tab was previously active
        var activeTab = localStorage.getItem('activeCourseTab');
        if (activeTab) {
            var tabToShow = document.querySelector(`#courseTab button[data-bs-target="${activeTab}"]`);
            if (tabToShow) {
                var tabInstance = new bootstrap.Tab(tabToShow);
                tabInstance.show();
            }
        }

        // Save active tab when clicked
        tabs.forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function (event) {
                localStorage.setItem('activeCourseTab', event.target.getAttribute('data-bs-target'));
            });
        });
    });
</script>

</body>

</html>
