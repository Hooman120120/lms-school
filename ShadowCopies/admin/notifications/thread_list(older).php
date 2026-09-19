<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }
if (!isset($_SESSION['admin'])) { header('location: ../login.php'); die(); }

// Protect against SESSION Hijacking (invalidate if IP OR OS mismatches)
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset(); header('location: ../login.php'); die();
}

// Ensure single active session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) { session_unset(); header('location: ' . url('admin/login.php')); die(); }


if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

/* ====== NON-AJAX STATUS UPDATE (simple POST + redirect) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    // Only managers can change status (same rule you use to show the button)
    $me = $_SESSION['admin']['user'] ?? '';
    $meRowStmt = $conn->prepare("SELECT permision_viewalert FROM dbadmin WHERE username=? LIMIT 1");
    $meRowStmt->execute([$me]);
    $isManager = (bool)$meRowStmt->fetchColumn();

    // CSRF check
    $csrfOk = (isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''));

    // Validate inputs
    $id = (int)($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $allowed = ['open','chatting','resolved','rejected'];
    if (!$isManager) {
        $_SESSION['flash_err'] = 'اجازه تغییر وضعیت ندارید.';
    } elseif (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
    } elseif ($id <= 0 || !in_array($status, $allowed, true)) {
        $_SESSION['flash_err'] = 'داده‌های ارسالی نامعتبر است.';
    } else {
        // Update report_threads
        $u = $conn->prepare("UPDATE report_threads SET status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $u->execute([$status, $id]);
        $_SESSION['flash_ok'] = 'وضعیت با موفقیت بروزرسانی شد.';
    }

    // Redirect back to the same page (preserve existing filters)
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    $back = $path . ($qs ? "?$qs" : '');
    header("Location: $back");
    exit;
}

/* =========================
   NORMAL PAGE (GET)
   ========================= */

// Me / permissions
$me = $_SESSION['admin']['user'] ?? '';
$meRowStmt = $conn->prepare("
    SELECT username, permision_viewalert, permision_suporter, permision_viewemploy
    FROM dbadmin
    WHERE username=? LIMIT 1
");
$meRowStmt->execute([$me]);
$meRow = $meRowStmt->fetch(PDO::FETCH_ASSOC);

$isManager  = !empty($meRow['permision_viewalert']); // alert manager
$isOperator = !empty($meRow['permision_suporter']);  // support operator
$canSeeAll  = $isManager && !empty($meRow['permision_viewemploy']); // <-- NEW

// -------- filters (manager only)
$status    = $isManager ? trim($_GET['status'] ?? '') : '';
$fOperator = $isManager ? trim($_GET['op']     ?? '') : $me; // operators see only their own
$fResp     = $isManager ? trim($_GET['resp']   ?? '') : '';
$fCm       = $isManager ? trim($_GET['cm']     ?? '') : '';
$q         = $isManager ? trim($_GET['q']      ?? '') : '';

$where = [];
$args  = [];

if ($isManager) {
    // Visibility rule: if not see-all, show only unassigned OR owned by me
    if (!$canSeeAll) {
        $where[] = "(t.responder_username IS NULL OR t.responder_username = ?)";
        $args[]  = $me;
    }

    // existing filters stay as-is
    if ($status !== '' && in_array($status, ['open','chatting','resolved','rejected'], true)) {
        $where[] = "t.status = ?"; $args[] = $status;
    }
    if ($fOperator !== '') { $where[] = "t.operator_username = ?";   $args[] = $fOperator; }
    if ($fResp !== '')     { $where[] = "t.responder_username = ?";  $args[] = $fResp; }
    if ($fCm !== '')       { $where[] = "t.codemeli = ?";            $args[] = $fCm; }
    if ($q !== '')         { $where[] = "t.title LIKE ?";            $args[] = "%$q%"; }
} else {
    $where[] = "t.operator_username = ?"; $args[] = $fOperator;
}


$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// -------- list query (single, with message count)
$stmt = $conn->prepare("
  SELECT
    t.id,
    t.title,
    t.codemeli,
    t.operator_username,
    t.responder_username,
    t.status,
    t.updated_at,
    p.fname,
    p.lname,
    COALESCE(m.msg_count, 0) AS msg_count
  FROM report_threads t
  LEFT JOIN dbpersonal p ON p.codemeli = t.codemeli
  LEFT JOIN (
    SELECT thread_id, COUNT(*) AS msg_count
    FROM report_messages
    GROUP BY thread_id
  ) m ON m.thread_id = t.id
  $sqlWhere
  ORDER BY t.updated_at DESC
  LIMIT 300
");
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// preload dropdowns for manager
$ops = $resps = [];
if ($isManager) {
    // Operators: support users
    $ops = $conn->query("
        SELECT username
        FROM dbadmin
        WHERE permision_suporter = 1
        ORDER BY username
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Responders: alert managers
    $resps = $conn->query("
        SELECT username
        FROM dbadmin
        WHERE permision_viewalert = 1
        ORDER BY username
    ")->fetchAll(PDO::FETCH_COLUMN);
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>تیکت های اعلان‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .row-hover tbody tr:hover { background:#fafafa; }
        .badge-open { background:#ffc107; color:#111; }
        .badge-chatting { background:#0dcaf0; color:#111; }
        .badge-resolved { background:#28a745; }

        /* subtle pulsing backgrounds */
        @keyframes blinkYellow {
            0%   { background-color: #fffbe6; }   /* light yellow */
            50%  { background-color: #fff3bf; }   /* a bit stronger */
            100% { background-color: #fffbe6; }
        }
        @keyframes blinkRed {
            0%   { background-color: #ffe3e3; }   /* very light red */
            50%  { background-color: #ffc9c9; }   /* a bit stronger */
            100% { background-color: #ffe3e3; }
        }
        .blink-chatting {
            animation: blinkYellow 1.6s ease-in-out infinite;
            will-change: background-color;
        }
        .blink-open {
            animation: blinkRed 1.6s ease-in-out infinite;
            will-change: background-color;
        }
        .row-hover tbody tr:hover {
            animation: none !important;
        }
        @media (prefers-reduced-motion: reduce) {
            .blink-chatting, .blink-open { animation: none; }
        }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>
    <!-- ✅ STRUCTURE -->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <?php if (!empty($_SESSION['flash_ok'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_ok'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php unset($_SESSION['flash_ok']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['flash_err'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_err'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php unset($_SESSION['flash_err']); ?>
                <?php endif; ?>

                <div class="card-body">

                    <?php
                    // safety helper if not already defined
                    if (!function_exists('h')) {
                        function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
                    }
                    function status_label_fa(string $s): string {
                        return $s==='open' ? 'باز'
                            : ($s==='chatting' ? 'در حال گفتگو'
                                : ($s==='resolved' ? 'حل‌شده'
                                    : ($s==='rejected' ? 'تایید نشد' : $s)));
                    }
                    function badge_class(string $s): string {
                        if ($s==='resolved') return 'bg-success';
                        if ($s==='chatting') return 'bg-warning text-dark';
                        if ($s==='rejected') return 'bg-secondary';
                        return 'bg-danger';
                    }

                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">لیست تیکت‌ها</h5>
                    </div>

                    <?php if ($isManager): ?>
                        <!-- Manager filters -->
                        <form class="row g-2 mb-3" method="get">
                            <div class="col-6 col-md-2">
                                <label class="form-label">وضعیت</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">همه</option>
                                    <option value="open"     <?= ($status==='open'?'selected':'') ?>>باز</option>
                                    <option value="chatting" <?= ($status==='chatting'?'selected':'') ?>>در حال گفتگو</option>
                                    <option value="resolved" <?= ($status==='resolved'?'selected':'') ?>>حل‌شده</option>
                                    <option value="rejected" <?= ($status==='rejected'?'selected':'') ?>>تایید نشد</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label">اپراتور</label>
                                <input list="ops" name="op" value="<?= h($fOperator) ?>" class="form-control form-control-sm">
                                <datalist id="ops"><?php foreach ($ops as $u) echo '<option value="'.h($u).'">'; ?></datalist>
                            </div>
                            <?php if ($canSeeAll): ?>
                                <div class="col-6 col-md-2">
                                    <label class="form-label">پاسخ‌دهنده</label>
                                    <input list="resps" name="resp" value="<?= h($fResp) ?>" class="form-control form-control-sm">
                                    <datalist id="resps"><?php foreach ($resps as $u) echo '<option value="'.h($u).'">'; ?></datalist>
                                </div>
                            <?php endif; ?>                            <div class="col-6 col-md-2">
                                <label class="form-label">کد ملی</label>
                                <input name="cm" value="<?= h($fCm) ?>" maxlength="12" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">عنوان</label>
                                <input name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="جستجو در عنوان">
                            </div>
                            <div class="col-6 col-md-1 d-flex align-items-end">
                                <button class="btn btn-outline-info btn-sm w-100">فیلتر</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <?php
                        $colspan = 6
                            + ($isManager ? 1 : 0)
                            + ($isManager && $canSeeAll ? 1 : 0);
                        ?>
                        <table class="table table-bordered table-striped align-middle row-hover">
                            <thead class="bg-light">
                            <tr class="border-light">
                                <th class="text-center" style="width:60px">#</th>
                                <th>عنوان</th>
                                <th>دانش‌آموز</th>
                                <th class="text-center">وضعیت</th>
                                <?php if ($isManager): ?>
                                    <th class="text-center">اپراتور</th>
                                    <?php if ($canSeeAll): ?>
                                        <th class="text-center">پاسخ‌دهنده</th>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <th class="text-center">آخرین بروزرسانی</th>
                                <th class="text-center" style="width:260px">اقدامات</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="<?= $colspan ?>" class="text-center text-muted">موردی یافت نشد.</td></tr>
                            <?php else:
                                $i=1;
                                foreach ($rows as $r):

                                    $statusText = $r['status'];
                                    $badgeClass = badge_class($statusText);
                                    $statusLabelFa = status_label_fa($statusText);

                                    $rowClass = '';
                                    if ($statusText === 'chatting') {
                                        $rowClass = 'blink-chatting';
                                    } elseif ($statusText === 'open') {
                                        $rowClass = 'blink-open';
                                    }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="text-center"><?= $i++ ?></td>
                                        <td>
                                            <a class="fw-bold text-decoration-none"
                                               href="thread_view.php?id=<?= (int)$r['id'] ?>">
                                                <?= h($r['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?= h(trim(($r['fname'] ?? '').' '.($r['lname'] ?? ''))) ?>
                                            <span class="text-muted small">| <?= h($r['codemeli']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $badgeClass ?>" data-status-badge="1">
                                                <?= h($statusLabelFa) ?>
                                            </span>
                                        </td>

                                        <?php if ($isManager): ?>
                                            <td class="text-center"><?= h($r['operator_username']) ?></td>

                                            <?php if ($canSeeAll): ?>
                                                <td class="text-center"><?= h($r['responder_username'] ?: '—') ?></td>
                                            <?php endif; ?>

                                        <?php endif; ?>

                                        <?php
                                        $ts = !empty($r['updated_at']) ? strtotime($r['updated_at']) : 0;
                                        $jalali = $ts ? jdate('Y/m/d H:i', $ts) : '—';
                                        ?>
                                        <td class="text-center">
                                          <span class="small " title="<?= h($r['updated_at']) ?>">
                                            <?= h($jalali) ?>
                                          </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-2">

                                                <?php if (!$isManager): ?>
                                                    <!-- Operator: always allowed to open the thread now -->
                                                    <a class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
                                                       href="thread_view.php?id=<?= (int)$r['id'] ?>">
                                                        <i class="fe fe-eye"></i><span>مشاهده</span>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($isManager): ?>
                                                    <?php
                                                    // manager status button logic unchanged
                                                    $btnClass = $statusText==='resolved' ? 'btn-success'
                                                        : ($statusText==='chatting' ? 'btn-warning text-dark'
                                                            : ($statusText==='rejected' ? 'btn-secondary'
                                                                : 'btn-danger'));
                                                    ?>
                                                    <?php if (in_array($statusText, ['resolved','rejected'], true)): ?>
                                                        <button class="btn btn-sm <?= $btnClass ?>" disabled><?= h(status_label_fa($statusText)) ?></button>
                                                    <?php else: ?>
                                                        <button
                                                                class="btn btn-sm <?= $btnClass ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statusModal"
                                                                data-thread-id="<?= (int)$r['id'] ?>"
                                                                data-status="<?= h($statusText) ?>">
                                                            وضعیت: <?= h($statusLabelFa) ?>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>

                        </table>
                    </div>

                </div>
                <!-- Modal -->
                <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <!-- action="" => post back to same page; NO JS needed -->
                        <form method="post" action="" class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">تغییر وضعیت تیکت</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body d-flex gap-2 align-items-center">
                                <input type="hidden" name="id" value="">
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                <select name="status" class="form-select">
                                    <option value="open">باز</option>
                                    <option value="chatting">در حال گفتگو</option>
                                    <option value="resolved">تایید و حل‌شده</option>
                                    <option value="rejected">تایید نشد</option>

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
    // Prefill modal fields from the clicked button
    document.getElementById('statusModal')?.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const id = btn?.getAttribute('data-thread-id') || '';
        const st = btn?.getAttribute('data-status') || 'open';
        const form = this.querySelector('form');
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('select[name="status"]').value = st;
    });
    document.querySelector('#statusModal form')?.addEventListener('submit', function () {
        // if user picked resolved, lock the row button right away (UX only; server still redirects)
        const modal = document.getElementById('statusModal');
        const invoker = document.querySelector(modal.dataset.invokerSelector);
        const chosen = this.querySelector('select[name="status"]').value;
        if (chosen === 'resolved' && invoker) {
            invoker.textContent = 'تایید و حل‌شده';
            invoker.className = 'btn btn-sm btn-success';
            invoker.disabled = true;
            invoker.removeAttribute('data-bs-toggle');
            invoker.removeAttribute('data-bs-target');
        }
    });
</script>
</body>
</html>
