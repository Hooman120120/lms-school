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
$teacherCourseTable = 'course_teacher'; // ← your mapping table (id, codecourse, teacher_username)



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
                                                    <h6 class="main-content-label mb-1">پایه های فعال </h6>
                                                    <p class="text-muted card-sub-title">
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="col-1">
                                                <a href="?paye=all"
                                                   class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'all') ? 'active' : ''; ?>">
                                                    همه
                                                </a>
                                            </div>

                                            <div class="col-6">
                                                <!-- Elementary -->
                                                <div class="btn-group dropdown me-2 mb-3">
                                                    <a href="?paye=ابتدایی"
                                                       class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ابتدایی') ? 'active' : ''; ?>">
                                                        <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'اول') ? 'active' : ''; ?>"
                                                               href="?paye=اول">کلاس اول دبستان</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوم') ? 'active' : ''; ?>"
                                                               href="?paye=دوم">کلاس دوم دبستان</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'چهارم') ? 'active' : ''; ?>"
                                                               href="?paye=چهارم">کلاس چهارم دبستان</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'پنجم') ? 'active' : ''; ?>"
                                                               href="?paye=پنجم">کلاس پنجم دبستان</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ششم') ? 'active' : ''; ?>"
                                                               href="?paye=ششم">کلاس ششم دبستان</a></li>
                                                    </ul>
                                                </div>

                                                <!-- Middle School -->
                                                <div class="btn-group dropdown me-2 mb-3">
                                                    <a href="?paye=متوسطه_اول"
                                                       class="btn btn-success shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_اول') ? 'active' : ''; ?>">
                                                        <i class="fa fa-book"></i> متوسطه اول
                                                    </a>
                                                    <button type="button"
                                                            class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هفتم') ? 'active' : ''; ?>"
                                                               href="?paye=هفتم">هفتم</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هشتم') ? 'active' : ''; ?>"
                                                               href="?paye=هشتم">هشتم</a></li>
                                                        <li>
                                                            <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'نهم') ? 'active' : ''; ?>"
                                                               href="?paye=نهم">نهم</a></li>
                                                    </ul>
                                                </div>

                                                <!-- High School -->
                                                <div class="btn-group dropdown me-2 mb-3">
                                                    <a href="?paye=متوسطه_دوم"
                                                       class="btn btn-warning shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_دوم') ? 'active' : ''; ?>">
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
                                            <?php if (Permision('permision_viewcourse')) { ?>
                                            <div class="col-2 text-left mb-3 bg">
                                                <a href="login_skyroom.php?loginPublick " target="_blank"
                                                   class="btn btn-sm btn-info"><i class="fa fa-tv"></i> ورود به کلاس
                                                    مشاوره
                                                    (عمومی) </a>
                                            </div>
                                            <?php } ?>
                                            <?php
                                            if (Permision('permision_viewtest')) {
                                                ?>
                                                <div class="col-2 text-right ">
                                                    <a href="?loginquiz" target="_blank" class="btn btn-sm btn-success"><i
                                                                class="fe fe-check-square"></i> ورود بـه سـامانـه آزمـون
                                                    </a>
                                                </div>
                                            <?php } ?>
                                        </div>


                                        <div class="table">
                                            <table class="table table-bordered mg-b-0">
                                                <thead>
                                                <tr>
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
                                                    if (!isset($_GET['paye'])) {
                                                        // If no filter param given, still return teacher-scoped (or empty) to avoid dumping all
                                                        $sqlBase = 'SELECT c.* FROM codtbl_course c';
                                                        $params  = [];

                                                        if ((int)$adminRole === 3) {
                                                            $sqlBase .= " INNER JOIN {$teacherCourseTable} t ON t.codecourse = c.codecourse AND t.teacher_username = ?";
                                                            $params[] = $currentUser;
                                                        }

                                                        $sqlBase .= ' WHERE c.activecourse = 1 AND c.course_type = 0 ORDER BY c.idcourse DESC';
                                                        $query = $conn->prepare($sqlBase);
                                                        $query->execute($params);
                                                        return $query->fetchAll(PDO::FETCH_OBJ);
                                                    }

                                                    $validPayeValues = ['all','ابتدایی','متوسطه_اول','متوسطه_دوم','اول','دوم','سوم','چهارم','پنجم','ششم','هفتم','هشتم','نهم','دهم','یازدهم','دوازدهم','ریاضی','تجربی','انسانی','فنی','کارودانش'];

                                                    $paye = $_GET['paye'];
                                                    // Base SELECT with optional teacher join
                                                    $sql = 'SELECT c.* FROM codtbl_course c';
                                                    $params = [];

                                                    if ((int)$adminRole === 3) {
                                                        $sql .= " INNER JOIN {$teacherCourseTable} t ON t.codecourse = c.codecourse AND t.teacher_username = ?";
                                                        $params[] = $currentUser;
                                                    }

                                                    $sql .= ' WHERE c.activecourse = ? AND c.course_type = 0';
                                                    $params[] = 1;

                                                    if ($paye !== 'all') {
                                                        $payeParts = explode(',', $paye);
                                                        foreach ($payeParts as $part) {
                                                            if (!in_array($part, $validPayeValues, true)) {
                                                                return []; // invalid filter → empty result
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
                                                    foreach ($result as $res) { ?>
                                                        <tr>
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
                                                                            </button>
                                                                            <div class="dropdown-menu tx-8">
                                                                                <?php if ($adminRole == 3): ?>
                                                                                    <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                    </a>
                                                                                    <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-more-vertical"></i> لیست دانش آموزان
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
                                                                                    <a class="dropdown-item" href="add-quiz.php?id=<?php echo $res->codecourse ?>">
                                                                                                <i class="fa fa-qrcode"></i> مدیریت آزمون
                                                                                            </a>  
                                                                                    <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-calendar"></i> مدیریت کلاس ها
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
                                                                                class="mdi mdi-adjust"></i></div>
                                                                <?php } else { ?>
                                                                    <div class="text-success"><i class="fe fe-wifi"></i>
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                    <div class="text-danger"><i
                                                                                class="mdi mdi-adjust"></i></div>
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
                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                <div class="col-2 text-left mb-3">
                                                    <a href="login_skyroom.php?loginPublick " target="_blank"
                                                       class="btn btn-sm btn-info"><i class="fa fa-tv"></i> ورود به کلاس
                                                        مشاوره
                                                        (عمومی) </a>
                                                </div>
                                                <?php } ?>
                                                <?php
                                                if (Permision('permision_viewtest')) {
                                                    ?>
                                                    <div class="col-2 text-right ">
                                                        <a href="?loginquiz" target="_blank"
                                                           class="btn btn-sm btn-success"><i
                                                                    class="fe fe-check-square"></i> ورود بـه سـامانـه
                                                            آزمـون </a>
                                                    </div>
                                                <?php } ?>

                                            </div>
                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
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
                                                    if ($adminRole == 3) {
                                                        $sql = "SELECT c.* FROM codtbl_course c
                                                    INNER JOIN {$teacherCourseTable} t
                                                        ON t.codecourse = c.codecourse AND t.teacher_username = ?
                                                    WHERE c.activecourse = ? AND c.course_type = 1
                                                    ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } else {
                                                        $sql = 'SELECT * FROM codtbl_course WHERE activecourse = ? AND course_type = 1 ORDER BY idcourse DESC';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([1]);
                                                    }


                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) { ?>
                                                            <tr>
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
                                                                                    <?php if ((int)$adminRole == 3): ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-more-vertical"></i> لیست دانش آموزان
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
                                                                                        <a class="dropdown-item" href="add-quiz.php?id=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fa fa-qrcode"></i> مدیریت آزمون
                                                                                                </a>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
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
                                                            <?php endif; ?>
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

                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                    <div class="col-2 text-left mb-3">
                                                        <a href="login_skyroom.php?loginPublick" target="_blank"
                                                           class="btn btn-sm btn-info">
                                                            <i class="fa fa-tv"></i> ورود به کلاس مشاوره (عمومی)
                                                        </a>
                                                    </div>
                                                <?php } ?>

                                                <?php if (Permision('permision_viewtest')) { ?>
                                                    <div class="col-2 text-right">
                                                        <a href="?loginquiz" target="_blank" class="btn btn-sm btn-success">
                                                            <i class="fe fe-check-square"></i> ورود بـه سـامانـه آزمـون
                                                        </a>
                                                    </div>
                                                <?php } ?>
                                            </div>

                                            <div class="table">
                                                <table class="table table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <th>اقدامات</th>
                                                        <?php if($adminRole!==3) :  ?>
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
                                                    // Olympiad = type 3 -> course_type = 2
                                                    if ($adminRole == 3) {
                                                        $sql = "SELECT c.* FROM codtbl_course c
                                                        INNER JOIN {$teacherCourseTable} t
                                                            ON t.codecourse = c.codecourse AND t.teacher_username = ?
                                                        WHERE c.activecourse = ? AND c.course_type = 2
                                                        ORDER BY c.idcourse DESC";
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([$currentUser, 1]);
                                                    } else {
                                                        $sql = 'SELECT * FROM codtbl_course WHERE activecourse = ? AND course_type = 2 ORDER BY idcourse DESC';
                                                        $query = $conn->prepare($sql);
                                                        $query->execute([1]);
                                                    }


                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);

                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) { ?>
                                                            <tr>

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
                                                                                    <?php if ((int)$adminRole == 3): ?>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
                                                                                        </a>
                                                                                        <a class="dropdown-item" href="list-student.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-more-vertical"></i> لیست دانش آموزان
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
                                                                                        <a class="dropdown-item" href="add-quiz.php?id=<?php echo $res->codecourse ?>">
                                                                                                    <i class="fa fa-qrcode"></i> مدیریت آزمون
                                                                                                </a>
                                                                                        <a class="dropdown-item" href="list-class.php?code=<?php echo $res->codecourse ?>">
                                                                                            <i class="fe fe-calendar"></i> مدیریت کلاس ها
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