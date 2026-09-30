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
if (isset($_POST['changesuporter'])) {
    AdminRequire('dashboard.desks.manager.move-supporters');
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $agent = $_SERVER["HTTP_USER_AGENT"];
        $mor = NULL;
        if (preg_match('/MSIE (\d+\.\d+);/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Edge\/\d+/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Firefox[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'application/vnd.ms-excel';
        } else if (preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        }
        if ($_FILES['fileToUpload']['type'] != $mor) {
            echo "<script>alert('   فایل  مجاز برای  بارگذاری CSV ');</script>";
        } else {
            $target_dir = '../../assets/doc/temp';
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);

            if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
                $CSVfp = fopen($target_file, "r");
                if ($CSVfp !== FALSE) {
                    $icc = 0;
                    $erorUser = array();

                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 1000000, ",");
                        if ($data) {
                            if ($icc != 0) {
                                if (checkactCourse($data[0], $data[1])) {

                                    $sql = 'UPDATE dbcourse SET suporter=? WHERE username=? AND codecourse=?';
                                    $query = $conn->prepare($sql);
                                    $query->execute([$data[2], $data[0], $data[1]]);
                                } else {
                                    array_push($erorUser, $data[0]);
                                }
                            }

                            $icc++;
                        }
                    }
                }
                fclose($CSVfp);
                unlink($target_file);
            }
        }
    }
}
$schoolNotificationCount = CountSchoolNotificationMenu((string)$_SESSION['admin']['user']);


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مدیریت پشتیبان ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        @keyframes pulseZoom {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        .pulse-zoom {
            animation: pulseZoom 2s ease-in-out infinite;
            transition: transform 0.3s ease-in-out;
        }

        .pulse-zoom:hover {
            animation-play-state: paused;
            transform: scale(1.07);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        .split-btn:hover{
            box-shadow: 0 .25rem .75rem rgba(0,0,0,.12);
            position: relative;
            z-index: 1;
        }
        .notification-alarm-card {
            transition: transform .2s ease-in-out, box-shadow .2s ease-in-out;
        }
        .notification-alarm-card.has-alert {
            animation: notificationBlink 1.2s infinite;
            border: 2px solid #dc3545;
        }
        .notification-alarm-card.has-alert:hover {
            animation-play-state: paused;
            transform: scale(1.02);
            box-shadow: 0 0 18px rgba(220, 53, 69, .65);
        }
        @keyframes notificationBlink {
            0%, 100% { background-color: #ffe5e5; }
            50% { background-color: #ff3b3b; }
        }
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

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <?php if (AdminCanSee('dashboard.desks.manager.overview') || AdminCanSee('dashboard.desks.manager.support')
                || AdminCan('dashboard.desks.manager.sales') || AdminCan('dashboard.desks.manager.move-supporters')): ?>
            <?php if (isset($icc) and $icc > 0) { ?>
                <div class="alert alert-solid-success" role="alert">
                    <button aria-label="Close" class="close" data-bs-dismiss="alert" type="button">
                        <span aria-hidden="true">&times;</span></button>
                    <strong>توجه کنید !</strong> فایل جابجایی پشتیبان ها با موفقیت بارگزاری شد
                </div>
            <?php } ?>
            <?php if (isset($erorUser) and sizeof($erorUser) > 0) { ?>
                <div class="alert alert-solid-danger" role="alert">
                    <button aria-label="Close" class="close" data-bs-dismiss="alert" type="button">
                        <span aria-hidden="true">&times;</span></button>
                    <strong>خطـــا !</strong> تعدادی از (کدملی ، کد پایه) را در سیستم پیدا نکردیم بنا براین برای افراد
                    زیر هیچ اتفاقی نیفتاد
                    <div class="col-3">
                        <table class="table table-bordered">
                            <thead>
                            <th>#</th>
                            <th>کدملی</th>
                            </thead>
                            <tbody>
                            <?php $id = 1;
                            foreach ($erorUser as $res) { ?>
                                <tr>
                                    <td class="wd-15"><?php echo $id ?></td>
                                    <td><?php echo $res ?></td>

                                </tr>
                                <?php
                                $id++;
                            }
                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>

            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-sm-12 col-lg-12 col-xl-8">
                        <!--                        here-->
                        <?php if (AdminCanSee('dashboard.desks.manager.overview')): ?>
                        <div class="row row-sm " style="background-color: #e1e1e1; border-radius: 10px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <p class="fs-5 fw-bold mb-0">بخش کل</p>
                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش خلاصه‌ای از آمار کلی سامانه نمایش داده می‌شود. اطلاعاتی مانند تعداد دانش‌آموزان، همکاران مدرسه، پایه‌های در حال اجرا و اعلان‌های نیازمند بررسی قابل مشاهده است."
                                      aria-label="راهنمای بخش کل داشبورد"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <?php if (AdminCan('dashboard.desks.manager.overview.courses')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <a class="card custom-card "
                                   style="color: #0a0a0a; cursor: pointer; text-decoration: none;"
                                   onclick="window.open('<?php echo url('admin/panel/course_detail.php') ?>',
                                           'CourseDetail',
                                           'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                    <div class="card-body">
                                        <div class="card-order">
                                            <?php
                                            $co_course = 'SELECT COUNT(idcourse) as course FROM codtbl_course WHERE activecourse = ? AND course_type = 0';
                                            $query_st = $conn->prepare($co_course);
                                            $query_st->execute([1]);
                                            $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">
                                                    پایه‌های در حال اجرا
                                                </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد پایه‌های فعال و در حال اجرای مدرسه نمایش داده می‌شود."
                                                      aria-label="راهنمای پایه‌های در حال اجرا">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left">
                                                <i class="fe fe-activity icon-size float-right text-primary"></i>
                                                <span class="font-weight-bold">
                        <?php echo number_format($result_student->course) ?>
                    </span>
                                            </h2>
                                            <p class="mb-0 mt-4 text-muted">تعداد پایه‌های فعال مدرسه</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.overview.staff')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <a class="split-btn block" style="color: #0a0a0a; cursor: pointer;"
                                   onclick="window.open('<?php echo url('admin/panel/staff_management.php') ?>',
                                           'StaffManagement',
                                           'width=1400,height=850,menubar=0,toolbar=0,resizable=1,left=60,top=30,status=0,scrollbars=1,location=0'); return false;">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <div class="card-order">
                                                <?php
                                                $co_student = 'SELECT COUNT(*) as countcollague FROM dbadmin';
                                                $query_st = $conn->prepare($co_student);
                                                $query_st->execute();
                                                $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                                ?>
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1">
                                                        همکاران
                                                    </label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="<?php echo m('tooltip.staff_count') ?>"
                                                          aria-label="راهنمای همکاران">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                <h2 class="text-left"><i
                                                            class="mdi mdi-cube icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo number_format($result_student->countcollague) ?> </span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-muted"><?php echo m('label.teachers_and_staff') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.overview.students')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 ">
                                <a class=" split-btn block" style="color: #0a0a0a; cursor: pointer;"
                                   onclick="window.open('<?php echo url('admin/panel/all_users_management.php') ?>',
                                           'lacking support',
                                           'width=1250,height=450,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">                                <div class="card custom-card">
                                        <div class="card-body">
                                            <div class="card-order">
                                                <?php
                                                $co_student = 'SELECT COUNT(*) as countstudent from dbpersonal ';
                                                $query_st = $conn->prepare($co_student);
                                                $query_st->execute();
                                                $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                                ?>
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1">
                                                        دانش آموزان
                                                    </label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش تعداد کل دانش آموزانی که دارای حساب کاربری در سامانه هستند نمایش داده می‌شود. این آمار شامل تمامی دانش آموزان ثبت‌شده بوده و می‌تواند برای بررسی وضعیت کلی کاربران سامانه مورد استفاده قرار گیرد."
                                                          aria-label="راهنمای دانش آموزان">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                <h2 class="text-left card-item-icon card-icon">
                                                    <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-muted">افرادی که پنل کاربری دارند</p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (AdminCan('dashboard.desks.manager.overview.notifications')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <a class="card custom-card notification-alarm-card <?= $schoolNotificationCount > 0 ? 'has-alert' : '' ?>"
                                   href="<?= url(inst_is('school') ? 'admin/notifications/school_notifications.php' : 'admin/notifications/institute_notifications.php') ?>"
                                   style="color:#0a0a0a;text-decoration:none;cursor:pointer;">
                                    <div class="card-body">
                                        <div class="card-order">
                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">
                                                    اعلان‌ها
                                                </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="اعلان‌های متناسب با سمت و پایه‌های تخصیص‌یافته این حساب نمایش داده می‌شود. در صورت وجود اعلان جدید، کارت به حالت هشدار قرمز و چشمک‌زن درمی‌آید."
                                                      aria-label="راهنمای اعلان‌ها"
                                                      onclick="event.preventDefault();event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left"><i
                                                        class="fe fe-bell icon-size float-right <?= $schoolNotificationCount > 0 ? 'text-danger' : 'text-primary' ?>"></i><span
                                                        class="font-weight-bold"><?= number_format($schoolNotificationCount) ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4 <?= $schoolNotificationCount > 0 ? 'text-dark font-weight-bold' : 'text-muted' ?>">
                                                <?= $schoolNotificationCount > 0 ? 'اعلان نیازمند بررسی دارید' : 'اعلان جدیدی ندارید' ?>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <br>
                        <!--                        to here-->
                        <?php if (AdminCanSee('dashboard.desks.manager.support')): ?>
                        <div class="row row-sm " style="background-color: #e1e1e1; border-radius: 10px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <p class="fs-5 fw-bold mb-0">بخش پشتیبان</p>
                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش اطلاعات و آمار مرتبط با فرآیند پشتیبانی دانش آموزان نمایش داده می‌شود. مواردی مانند پیام‌های منتظر پاسخ، دانش آموزان فاقد پشتیبانی، تأخیر در ارائه خدمات، برنامه‌های پشتیبانی امروز و آینده، تعداد گزارش‌های ثبت‌شده و وضعیت مدیریت پشتیبان‌های اختصاص‌یافته قابل مشاهده و بررسی است."
                                      aria-label="راهنمای بخش پشتیبان"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <!--                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">-->
                            <!--                                <a class="card custom-card pulse-zoom" style="color: #0a0a0a; cursor: pointer;"-->
                            <!--                                   onclick="window.open('--><?php //echo url('admin/panel/studentsummary.php/') ?>
                            <!--                                          'StudentSummary',-->
                            <!--                                           'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">-->
                            <!--                                  <div class="card-body">-->
                            <!--                                       <div class="card-order">-->
                            <?php
                            //                                            $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=?';
                            //                                            $qu_lock = $conn->prepare($co_lock);
                            //                                            $qu_lock->execute(['موفق']);
                            //                                            $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                            //                                            $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
                            //                                            $query = $conn->prepare($sql);
                            //                                            $query->execute([1, 1]);
                            //                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                            //                                            $user = array();
                            //                                            $id = 0;
                            //                                            if ($query->rowCount() > 0) {
                            //
                            //                                                foreach ($result as $res) {
                            //                                                    if (!in_array($res->username, $user)) {
                            //                                                        array_push($user, $res->username);
                            //                                                        $id++;
                            //                                                    }
                            //                                                }
                            //                                            }
                            //                                            ?>
                            <!--                                            <label class="main-content-label mb-3 pt-1">دانش آموزان </label>-->
                            <!--                                            <h2 class="text-left card-item-icon card-icon">-->
                            <!--                                                <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span-->
                            <!--                                                        class="font-weight-bold">(--><?php //echo $rec->coreg ?><!--)--><?php //echo $id ?><!-- </span>-->
                            <!--                                            </h2>-->
                            <!--                                            <p class="mb-0 mt-4 text-muted">فرد (نفرپایه)</p>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </a>-->
                            <!--                            </div>-->

                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.waiting')): ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="card-order">

                                            <?php
                                            $sql = "SELECT COUNT(*) AS total_unseen_messages FROM ( SELECT * FROM suport_message WHERE status = ? GROUP BY sender_username ) AS grouped_messages WHERE receiver_username IN ( SELECT username FROM dbadmin WHERE fname = ? OR fname = ? ) OR receiver_username = ?";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute(['sent', 'اپراتور', 'خانم', 'managers']);
                                            $unseen = $stmt->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">
                                                    منتظر پاسخ
                                                </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که از طریق گفتگوی متنی برای واحد پشتیبانی پیام ارسال کرده‌اند، اما پیام آن‌ها هنوز توسط کارشناسان مشاهده یا بررسی نشده است. این بخش برای مدیریت و پاسخگویی سریع‌تر به درخواست‌های دانش آموزان کاربرد دارد."
                                                      aria-label="راهنمای دانش آموزان منتظر پاسخ">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left"><i
                                                        class="fe fe-edit icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"><?= $unseen->total_unseen_messages ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4 text-muted">دانش آموز منتظر پاسخ پیام</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.unsupported')): ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="card-order">

                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">
                                                    پشتیبانی نشده
                                                </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="<?= htmlspecialchars(inst_is('school') ? 'در این بخش تعداد دانش‌آموزانی نمایش داده می‌شود که تاکنون هیچ سابقه دریافت پشتیبانی ندارند. هر دانش‌آموز یک بار محاسبه می‌شود.' : 'در این بخش تعداد پایه‌هایی نمایش داده می‌شود که دانش آموزان ثبت‌نام‌شده در آن‌ها تاکنون هیچ سابقه پشتیبانی دریافت نکرده‌اند. این آمار به تفکیک پایه محاسبه می‌شود؛ بنابراین اگر یک دانش آموز در چند پایه ثبت‌نام کرده باشد و تنها در یکی از پایه‌ها پشتیبانی دریافت نکرده باشد، همان پایه به‌عنوان یک مورد در این شمارنده ثبت خواهد شد.', ENT_QUOTES, 'UTF-8') ?>"
                                                      aria-label="راهنمای پایه‌های فاقد پشتیبانی">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left"><i
                                                        class="fe fe-eye-off icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"> <?php $notSupport = notSupportAdmin(2);
                                                    echo $notSupport; ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4 text-muted">نفر فاقد پشتیبانی </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.no-supporter')): ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card pulse-zoom bg-orange">
                                    <a class="" style="color: #0a0a0a; cursor: pointer;"
                                       onclick="window.open('<?php echo url('admin/panel/lacking_support.php/') ?>',
                                               'lacking support',
                                               'width=1250,height=450,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                        <div class="card-body">
                                            <div class="card-order">

                                                <?php
                                                $sql = 'SELECT COUNT(*) as countNotSuporter FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=1 AND dbcourse.activityuser=1 AND dbcourse.has_operator=0 AND dbcourse.suporter IS NULL   ';
                                                if (inst_is('school')) $sql = str_replace('COUNT(*)', 'COUNT(DISTINCT dbcourse.username)', $sql);

                                                $query_not = $conn->prepare($sql);
                                                $query_not->execute();
                                                $ref = $query_not->fetch();
                                                ?>
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1">
                                                        فاقد پشتیبان
                                                    </label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="<?= htmlspecialchars(inst_is('school') ? 'در این بخش تعداد دانش‌آموزانی نمایش داده می‌شود که در پایه فعال ثبت‌نام دارند و به مشاور نیاز دارند، اما هنوز مشاوری به آن‌ها اختصاص داده نشده است. هر دانش‌آموز یک بار محاسبه می‌شود.' : 'در این بخش تعداد ثبت‌نام‌های فعال در پایه‌ها نمایش داده می‌شود که برای آن‌ها نیاز به پشتیبان وجود دارد، اما هنوز هیچ پشتیبانی به آن‌ها اختصاص داده نشده است. این آمار بر اساس ثبت‌نام هر دانش آموز در هر پایه محاسبه می‌شود؛ بنابراین اگر یک دانش آموز در چند پایه فعال بدون پشتیبان ثبت‌نام کرده باشد، هر پایه به‌صورت یک مورد مستقل در این شمارنده محاسبه خواهد شد.', ENT_QUOTES, 'UTF-8') ?>"
                                                          aria-label="راهنمای پایه‌های فاقد پشتیبان">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                <h2 class="text-left"><i
                                                            class="fe fe-database icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo $ref['countNotSuporter'] ?> </span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-muted"><?= inst_is('school') ? 'نفر' : 'نفر دوره' ?></p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php
                            $dateconfig = delaySupportAdmin(2);
                            ?>
                            <?php if (AdminCan('dashboard.desks.manager.support.late')): ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card bg-warning">
                                    <div class="card-body">
                                        <div class="card-order ">
                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">
                                                    تاخیر در پشتیبانی
                                                </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد درخواست‌ها یا موارد پشتیبانی نمایش داده می‌شود که زمان پاسخ‌گویی یا پیگیری آن‌ها از زمان تعیین‌شده گذشته است. این آمار به شناسایی پشتیبانی‌های معوق کمک می‌کند تا مواردی که نیاز به بررسی و پیگیری سریع‌تر دارند مشخص شوند."
                                                      aria-label="راهنمای تاخیر در پشتیبانی">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left card-item-icon card-icon">
                                                <i class="fa fa-calendar-times-o icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"><?php echo $dateconfig['Last']; ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4 ">تاریخ پشتیبانی گذشته</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.today')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <div class="card custom-card bg-success">
                                    <div class="card-body">
                                        <div class="card-order">

                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">

                                                <label class="main-content-label mb-3 pt-1">
                                                    پشتیبانی های امروز
                                                </label>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد برنامه‌های پشتیبانی که برای امروز زمان‌بندی شده‌اند نمایش داده می‌شود. این آمار شامل مواردی است که نیاز به پیگیری یا ارتباط با دانش آموزان در طول روز جاری دارند و به تیم پشتیبانی برای مدیریت بهتر وظایف روزانه کمک می‌کند."
                                                      aria-label="راهنمای پشتیبانی های امروز">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left"><i
                                                        class="fa fa-calendar-check-o icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"><?php echo $dateconfig['Today']; ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4 ">برنامه ریزی شده امروز</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.tomorrow')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <div class="card custom-card bg-info">
                                    <div class="card-body">
                                        <div class="card-order">

                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">

                                                <label class="main-content-label mb-3 pt-1">
                                                    پشتیبانی فردا
                                                </label>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد برنامه‌های پشتیبانی که برای روز آینده زمان‌بندی شده‌اند نمایش داده می‌شود. این اطلاعات به تیم پشتیبانی کمک می‌کند تا از قبل برای تماس‌ها، پیگیری‌ها و وظایف روز بعد برنامه‌ریزی داشته باشند و از ایجاد تأخیر در پاسخ‌گویی جلوگیری شود."
                                                      aria-label="راهنمای پشتیبانی فردا">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left"><i
                                                        class="fe fe-loader icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"> <?php echo $dateconfig['Tomarow']; ?></span>
                                            </h2>
                                            <p class="mb-0 mt-4"> برنامه ریزی شده فردا </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- COL END -->
                            <?php if (AdminCan('dashboard.desks.manager.support.count')): ?>
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                <div class="card custom-card bg-teal pulse-zoom">
                                    <a class="" style="color: #0a0a0a; cursor: pointer;"
                                       onclick="window.open('<?php echo url('admin/panel/operatorstudents.php/') ?>',
                                               'operatorstudents',
                                               'width=1250,height=450,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                        <div class="card-body">
                                            <div class="card-order">
                                                <?php
                                                $sql = 'SELECT COUNT(*) as counReport FROM suport_report ';
                                                $exe = $conn->prepare($sql);
                                                $exe->execute();
                                                $natije = $exe->fetch();
                                                ?>
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1">
                                                        تعداد پشتیبانی
                                                    </label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش تعداد کل گزارش‌های ثبت‌شده مربوط به پشتیبانی نمایش داده می‌شود. این آمار شامل تمامی مواردی است که توسط تیم پشتیبانی ثبت شده‌اند و برای بررسی میزان فعالیت و پیگیری عملکرد پشتیبان‌ها استفاده می‌شود."
                                                          aria-label="راهنمای تعداد پشتیبانی">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                <h2 class="text-left"><i
                                                            class="si si-list icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo $natije['counReport']; ?> </span>
                                                </h2>
                                                <p class="mb-0 mt-4 "> گزارش ثبت شده </p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (AdminCan('dashboard.desks.manager.support.colors')): ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <a class="card custom-card pulse-zoom" style="color: #0a0a0a; cursor: pointer;"
                                   onclick="window.open('<?php echo url('admin/panel/studentsummary.php/') ?>',
                                           'StudentSummary',
                                           'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                    <div class="card-body">
                                        <div class="card-order">
                                            <?php
                                            $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=?';
                                            $qu_lock = $conn->prepare($co_lock);
                                            $qu_lock->execute(['موفق']);
                                            $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                            $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([1, 1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $user = array();
                                            $id = 0;
                                            if ($query->rowCount() > 0) {

                                                foreach ($result as $res) {
                                                    if (!in_array($res->username, $user)) {
                                                        array_push($user, $res->username);
                                                        $id++;
                                                    }
                                                }
                                            }
                                            ?>
                                            <div class="d-flex align-items-center mb-2" style="gap:12px;">

                                                <label class="main-content-label mb-3 pt-1">
                                                    تغییر رنگ دانش آموزان
                                                </label>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد دانش آموزان بر اساس وضعیت عملکرد و فعالیت آن‌ها نمایش داده می‌شود. دانش آموزان با توجه به معیارهای تعیین‌شده در سیستم، در دسته‌های رنگی مختلف قرار می‌گیرند تا شناسایی افراد نیازمند پیگیری، بررسی عملکرد و مدیریت بهتر آن‌ها ساده‌تر شود."
                                                      aria-label="راهنمای تغییر رنگ دانش آموزان">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <h2 class="text-left card-item-icon card-icon">
                                                <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold"><?php if (inst_is('institute')): ?>(<?php echo $rec->coreg ?>)<?php endif; ?><?php echo $id ?> </span>
                                            </h2>
                                            <p class="mb-0 mt-4 text-muted"><?= inst_is('school') ? 'فرد' : 'فرد (نفردوره)' ?></p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>

                            <!-- COL END -->
                        </div>
                        <?php endif; ?>
                        <br>
                        <?php if (AdminCan('dashboard.desks.manager.sales')): ?>
                        <div class="row row-sm" style="background-color: #E1E1E1; border-radius: 10px;">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <p class="fs-5 fw-bold mb-0">بخش کارشناس فروش</p>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش آمار و اطلاعات مرتبط با فرآیند فروش نمایش داده می‌شود. مواردی مانند ثبت موقت دانش آموزان، ثبت قطعی‌های انجام‌شده، تعداد ثبت‌های امروز و همچنین لیست اکانت‌های رد شده قابل مشاهده و پیگیری است. کارشناسان فروش می‌توانند وضعیت مشتریان احتمالی و روند تبدیل ثبت‌نام‌های موقت به قطعی را بررسی کنند."
                                      aria-label="راهنمای بخش کارشناس فروش"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card overflow-hidden" style="height:164px;">
                                    <div class="card-body p-0 h-100 d-flex flex-column">

                                        <!-- Top half -->
                                        <?php if (AdminScope('dashboard.reports.temp-sales.today') !== null): ?>
                                        <a href="<?= url('admin/panel/temp-users-sales.php?type=2') ?>"
                                           class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                           style="flex:1; transition: box-shadow .2s ease;">
                                            <div class="d-flex align-items-start justify-content-between">
                                                <div>
                                                    <?php if(AdminScope('dashboard.reports.temp-sales.today') === 'all'){
                                                        $totalToday = countTodayUsers();
                                                    }
                                                    else{
                                                        $totalToday = countTodayUsers($_SESSION['admin']['user']);
                                                    }?>
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                        <label class="main-content-label mb-2 pt-1">
                                                            ثبت موقت فعال امروز
                                                        </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد ثبت‌نام‌های موقتی که در طول امروز ایجاد شده و همچنان فعال هستند نمایش داده می‌شود. این موارد معمولاً نیاز به پیگیری، تکمیل اطلاعات یا انجام مراحل بعدی ثبت‌نام دارند."
                                                              aria-label="راهنمای ثبت موقت فعال امروز">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <i class="fe fe-database icon-size text-primary"></i>
                                            </div>
                                            <div class="text-center font-weight-bold fs-6"><?= $totalToday ?></div>
                                        </a>
                                        <?php endif; ?>

                                        <div class="border-top"></div>

                                        <!-- Bottom half -->
                                        <?php if (AdminScope('dashboard.reports.temp-sales.period') !== null): ?>
                                        <a href="<?= url('admin/panel/temp-users-sales.php?type=1') ?>"
                                           class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                           style="flex:1; transition: box-shadow .2s ease;">
                                            <?php
                                            if (AdminScope('dashboard.reports.temp-sales.period') === 'all') {
                                                $totaltemp = countUsers();
                                            } else {
                                                $totaltemp = countUsers($_SESSION['admin']['user']);
                                            } ?>
                                            <div class="d-flex align-items-start justify-content-between">
                                                <div>
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                        <label class="main-content-label mb-2 pt-1">
                                                            ثبت موقت های فعال
                                                        </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد ثبت‌نام‌های موقتی که هنوز در وضعیت فعال قرار دارند نمایش داده می‌شود. این موارد شامل ثبت‌هایی است که فرآیند ثبت‌نام آن‌ها به‌صورت کامل نهایی نشده و نیاز به پیگیری یا تکمیل مراحل ثبت‌نام دارند."
                                                              aria-label="راهنمای ثبت موقت های فعال">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <i class="fe fe-database icon-size text-primary"></i>
                                            </div>
                                            <div class="text-center"><?= $totaltemp ?></div>
                                        </a>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>

                            <style>
                                .split-btn:hover{
                                    box-shadow: 0 .25rem .75rem rgba(0,0,0,.12);
                                    position: relative;
                                    z-index: 1;
                                }
                            </style>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                <div class="card custom-card overflow-hidden" style="height:164px;">
                                    <div class="card-body p-0 h-100 d-flex flex-column">

                                        <!-- Top half -->
                                        <?php if (AdminScope('dashboard.reports.permanent-sales.today') !== null): ?>
                                        <a href="<?= url('admin/panel/permanet-users-sales.php?type=2') ?>"
                                           class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                           style="flex:1; transition: box-shadow .2s ease;">
                                            <div class="d-flex align-items-start justify-content-between">
                                                <div>
                                                    <?php if(AdminScope('dashboard.reports.permanent-sales.today') === 'all'){
                                                        $totalpermToday = countTodayPermanetUsers();
                                                    }
                                                    else{
                                                        $totalpermToday = countTodayPermanetUsers($_SESSION['admin']['user']);
                                                    }?>
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                        <label class="main-content-label mb-2 pt-1">
                                                            ثبت قطعی امروز
                                                        </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که امروز ثبت‌نام آن‌ها در پایه‌ها به‌صورت قطعی انجام شده است. این آمار شامل ثبت‌نام‌هایی است که فرآیند خرید یا ثبت‌نام نهایی شده و وضعیت آن‌ها از حالت موقت به ثبت قطعی تغییر کرده است."
                                                              aria-label="راهنمای ثبت قطعی امروز">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <i class="fe fe-database icon-size text-primary"></i>
                                            </div>
                                            <div class="text-center font-weight-bold fs-6"><?= $totalpermToday ?></div>
                                        </a>
                                        <?php endif; ?>

                                        <div class="border-top"></div>

                                        <!-- Bottom half -->
                                        <?php if (AdminScope('dashboard.reports.permanent-sales.period') !== null): ?>
                                        <a href="<?= url('admin/panel/permanet-users-sales.php?type=1') ?>"
                                           class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                           style="flex:1; transition: box-shadow .2s ease;">
                                            <?php
                                            if (AdminScope('dashboard.reports.permanent-sales.period') === 'all') {
                                                $totalperm = countPermanetUsers();
                                            } else {
                                                $totalperm = countPermanetUsers($_SESSION['admin']['user']);
                                            } ?>
                                            <div class="d-flex align-items-start justify-content-between">
                                                <div>
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">

                                                        <label class="main-content-label mb-2 pt-1">
                                                            ثبت قطعی
                                                        </label>

                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد کل دانش آموزانی نمایش داده می‌شود که ثبت‌نام آن‌ها در پایه‌ها به‌صورت قطعی انجام شده است. این آمار شامل تمامی ثبت‌نام‌های نهایی‌شده در سیستم است که وضعیت آن‌ها فعال بوده و دانش آموز امکان استفاده از پایه را دارد."
                                                              aria-label="راهنمای ثبت قطعی">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <i class="fe fe-database icon-size text-primary"></i>
                                            </div>
                                            <div class="text-center"><?= $totalperm ?></div>
                                        </a>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                            <!-- COL END -->
                            <?php
                            $dateconfig = delaySupportAdmin();
                            if (AdminScope('dashboard.reports.reactivate') !== null) {
                                ?>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                    <a class="clickable-card2" href="<?php echo url('admin/panel/reactivate_experimental.php?type=1') ?>">
                                        <div class="card custom-card bg-secondary ">
                                            <div class="card-body">
                                                <div class="card-order">

                                                    <label class="main-content-label mb-3 pt-1"> اکانت های رد شده</label>
                                                    <h2 class="text-left"><i
                                                                class="fe fe-loader icon-size float-right text-primary"></i><span
                                                                class="font-weight-bold"> *</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4"> لیست اکانت های رد شده </p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                        <?php endif; ?>
                        <br>
                        <div class="row row-sm">
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="card custom-card overflow-hidden">-->
                            <!--                                    <div class="card-header  border-bottom-0 pb-0">-->
                            <!--                                        <div>-->
                            <!--                                            <div class="d-flex">-->
                            <!--                                                <label class="main-content-label my-auto pt-2">زمانبندی دانش آموزان-->
                            <!--                                                </label>-->
                            <!---->
                            <!--                                            </div>-->
                            <!--                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> تاریخ های برنامه ریزی شده آتی- محاسبه بصورت نفر پایه </span>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                    <div class="card-body">-->
                            <!--                                        <div class="row">-->
                            <!--                                            <table class="table table-bordered">-->
                            <!--                                                <thead>-->
                            <!--                                                <tr class="bg-light">-->
                            <!--                                                    <th>تاریخ</th>-->
                            <!--                                                    <th class="wd-25">تعداد</th>-->
                            <!--                                                </tr>-->
                            <!--                                                </thead>-->
                            <!--                                                <tbody>-->
                            <!--                                                --><?php
                            //                                                $sql = 'SELECT COUNT(*) as countdate,`afterdate` FROM `suport_user` LEFT JOIN dbcourse ON suport_user.username=dbcourse.username LEFT JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse  WHERE  dbcourse.activityuser=1 AND codtbl_course.activecourse=1 GROUP BY `afterdate` ORDER BY afterdate ASC ';
                            //                                                $wqu = $conn->prepare($sql);
                            //                                                $wqu->execute();
                            //                                                $username = array();
                            //                                                $require = $wqu->fetchAll(PDO::FETCH_OBJ);
                            //                                                if ($wqu->rowCount() > 0) {
                            //                                                    foreach ($require as $res) {
                            //                                                        ?>
                            <!--                                                        <tr>-->
                            <!--                                                            <td>-->
                            <!--                                                                --><?php
                            //                                                                $last = $res->afterdate;
                            //                                                                $last = explode('-', $last);
                            //                                                                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                            //                                                                $last = implode('/', $last);
                            //                                                                echo $last;
                            //                                                                ?>
                            <!--                                                            </td>-->
                            <!--                                                            <td>--><?php //echo $res->countdate ?><!--</td>-->
                            <!--                                                        </tr>-->
                            <!--                                                        --><?php
                            //                                                    }
                            //                                                }
                            //                                                ?>
                            <!--                                                </tbody>-->
                            <!--                                            </table>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!-- COL END -->
                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                <div class="row">
                                    <?php if (AdminCan('dashboard.desks.manager.move-supporters')): ?>
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                        <div class="card custom-card overflow-hidden">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div style="display:flex; align-items:center; flex-wrap:wrap; gap:12px;">
                                                    <label class="main-content-label my-auto pt-2">
                                                        جابجایی پشتیبان‌ها
                                                    </label>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش امکان جابجایی گروهی دانش آموزان بین پشتیبان‌ها وجود دارد.باافزودن فایل CSV میتوانید فرایند جابه جایی پشتیبان را بین دانش آموزان انجام دهید."
                                                          aria-label="راهنمای جابجایی پشتیبان‌ها"
                                                          onclick="event.preventDefault(); event.stopPropagation();">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                    <?php if (AdminCanSee('dashboard.ready-messages')): ?>
                                                        <a class="btn btn-primary mr-auto" href="<?= url('admin/setting/ready-message.php') ?>">
                                                            <i class="fe fe-message-square" aria-hidden="true"></i>
                                                            مدیریت پیام‌های آماده
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <form method="post" data-parsley-validate=""
                                                          enctype="multipart/form-data">
                                                        <div class="card-body border ">
                                                            <div class="row">
                                                                <div class="col-xl-12">

                                                                    <div class="input-group file-browser">


                                                                        <input type="text"
                                                                               class="form-control border-left-0 browse-file"
                                                                               placeholder="فایل مطابق با الگو بارگذاری نمایید*"
                                                                               readonly="">
                                                                        <label class="input-group-btn">
                                                                                <span class="btn btn-primary">
                                                                                    انتخاب فایل CSV<input type="file"
                                                                                                          name="fileToUpload"
                                                                                                          style="display: none;"
                                                                                                          required>
                                                                                </span>
                                                                        </label>
                                                                    </div>
                                                                    <div class="text-muted">
                                                                        فایل الگو را از <a class="font-weight-bolder"
                                                                                           href="<?php echo url('assets/doc/changeSupport.xlsx') ?>"
                                                                                           target="_blank">اینجا</a>
                                                                        و فایل راهنما را <a class="font-weight-bolder"
                                                                                            href="<?php echo url('assets/doc/s3.pdf') ?>"
                                                                                            target="_blank"> از
                                                                            اینجا </a>
                                                                        دانلود کنید
                                                                    </div>
                                                                    <?php
                                                                    $hash = bin2hex(random_bytes(32));
                                                                    $_SESSION['token'] = $hash;
                                                                    ?>
                                                                    <input class="form-control" name="token"
                                                                           type="hidden"
                                                                           value="<?php echo $hash ?>">
                                                                    <div class="mt-3 text-center">
                                                                        <button type="submit" name="changesuporter"
                                                                                class="btn btn-warning  mr-1">
                                                                            بررسی فایل و جایگزینی
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
                                    <?php endif; ?>
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                        <?php if (isset($_SESSION['success'])) { ?>
                                            <div class="alert alert-success" role="alert">
                                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                                <?= $_SESSION['success'] ?>
                                            </div>
                                            <?php unset($_SESSION['success']);
                                        } ?>
                                    </div>
                                    <!--                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                        <div class="card custom-card overflow-hidden">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div>
                                                    <div class="d-flex">
                                                        <label class="main-content-label my-auto pt-2">تعداد
                                                            دانش آموزان هر پایه
                                                        </label>

                                                    </div>
                                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="col-xl-12">
                                                    <div class="table">
                                                        <table class="table table-bordered mg-b-0">
                                                            <thead>
                                                            <tr>
                                                                <th>ردیف</th>
                                                                <th>پایه</th>
                                                                <th>تعداد</th>
                                                                <th>اقدامات</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php
                                    /*                                                            $sql = "SELECT *, COUNT(*) AS count FROM `dbcourse` INNER JOIN `codtbl_course` ON dbcourse.codecourse = codtbl_course.codecourse WHERE  dbcourse.activityuser= 1 AND codtbl_course.activecourse= 1 GROUP BY dbcourse.codecourse ORDER BY dbcourse.codecourse DESC";
                                                                                                $stmt = $conn->prepare($sql);
                                                                                                $stmt->execute();
                                                                                                $courses = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                                                                $id = 1;
                                                                                                if ($stmt->rowCount() > 0) {
                                                                                                    foreach ($courses as $course) {
                                                                                                        if (iscourseActivate($course->codecourse)) {
                                                                                                            $coursee = returnCourse($course->codecourse);
                                                                                                            */?>
                                                                        <tr>
                                                                            <td class="wd-15"><?/*= $id */?></td>
                                                                            <td class="wd-500"><?/*= $coursee->coursename */?></td>
                                                                            <td class="wd-500"><?/*= $course->count */?></td>
                                                                            <td class="wd-5">
                                                                                <div class="text-wrap">
                                                                                    <div class="text-center">
                                                                                        <div class="dropdown">
                                                                                            <button aria-expanded="false"
                                                                                                    aria-haspopup="true"
                                                                                                    class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                                    data-bs-toggle="dropdown"
                                                                                                    type="button">
                                                                                                اقدام<i
                                                                                                        class="fas fa-caret-down mr-1"></i>
                                                                                            </button>
                                                                                            <div class="dropdown-menu tx-8">
                                                                                                <a class="dropdown-item text-success"
                                                                                                   href="#" onclick="window.open('suportercourses.php?code='+'<?php /*echo $course->codecourse */?>','مشاهده پایه','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' ); return false;">
                                                                                                    <i class="fe fe-edit"></i>
                                                                                                    مشاهده دانش آموزان</a>

                                                                                                <a class="dropdown-item text-danger" href="?code=<?/*= $course->codecourse */?>&delallcourse=true" onclick="return confirm('آیا با حذف تمامی پیام های پایه موافقید ؟')"><i class="fe fe-x-square"></i>
                                                                                                    حذف تمامی پیام های
                                                                                                    پایه</a>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                        <?php /*$id++;
                                                                    }
                                                                }
                                                            } */?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
-->
                                </div>
                            </div>
                            <!-- COL END -->

                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-sm-12 col-lg-12 col-xl-4">
                        <?php if (AdminCanSee('dashboard.desks.manager.activity')): ?>
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-header  border-bottom-0 pb-0">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <label class="main-content-label my-auto pt-2">
                                                فعالیت امروز
                                            </label>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش خلاصه عملکرد روز جاری در حوزه پشتیبانی نمایش داده می‌شود. اطلاعاتی مانند تعداد گزارش‌های ثبت‌شده پشتیبانی توسط مرکز تماس، تماس‌های موفق، عدم پاسخگویی، آمار مرکز تماس، مدت زمان مکالمات و گزارش مقایسه‌ای عملکرد کارشناسان قابل مشاهده و بررسی است."
                                                  aria-label="راهنمای فعالیت امروز"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted"> تعداد اقدامات پشتیبانی ثبت شده امروز </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-6 my-auto">
                                            <h6 class="mb-3 font-weight-normal">گزارش پشتیبانی </h6>
                                            <div class="text-right">
                                                <?php
                                                $datenow = date('Y-m-d');
                                                $sql = 'SELECT COUNT(*) as counReportdate FROM suport_report WHERE  datenow=?';
                                                $exe1 = $conn->prepare($sql);
                                                $exe1->execute([$datenow]);
                                                $natije1 = $exe1->fetch();
                                                ?>
                                                <h3 class="font-weight-bold ml-3 mb-2 text-primary"><?php echo $natije1['counReportdate']; ?>
                                                    گزارش </h3>
                                                <p class="tx-13 my-auto text-muted"> <?php
                                                    echo jdate('l Y/m/d ');
                                                    ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 my-auto">
                                            <div class="forth circle">
                                                <div class="chart-circle-value circle-style">
                                                    <div class="tx-16 font-weight-bold"><?php echo $natije1['counReportdate']; ?> </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mg-b-30">
                                        </div>
                                        <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                            <div class="card custom-card overflow-hidden">
                                                <?php

                                                $report = reportCallLMSmanager($datenow, $datenow);
                                                ?>
                                                <ul class="crypto-transcation list-unstyled mg-b-0">
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> تماس موفق</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall Successful'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی تماس</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall NOT'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> متنی موفق</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT Successful'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی متنی</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT NOT'] ?></h5>
                                                        </div>
                                                    </li>

                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                            <div class="card custom-card overflow-hidden">
                                                <?php

                                                $start = $datenow . ' 00:01';
                                                $end = $datenow . ' 23:59';

                                               $simotel = reportCallSimotel($start, $end, '', 200000);
                                                ?>
                                                <ul class="crypto-transcation list-unstyled mg-b-0">
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13">پاسخ داده شده</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $simotel['TIME ANSWERED'] ?> </p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13">عدم پاسخ</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $simotel['TIME NO ANSWERED'] ?> </p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['NO ANSWERED'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> کل تماس شما</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $simotel['TIME TOTAL'] ?> </p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] + $simotel['NO ANSWERED']; ?></h5>
                                                        </div>
                                                    </li>


                                                </ul>
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <!-- Button 1 -->
                                            <?php if (AdminCan('dashboard.desks.manager.activity.call-report') && AdminCanSee('dashboard.reports.call-report')): ?>
                                                <div class="col-12 col-sm-6">
                                                    <div class="btn ripple btn-outline-primary w-100"
                                                         style="white-space: nowrap; min-width: 140px;"
                                                         onclick="window.open('advanced-report.php','گزارش پیشرفته تماس',
             'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                        <i class="fe fe-database"></i>
                                                        گزارش پیشرفته
                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <!-- Button 2 -->
                                            <?php if (AdminCan('dashboard.desks.manager.activity.compare') && AdminCanSee('dashboard.reports.operators-compare')): ?>
                                            <div class="col-12 col-sm-6">
                                                <div class="btn ripple btn-outline-danger w-100"
                                                     style="white-space: nowrap; min-width: 140px;"
                                                     onclick="window.open('operatorsreport.php','مقایسه گزارشات',
         'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                    <i class="fe fe-database"></i>
                                                    مقایسه گزارشات
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- COL END -->
                        <?php if (AdminCanSee('dashboard.desks.manager.activity')): ?>

                            <!--                        <div class="row row-sm">-->
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="col-lg-12 mg-b-25 ">-->
                            <!--                                    <a href="--><?php //echo url('admin/panel.php/') ?><!--  " target="_blank"-->
                            <!--                                       class="btn btn-block btn-outline-info"><i-->
                            <!--                                                class="fe fe-list"></i> ثبت و ایجاد چک لیست </a>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="col-lg-12 mg-b-25 ">-->
                            <!--                                    <a href="--><?php //echo url('admin/panel.php/') ?><!--  " target="_blank"-->
                            <!--                                       class="btn btn-block btn-outline-warning"><i-->
                            <!--                                                class="fe fe-list"></i> گزارش چک لیست های ثبتی </a>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="col-lg-12 mg-b-25 ">-->
                            <!--                                    <a class="btn btn-block btn-outline-success" onclick="window.open('dahak_report.php','لیست دهک های 1 تا 4',-->
                            <!--                                        'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"><i-->
                            <!--                                                class="fe fe-list"></i> لیست دهک های 1 تا 4 </a>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="col-lg-12 mg-b-25 ">-->
                            <!--                                    <a class="btn btn-block btn-outline-secondary" onclick="window.open('misscall_report.php','لیست دهک های 1 تا 4',-->
                            <!--                                        'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"><i-->
                            <!--                                                class="fe fe-list"></i> لیست miss call ها </a>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!---->
                            <!---->
                            <!--                        </div>-->
                        <?php endif; ?>

                    </div>
                    <!-- COL END -->
                </div>
            </div>
        </div>

    </div>

</div>
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>

</html>
