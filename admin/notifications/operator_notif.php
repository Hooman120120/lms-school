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
    header('location: ' . url('../admin/login.php'));
    die();
}
$currentUser = (string)$_SESSION['admin']['user'];
$administrator = AdminCan('notifications.all-records') ? 1 : 0;
$studentOpChangeCount = countStudentOperatorChanged( $currentUser, (int)$administrator);

if (!function_exists('countNewStudent')) {
    function countNewStudent( string $me, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        $where = [
            "d.first_suporter_assigned_at IS NOT NULL"
        ];
        $args = [];

        // Non-admin: only my students
        if ($administrator !== 1) {
            $where[] = "d.suporter = ?";
            $args[]  = $me;
        }

        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT
                    d.username AS student_username,
                    d.suporter AS suporter,
                    MAX(d.first_suporter_assigned_at) AS batch_ts
                FROM dbcourse d
                INNER JOIN dbpersonal p ON p.codemeli = d.username
                INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
                WHERE " . implode(' AND ', $where) . "
                GROUP BY d.username, d.suporter
            ) s
            WHERE NOT EXISTS (
                SELECT 1
                FROM admin_tickets t
                WHERE t.type = 'new_students'
                  AND t.ticket_status = 1
                  AND t.student_username = s.student_username
                  AND t.first_suporter_assigned_at = s.batch_ts
                  AND t.ticket_assigned_to = " . ($administrator === 1 ? "s.suporter" : "?") . "
            )
        ";

        // If non-admin, we need one more bind for ticket_assigned_to = ?
        if ($administrator !== 1) {
            $args[] = $me;
        }

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
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
    <title> مشاهده اعلان اپرتور ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .safe-call-menu {
            margin-top: 30px;
            justify-content: center;
        }

        .safe-call-card {
            display: block;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            background: #f7f7f7;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.2s ease-in-out;
            height: 120px;
        }

        .safe-call-card:hover {
            text-decoration: none;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            background: #ffffff;
        }

        .safe-call-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:12px;
        }

        .safe-call-card-desc {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('../assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
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

                <div class="row row-sm mg-b-20 " >
                    <div class="col-12 ">
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش تماس
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/notifications.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>لیست اعلان تماس</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش دانش آموزانی نمایش داده می‌شوند که بر اساس قوانین سیستم اعلان و تنظیمات کنترل محدودیت اعلان، شرایط تعریف‌شده برای آن‌ها ارضا نشده است. این دانش آموزان نیاز به بررسی، پیگیری یا تماس دارند تا اقدامات لازم مطابق فرآیندهای آموزشی و پشتیبانی انجام شود."
                                                  aria-label="راهنمای لیست اعلان تماس"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <span class="badge pr-2 fs-5" style="background-color: #6259CB">!</span>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/thread_list.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>تیکت اعلان تماس</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش تیکت‌های مربوط به اعلان تماس دانش آموزان نمایش داده می‌شود. این تیکت‌ها توسط پشتیبانانی ثبت می‌شوند که هنگام اجرای قانون اعلان تماس برای دانش آموز موردنظرشان با شرایط استثنا مواجه شده‌اند؛ مانند زمانی که دانش آموز پاسخگو نبوده، امکان برقراری تماس وجود نداشته یا هر وضعیت خاص دیگری رخ داده است. مدیریت می‌تواند این موارد را بررسی، پیگیری و در صورت نیاز تصمیم‌گیری کند."
                                                  aria-label="راهنمای تیکت اعلان تماس"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php if ($reportCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= $reportCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                            </div>
                        </div>
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش تغییر پشتیبانی
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/studentoperator_changes_notif.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>لیست تغییر پشتیبان</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش درخواست‌های تغییر پشتیبان نمایش داده می‌شود. این درخواست‌ها زمانی ثبت می‌شوند که یک پشتیبان به هر دلیلی درخواست کرده باشد دانش آموز تحت پشتیبانی او به پشتیبان دیگری منتقل شود. پس از بررسی و تأیید درخواست، دانش آموز به پشتیبان جدید اختصاص داده شده و سوابق مربوط به درخواست نیز قابل پیگیری خواهد بود."
                                                  aria-label="راهنمای لیست تغییر پشتیبان"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php if ($studentOpChangeCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$studentOpChangeCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش دانش آموز
                                    </h5>
                                </div>
                            </div>

                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/new_students.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>دانش آموز جدید</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش اعلان مربوط به دانش آموزانی نمایش داده می‌شود که به‌تازگی به شما به‌عنوان پشتیبان اختصاص داده شده‌اند. با استفاده از این بخش می‌توانید از اضافه شدن دانش آموزان جدید مطلع شده و در سریع‌ترین زمان ممکن فرآیند برقراری ارتباط، پیگیری و ارائه خدمات پشتیبانی را آغاز کنید."
                                                  aria-label="راهنمای دانش آموز جدید"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <?php if ($countNewStudent > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countNewStudent ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/new_student_tickets.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>تیکت دانش آموز جدید</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش تیکت‌های مربوط به دانش آموزان جدید نمایش داده می‌شود. این تیکت‌ها برای رسیدگی بیشتر، رفع ابهام و پیگیری سؤالات یا مشکلات دانش آموزان جدید ثبت می‌شوند. موضوع، جزئیات و دلیل ثبت هر تیکت در متن همان تیکت شرح داده شده است تا مدیریت یا مسئول مربوطه بتواند اقدامات لازم را انجام دهد."
                                                  aria-label="راهنمای تیکت دانش آموز جدید"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                        </div>

                                        <?php if ($countNewStudentticket > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countNewStudentticket ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/suspended_student_op_ticket.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>تیکت دانش آموز تعلیقی</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش وضعیت درخواست‌های تعلیق دانش آموز که توسط شما ثبت شده است نمایش داده می‌شود. پشتیبان می‌تواند روند بررسی هر درخواست را مشاهده کرده و از وضعیت آن، شامل در انتظار بررسی، تأیید یا رد شدن، مطلع شود."
                                                  aria-label="راهنمای تیکت دانش آموز تعلیقی"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php if ($Countsus > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$Countsus ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/NoOperator_student_op_ticket.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>تیکت دانش آموز بدون پشتیبان</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش وضعیت درخواست‌های بدون پشتیبان کردن دانش آموز که توسط شما ثبت شده است نمایش داده می‌شود. پشتیبان می‌تواند روند بررسی هر درخواست را مشاهده کرده و از وضعیت آن، شامل در انتظار بررسی، تأیید یا رد شدن، مطلع شود."
                                                  aria-label="راهنمای تیکت دانش آموز بدون پشتیبان"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <?php if ($Countnoop > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$Countnoop ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                            </div>
                        </div>

                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <?php echo m('label.teachers_section') ?>
</h5>
                                </div>
                            </div>

                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/teachers_notif.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span><?php echo m('label.teacher_tickets_section') ?></span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="<?php echo m('tooltip.teacher_tickets_operator') ?>"
                                                  aria-label="<?php echo m('aria.teacher_tickets_help') ?>"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>
                                        <?php if ($teacherNotifCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$teacherNotifCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                            </div>
                        </div>


                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش اقساط
                                    </h5>
                                </div>
                            </div>

                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/Bill/payment.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>اعلان اقساط</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش دانش آموزانی نمایش داده می‌شوند که موعد پرداخت قسط آن‌ها فرارسیده یا سررسید اقساطشان گذشته است. این بخش به‌منظور مدیریت، پیگیری و ثبت وضعیت پرداخت اقساط دانش آموزان طراحی شده تا اقدامات لازم برای وصول اقساط در زمان مناسب انجام شود."
                                                  aria-label="راهنمای اعلان اقساط"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>
                                        <?php if ($countInstallmentOverdue > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countInstallmentOverdue ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>


                                <!--<div class="col-sm-6 col-md-4 col-xl-3 mb-3">-->
                                <!--    <a href="#" class="safe-call-card">-->
                                <!--        <div class="safe-call-card-title">تیکت اقساط</div>-->
                                <!--        <div class="safe-call-card-desc">-->
                                <!--            تیکت هایی که برای اعلان اقساط ارسال شده-->
                                <!--        </div>-->
                                <!--                                        --><?php //if ($countNewStudentticket > 0): ?>
                                <!--                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB">--><?//= (int)$countNewStudentticket ?><!--</span>-->
                                <!--                                        --><?php //endif; ?>
                                <!--    </a>-->
                                <!--</div>-->


                            </div>
                        </div>


                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- End Row -->


<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>
</html>
