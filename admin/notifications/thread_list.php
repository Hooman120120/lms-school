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
    $isManager = (bool)$meRowStmt->fetchColumn()
        || in_array(SchoolStaffRoleKey($me), ['manager', 'general_deputy'], true);

    // CSRF check
    $csrfOk = (isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''));

    // Validate inputs
    $id = (int)($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));
    $allowed = ['open','chatting','resolved','rejected'];
    if (!$isManager) {
        $_SESSION['flash_err'] = 'اجازه تغییر وضعیت ندارید.';
    }
//    elseif (!$csrfOk) {
//        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
//    }
    elseif ($id <= 0 || !in_array($status, $allowed, true)) {
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

$schoolRoleKey = SchoolStaffRoleKey($me);
$isSchoolManager = in_array($schoolRoleKey, ['manager', 'general_deputy'], true);
$isManager  = !empty($meRow['permision_viewalert']) || $isSchoolManager;
$isOperator = !empty($meRow['permision_suporter']) || $schoolRoleKey === 'counselor';
$canSeeAll  = $isSchoolManager || ($isManager && !empty($meRow['permision_viewemploy']));
if (!$isManager && !$isOperator) {
    http_response_code(403);
    exit('دسترسی تیکت‌های اعلان تماس برای سمت شما تعریف نشده است.');
}

// ===== Notification rule (needed for fail-counter) =====
$rule = $conn->query("
    SELECT period_days, min_calls, min_talk_time
    FROM notification_rules
    WHERE active=1
    ORDER BY created_at DESC, id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$periodDays = max(1, (int)($rule['period_days'] ?? 1));
$MIN_CALLS  = (int)($rule['min_calls'] ?? 5);      // successful calls threshold
$MIN_TALK   = (int)($rule['min_talk_time'] ?? 300);  // seconds threshold

// -------- filters
$status    = trim($_GET['status'] ?? '');
$fOperator = $isManager ? trim($_GET['op']   ?? '') : $me;  // operators = self
$fResp     = $isManager ? trim($_GET['resp'] ?? '') : '';
$fCm       = trim($_GET['cm'] ?? '');
$q         = trim($_GET['q']  ?? '');

$where = [];
$args  = [];

// ======================= CHANGED SECTION START =======================
// Handle status filtering centrally
if ($status !== '' && in_array($status, ['open','chatting','resolved','rejected'], true)) {
    // If a specific status is requested via the URL (e.g., ?status=resolved), filter by it.
    $where[] = "t.status = ?";
    $args[] = $status;
} else {
    // By default, if no status is specified, hide the 'resolved' (حل شده) tickets.
    $where[] = "t.status <> 'resolved'";
}

if ($isManager) {
    // Visibility rule: if not see-all, show only unassigned OR owned by me
    if (!$canSeeAll) {
        $where[] = "(t.responder_username IS NULL OR t.responder_username = ?)";
        $args[]  = $me;
    }

    // Add other manager-specific filters (status is already handled above)
    if ($fOperator !== '') { $where[] = "t.operator_username = ?";   $args[] = $fOperator; }
    if ($fResp !== '')     { $where[] = "t.responder_username = ?";  $args[] = $fResp; }
    if ($fCm !== '')       { $where[] = "t.codemeli = ?";            $args[] = $fCm; }
    if ($q !== '')         { $where[] = "t.title LIKE ?";            $args[] = "%$q%"; }

} else {
    // Operator scope: only own threads, plus allowed filters (status is already handled above)
    $where[] = "t.operator_username = ?"; $args[] = $fOperator;

    // Add other operator-specific filters
    if ($fCm !== '') { $where[] = "t.codemeli = ?";    $args[] = $fCm; }
    if ($q !== '')   { $where[] = "t.title LIKE ?";    $args[] = "%$q%"; }
}
// ======================= CHANGED SECTION END =======================

$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* -------- list query (with total messages + unseen-for-me) -------- */
$unseenExpr = $isManager
    ? "(SELECT COUNT(*) FROM report_messages mm
        WHERE mm.thread_id = t.id
          AND mm.sender_username <> ?
          AND mm.seen_by_manager = 0)"
    : "(SELECT COUNT(*) FROM report_messages mm
        WHERE mm.thread_id = t.id
          AND mm.sender_username <> ?
          AND mm.seen_by_operator = 0)";

$failCounterExpr = "
  COALESCE((
    SELECT SUM(
      CASE
        WHEN (x.successful_calls < $MIN_CALLS OR x.total_talk_time < $MIN_TALK) THEN 1
        ELSE 0
      END
    )
    FROM (
      SELECT
        GREATEST(COALESCE(s.total_calls,0) - COALESCE(s.unanswered_calls,0), 0) AS successful_calls,
        COALESCE(s.total_talk_time,0) AS total_talk_time,
        ROW_NUMBER() OVER (
          PARTITION BY FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / $periodDays)
          ORDER BY s.last_updated DESC, s.id DESC
        ) AS rn
      FROM simotel_calls_data s
      WHERE s.codemeli = t.codemeli
        AND s.operator_username = t.operator_username
        AND FLOOR(DATEDIFF(CURDATE(), DATE(s.last_updated)) / $periodDays) >= 1   -- exclude current block
    ) x
    WHERE x.rn = 1
  ), 0) AS fail_counter
";

$sql = "
WITH olympiad_enrollment AS (
    SELECT
        dc.username AS codemeli,
        MAX(CASE 
            WHEN ctc.course_type = 2 AND ctc.courseactive = 1 THEN 1 
            ELSE 0 
        END) AS has_active_olympiad_course
    FROM
        dbcourse dc
    JOIN
        codtbl_course ctc ON dc.codecourse = ctc.codecourse
    WHERE
        ctc.courseactive = 1 -- Optimization: only check active course mapping
    GROUP BY
        dc.username
)
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
    p.paye,
    /* total messages in thread */
    COALESCE(m.msg_count, 0) AS msg_count,
    /* unseen for current viewer */
    $unseenExpr AS unseen_for_me,
    /* fail counter badge */
    $failCounterExpr,
    /* New column from the CTE */
    COALESCE(oe.has_active_olympiad_course, 0) AS has_active_olympiad_course
FROM 
    report_threads t
      
LEFT JOIN 
    dbpersonal p ON p.codemeli = t.codemeli
      
LEFT JOIN (
    SELECT thread_id, COUNT(*) AS msg_count
    FROM report_messages
    GROUP BY thread_id
) m ON m.thread_id = t.id
      
/* JOIN the new CTE */
LEFT JOIN 
    olympiad_enrollment oe ON oe.codemeli = t.codemeli
      
$sqlWhere
  
ORDER BY 
    t.updated_at DESC
LIMIT 300;

";

/* bind order: the SELECT subquery placeholder(s) first, then filter args */
$selectArgs = [$me];
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge($selectArgs, $args));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// preload dropdowns for manager
$ops = $resps = [];
if ($isManager) {
    // Operators: support users
    $ops = $conn->query("
        SELECT username
        FROM dbadmin
        WHERE permision_suporter = 1
           OR school_role_id = (SELECT id FROM school_admin_roles WHERE role_key = 'counselor' LIMIT 1)
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
        @keyframes blinkYellow { 0% {background:#fffbe6;} 50% {background:#fff3bf;} 100% {background:#fffbe6;} }
        @keyframes blinkRed    { 0% {background:#ffe3e3;} 50% {background:#ffc9c9;} 100% {background:#ffe3e3;} }
        .blink-chatting { animation: blinkYellow 1.6s ease-in-out infinite; will-change: background-color; }
        .blink-open     { animation: blinkRed    1.6s ease-in-out infinite; will-change: background-color; }
        .row-hover tbody tr:hover { animation: none !important; }
        @media (prefers-reduced-motion: reduce) { .blink-chatting, .blink-open { animation: none; } }

        /* tiny red unread counter for مشاهده button */
        .unseen-dot{
            position: absolute;
            top: -6px;
            left: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: #dc3545;
            color: #fff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 0 0 2px rgba(255,255,255,.9);
        }
        #example5 th, #example5 td {
            white-space: nowrap;
        }

        #example5 td:nth-child(3),   /* عنوان */
        #example5 td:nth-child(4) {  /* دانش‌آموز */
            white-space: normal;       /* allow wrap */
            max-width: 260px;          /* pick a value you like */
            word-break: break-word;
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

                    <div class="table-responsive">
                        <?php
                        $colspan = 6
                            + ($isManager ? 1 : 0)
                            + ($isManager && $canSeeAll ? 1 : 0);
                        ?>
                        <div class="col-12 d-flex flex-row justify-content-center gap-2 mb-3" id="payeFilters">

                            <button type="button" class="btn btn-info shadow rounded active" data-filter="all">همه</button>
                            <button type="button" class="btn btn-info shadow rounded" data-filter="olympiad" data-olympiad="1">المپیاد</button>

                            <!-- Elementary -->
                            <div class="btn-group dropdown">
                                <button type="button" class="btn btn-info shadow rounded" data-range="1-6">
                                    <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                </button>
                                <button type="button" class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" type="button" data-paye="1">کلاس اول دبستان</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="2">کلاس دوم دبستان</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="3">کلاس سوم دبستان</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="4">کلاس چهارم دبستان</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="5">کلاس پنجم دبستان</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="6">کلاس ششم دبستان</button></li>
                                </ul>
                            </div>

                            <!-- Middle School -->
                            <div class="btn-group dropdown">
                                <button type="button" class="btn btn-success shadow rounded" data-range="7-9">
                                    <i class="fa fa-book"></i> متوسطه اول
                                </button>
                                <button type="button" class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" type="button" data-paye="7">هفتم</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="8">هشتم</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="9">نهم</button></li>
                                </ul>
                            </div>

                            <!-- High School -->
                            <div class="btn-group dropdown">
                                <button type="button" class="btn btn-warning shadow rounded" data-range="10-12">
                                    <i class="fa fa-star"></i> متوسطه دوم
                                </button>
                                <button type="button" class="btn btn-warning shadow rounded dropdown-toggle dropdown-toggle-split"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" type="button" data-paye="10">دهم</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="11">یازدهم</button></li>
                                    <li><button class="dropdown-item" type="button" data-paye="12">دوازدهم</button></li>
                                </ul>
                            </div>

                        </div>

                        <table id="example5" class="table table-bordered table-striped align-middle row-hover">
                            <thead class="bg-light">
                            <tr class="border-light">
                                <th class="text-center" style="width:60px">#</th>
                                <th>ورود</th>
                                <th style="max-width: 200px;">عنوان</th>
                                <th>دانش‌آموز</th>
                                <th>پایه</th>
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
                                <tr> <td colspan="<?= $colspan ?>" class="text-center text-muted">موردی یافت نشد.</td></tr>
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

                                    // counts for button
                                    $msgTotal = (int)($r['msg_count'] ?? 0);
                                    $unseen   = (int)($r['unseen_for_me'] ?? 0);

                                    // fail counter badge
                                    $fc = (int)($r['fail_counter'] ?? 0);

                                    ?>
                                    <tr class="<?= $rowClass ?>"   data-paye="<?= $r['paye'] ?>">
                                        <td data-olympiad-flag="<?= (int)$r['has_active_olympiad_course'] ?>" class="text-center">
                                            <span
                                                    class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-bold ms-2"
                                                    style="width: 26px; height: 26px; font-size: 12px; background: <?= ($fc>0 ? '#dc3545' : '#6c757d') ?>;"
                                                    title="کانتر"
                                            ><?= $fc ?></span>
                                        </td>
                                        <td class="wd-15">
                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                 onclick="window.open('../panel/suporting.php?Id='+'<?php echo h($r['codemeli']) ?>','پشتیبانی'
                                                         ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                <i class="fa fa-key"></i></div>

                                        </td>

                                        <td  style="max-width: 200px; ">
                                            <a class="fw-bold text-decoration-none" style=" display: flex; flex-wrap: wrap; "
                                               href="thread_view.php?id=<?= (int)$r['id'] ?>">
                                                <?= h($r['title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?= h(trim(($r['fname'] ?? '').' '.($r['lname'] ?? ''))) ?>
                                            <span class="text-muted small">| <?= h($r['codemeli']) ?></span>
                                        </td>
                                        <td><?= h($r['paye']) ?></td>
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
                                            <div class="d-flex justify-content-center align-items-center gap-2 position-relative">

                                                <!-- Everyone: role-aware مشاهده with badges -->
                                                <a class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 position-relative"
                                                   href="thread_view.php?id=<?= (int)$r['id'] ?>">
                                                    <i class="fe fe-eye"></i><span>گفتگو</span>
                                                    <?php if ($unseen > 0): ?>
                                                        <span class="unseen-dot"><?= $unseen ?></span>
                                                    <?php endif; ?>
                                                </a>

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
    // --- MODAL PRE-FILL LOGIC ---

    // 1. Status Modal Prefill
    document.getElementById('statusModal')?.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const id = btn?.getAttribute('data-thread-id') || '';
        const st = btn?.getAttribute('data-status') || 'open';
        const form = this.querySelector('form');
        if (form) {
            form.querySelector('input[name="id"]').value = id;
            form.querySelector('select[name="status"]').value = st;
        }
    });

    // 2. Ignore Modal Prefill
    const ignoreModal = document.getElementById('ignoreModal');
    if (ignoreModal) {
        ignoreModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const cm = button?.getAttribute('data-codemeli') || '';
            document.getElementById('ignoreCodemeli').value = cm;
        });
    }

    // 3. Status Modal Submit (UX only hint)
    document.querySelector('#statusModal form')?.addEventListener('submit', function () {
        const chosen = this.querySelector('select[name="status"]').value;
        if (chosen === 'resolved') {
            // Implement any visual lock/hint here if needed
        }
    });

    // --- FORM VALIDATION LOGIC (Enforce "سایر" requires text) ---
    (function(){
        const form   = document.getElementById('createThreadForm');
        const select = document.getElementById('reason_code');
        const text   = document.getElementById('reason_text');

        if (form && select && text) {
            // Submit guard
            form.addEventListener('submit', function(e){
                if (select.value === '4' && text.value.trim() === '') {
                    e.preventDefault();
                    alert('برای گزینه «سایر» درج توضیحات الزامی است.');
                    text.focus();
                }
            });

            // Toggle required attribute on change
            const toggleReq = () => { text.required = (select.value === '4'); };
            select.addEventListener('change', toggleReq);
            toggleReq(); // Initial check
        }
    })();

    // --- UTILITY FUNCTION ---
    function openSupportProfile(codemeli) {
        window.open(
            '../panel/suporting.php?Id=' + encodeURIComponent(codemeli),
            'پشتیبانی',
            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
        );
    }

    // --- DATATABLES INITIALIZATION AND FILTERING ---
    $(function () {
        // State to hold filter values
        let filterState = {
            paye: 'all',
            range: 'all',
            olympiad: 'all',
            exact: null, // For exact paye match
            min: null,   // For range matching
            max: null    // For range matching
        };

        // Initialize DataTable with styling callback
        const table = $("#example5").DataTable({
            stateSave: true,
            language: {
                searchPlaceholder: "جستجو کردن...",
                sSearch: "",
                lengthMenu: "_MENU_ موارد/صفحه"
            },
            drawCallback: function(settings) {
                let titleColumnIndex = -1;

                // 1. Find the index of the "عنوان" column dynamically
                $(this.api().table().header()).find('th').each(function(index) {
                    if ($(this).text().trim() === 'عنوان') {
                        titleColumnIndex = index;
                        return false; // Exit loop
                    }
                });

                if (titleColumnIndex !== -1) {
                    // 2. Apply the wrapping styles to the <a> tags in the specific column index
                    const columnIndexSelector = `:nth-child(${titleColumnIndex + 1}) a`;

                    $(this.api().table().body()).find('tr td' + columnIndexSelector).css({
                        'display': 'block',
                        'word-break': 'break-word',
                        'overflow-wrap': 'break-word',
                        'white-space': 'normal'
                    });
                }
            }
        });

        // --- DATATABLES CUSTOM ROW VISIBILITY FILTER ---
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            // Only apply this custom filter to our specific table
            if (settings.nTable.id !== 'example5') return true;

            const rowNode = table.row(dataIndex).node();
            if (!rowNode) return true;

            const payeValue = parseInt(rowNode.dataset.paye || '0', 10);
            const olympiadFlag = $(rowNode).find('td:first').data('olympiad-flag');
            let isVisible = true;

            // 1. Olympiad Filter Check
            if (filterState.olympiad !== 'all') {
                if (String(olympiadFlag) !== filterState.olympiad) {
                    isVisible = false;
                }
            }

            // 2. Paye/Range Filter Check (only if still visible and a paye filter is active)
            if (isVisible && filterState.paye !== 'all') {
                if (filterState.paye === 'exact') {
                    if (payeValue !== filterState.exact) isVisible = false;
                }
                if (filterState.paye === 'range') {
                    if (!(payeValue >= filterState.min && payeValue <= filterState.max)) isVisible = false;
                }
            }
            return isVisible;
        });

        // --- UI MANAGEMENT FUNCTIONS ---

        function updateActiveUI() {
            const container = document.getElementById('payeFilters');
            if (!container) return;

            // Reset all buttons
            container.querySelectorAll('button').forEach(x => x.classList.remove('active'));

            let activeFilterButton = null;

            if (filterState.olympiad !== 'all') {
                activeFilterButton = container.querySelector(`[data-olympiad="${filterState.olympiad}"]`);
            } else if (filterState.paye !== 'all') {
                if (filterState.paye === 'exact') {
                    activeFilterButton = container.querySelector(`[data-paye="${filterState.exact}"]`);
                } else if (filterState.paye === 'range') {
                    activeFilterButton = container.querySelector(`[data-range="${filterState.range}"]`);
                }
            }

            if (activeFilterButton) {
                activeFilterButton.classList.add('active');
            } else {
                // Default to 'All' button
                const allBtn = container.querySelector('[data-filter="all"]');
                if (allBtn) allBtn.classList.add('active');
            }
        }

        function setUrlParam() {
            const url = new URL(window.location.href);
            let paramSet = false;

            if (filterState.olympiad !== 'all') {
                url.searchParams.set('olympiad', filterState.olympiad);
                paramSet = true;
            } else {
                url.searchParams.delete('olympiad');
            }

            if (filterState.olympiad === 'all') {
                if (filterState.paye === 'exact') {
                    url.searchParams.set('paye', String(filterState.exact));
                    paramSet = true;
                } else if (filterState.paye === 'range') {
                    url.searchParams.set('paye', filterState.range);
                    paramSet = true;
                } else {
                    url.searchParams.delete('paye');
                }
            }

            if (!paramSet) url.searchParams.delete('paye');

            window.history.replaceState({}, '', url);
        }

        // --- FILTER APPLICATION FUNCTIONS ---
        function applyAll() {
            filterState.paye = 'all';
            filterState.range = 'all';
            filterState.olympiad = 'all';
            filterState.exact = filterState.min = filterState.max = null;

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        function applyPayeExact(n) {
            filterState.paye = 'exact';
            filterState.exact = n;
            filterState.range = 'all';
            filterState.olympiad = 'all';

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        function applyPayeRange(min, max, raw) {
            filterState.paye = 'range';
            filterState.min = min;
            filterState.max = max;
            filterState.range = raw;
            filterState.exact = null;
            filterState.olympiad = 'all';

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        function applyOlympiadFilter(value) {
            filterState.olympiad = value;
            filterState.paye = 'all';
            filterState.range = 'all';
            filterState.exact = null;

            setUrlParam();
            updateActiveUI();
            table.draw();
        }

        // --- UI CLICK HOOK ---
        const container = document.getElementById('payeFilters');
        if (container) {
            container.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-filter],[data-paye],[data-range],[data-olympiad]');
                if (!btn) return;

                if (btn.dataset.filter === 'all') {
                    applyAll();
                    return;
                }

                if (btn.dataset.olympiad) {
                    applyOlympiadFilter(btn.dataset.olympiad);
                    return;
                }

                if (btn.dataset.paye) {
                    const n = parseInt(btn.dataset.paye, 10);
                    if (Number.isFinite(n)) applyPayeExact(n);
                    return;
                }

                if (btn.dataset.range) {
                    const [a, b] = btn.dataset.range.split('-').map(x => parseInt(x, 10));
                    if (Number.isFinite(a) && Number.isFinite(b)) applyPayeRange(a, b, btn.dataset.range);
                }
            });
        }

        // --- INITIAL LOAD (URL PARAMETER HANDLING) ---
        const urlParams = new URLSearchParams(window.location.search);
        const initialParam = urlParams.get('paye') || urlParams.get('olympiad');

        if (!initialParam || initialParam === 'all') {
            applyAll();
        } else if (initialParam === '1' || initialParam === '0') {
            // Olympiad Filter
            const el = container?.querySelector(`[data-olympiad="${CSS.escape(initialParam)}"]`);
            applyOlympiadFilter(initialParam);
        } else if (/^\d+$/.test(initialParam)) {
            // Paye Exact Filter
            const el = container?.querySelector(`[data-paye="${CSS.escape(initialParam)}"]`);
            applyPayeExact(parseInt(initialParam, 10));
        } else if (/^\d+\-\d+$/.test(initialParam)) {
            // Paye Range Filter
            const [a, b] = initialParam.split('-').map(x => parseInt(x, 10));
            applyPayeRange(a, b, initialParam);
        } else {
            // Fallback
            applyAll();
        }

        // Final UI update based on initialized state
        updateActiveUI();
    });
</script>



</body>
</html>
