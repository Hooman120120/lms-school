<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once 'autodialer_functions.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }

if (!isset($_SESSION['admin'])) { header('location: ../login.php'); die(); }

// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset(); header('location: ../login.php'); die();
}

if (!permision('autodialer') || (int)($_SESSION['admin']['adminrole'] ?? -1) !== 0) {
    echo "دسترسی ندارید.";
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset(); header('location: ' . url('admin/login.php')); die();
}

if (!$conn instanceof PDO) {
    die('DB connection error');
}

// Search
$q = trim((string)($_GET['q'] ?? ''));

// Fetch logs
$params = [];
if ($q !== '') {
    $sql = "
        SELECT id, actor, action, entity_type, created_at
        FROM autodialer_log
        WHERE actor LIKE :q OR action LIKE :q OR entity_type LIKE :q
        ORDER BY id DESC
        LIMIT 500
    ";
    $params[':q'] = '%' . $q . '%';
} else {
    $sql = "
        SELECT id, actor, action, entity_type, created_at
        FROM autodialer_log
        ORDER BY id DESC
        LIMIT 500
    ";
}

$st = $conn->prepare($sql);
$st->execute($params);
$logs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
    <title>Log تماس گروهی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .log-action { white-space: normal; line-height: 1.6; }
        .log-meta { font-size: .85rem; }
        .badge-soft { border: 1px solid rgba(0,0,0,.08); }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm safe-call-menu">

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                                <div class="card-title mb-0 fs-5 font-weight-bold">
                                    Log تماس گروهی
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>کاربر</th>
                                            <th>عملیات</th>
                                            <th>نوع</th>
                                            <th>تاریخ</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($logs)): ?>
                                            <?php $i = 1; foreach ($logs as $row):

                                                $actor  = (string)($row['actor'] ?? '');
                                                $action = (string)($row['action'] ?? '');
                                                $entity = (string)($row['entity_type'] ?? '');
                                                $created = (string)($row['created_at'] ?? '');

                                                // Jalali display (optional)
                                                $createdShow = $created;
                                                if ($created !== '') {
                                                    $ts = strtotime($created);
                                                    if ($ts) $createdShow = jdate('Y/m/d H:i:s', $ts, '', 'Asia/Tehran', 'fa');
                                                }

                                                // Entity badge color
                                                $entityLower = strtolower(trim($entity));
                                                $entityBadge = 'bg-secondary';
                                                if ($entityLower === 'campaign') $entityBadge = 'bg-primary';
                                                elseif ($entityLower === 'announcement') $entityBadge = 'bg-warning text-dark';
                                                elseif ($entityLower === 'blacklist') $entityBadge = 'bg-dark';

                                                // Action badge
                                                $actionLower = strtolower($action);
                                                $opBadge = 'bg-info';
                                                $opText  = 'ACTION';

                                                if (strpos($actionLower, ' add ') !== false || strpos($actionLower, 'campaign add') !== false || strpos($actionLower, 'add |') !== false) {
                                                    $opBadge = 'bg-success'; $opText = 'ADD';
                                                }
                                                if (strpos($actionLower, ' remove ') !== false || strpos($actionLower, 'campaign remove') !== false || strpos($actionLower, 'remove |') !== false) {
                                                    $opBadge = 'bg-danger'; $opText = 'REMOVE';
                                                }
                                                if (strpos($actionLower, 'failed') !== false) {
                                                    $opBadge = 'bg-danger'; $opText = 'FAILED';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>

                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($actor, ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="text-muted log-meta"><?php echo htmlspecialchars($createdShow, ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </td>

                                                    <td class="log-action">
                                                        <span class="badge <?php echo $opBadge; ?> me-2"><?php echo $opText; ?></span>
                                                        <?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>

                                                    <td>
            <span class="badge badge-soft <?php echo $entityBadge; ?>">
                <?php echo htmlspecialchars($entity, ENT_QUOTES, 'UTF-8'); ?>
            </span>
                                                    </td>

                                                    <td class="text-muted"><?php echo htmlspecialchars($createdShow, ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center">موردی یافت نشد</td></tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-2 text-muted small">
                                    نمایش آخرین <?php echo count($logs); ?> رکورد (حداکثر 500)
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- row -->
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
