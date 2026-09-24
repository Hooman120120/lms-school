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
//* End Check Session JUST ONE Session
$schoolRoleKey = SchoolStaffRoleKey((string)$_SESSION['admin']['user']);
if (!Permision('permision_viewalert') && !in_array($schoolRoleKey, ['manager', 'general_deputy'], true)) {
    header('location: ../panel/desktop.php');
    exit;
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
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('../assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
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
            <div class="inner-body">

                <?php
                // In school mode the former supporter call mechanism belongs to counselors.
                $stmt = $conn->query("
        SELECT a.username, a.fname, a.lname
        FROM dbadmin a
        INNER JOIN school_admin_roles r ON r.id = a.school_role_id
        WHERE r.role_key = 'counselor'
          AND a.adminactive = 1
        ORDER BY lname ASC, fname ASC, username ASC
    ");
                $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="row row-sm mg-b-20">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0"
                                        style="display:flex; align-items:center; gap:10px;">

                                        <span>لیست مشاوران برای مشاهده اعلان‌های تماس</span>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید بر اساس قوانین تماس تعریف‌شده، وضعیت تماس هر مشاور با دانش‌آموزان پایه‌های تخصیص‌یافته به او را مشاهده و مدیریت کنید."
                                              aria-label="راهنمای لیست اپراتورها برای مشاهده اعلان‌ها"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                            <i class="fe fe-info fs-4"></i>
                                        </span>

                                    </h5>
                                </div>
                            </div>

                            <div class="card-body">
                                <?php if (empty($operators)): ?>
                                    <div class="alert alert-warning mb-0">
                                        هیچ مشاور فعالی یافت نشد.
                                    </div>
                                <?php else: ?>
                                    <div class="row gx-2 gy-2">
                                        <?php foreach ($operators as $op): ?>
                                            <?php
                                            $uname = $op['username'];
                                            $name  = trim(($op['fname'] ?? '') . ' ' . ($op['lname'] ?? ''));
                                            if ($name === '') {
                                                $name = $uname;
                                            }
                                            ?>
                                            <div class="col-sm-6 col-md-4 col-lg-3">
                                                <a href="notifications.php?Op=<?= urlencode($uname) ?>"
                                                   class="btn btn-block btn-outline-primary d-flex justify-content-between align-items-center">
                                                    <span><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="badge bg-primary tx-12">
                                            <?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
