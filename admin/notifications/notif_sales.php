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
$administrator = (int)($row['administrator'] ?? 0);
$studentOpChangeCount = countStudentOperatorChanged( $currentUser, (int)$administrator);
$backfromcrmcount = countbackfromcrmOP($currentUser);

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
                                        دانش آموزان موقت با اولویت ثبت نام
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/high-priority-temp-students.php') ?>" class="safe-call-card">
                                        <div class="d-flex align-items-center justify-content-between mb-1">

                                            <div class="safe-call-card-title mb-0">
                                                لیست دانش آموزان موقت دارای اولویت
                                            </div>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش دانش آموزان موقتی نمایش داده می‌شوند که بر اساس سوابق فعالیت، از جمله شرکت در جلسات آموزشی، کاندید مناسبی برای خرید محصولات و تبدیل شدن به دانش آموز قطعی هستند. این لیست به واحد پشتیبانی و فروش کمک می‌کند تا پیگیری‌های لازم را برای تبدیل این دانش آموزان به مشتری انجام دهد."
                                                  aria-label="راهنمای لیست دانش آموزان موقت دارای اولویت">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php if ($studentOpChangeCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$studentOpChangeCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/reactivate_tickets.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span> دانش آموزان برگشت‌خورده از CRM</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش درخواست‌های بازگشت دانش آموزان به CRM نمایش داده می‌شود. این درخواست‌ها زمانی ثبت می‌شوند که سرنخ هیچ‌گونه خریدی انجام نداده باشد و کارشناس درخواست انتقال مجدد اطلاعات به CRM را با حفظ کامل سوابق و تاریخچه ثبت کند. پس از بررسی و تأیید، اطلاعات دانش آموز همراه با سوابق قبلی به CRM بازگردانده خواهد شد."
                                                  aria-label="راهنمای تیکت دانش آموزان برگشت‌خورده از CRM"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php if ($backfromcrmcount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$backfromcrmcount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

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
