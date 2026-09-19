<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
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
if(Permision('permision_suporter') AND Permision('permision_viewcourse')==false){
    header('location: panel/desktop.php');
    exit;
}


// username from $re
$username = $re['username'] ?? '';

// default (so code doesn't crash if user not found)
$adminRole = null;

if ($username !== '') {
    $st = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $adminRole = $st->fetchColumn();
}

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
    <title> حساب کاربری | <?php echo __TITLE ?></title>
    <?php include_once 'link.php'; ?>


</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once 'menu.php';
    include_once 'header.php';

    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <?php if ((int)$adminRole !== 3) {?>
                <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                    <div class="dashboard-wrap">

                        <div class="dashboard-hero">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="dashboard-title">خلاصه وضعیت </h5>
                                    <p class="dashboard-subtitle">داشبورد مدیریتی — نمای کلی و آمار امسال</p>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Yearly stats
                        $totalperm = countPermanetUsers();

                        $counts = countSmsInJalaliYearWindow();
                        $countSMS = $counts['total'];

                        $quizCounts = countDbquizInJalaliYearWindow();
                        $totalQuizzes = $quizCounts['quizzes'];

                        $att = countAttendanceInJalaliYearWindow();
                        $totalAttendance = $att['attendance'];

                        // Operational cards
                        $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin WHERE role=?';
                        $query_st = $conn->prepare($co_student);
                        $query_st->execute([1]);
                        $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                        $countStudents = (int)($result_student->countstudent ?? 0);

                        $co_coll = 'SELECT COUNT(iduser) as countcollague FROM dblogin WHERE role=?';
                        $query_coll = $conn->prepare($co_coll);
                        $query_coll->execute([2]);
                        $result_coll = $query_coll->fetch(PDO::FETCH_OBJ);
                        $countColl = (int)($result_coll->countcollague ?? 0);

                        $co_course = 'SELECT COUNT(idcourse) as course FROM codtbl_course WHERE courseactive=?';
                        $query_course = $conn->prepare($co_course);
                        $query_course->execute([1]);
                        $result_course = $query_course->fetch(PDO::FETCH_OBJ);
                        $countCourses = (int)($result_course->course ?? 0);

                        $datenow = date("Y-m-d");
                        $co_login = 'SELECT COUNT(id) as countlogin FROM dbh_login WHERE datelogin=?';
                        $query_login = $conn->prepare($co_login);
                        $query_login->execute([$datenow]);
                        $countloginObj = $query_login->fetch(PDO::FETCH_OBJ);
                        $countLoginsToday = (int)($countloginObj->countlogin ?? 0);
                        ?>

                        <!-- Section: Operational -->

                        <div class="stats-grid">

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">دانش‌آموزان</p>
                                    <p class="stat-value"><?php echo number_format($countStudents); ?></p>
                                    <p class="stat-note">افرادی که پنل کاربری دارند</p>
                                </div>
                                <div class="stat-icon icon-indigo" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z"/></svg>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">همکاران</p>
                                    <p class="stat-value"><?php echo number_format($countColl); ?></p>
                                    <p class="stat-note">افرادی که پنل کاربری دارند</p>
                                </div>
                                <div class="stat-icon icon-teal" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm8 2c-2.33 0-7 1.17-7 3.5V21h14v-2.5C23 14.17 18.33 13 16 13z"/></svg>
                                </div>
                            </div>

                            <a class="stat-card link-card"
                               onclick="window.open('<?php echo url('admin/panel/course_detail.php') ?>','CourseDetail','width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label">دوره‌های در حال ثبت‌نام</p>
                                    <p class="stat-value"><?php echo number_format($countCourses); ?></p>
                                    <p class="stat-note">جزئیات دوره‌ها </p>
                                </div>
                                <div class="stat-icon icon-green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16v2H4V4zm0 4h10v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2z"/></svg>
                                </div>
                            </a>

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">بازدیدهای امروز</p>
                                    <p class="stat-value"><?php echo number_format($countLoginsToday); ?></p>
                                    <p class="stat-note">ورود موفق به حساب کاربری</p>
                                </div>
                                <div class="stat-icon icon-pink" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 6c3.79 0 7.17 2.13 8.82 5.5C19.17 14.87 15.79 17 12 17s-7.17-2.13-8.82-5.5C4.83 8.13 8.21 6 12 6zm0 2c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 3a2 2 0 110 4 2 2 0 010-4z"/></svg>
                                </div>
                            </div>

                        </div>

                        <!-- Section: Yearly -->
                        <div class="section-head">
                            <div>
                                <p class="section-title">آمار امسال</p>
                                <p class="section-sub">بر اساس بازه سال شمسی (تقریبی)</p>
                            </div>
                        </div>

                        <div class="stats-grid">

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">ثبت‌نام‌های قطعی امسال</p>
                                    <p class="stat-value text-success"><?php echo number_format($totalperm); ?> <span class="small-muted">نفر</span></p>
                                </div>
                                <div class="stat-icon icon-purple" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V21h14v-4.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.93 1.97 3.45V21h7v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">پیامک‌های ارسالی امسال</p>
                                    <p class="stat-value text-info"><?php echo number_format($countSMS); ?></p>
                                </div>
                                <div class="stat-icon icon-cyan" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">آزمون‌های برگزار شده امسال</p>
                                    <p class="stat-value text-warning"><?php echo number_format($totalQuizzes); ?></p>
                                </div>
                                <div class="stat-icon icon-amber" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM7 7h10v2H7V7zm0 4h10v2H7v-2zm0 4h7v2H7v-2z"/></svg>
                                </div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">   تعداد کل کلاس های برگزار شده </p>
                                    <p class="stat-value text-dark"> ≈ <?php echo number_format($totalAttendance); ?></p>
                                </div>
                                <div class="stat-icon icon-slate" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zM19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H5V8h14v13z"/></svg>
                                </div>
                            </div>

                        </div>





                    </div>
                </div>
            <?php }  else { ?>
           <?php } ?>
        </div>
    </div>

    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
