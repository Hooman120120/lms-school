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

function ticket_status_fa(int $s): string {
    return $s === 0 ? 'باز'
        : ($s === 1 ? 'انجام شد'
            : ($s === 2 ? 'بررسی شود'
                : ($s === 3 ? 'در حال مکالمه' : (string)$s)));
}
function ticket_badge_class(int $s): string {
    if ($s === 1) return 'bg-success';
    if ($s === 3) return 'bg-warning text-dark';
    if ($s === 2) return 'bg-info text-dark';
    return 'bg-danger';
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// who am I
$me = $_SESSION['admin']['user'] ?? '';

// administrator flag
$sq = "SELECT administrator FROM dbadmin WHERE username = ? LIMIT 1";
$st = $conn->prepare($sq);
$st->execute([$me]);
$adminRow = $st->fetch(PDO::FETCH_ASSOC);
$isAdministrator = !empty($adminRow) && (int)$adminRow['administrator'] === 1;

/* =========================
   POST: set ticket status
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    $csrfOk = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? -1);

    $allowed = [0,1,2,3];

    if (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
    } elseif ($id <= 0 || !in_array($status, $allowed, true)) {
        $_SESSION['flash_err'] = 'داده‌های ارسالی نامعتبر است.';
    } else {
        // load ticket and verify access (admin sees all; others only own tickets)
        $tq = $conn->prepare("SELECT id, ticket_creator, ticket_assigned_to FROM admin_tickets WHERE id=? LIMIT 1");
        $tq->execute([$id]);
        $t = $tq->fetch(PDO::FETCH_ASSOC);

        if (!$t) {
            $_SESSION['flash_err'] = 'تیکت یافت نشد.';
        } else {
            $isMine = ($t['ticket_creator'] === $me) || ($t['ticket_assigned_to'] === $me);

            // if you want STRICTLY assigned-only for non-admin, replace $isMine with:
            // $isMine = ($t['ticket_assigned_to'] === $me);

            if (!$isAdministrator && !$isMine) {
                $_SESSION['flash_err'] = 'شما اجازه تغییر وضعیت این تیکت را ندارید.';
            } else {
                $up = $conn->prepare("UPDATE admin_tickets SET ticket_status=?, updated_at=NOW() WHERE id=? LIMIT 1");
                $up->execute([$status, $id]);
                $_SESSION['flash_ok'] = 'وضعیت تیکت بروزرسانی شد.';
            }
        }
    }

    // PRG redirect
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    header("Location: " . $path . ($qs ? "?$qs" : ""));
    exit;
}

/* =========================
   GET: list tickets
   ========================= */

// optional filters
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$typeFilter = 'new_students';

$where = [];
$args  = [];

// scope
if ($isAdministrator) {
    // all tickets
} else {
    // "own tickets" = created by me OR assigned to me
    $where[] = "(t.ticket_creator = ? OR t.ticket_assigned_to = ?)";
    $args[]  = $me;
    $args[]  = $me;

    // If you want STRICTLY assigned-only for non-admin, use:
    // $where = ["t.ticket_assigned_to = ?"];
    // $args  = [$me];
}

// filters
// type filter (ONLY new_students)
$where[] = "t.type = ?";
$args[]  = $typeFilter; // 'new_students'

if (in_array($statusFilter, [0,1,2,3], true)) {
    $where[] = "t.ticket_status = ? ";
    $args[]  = $statusFilter;
}


$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT
        t.*,

        p.fname AS student_fname,
        p.lname AS student_lname,

        (SELECT COUNT(*) FROM admin_messages m WHERE m.admin_ticket_id = t.id) AS msg_count,

        (SELECT COUNT(*)
         FROM admin_messages m
         WHERE m.admin_ticket_id = t.id
           AND m.sender_username <> ?
           AND (m.seen_by IS NULL OR m.seen_by <> ?)
        ) AS unseen_for_me

    FROM admin_tickets t
    LEFT JOIN dbpersonal p
        ON p.codemeli = t.student_username
    $sqlWhere
    
    ORDER BY t.updated_at DESC
    LIMIT 300
";



$stmt = $conn->prepare($sql);
$stmt->execute(array_merge([$me, $me], $args));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>تیکت‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        .row-hover tbody tr:hover { background:#fafafa; }

        @keyframes blinkYellow { 0% {background:#fffbe6;} 50% {background:#fff3bf;} 100% {background:#fffbe6;} }
        @keyframes blinkRed    { 0% {background:#ffe3e3;} 50% {background:#ffc9c9;} 100% {background:#ffe3e3;} }

        .blink-ongoing { animation: blinkYellow 1.6s ease-in-out infinite; will-change: background-color; }
        .blink-open    { animation: blinkRed    1.6s ease-in-out infinite; will-change: background-color; }
        .row-hover tbody tr:hover { animation: none !important; }
        @media (prefers-reduced-motion: reduce) { .blink-ongoing, .blink-open { animation: none; } }

        .unseen-dot{
            position:absolute;
            top:-6px;
            left:-6px;
            min-width:18px;
            height:18px;
            padding:0 4px;
            border-radius:999px;
            background:#ffc107; /* yellow */
            color:#000;
            font-size:11px;
            line-height:18px;
            text-align:center;
            box-shadow:0 0 0 2px rgba(255,255,255,.9);
        }

        /* limit long subject/type cells */
        .cell-wrap {
            max-width: 320px;
            white-space: normal;
            word-break: break-word;
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
                                    <label class="main-content-label my-auto pt-2">لیست تیکت‌ها</label>

                                    <!-- later: create button -->
                                    <!-- <a class="btn btn-sm btn-success" href="<?= h(url('admin/tickets/create.php')) ?>">➕ ایجاد تیکت</a> -->
                                </div>
                            </div>

                            <div class="card-body">

                                <div class="table-responsive">
                                    <table id="example1" class="table table-bordered table-striped align-middle row-hover mb-0">
                                        <thead class="bg-light">
                                        <tr>
                                            <th class="text-center" style="width:60px">#</th>
                                            <?php if ($isAdministrator): ?>
                                                <th class="text-center" style="width:130px">ارسال‌کننده</th>
                                            <?php endif; ?>
                                            <th class="text-center" style="width:120px">نوع</th>
                                            <th>موضوع</th>
                                            <th class="text-center" style="width:160px">دانش آموز</th>
                                            <th class="text-center" style="width:130px">مسئول</th>
                                            <th class="text-center" style="width:120px">وضعیت</th>
                                            <th class="text-center" style="width:170px">آخرین بروزرسانی</th>
                                            <th class="text-center" style="width:260px">اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$rows): ?>
                                            <tr>
                                                <td colspan="<?= $isAdministrator ? 9 : 8 ?>" class="text-center text-muted">موردی یافت نشد.</td>
                                            </tr>
                                        <?php else:
                                            $i=1;
                                            foreach ($rows as $r):
                                                $st = (int)$r['ticket_status'];
                                                $rowClass = ($st === 3) ? 'blink-ongoing' : (($st === 0) ? 'blink-open' : '');
                                                $unseen = (int)($r['unseen_for_me'] ?? 0);

                                                $ts = !empty($r['updated_at']) ? strtotime($r['updated_at']) : 0;
                                                $jalali = $ts ? jdate('Y/m/d H:i', $ts) : '—';

                                                $subject = trim((string)($r['subject'] ?? ''));
                                                $type    = trim((string)($r['type'] ?? ''));
                                                $creator = (string)($r['ticket_creator'] ?? '');
                                                $assigned= (string)($r['ticket_assigned_to'] ?? '');
                                                $id      = (int)$r['id'];

                                                // thread url (absolute root-safe)
                                                $threadUrl = url("admin/notifications/admin_chat.php?id={$id}");
                                                ?>
                                                <tr class="<?= h($rowClass) ?>">
                                                    <td class="text-center"><?= $i++ ?></td>

                                                    <?php if ($isAdministrator): ?>
                                                        <td class="text-center">
                                                            <span class="badge bg-info"><?= h($creator ?: '—') ?></span>
                                                        </td>
                                                    <?php endif; ?>

                                                    <td class="text-center">
                                                        <span class="badge bg-secondary">دانش آموز جدید</span>
                                                    </td>

                                                    <td class="cell-wrap">
                                                        <a class="fw-bold text-decoration-none"
                                                           href="<?= h($threadUrl) ?>"
                                                           onclick="window.open(
                                                                this.href,
                                                                'adminTicket',
                                                                'width=1200,height=800,menubar=0,toolbar=0,location=0,status=0,scrollbars=1,resizable=1,top=80'
                                                           ); return false;">
                                                            <?= h($subject !== '' ? $subject : '—') ?>
                                                        </a>
                                                    </td>
                                                    <?php
                                                    $studentFullName = trim((string)($r['student_fname'] ?? '') . ' ' . (string)($r['student_lname'] ?? ''));
                                                    ?>
                                                    <td class="text-center">
                                                        <?= $studentFullName !== '' ? h($studentFullName) : '<span class="text-muted">—</span>' ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?php if ($assigned !== ''): ?>
                                                            <?= h($assigned) ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <span class="badge <?= h(ticket_badge_class($st)) ?>">
                                                            <?= h(ticket_status_fa($st)) ?>
                                                        </span>
                                                    </td>

                                                    <td class="text-center">
                                                        <span class="small"><?= h($jalali) ?></span>
                                                    </td>

                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center align-items-center gap-2 position-relative">

                                                            <!-- View thread -->
                                                            <a class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 position-relative"
                                                               href="<?= h($threadUrl) ?>"
                                                               onclick="window.open(
                                                                    this.href,
                                                                    'adminTicket',
                                                                    'width=1200,height=800,menubar=0,toolbar=0,location=0,status=0,scrollbars=1,resizable=1,top=80'
                                                               ); return false;">
                                                                <i class="fe fe-eye"></i><span>گفتگو</span>
                                                                <?php if ($unseen > 0): ?>
                                                                    <span class="unseen-dot"><?= (int)$unseen ?></span>
                                                                <?php endif; ?>
                                                            </a>

                                                            <!-- Change status (admin or owner) -->
                                                            <?php
                                                            $isMine = ($creator === $me) || ($assigned === $me);
                                                            if ($isAdministrator ):
                                                                ?>
                                                                <button
                                                                    class="btn btn-sm btn-outline-secondary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#statusModal"
                                                                    data-ticket-id="<?= (int)$id ?>"
                                                                    data-status="<?= (int)$st ?>">
                                                                    تغییر وضعیت
                                                                </button>
                                                            <?php endif; ?>

                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Modal -->
                <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="post" action="" class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">تغییر وضعیت تیکت</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body d-flex gap-2 align-items-center">
                                <input type="hidden" name="id" value="">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

                                <select name="status" class="form-select">
                                    <option value="0">باز</option>
                                    <option value="3">در حال مکالمه</option>
                                    <option value="2">بررسی شود</option>
                                    <option value="1">انجام شد</option>
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary" type="submit">ثبت</button>
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">بستن</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

<script>
    document.getElementById('statusModal')?.addEventListener('show.bs.modal', function (e) {
        const btn  = e.relatedTarget;
        const id   = btn?.getAttribute('data-ticket-id') || '';
        const st   = btn?.getAttribute('data-status') || '0';
        const form = this.querySelector('form');
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('select[name="status"]').value = st;
    });
</script>

</body>
</html>
