<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }
if (!isset($_SESSION['admin'])) { header('location: ../login.php'); die(); }

// Protect against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset(); header('location: ../login.php'); die();
}

// Ensure single active session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) { session_unset(); header('location: ' . url('admin/login.php')); die(); }

// helpers
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function req_status_fa(int $s): string {
    return $s === 0 ? 'در انتظار بررسی'
        : ($s === 1 ? 'تایید شد'
            : ($s === 2 ? 'رد شد' : (string)$s));
}
function req_badge_class(int $s): string {
    if ($s === 1) return 'bg-success';
    if ($s === 2) return 'bg-danger';
    return 'bg-warning text-dark'; // pending
}
function op_mode_fa(int $m): string {
    // requested_mode: 1 = cancel support, else = undo cancel / restore
    return $m === 1 ? 'لغو پشتیبانی' : 'رفع لغو پشتیبانی';
}

// who am I
$me = $_SESSION['admin']['user'] ?? '';

// administrator flag
$sq = "SELECT administrator FROM dbadmin WHERE username = ? LIMIT 1";
$st = $conn->prepare($sq);
$st->execute([$me]);
$adminRow = $st->fetch(PDO::FETCH_ASSOC);
$isAdministrator = !empty($adminRow) && (int)$adminRow['administrator'] === 1;

/* =========================
   GET: list operator-cancel requests
   ========================= */

// optional filter: status=0|1|2
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : -1;

$where = [];
$args  = [];

// scope: admin sees all, operators see only their own requests
if (!$isAdministrator) {
    $where[] = "r.requested_by = ?";
    $args[]  = $me;
}

// status filter
if (in_array($statusFilter, [0,1,2], true)) {
    $where[] = "r.status = ?";
    $args[]  = $statusFilter;
}

$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        r.*,
        p.fname AS student_fname,
        p.lname AS student_lname,
        p.codemeli AS student_codemeli
    FROM cancel_student_operator_request r
    LEFT JOIN dbpersonal p
        ON p.codemeli = r.student_username
    $sqlWhere
    ORDER BY
      CASE
        WHEN r.status IN (1,2)
         AND r.reviewed_at IS NOT NULL
         AND r.seen_at IS NULL
        THEN 0 ELSE 1
      END,
      r.reviewed_at DESC,
      r.created_at DESC
    LIMIT 300
";
$stmt = $conn->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$isAdministrator && $me !== '') {
    $u = $conn->prepare("
        UPDATE cancel_student_operator_request
        SET seen_at = NOW()
        WHERE requested_by = ?
          AND status IN (1,2)
          AND reviewed_at IS NOT NULL
          AND seen_at IS NULL
    ");
    $u->execute([$me]);
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>درخواست‌های لغو پشتیبانی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        .row-hover tbody tr:hover { background:#fafafa; }
        .cell-wrap {
            max-width: 360px;
            white-space: normal;
            word-break: break-word;
        }
        .filters a.btn { margin-left: 6px; }
        @keyframes glowPulse {
            0%   { background-color:#fffbe6; }
            50%  { background-color:#fff3bf; }
            100% { background-color:#fffbe6; }
        }

        tr.row-glow > td{
            animation: glowPulse 1.6s ease-in-out infinite !important;
        }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <?php if (!empty($_SESSION['flash_ok'])): ?>
                    <div class="alert alert-success"><?= h($_SESSION['flash_ok']) ?></div>
                    <?php unset($_SESSION['flash_ok']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['flash_err'])): ?>
                    <div class="alert alert-danger"><?= h($_SESSION['flash_err']) ?></div>
                    <?php unset($_SESSION['flash_err']); ?>
                <?php endif; ?>

                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header border-bottom-0 pb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <label class="main-content-label my-auto pt-2">لیست درخواست‌های لغو پشتیبانی</label>
                                        <div class="tx-12 text-muted mt-1">
                                            <?= $isAdministrator ? 'نمایش همه درخواست‌ها' : ('نمایش درخواست‌های شما: ' . h($me)) ?>
                                        </div>
                                    </div>

                                    <div class="filters">
                                        <?php
                                        $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
                                        $btnClass = fn($v) => ($statusFilter === $v ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-primary');
                                        ?>
                                        <a class="<?= h($btnClass(-1)) ?>" href="<?= h($base) ?>">همه</a>
                                        <a class="<?= h($btnClass(0)) ?>" href="<?= h($base . '?status=0') ?>">در انتظار</a>
                                        <a class="<?= h($btnClass(1)) ?>" href="<?= h($base . '?status=1') ?>">تایید شده</a>
                                        <a class="<?= h($btnClass(2)) ?>" href="<?= h($base . '?status=2') ?>">رد شده</a>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table table-bordered table-striped align-middle row-hover mb-0">
                                        <thead class="bg-light">
                                        <tr>
                                            <th class="text-center" style="width:60px">#</th>

                                            <?php if ($isAdministrator): ?>
                                                <th class="text-center" style="width:150px">ارسال‌کننده</th>
                                                <th class="text-center" style="width:210px">بررسی توسط</th>

                                            <?php endif; ?>

                                            <th class="text-center" style="width:160px">دانش آموز</th>
                                            <th class="text-center" style="width:130px">نام کاربری</th>
                                            <th class="text-center" style="width:150px">نوع درخواست</th>
                                            <th class="text-center" style="width:120px">وضعیت</th>
                                            <th class="text-center" style="width:170px">تاریخ ثبت</th>
                                            <th class="text-center" style="width:260px">علت رد</th>
                                        </tr>
                                        </thead>

                                        <tbody>
                                        <?php if (!$rows): ?>
                                            <tr>
                                                <td colspan="<?= $isAdministrator ? 9 : 7 ?>" class="text-center text-muted">موردی یافت نشد.</td>
                                            </tr>
                                        <?php else:
                                            $i = 1;
                                            foreach ($rows as $r):

                                                $status     = (int)($r['status'] ?? 0);
                                                $mode       = (int)($r['requested_mode'] ?? 0);

                                                $reviewedAt = trim((string)($r['reviewed_at'] ?? ''));
                                                $seenAt     = trim((string)($r['seen_at'] ?? ''));

                                                $shouldGlow = (
                                                    !$isAdministrator &&
                                                    in_array($status, [1,2], true) &&
                                                    $reviewedAt !== '' &&
                                                    $seenAt === ''
                                                );

                                                $studentFullName = trim((string)($r['student_fname'] ?? '') . ' ' . (string)($r['student_lname'] ?? ''));
                                                $studentUsername = (string)($r['student_username'] ?? '');

                                                $createdAt = (string)($r['created_at'] ?? '');
                                                $createdJ  = '—';
                                                if ($createdAt !== '') {
                                                    $ts = strtotime($createdAt);
                                                    $createdJ = $ts ? jdate('Y/m/d H:i', $ts) : h($createdAt);
                                                }

                                                $reviewedBy = trim((string)($r['reviewed_by'] ?? ''));

                                                $reviewInfo = '—';
                                                if ($reviewedBy !== '') {
                                                    $reviewInfo = $reviewedBy;
                                                    if ($reviewedAt !== '') {
                                                        $ts2 = strtotime($reviewedAt);
                                                        $reviewInfo .= $ts2 ? (' - ' . jdate('Y/m/d H:i', $ts2)) : (' - ' . $reviewedAt);
                                                    }
                                                }


                                                $rejectReason = trim((string)($r['reject_reason'] ?? ''));
                                                ?>
                                                <tr class="<?= $shouldGlow ? 'row-glow' : '' ?>">
                                                    <td class="text-center"><?= $i++ ?></td>

                                                    <?php if ($isAdministrator): ?>
                                                        <td class="text-center">
                                                            <span class="badge bg-info"><?= h($r['requested_by'] ?? '—') ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="small"><?= h($reviewInfo) ?></span>
                                                        </td>

                                                    <?php endif; ?>

                                                    <td class="text-center">
                                                        <?= $studentFullName !== '' ? h($studentFullName) : '<span class="text-muted">—</span>' ?>
                                                    </td>

                                                    <td class="text-center"><?= h($studentUsername !== '' ? $studentUsername : '—') ?></td>

                                                    <td class="text-center">
                                                        <span class="badge bg-secondary"><?= h(op_mode_fa($mode)) ?></span>
                                                    </td>

                                                    <td class="text-center">
                                                        <span class="badge <?= h(req_badge_class($status)) ?>">
                                                            <?= h(req_status_fa($status)) ?>
                                                        </span>
                                                    </td>

                                                    <td class="text-center">
                                                        <span class="small"><?= h($createdJ) ?></span>
                                                    </td>


                                                    <td class="cell-wrap">
                                                        <?= $rejectReason !== '' ? h($rejectReason) : '<span class="text-muted">—</span>' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div><!-- /card-body -->
                        </div><!-- /card -->
                    </div><!-- /col -->
                </div><!-- /row -->

            </div><!-- /inner-body -->
        </div><!-- /container -->
    </div><!-- /main-content -->

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>