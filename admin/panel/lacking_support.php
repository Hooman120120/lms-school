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
// Protect against session hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// Single-session check
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
// Permission
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}

/**
 * 1) COUNT – must match the dashboard card exactly
 *    (only ACTIVE courses with no supporter)
 */
$sqlCount = "
    SELECT COUNT(*) AS countNotSuporter
    FROM dbcourse d
    INNER JOIN codtbl_course c ON d.codecourse = c.codecourse
    WHERE c.activecourse = 1
      AND d.activityuser = 1
      AND d.suporter IS NULL
      AND d.has_operator = 0
";

$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute();
$ref = $stmtCount->fetch(PDO::FETCH_ASSOC);
$countNotSuporter = (int)($ref['countNotSuporter'] ?? 0);

/**
 * 2) LIST – same filter as the card, but detailed:
 *    which active course(s) each user lacks a supporter on.
 */
$sqlList = "
    SELECT 
      d.username,
      COALESCE(u.fname, '') AS fname,
      COALESCE(u.lname, '') AS lname,
      c.codecourse,
      c.coursename
    FROM dbcourse d
    INNER JOIN codtbl_course c ON d.codecourse = c.codecourse
    LEFT JOIN dbpersonal u ON u.codemeli = d.username
    WHERE c.activecourse = 1
      AND d.activityuser = 1
      AND d.suporter IS NULL
      AND d.has_operator = 0
    ORDER BY d.username ASC, c.coursename ASC
";

$stmtList = $conn->prepare($sqlList);
$stmtList->execute();
$rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <title>فاقد پشتیبان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .search-input { max-width: 360px; }
        .card-title { display: flex; align-items: center; gap: .5rem; }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">

            <?php if (!empty($_SESSION['payment_message'])): ?>
                <?= $_SESSION['payment_message']; unset($_SESSION['payment_message']); ?>
            <?php endif; ?>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    کاربران فاقد پشتیبان (نفردوره)
                                    <span class="badge bg-danger">تعداد: <?= $countNotSuporter ?></span>
                                </h5>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover text-center align-middle" id="ns-table">
                                    <thead class="table-light">
                                    <tr>
                                        <th>صفحه دانش آموز</th>
                                        <th>نام کاربری</th>
                                        <th>نام</th>
                                        <th>نام خانوادگی</th>
                                        <th>کد دوره</th>
                                        <th>عنوان دوره</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($rows)): ?>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td class="wd-15">
                                                    <div class="btn btn-sm ripple btn-info btn-icon"
                                                         onclick="window.open('suporting.php?Id=<?= rawurlencode($r['username']) ?>',
                                                             'پشتیبانی',
                                                             'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0');">
                                                        <i class="fa fa-key"></i>
                                                    </div>
                                                <td><?= htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($r['fname'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($r['lname'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($r['codecourse'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($r['coursename'], ENT_QUOTES, 'UTF-8') ?></td>

                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6">موردی یافت نشد.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 text-muted">
                                <small>کاربرانی که حداقل یک دورهٔ فعال دارند و برای آن‌ها پشتیبان تعیین نشده است.</small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('ns-search');
        const rows  = Array.from(document.querySelectorAll('#ns-table tbody tr'));
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            rows.forEach(tr => {
                const username = tr.children[0].innerText.toLowerCase();
                const fname    = tr.children[1].innerText.toLowerCase();
                const lname    = tr.children[2].innerText.toLowerCase();
                tr.style.display = (username.includes(q) || fname.includes(q) || lname.includes(q)) ? '' : 'none';
            });
        });
    });
</script>

</body>
</html>
