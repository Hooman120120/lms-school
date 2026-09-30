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


// username from $re
$username = $_SESSION['admin']['user'] ?? '';

// default (so code doesn't crash if user not found)
$adminRole = null;

if ($username !== '') {
    $st = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $adminRole = $st->fetchColumn();
}

/* Each part of the dashboard is its own node of the permission tree. */
$dashboardCan = static fn(string $code): bool => AdminCanSee($code);
$dashboardCalendarKinds = [
    'executive' => $dashboardCan('dashboard.calendar.executive'),
    'academic' => $dashboardCan('dashboard.calendar.academic'),
];
$showDashboardSummary = $dashboardCan('dashboard.summary') || $dashboardCan('dashboard.desks')
    || $dashboardCan('dashboard.exam-system') || $dashboardCan('dashboard.online-events');

// notif get

$activeNotifs = ActiveDailyNotifications((string)$username);
$activeNotificationCount = inst_is('school')
    ? CountSchoolNotificationMenu((string)($_SESSION['admin']['user'] ?? ''))
    : CountInstituteNotificationMenu();

//news get

$activeNews = ActivePanelNews((string)$username);


if (in_array(true, $dashboardCalendarKinds, true)) {
    include_once 'panel/calendar.php';
}
$studentAcademicYear = getSchoolAcademicYearRange();
$currentAcademicYear = getSchoolAcademicYearRange(time());
$selectableAcademicYears = getSelectableAcademicYears();
if (empty($_SESSION['academic_year_token'])) {
    $_SESSION['academic_year_token'] = bin2hex(random_bytes(32));
}
$isArchiveView = (int)$studentAcademicYear['base_jalali_year'] !== (int)$currentAcademicYear['base_jalali_year'];
$isInstituteDashboard = inst_is('institute');
$dashboardHeroColor = '#15233f';
if (!$isInstituteDashboard && (int)$adminRole === 0) {
    $dashboardHeroColor = '#54AA9F';
} elseif ((int)$adminRole === 1) {
    $dashboardHeroColor = '#138420';
} elseif ((int)$adminRole === 2) {
    $dashboardHeroColor = '#516d9b';
} elseif ((int)$adminRole === 6) {
    $dashboardHeroColor = '#ff8a00';
} elseif ((int)$adminRole >= 51 && (int)$adminRole <= 59) {
    $dashboardHeroColor = '#244370';
}


// برای استفاده از توابع پنل آزمون
$crossPanelHelper = __DIR__ . '/../inc/cross_panel.php';
if (!function_exists('cross_panel_secret') && is_file($crossPanelHelper)) {
    require_once $crossPanelHelper;
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
    <style>
        .dashboard-wrap {
            overflow: visible;
        }
        .dashboard-hero {
            background: linear-gradient(135deg, <?= $dashboardHeroColor ?>, <?= $dashboardHeroColor ?>) !important;
            border-radius: 18px 18px 0 0;
        }
        .dashboard-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .dashboard-actions .dropdown-menu { z-index: 1050; }
        .dashboard-notification-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 40px;
            padding: 8px 15px;
            border: 0;
            border-radius: 999px;
            background: #fff;
            color: #17233f;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 5px 14px rgba(0, 0, 0, .16);
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .dashboard-notification-button:hover {
            color: #17233f;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .22);
        }
        .dashboard-notification-button .notification-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background: #e8edf5;
            color: #17233f;
            font-size: 12px;
        }
        .dashboard-notification-button.has-active-notifications {
            animation: dashboard-notification-alert 2s linear infinite;
        }
        @keyframes dashboard-notification-alert {
            0%, 49.99% {
                background: #dc2626;
                color: #fff;
                box-shadow: 0 0 0 0 rgba(220, 38, 38, .7), 0 7px 18px rgba(127, 29, 29, .45);
            }
            25% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0), 0 7px 18px rgba(127, 29, 29, .45); }
            50%, 100% {
                background: #fff;
                color: #17233f;
                box-shadow: 0 5px 14px rgba(0, 0, 0, .16);
            }
        }
        .dashboard-notification-button.has-active-notifications .notification-count {
            background: #fff;
            color: #dc2626;
        }
        .dashboard-wrap .stats-grid.stats-grid-three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .dashboard-wrap .stats-grid.stats-grid-four {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        @media (max-width: 1200px) {
            .dashboard-wrap .stats-grid.stats-grid-three {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .dashboard-wrap .stats-grid.stats-grid-four {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 576px) {
            .dashboard-wrap .stats-grid.stats-grid-three {
                grid-template-columns: 1fr;
            }
            .dashboard-wrap .stats-grid.stats-grid-four {
                grid-template-columns: 1fr;
            }
        }
    </style>

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
            <?php if ($showDashboardSummary) {?>
                <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                    <div class="dashboard-wrap">

                        <div class="dashboard-hero">
                            <div class="d-flex align-items-center justify-content-between" style="gap:12px;">

                                <div>
                                    <h5 class="dashboard-title d-flex align-items-center justify-content-between" style="gap:10px;">

                                        خلاصه وضعیت

                                        <span class="menu-info-trigger"
                                              data-tooltip="<?= $isInstituteDashboard ? 'در این بخش نمای کلی دوره‌ها، فراگیران، همکاران و سایر شاخص‌های مدیریتی آموزشگاه نمایش داده می‌شود.' : 'در این بخش نمای کلی پایه‌های فعال، دانش‌آموزان، همکاران و سایر شاخص‌های مدیریتی مدرسه نمایش داده می‌شود.' ?>"
                                              aria-label="راهنمای خلاصه وضعیت">

                <i class="fe fe-info fs-4"></i>

            </span>

                                    </h5>

                                    <p class="dashboard-subtitle">
                                        <?= $isInstituteDashboard ? 'داشبورد مدیریتی — نمای کلی مؤسسه' : 'داشبورد مدیریتی — نمای کلی و آمار سال تحصیلی ' . htmlspecialchars($studentAcademicYear['label']) ?>
                                    </p>

                                </div>

                                <div class="dashboard-actions">
                                <?php if ($dashboardCan('dashboard.desks')): ?>
                                <div class="dropdown" style="position:relative;z-index:2;">
                                    <button aria-expanded="false"
                                            aria-haspopup="true"
                                            class="btn ripple btn-light dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            type="button"
                                            style="font-size:14px;font-weight:700;">
                                        <i class="fe fe-hash ml-1"></i> چشم‌انداز کلی
                                    </button>
                                    <div class="dropdown-menu tx-13">
                                        <?php if ($dashboardCan('dashboard.desks.manager')): ?>
                                            <a class="dropdown-item" href="<?= url('admin/panel/manager.php') ?>">
                                                <i class="fe fe-grid ml-2"></i> داشبورد مدیریتی
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($dashboardCan('dashboard.desks.mine')): ?>
                                            <a class="dropdown-item" href="<?= url('admin/panel/desktop.php') ?>">
                                                <i class="fe fe-monitor ml-2"></i> میزکار من
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($dashboardCan('dashboard.summary.notifications')): ?>
                                <a class="dashboard-notification-button <?= $activeNotificationCount > 0 ? 'has-active-notifications' : '' ?>"
                                   href="<?= url(inst_is('school') ? 'admin/notifications/school_notifications.php' : 'admin/notifications/institute_notifications.php') ?>"
                                   aria-label="اعلان‌ها<?= $activeNotificationCount > 0 ? '، ' . $activeNotificationCount . ' اعلان فعال' : '' ?>">
                                    <i class="fe fe-bell"></i>
                                    <span>اعلان‌ها</span>
                                    <?php if ($activeNotificationCount > 0): ?>
                                        <span class="notification-count"><?= number_format($activeNotificationCount) ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endif; ?>
                                <?php if ($dashboardCan('dashboard.summary.academic-archive')): ?>
                                <div class="dropdown" style="position:relative;z-index:2;">
                                    <button aria-expanded="false"
                                            aria-haspopup="true"
                                            class="btn ripple btn-light dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            type="button"
                                            style="font-size:14px;font-weight:700;">
                                        <i class="fe fe-archive ml-1"></i> <?= $isArchiveView ? 'بایگانی ' . htmlspecialchars($studentAcademicYear['label']) : 'بایگانی سال‌های تحصیلی' ?>
                                    </button>
                                    <div class="dropdown-menu tx-13 p-3" style="min-width:245px;">
                                        <div class="font-weight-bold mb-2">نمایش داده‌های سال تحصیلی</div>
                                        <form method="post" action="select-academic-year.php">
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['academic_year_token']) ?>">
                                            <select class="form-control form-control-sm mb-2" name="academic_year" onchange="this.form.submit()" aria-label="انتخاب سال تحصیلی">
                                                <?php foreach ($selectableAcademicYears as $year): ?>
                                                    <option value="<?= (int)$year['base_jalali_year'] ?>" <?= (int)$year['base_jalali_year'] === (int)$studentAcademicYear['base_jalali_year'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($year['label']) ?><?= (int)$year['base_jalali_year'] === (int)$currentAcademicYear['base_jalali_year'] ? ' (سال جاری)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <noscript><button type="submit" class="btn btn-primary btn-sm btn-block">نمایش</button></noscript>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                                </div>

                            </div>
                        </div>

                        <?php
                        if (!empty($flashMessage)) {
                            echo '<div class="alert alert-success mb-3">' . htmlspecialchars((string)$flashMessage) . '</div>';
                        }
                        // Yearly stats
                        if ($dashboardCan('dashboard.summary.cards')):
                        $counts = countSmsInJalaliYearWindow();
                        $countSMS = $counts['total'];

                        $quizCounts = countDbquizInJalaliYearWindow();
                        $totalQuizzes = $quizCounts['quizzes'];

                        $att = countAttendanceInJalaliYearWindow();
                        $totalAttendance = $att['attendance'];

                        // Operational cards
                        
                        $co_student = 'SELECT COUNT(*) as countstudent FROM dbpersonal WHERE datecreated >= ? AND datecreated < ?';
                        $query_st = $conn->prepare($co_student);
                        $query_st->execute([
                            $studentAcademicYear['start_gregorian'],
                            $studentAcademicYear['end_exclusive_gregorian'],
                        ]);
                        $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                        $countStudents = (int)($result_student->countstudent ?? 0);

                        $co_coll = 'SELECT COUNT(idadmin) as countcollague FROM dbadmin WHERE COALESCE(adminrole, -1) <> 3';
                        $query_coll = $conn->prepare($co_coll);
                        $query_coll->execute();
                        $result_coll = $query_coll->fetch(PDO::FETCH_OBJ);
                        $countColl = (int)($result_coll->countcollague ?? 0);

                        $co_course = 'SELECT COUNT(idcourse) as course FROM codtbl_course WHERE courseactive=? AND activecourse=? AND course_type=?';
                        $query_course = $conn->prepare($co_course);
                        $query_course->execute([1, 1, 0]);
                        $result_course = $query_course->fetch(PDO::FETCH_OBJ);
                        $countCourses = (int)($result_course->course ?? 0);

                        $isInstitute = $isInstituteDashboard;
                        $totalLearners = $isInstitute ? allUsersCount() : $countStudents;
                        $todayVisits = 0;
                        if ($isInstitute) {
                            try {
                                $visitStmt = $conn->prepare('SELECT COUNT(*) FROM dbh_login WHERE datelogin = ?');
                                $visitStmt->execute([date('Y-m-d')]);
                                $todayVisits = (int)$visitStmt->fetchColumn();
                            } catch (PDOException $e) {
                                // Older installations without login history show zero safely.
                            }
                        }
                        $permanentEnrollments = $isInstitute ? countPermanetUsers() : 0;

                        ?>

                        <?php if ($isInstitute): ?>
                            <div class="section-head"><h6 class="section-title">آمار کل</h6></div>
                        <?php endif; ?>
                        <div class="stats-grid <?= $isInstitute ? 'stats-grid-four' : 'stats-grid-three' ?>">
                            
                            <?php if ($dashboardCan('dashboard.summary.cards.courses')): ?>
                            <a class="stat-card link-card"
                               onclick="window.open('<?php echo url('admin/panel/course_detail.php') ?>','CourseDetail','width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label"><?= $isInstitute ? 'دوره‌های برگزار شده' : 'پایه‌های در حال اجرا' ?></p>
                                    <p class="stat-value"><?php echo number_format($countCourses); ?></p>
                                    <p class="stat-note"><?= $isInstitute ? 'جزئیات دوره‌ها' : 'مشاهده پایه‌ها و دانش‌آموزان' ?></p>
                                </div>
                                <div class="stat-icon icon-green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16v2H4V4zm0 4h10v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2z"/></svg>
                                </div>
                            </a>
                            <?php endif; ?>

                            

                            <?php if ($dashboardCan('dashboard.summary.cards.staff')): ?>
                            <a class="stat-card link-card"
                               href="<?php echo url('admin/panel/staff_management.php') ?>"
                               onclick="window.open(this.href,'StaffManagement','width=1450,height=850,menubar=0,toolbar=0,resizable=1,left=60,top=40,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label">همکاران</p>
                                    <p class="stat-value"><?php echo number_format($countColl); ?></p>
                                    <p class="stat-note">همکاران ثبت‌شده در سامانه</p>
                                </div>
                                <div class="stat-icon icon-teal" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm8 2c-2.33 0-7 1.17-7 3.5V21h14v-2.5C23 14.17 18.33 13 16 13z"/></svg>
                                </div>
                            </a>
                            <?php endif; ?>

                            <?php if ($dashboardCan('dashboard.summary.cards.students')): ?>
                            <a class="stat-card link-card"
                               href="<?php echo url('admin/panel/academic_year_students.php') ?>"
                               onclick="window.open(this.href,'AcademicYearStudents','width=1450,height=850,menubar=0,toolbar=0,resizable=1,left=60,top=40,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label"><?= $isInstitute ? 'فراگیران کل' : 'دانش‌آموزان' ?></p>
                                    <p class="stat-value"><?php echo number_format($totalLearners); ?></p>
                                    <p class="stat-note"><?= $isInstitute ? 'افرادی که پنل کاربری دارند' : 'ثبت‌نام‌های سال تحصیلی ' . htmlspecialchars($studentAcademicYear['label']) ?></p>
                                </div>
                                <div class="stat-icon icon-indigo" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z"/></svg>
                                </div>
                            </a>
                            <?php endif; ?>

                            <?php if ($isInstitute && $dashboardCan('dashboard.summary.cards.achievements')): ?>
                                <div class="stat-card">
                                    <div class="stat-left">
                                        <p class="stat-label">افتخارآفرینان کل</p>
                                        <p class="stat-value"><?php echo number_format($todayVisits); ?></p>
                                        <p class="stat-note">قبولی‌های آزمون‌های کشوری</p>
                                    </div>
                                    <div class="stat-icon icon-pink" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7a4.5 4.5 0 0 1 0 9.5zm0-7.2A2.7 2.7 0 1 0 12 14.7a2.7 2.7 0 0 0 0-5.4z"/></svg>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                        <?php if ($isInstitute): ?>
                            <div class="section-head"><h6 class="section-title">آمار جاری</h6></div>
                        <?php endif; ?>
                        <div class="stats-grid <?= $isInstitute ? 'stats-grid-four' : 'stats-grid-three' ?>">
                            
                            <?php if ($dashboardCan('dashboard.summary.cards.classes')): ?>
                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label">تعداد کل کلاس‌های برگزار شده</p>
                                    <p class="stat-value text-dark"> ≈ <?php echo number_format($totalAttendance); ?></p>
                                </div>
                                <div class="stat-icon icon-slate" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zM19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H5V8h14v13z"/></svg>
                                </div>
                            </div>
                            <?php endif; ?>
                            

                            <?php if ($dashboardCan('dashboard.summary.cards.quizzes')): ?>
                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label"><?= $isInstitute ? 'آزمون‌های برگزار شده' : 'آزمون‌های برگزار شده امسال' ?></p>
                                    <p class="stat-value text-warning"><?php echo number_format($totalQuizzes); ?></p>
                                </div>
                                <div class="stat-icon icon-amber" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM7 7h10v2H7V7zm0 4h10v2H7v-2zm0 4h7v2H7v-2z"/></svg>
                                </div>
                            </div>
                            <?php endif; ?>

                            
                            <?php if ($dashboardCan($isInstitute ? 'dashboard.summary.cards.learners-current' : 'dashboard.summary.cards.messages')): ?>
                            <div class="stat-card">
                                <div class="stat-left">
                                    <p class="stat-label"><?= $isInstitute ? 'فراگیران' : 'اطلاع رسانی ها' ?></p>
                                    <p class="stat-value text-info"><?php echo number_format($isInstitute ? $totalLearners : $countSMS); ?></p>
                                </div>
                                <div class="stat-icon icon-cyan" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($isInstitute && $dashboardCan('dashboard.summary.cards.announcements')): ?>
                                <div class="stat-card">
                                    <div class="stat-left">
                                        <p class="stat-label">اطلاع‌رسانی‌ها</p>
                                        <p class="stat-value text-success"><?php echo number_format($countSMS); ?></p>
                                    </div>
                                    <div class="stat-icon icon-indigo" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm8 2c-2.33 0-7 1.17-7 3.5V21h14v-2.5C23 14.17 18.33 13 16 13z"/></svg>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                        <?php endif; ?>





                    </div>
                    <div class="row mt-4 mb-3">
    <div class="col-12">
        <div class="row" style="gap:0;">

            <!-- ورود به پنل آزمون: رنگ مدرسه طلایی و رنگ آموزشگاه یاسی است. -->
            <?php if ($dashboardCan('dashboard.exam-system') && function_exists('cross_panel_secret') && cross_panel_secret() !== ''): ?>
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="card card-box h-100"
                         style="background: <?= $isInstituteDashboard ? 'linear-gradient(135deg, #7c3aed 0%, #a855f7 100%)' : '#E8A752' ?>;
                                color:#fff;
                                border:none;
                                border-radius: 14px;
                                margin:0;">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap" style="gap:15px;">

                            <div class="d-flex align-items-center" style="gap:14px;">
                                <div style="width:50px; height:50px; border-radius:12px; background:rgba(255,255,255,0.18); display:flex; align-items:center; justify-content:center;">
                                    <i class="fe fe-external-link" style="font-size:24px; color:#fff;"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1" style="color:#fff; font-weight:700;">ورود به سامانه آزمون</h5>
                                    <p class="mb-0" style="color:rgba(255,255,255,0.85); font-size:13px;">
                                        مدیریت آزمون‌ها و مشاهده نتایج
                                    </p>
                                </div>
                            </div>

                            <a href="<?= htmlspecialchars(cross_panel_login_url((string) $_SESSION['admin']['user']), ENT_QUOTES, 'UTF-8') ?>"
                               class="btn ripple"
                               style="background:#fff; color:<?= $isInstituteDashboard ? '#7c3aed' : '#A36C16' ?>; font-weight:700; padding:10px 22px; border-radius:10px; transition: all 0.3s; white-space:nowrap;"
                               onmouseover="this.style.background='<?= $isInstituteDashboard ? '#f5f0ff' : '#fff7e7' ?>'; this.style.transform='translateY(-2px)';"
                               onmouseout="this.style.background='#fff'; this.style.transform='translateY(0)';">

                                <i class="fe fe-log-in ml-1"></i> ورود

                            </a>

                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- بخش چپ: ورود به پنل کلاس آنلاین (رنگ سبز جنگلی) -->
            <?php $chahardLoginUrl = cross_panel_chahard_login_url((string) $_SESSION['admin']['user']); ?>
            <?php if ($dashboardCan('dashboard.online-events') && $chahardLoginUrl !== ''): ?>
            <div class="col-12 col-md-6">
                <div class="card card-box h-100"
                     style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
                            color:#fff;
                            border:none;
                            border-radius: 14px;
                            margin:0;">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap" style="gap:15px;">

                        <div class="d-flex align-items-center" style="gap:14px;">
                            <div style="width:50px; height:50px; border-radius:12px; background:rgba(255,255,255,0.18); display:flex; align-items:center; justify-content:center;">
                                <i class="fe fe-video" style="font-size:24px; color:#fff;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1" style="color:#fff; font-weight:700;">ورود به سامانه برگزاری رویدادهای آنلاین</h5>
                                <p class="mb-0" style="color:rgba(255,255,255,0.85); font-size:13px;">
                                    برگزاری و مدیریت کلاس‌های آنلاین
                                </p>
                            </div>
                        </div>

                        <a href="<?= htmlspecialchars($chahardLoginUrl, ENT_QUOTES, 'UTF-8') ?>"
                           class="btn ripple"
                           style="background:#fff; color:#1b4332; font-weight:700; padding:10px 22px; border-radius:10px; transition: all 0.3s; white-space:nowrap;"
                           onmouseover="this.style.background='#eafff3'; this.style.transform='translateY(-2px)';"
                           onmouseout="this.style.background='#fff'; this.style.transform='translateY(0)';">

                            <i class="fe fe-log-in ml-1"></i> ورود

                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
                </div>
            <?php }
            if (in_array(true, $dashboardCalendarKinds, true)) {
                renderCalendar();
            }

            ?>
            <div class="row mt-4 mb-4">

                <!-- ستون تابلو اعلانات -->
                <?php if ($dashboardCan('dashboard.notice-board')): ?>
                <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                    <div class="card card-box h-100" style="background:#fff;color:#000;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3" style="gap:12px;">

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     width="22"
                                     height="22"
                                     fill="#000"
                                     viewBox="0 0 24 24">
                                    <path d="M3 10v4a1 1 0 0 0 1 1h2l3 5h2l-2.5-5H13l6 3V6l-6 3H4a1 1 0 0 0-1 1z" />
                                </svg>

                                <strong class="d-flex align-items-center" style="gap:10px;">

                                    تابلو اعلانات

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش اطلاعیه‌ها، اخبار و پیام‌های مهم سامانه نمایش داده می‌شود. مدیران می‌توانند از این قسمت اطلاع‌رسانی‌های عمومی، تغییرات، رویدادها و اطلاعیه‌های مورد نیاز را منتشر کنند تا کاربران از آخرین اخبار و اطلاعیه‌های سامانه مطلع شوند."
                                          aria-label="راهنمای تابلو اعلانات">
                                        <i class="fe fe-info fs-4"></i>
                                    </span>
                                </strong>

                            </div>

                            <?php if (count($activeNotifs) > 0): ?>
                                <div class="mt-3 pl-2" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                                    <?php foreach ($activeNotifs as $notif):
                                        $image_path_db = $notif->image_path ?? '';
                                        if (!empty($image_path_db)) {
                                            $image_url = '../' . $image_path_db;
                                            // Correct filesystem path
                                            $image_fs = __DIR__ . '/../' . $image_path_db;
                                        } else {
                                            $image_url = '';
                                            $image_fs = '';
                                        }
                                        ?>
                                        <div class="alert alert-info mb-3" style="background-color: #f8f9fa; border: 1px solid #e2e8f0; color: #333; border-radius: 8px;">
                                            <div style="line-height: 1.8; font-size: 14px;">
                                                <?php echo nl2br(htmlspecialchars($notif->text)); ?>
                                            </div>
                                            <?php if (!empty($image_path_db) && file_exists($image_fs)): ?>
                                                <div class="mt-2 d-flex justify-content-center">
                                                    <div style="position: relative; display: flex; justify-content: center; align-items: center; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;">
                                                        <img src="<?php echo htmlspecialchars($image_url); ?>"
                                                             alt="تصویر اعلان"
                                                             style="position: static; float: none; max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; display: block; margin: 0; padding: 0; border: none;"
                                                             data-bs-toggle="modal"
                                                             data-bs-target="#imageModal"
                                                             data-image-src="<?php echo htmlspecialchars($image_url); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center mt-2">
                                                <span class="badge badge-primary" style="background-color: #0d6efd;">جدید</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted" style="padding:30px 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="#888" viewBox="0 0 24 24" style="opacity:0.6; display:block; margin:0 auto;">
                                        <path d="M3 10v4a1 1 0 0 0 1 1h2l3 5h2l-2.5-5H13l6 3V6l-6 3H4a1 1 0 0 0-1 1z" />
                                    </svg>
                                    <div style="margin-top:10px;">شما در ۲۴ ساعت گذشته اعلانی ندارید</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
                <!-- ستون اخبار -->
                <?php if ($dashboardCan('dashboard.news-board')): ?>
                <div class="col-12 col-lg-6">
                    <div class="card card-box h-100" style="background:#fff;color:#000;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3" style="gap:12px;">

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     width="22"
                                     height="22"
                                     fill="#000"
                                     viewBox="0 0 24 24">
                                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                </svg>

                                <strong class="d-flex align-items-center" style="gap:10px;">

                                    اخبار

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش اخبار و اطلاعیه‌های مرتبط با مجموعه، پایه‌ها و سامانه آموزشی نمایش داده می‌شود. کاربران می‌توانند آخرین اخبار، رویدادها، تغییرات و اطلاع‌رسانی‌های مهم را از طریق این قسمت مشاهده کنند."
                                          aria-label="راهنمای اخبار">

            <i class="fe fe-info fs-4"></i>

        </span>

                                </strong>

                            </div>

                            <?php if (count($activeNews) > 0): ?>
                                <div class="mt-3 pl-2" style="max-height: 300px; overflow-y: auto; overflow-x: hidden;">
                                    <?php foreach ($activeNews as $news_item):
                                        $img_path_db = $news_item->image_path ?? '';
                                        if (!empty($img_path_db)) {
                                            $img_url = '../' . $img_path_db;   // already correct
                                            $img_fs = __DIR__ . '/../' . $img_path_db;
                                        } else {
                                            $img_url = '';
                                            $img_fs = '';
                                        }
                                        ?>
                                        <div class="alert mb-2" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px; overflow: hidden;">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="badge badge-success ml-2">خبر
                                                </span>
                                                <small class="text-muted" dir="ltr">
                                                    <?php echo function_exists('jdate') ? jdate('Y/m/d', strtotime($news_item->created_at)) : substr($news_item->created_at, 0, 10); ?>
                                                </small>
                                            </div>
                                            <div style="line-height: 1.8; font-size: 14px; word-break: break-word;">
                                                <?php echo nl2br(htmlspecialchars($news_item->text)); ?>
                                            </div>
                                            <?php if (!empty($img_path_db) && file_exists($img_fs)): ?>
                                                <div class="mt-2 d-flex justify-content-center">
                                                    <div style="position: relative; display: flex; justify-content: center; align-items: center; background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; overflow: hidden;">
                                                        <img src="<?php echo htmlspecialchars($img_url); ?>"
                                                             alt="تصویر خبر"
                                                             style="position: static; float: none; max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer; display: block; margin: 0; padding: 0; border: none;"
                                                             data-bs-toggle="modal"
                                                             data-bs-target="#imageModal"
                                                             data-image-src="<?php echo htmlspecialchars($img_url); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted" style="padding:30px 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="#888" viewBox="0 0 24 24" style="opacity:0.6; display:block; margin:0 auto;">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                                    </svg>
                                    <div style="margin-top:10px;">خبر جدیدی وجود ندارد</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
                <!-- Modal (placed once outside the row) -->
                <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">تصویر</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="modalImage" src="" alt="تصویر بزرگ" style="max-width:100%; max-height:80vh;">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('imageModal');
            if (modal) {
                modal.addEventListener('show.bs.modal', function(event) {
                    var trigger = event.relatedTarget;
                    var imgUrl = trigger.getAttribute('data-image-src');
                    document.getElementById('modalImage').src = imgUrl;
                });
            }
        });
    </script>
</body>
</html>
