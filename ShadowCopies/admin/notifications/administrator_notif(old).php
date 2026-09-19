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
$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

if ($type == 3 or $type == 1) {
    $sqlCountChanges = 'SELECT COUNT(*) FROM change_expert_requests WHERE status = ?';
    $stmtCountChanges = $conn->prepare($sqlCountChanges);
    $stmtCountChanges->execute(['pending']);
    $changeCount = $stmtCountChanges->fetchColumn();

    $sqlbackfromcrm = "
    SELECT COUNT(*)
    FROM admin_tickets
    WHERE type = 'reactivate_temp_user'
      AND ticket_status IN (0,3) ";
    $stmtbackfromcrm = $conn->prepare($sqlbackfromcrm);
    $stmtbackfromcrm->execute();
    $crmCount = (int)$stmtbackfromcrm->fetchColumn();
}

if (!in_array($type, [1, 2, 3, 4], true)) {
    http_response_code(400);
    exit('Invalid request');
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

                <div class="row row-sm mg-b-20">
                    <?php $allowed_users_inadministrators = [0,51,52];
                    if (in_array($_SESSION['admin']['adminrole']  , $allowed_users_inadministrators)) { ?>
                    <div class="col-12 ">
                        <?php if ($type == 1 or $type == 2 ) { ?>
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش پشتیبانی
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/thread_list.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">تیکت اعلان تماس</div>
                                        <div class="safe-call-card-desc">
                                            تیکت دانش آموزانی اعلان تماس
                                        </div>
                                        <?php if ($reportCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= $reportCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/notif_rule_management.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">کنترل اعلان تماس</div>
                                        <div class="safe-call-card-desc">
                                           قوانین اعلان تماس پشتیبان ها
                                        </div>

                                    </a>
                                </div>
                                                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/new_student_tickets.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">تیکت دانش آموز جدید</div>
                                        <div class="safe-call-card-desc">
                                            تیکت هایی که برای دانش آموزان جدید ثبت شده
                                        </div>
                                        <?php if ($countNewStudentticket > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countNewStudentticket ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/notification_view.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">اعلان پشتیبان ها</div>
                                        <div class="safe-call-card-desc">
                                            اعلام تماس هر پشتیبان به صورت جداگانه
                                        </div>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB">*</span>
                                    </a>
                                </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/verify_by_manager.php') ?>"
                                           class="safe-call-card">
                                            <div class="safe-call-card-title">فاکتور های منتظر تایید</div>
                                            <div class="safe-call-card-desc">
                                                فاکتور هایی که توسط کارشناس، تایید واریزی شده است
                                            </div>
                                        </a>
                                    </div>
                                                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/referred_tickets.php') ?>"
                                       class="safe-call-card">
                                        <div class="safe-call-card-title">تیکت های ارجاعی</div>
                                        <div class="safe-call-card-desc">
تیکت های ارجاع یافته به شما                                        </div>
                                        <?php if ($countPendingTicketReferrals > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countPendingTicketReferrals ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>



                            </div>
                        <?php }
                            else
                            {

                            }?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="row row-sm mg-b-20">
                    <?php $allowed_users_inadministrators = [0, 51, 52, 53];
                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_inadministrators)) { ?>
                    <div class="col-12 ">
                        <?php if ($type == 1 or $type == 3 ) { ?>
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش فروش
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/change_expert_reqs.php') ?>"
                                       class="safe-call-card">
                                        <div class="safe-call-card-title">درخواست تغییر کارشناس فروش</div>
                                        <div class="safe-call-card-desc">
                                            کارشناسانی که درخواست تغییر دانش آموز خود را داشته اند
                                        </div>
                                        <?php if ($changeCount > 0): ?>
                                            <span class="badge pr-2 fs-5"
                                                  style="background-color: #6259CB"><?= $changeCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/reactivate_tickets.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">لیست تیکت دانش آموزان برگشت خورده از crm</div>
                                        <div class="safe-call-card-desc">
                                            دانش آموزان که از crm دوباره به موقت بر میگردند
                                        </div>
                                    <?php if ($crmCount > 0): ?>
                                         <span class="badge pr-2 fs-5" style="background-color: #6259CB">
                                             <?= (int)$crmCount ?></span>
                                    <?php endif; ?>
                                    </a>
                                </div>


                            </div>
                            <?php } ?>
                        </div>

                        <?php } ?>
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
