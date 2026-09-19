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

// notif get

$roleParam = (string)$adminRole;

$sql_notifs = "SELECT DISTINCT n.* 
               FROM `notif` n
               INNER JOIN `notif_audience` na ON n.id = na.notif_id
               WHERE (na.audience = :role OR na.audience = '000')
                 AND (
                     n.expiration_date >= NOW()
                 )
               ORDER BY n.id DESC";

$stmt_notifs = $conn->prepare($sql_notifs);
$stmt_notifs->execute([':role' => $roleParam]);
$activeNotifs = $stmt_notifs->fetchAll(PDO::FETCH_OBJ);

//news get

$sql_news = "SELECT DISTINCT n.* 
             FROM `news` n
             INNER JOIN `news_audience` na ON n.id = na.news_id
             WHERE (na.audience = :role OR na.audience = '000') 
               AND n.`status` = 1 
             ORDER BY n.`id` DESC";

$stmt_news = $conn->prepare($sql_news);
$stmt_news->execute([':role' => (string)$adminRole]);
$activeNews = $stmt_news->fetchAll(PDO::FETCH_OBJ);


if (!Permision('permission_calendar')) {
    include_once 'panel/calendar.php';
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
        .dashboard-wrap .stats-grid.stats-grid-three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        @media (max-width: 1200px) {
            .dashboard-wrap .stats-grid.stats-grid-three {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 576px) {
            .dashboard-wrap .stats-grid.stats-grid-three {
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
            <?php if ((int)$adminRole == 0) {?>
                <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                    <div class="dashboard-wrap">

                        <div class="dashboard-hero">
                            <div class="d-flex align-items-center justify-content-between" style="gap:12px;">

                                <div>
                                    <h5 class="dashboard-title d-flex align-items-center justify-content-between" style="gap:10px;">

                                        خلاصه وضعیت

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش نمای کلی از وضعیت سامانه نمایش داده می‌شود. اطلاعاتی مانند تعداد پایه‌های فعال در سال تحصیلی جاری، آمار ثبت‌نام‌ها، وضعیت دانش آموزان، همکاران و سایر شاخص‌های مهم مدیریتی در این داشبورد قرار گرفته‌اند تا بتوانید در یک نگاه وضعیت کلی سامانه را بررسی و ارزیابی کنید."
                                              aria-label="راهنمای خلاصه وضعیت">

                <i class="fe fe-info fs-4"></i>

            </span>

                                    </h5>

                                    <p class="dashboard-subtitle">
                                        داشبورد مدیریتی — نمای کلی و آمار امسال
                                    </p>

                                </div>

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
                                        <?php if (Permision('permision_manage_suporter')): ?>
                                            <a class="dropdown-item" href="<?= url('admin/panel/manager.php') ?>">
                                                <i class="fe fe-grid ml-2"></i> داشبورد مدیریتی
                                            </a>
                                        <?php endif; ?>
                                        <?php if (in_array((int)$adminRole, [1, 6, 52], true)): ?>
                                            <a class="dropdown-item" href="<?= url('admin/panel/desktop.php') ?>">
                                                <i class="fe fe-monitor ml-2"></i> میزکار من
                                            </a>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-primary" href="<?= url('admin/notifications/school_notifications.php') ?>">
                                            <i class="fe fe-bell ml-2"></i> اعلان‌های مدرسه
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <?php
                        // Yearly stats
                        $counts = countSmsInJalaliYearWindow();
                        $countSMS = $counts['total'];

                        $quizCounts = countDbquizInJalaliYearWindow();
                        $totalQuizzes = $quizCounts['quizzes'];

                        $att = countAttendanceInJalaliYearWindow();
                        $totalAttendance = $att['attendance'];

                        // Operational cards
                        $studentAcademicYear = getSchoolAcademicYearRange();
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

                        ?>

                        <div class="stats-grid stats-grid-three">

                            <a class="stat-card link-card"
                               href="<?php echo url('admin/panel/academic_year_students.php') ?>"
                               onclick="window.open(this.href,'AcademicYearStudents','width=1450,height=850,menubar=0,toolbar=0,resizable=1,left=60,top=40,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label">دانش‌آموزان</p>
                                    <p class="stat-value"><?php echo number_format($countStudents); ?></p>
                                    <p class="stat-note">ثبت‌نام‌های سال تحصیلی <?php echo htmlspecialchars($studentAcademicYear['label']); ?></p>
                                </div>
                                <div class="stat-icon icon-indigo" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z"/></svg>
                                </div>
                            </a>

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

                            <a class="stat-card link-card"
                               onclick="window.open('<?php echo url('admin/panel/course_detail.php') ?>','CourseDetail','width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                <div class="stat-left">
                                    <p class="stat-label">پایه‌های در حال ثبت‌نام</p>
                                    <p class="stat-value"><?php echo number_format($countCourses); ?></p>
                                    <p class="stat-note">مشاهده پایه‌ها و دانش‌آموزان</p>
                                </div>
                                <div class="stat-icon icon-green" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16v2H4V4zm0 4h10v2H4V8zm0 4h16v2H4v-2zm0 4h10v2H4v-2z"/></svg>
                                </div>
                            </a>

                        </div>



                        <div class="stats-grid stats-grid-three">

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
            <?php }
            if (!Permision('permission_calendar')) {
                renderCalendar();
            }

            ?>
            <div class="row mt-4 mb-4">

                <!-- ستون تابلو اعلانات -->
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

                <!-- ستون اخبار -->
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
                                          data-tooltip="در این بخش اخبار و اطلاعیه‌های مرتبط با مجموعه، دوره‌ها و سامانه آموزشی نمایش داده می‌شود. کاربران می‌توانند آخرین اخبار، رویدادها، تغییرات و اطلاع‌رسانی‌های مهم را از طریق این قسمت مشاهده کنند."
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
