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
$studentOpChangeCount = countStudentOperatorChanged($currentUser, (int)$administrator);

if (!function_exists('countNewStudent')) {
    function countNewStudent(string $me, int $administrator): int
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
            $args[] = $me;
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
$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

if ($type == 2 ) {
    $sqlCountChanges = 'SELECT COUNT(*) FROM change_expert_requests WHERE status = ?';
    $stmtCountChanges = $conn->prepare($sqlCountChanges);
    $stmtCountChanges->execute(['pending']);
    $changeCount = $stmtCountChanges->fetchColumn();
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease-in-out;
            height: 120px;
        }

        .safe-call-card:hover {
            text-decoration: none;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
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
<!--    <img src="-->
<?php //echo url('../assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
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

                <div class="row row-sm mg-b-20">
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
                                    <a href="<?= url('admin/notifications/verify_by_manager.php') ?>"
                                       class="safe-call-card">
                                        <div class="safe-call-card-title">

                                            <span>فاکتورهای منتظر تأیید</span>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش امکان بررسی فاکتورهای درخواست داده شده توسط کارشناسان فروش و ثبت و تایید نهایی فاکتور و همچنین مشاهده لیست کلیه ی فاکتورهای تاییدی و یا در انتظار تایید وجود دارد."
                                                  aria-label="راهنمای فاکتورهای منتظر تأیید مدیر"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>
                                        <div class="safe-call-card-desc">
                                            بررسی و تأیید نهایی فاکتورهای تأییدشده توسط تیم فروش
                                        </div>
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
