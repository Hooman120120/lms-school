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
function status_label_fa(string $s): string {
    return $s==='open' ? 'باز'
        : ($s==='chatting' ? 'در حال گفتگو'
            : ($s==='resolved' ? 'حل‌شده' : $s));
}
function badge_class(string $s): string {
    if ($s==='resolved') return 'bg-success';
    if ($s==='chatting') return 'bg-warning text-dark';
    return 'bg-danger';
}
function target_group_label_fa(string $tg): string {
    return $tg==='admins' ? 'مدیر میانی'
        : ($tg==='management' ? 'مدیر کل'
            : ($tg==='operators' ? 'پشتیبان' : $tg));
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// who am I
$me = $_SESSION['admin']['user'] ?? '';
$adminrole = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;

// load dbadmin.administrator
$meRowStmt = $conn->prepare("SELECT username, administrator FROM dbadmin WHERE username=? LIMIT 1");
$meRowStmt->execute([$me]);
$meRow = $meRowStmt->fetch(PDO::FETCH_ASSOC) ?: ['administrator'=>0];
$administrator = (int)($meRow['administrator'] ?? 0);

// role flags
$isTeacher    = ($adminrole === 3);
$isManagement = ($administrator === 1);
$isMiddle     = (!$isManagement && $adminrole === 2);
$isOperator   = (!$isManagement && $adminrole === 1); // and administrator != 1 already ensured above

if ($isTeacher) {
    RequireTeacherPermission('teacher_perm_messages', url('admin/index.php'));
}

// visibility check for one ticket (used in POST)
function can_view_teacher_ticket(PDO $conn, array $t, string $me, bool $isTeacher, bool $isOperator, bool $isMiddle, bool $isManagement): bool
{
    if ($isManagement) return true;

    if ($isTeacher) {
        return isset($t['teacher_username']) && $t['teacher_username'] === $me;
    }

    $tg = (string)($t['target_group'] ?? '');

    if ($isMiddle) {
        // middle admin sees admins + operators
        return in_array($tg, ['admins','operators'], true);
    }

    if ($isOperator) {
        // operator sees only operator tickets AND only if supporter of codemeli (option B)
        if ($tg !== 'operators') return false;
        $cm = $t['codemeli'] ?? null;
        if (!$cm) return false;

        $s = $conn->prepare("SELECT 1 FROM dbcourse WHERE username=? AND suporter=? LIMIT 1");
        $s->execute([(string)$cm, $me]);
        return (bool)$s->fetchColumn();
    }

    return false;
}
/* ====== TEACHER: CREATE NEW TICKET (NO student) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_teacher_ticket') {

    $csrfOk = (isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''));

    if (!$isTeacher) {
        $_SESSION['flash_err'] = m('msg.only_teacher_new_ticket');
    } elseif (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
    } else {
        $tg    = trim((string)($_POST['target_group'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));

        // only these two groups
        if (!in_array($tg, ['admins','management'], true)) {
            $_SESSION['flash_err'] = 'گروه مقصد نامعتبر است.';
        } elseif ($title === '') {
            $_SESSION['flash_err'] = 'عنوان تیکت الزامی است.';
        } else {
            try {
                $ins = $conn->prepare("
                    INSERT INTO teacher_ticket
                        (codemeli, teacher_username, target_group, responder_username, title, status, class_id, created_at, updated_at)
                    VALUES
                        (NULL, ?, ?, NULL, ?, 'open', NULL, NOW(), NOW())
                ");
                $ins->execute([$me, $tg, $title]);

                $_SESSION['flash_ok'] = 'تیکت با موفقیت ایجاد شد.';
            } catch (PDOException $e) {
                $_SESSION['flash_err'] = 'خطا در ایجاد تیکت: ' . $e->getMessage();
            }
        }
    }

    // PRG redirect (so refresh won't repost)
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    $back = $path . ($qs ? "?$qs" : '');
    header("Location: $back");
    exit;
}

/* ====== NON-AJAX STATUS UPDATE (POST + redirect) ====== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {

    $csrfOk = (isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? ''));

    $allowed = ['open','chatting','resolved'];

    if (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
    } elseif ($id <= 0 || !in_array($status, $allowed, true)) {
        $_SESSION['flash_err'] = 'داده‌های ارسالی نامعتبر است.';
    } elseif ($isTeacher) {
        $_SESSION['flash_err'] = m('msg.teacher_cannot_change_status');
    } else {
        // fetch ticket and verify access
        $t = $conn->prepare("SELECT id, codemeli, teacher_username, target_group FROM teacher_ticket WHERE id=? LIMIT 1");
        $t->execute([$id]);
        $ticket = $t->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $_SESSION['flash_err'] = 'تیکت یافت نشد.';
        } elseif (!can_view_teacher_ticket($conn, $ticket, $me, $isTeacher, $isOperator, $isMiddle, $isManagement)) {
            $_SESSION['flash_err'] = 'اجازه تغییر وضعیت ندارید.';
        } else {
            $u = $conn->prepare("UPDATE teacher_ticket SET status=?, updated_at=NOW() WHERE id=? LIMIT 1");
            $u->execute([$status, $id]);
            $_SESSION['flash_ok'] = 'وضعیت با موفقیت بروزرسانی شد.';
        }
    }

    // Redirect back (keep filters)
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    $back = $path . ($qs ? "?$qs" : '');
    header("Location: $back");
    exit;
}

/* =========================
   NORMAL PAGE (GET)
   ========================= */

// filters
$status = trim($_GET['status'] ?? '');
$tg     = trim($_GET['tg'] ?? '');

// status validation
if ($status !== '' && !in_array($status, ['open','chatting','resolved'], true)) $status = '';

// target_group validation based on viewer
$allowedTg = ['admins','management','operators'];

if ($isOperator) {
    // operator must NOT have target_group filter; they only see operators anyway
    $tg = '';
} elseif ($isMiddle) {
    // middle can filter only admins + operators
    if ($tg !== '' && !in_array($tg, ['admins','operators'], true)) $tg = '';
} else {
    // management + teacher can use all
    if ($tg !== '' && !in_array($tg, $allowedTg, true)) $tg = '';
}

// where clauses
$where = [];
$args  = [];

// scope
if ($isManagement) {
    // all tickets
} elseif ($isTeacher) {
    $where[] = "t.teacher_username = ?";
    $args[]  = $me;
} elseif ($isMiddle) {
    $where[] = "t.target_group IN ('admins','operators')";
} elseif ($isOperator) {
    // only operator tickets where I'm supporter (option B)
    $where[] = "t.target_group = 'operators'";
    $where[] = "t.codemeli IS NOT NULL";
    $where[] = "EXISTS (SELECT 1 FROM dbcourse dc WHERE dc.username = t.codemeli AND dc.suporter = ?)";
    $args[]  = $me;
} else {
    // unknown role => show nothing
    $where[] = "1=0";
}

// apply filters
if ($status !== '') {
    $where[] = "t.status = ?";
    $args[]  = $status;
}
if ($tg !== '') {
    $where[] = "t.target_group = ?";
    $args[]  = $tg;
}

$sqlWhere = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// unseen expression (teacher vs responder-side)
$unseenExpr = $isTeacher
    ? "(SELECT COUNT(*) FROM teacher_ticket_messages mm
        WHERE mm.teacher_ticket_id = t.id
          AND mm.sender_username <> ?
          AND mm.seen_by_teacher = 0)"
    : "(SELECT COUNT(*) FROM teacher_ticket_messages mm
        WHERE mm.teacher_ticket_id = t.id
          AND mm.sender_username <> ?
          AND mm.seen_by_respounder = 0)";

$sql = "
  SELECT
    t.id,
    t.title,
    t.codemeli,
    t.teacher_username,
    t.target_group,
    t.responder_username,
    t.status,
    t.updated_at,
    t.class_id,
    p.fname,
    p.lname,
    COALESCE(m.msg_count, 0) AS msg_count,
    $unseenExpr AS unseen_for_me
  FROM teacher_ticket t
  LEFT JOIN dbpersonal p ON p.codemeli = t.codemeli
  LEFT JOIN (
    SELECT teacher_ticket_id, COUNT(*) AS msg_count
    FROM teacher_ticket_messages
    GROUP BY teacher_ticket_id
  ) m ON m.teacher_ticket_id = t.id
  $sqlWhere
  ORDER BY COALESCE(t.updated_at, t.created_at) DESC
  LIMIT 300
";

$selectArgs = [$me];
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge($selectArgs, $args));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title><?php echo m('page.teacher_tickets') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .row-hover tbody tr:hover { background:#fafafa; }
        @keyframes blinkYellow { 0% {background:#fffbe6;} 50% {background:#fff3bf;} 100% {background:#fffbe6;} }
        @keyframes blinkRed    { 0% {background:#ffe3e3;} 50% {background:#ffc9c9;} 100% {background:#ffe3e3;} }
        .blink-chatting { animation: blinkYellow 1.6s ease-in-out infinite; will-change: background-color; }
        .blink-open     { animation: blinkRed    1.6s ease-in-out infinite; will-change: background-color; }
        .row-hover tbody tr:hover { animation: none !important; }
        @media (prefers-reduced-motion: reduce) { .blink-chatting, .blink-open { animation: none; } }

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
        #example1 td:nth-child(4) {  /* دانش‌آموز */
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

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">لیست تیکت‌ها</h5>

                        <?php if ($isTeacher): ?>
                            <button type="button"
                                    class="btn btn-sm btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#createTicketModal">
                                ➕ ایجاد تیکت جدید
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Filters -->
                    <div class="table-responsive">
                        <?php
                        // columns: #, teacher, title, student, group, status, updated, actions (+ responder if not teacher maybe)
                        $colspan = 8;
                        ?>
                        <table id="example1" class="table table-bordered table-striped align-middle row-hover">
                            <thead class="bg-light">
                            <tr class="border-light">
                                <th class="text-center" style="width:60px">#</th>
                                <th class="text-center" style="width:130px"><?php echo t('teacher') ?></th>
                                <th>عنوان</th>
                                <th>دانش‌آموز</th>
                                <th class="text-center" style="width:120px">کلاس</th>
                                <th class="text-center" style="width:120px">گروه</th>
                                <th class="text-center" style="width:120px">وضعیت</th>
                                <th class="text-center" style="width:160px">آخرین بروزرسانی</th>
                                <th class="text-center" style="width:280px">اقدامات</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$rows): ?>
                                <tr><td colspan="<?= $colspan ?>" class="text-center text-muted">موردی یافت نشد.</td></tr>
                            <?php else:
                                $i=1;
                                foreach ($rows as $r):
                                    $statusText = (string)$r['status'];
                                    $rowClass = ($statusText==='chatting') ? 'blink-chatting' : (($statusText==='open') ? 'blink-open' : '');

                                    $unseen = (int)($r['unseen_for_me'] ?? 0);
                                    $updated_at = $r['updated_at'] ?? null;
                                    $ts = $updated_at ? strtotime($updated_at) : 0;
                                    $jalali = $ts ? jdate('Y/m/d H:i', $ts) : '—';

                                    $studentName = trim(($r['fname'] ?? '').' '.($r['lname'] ?? ''));
                                    $cm = $r['codemeli'] ?? '';
                                    $tgRow = (string)($r['target_group'] ?? '');
                                    $classId = (int)($r['class_id'] ?? 0);

                                    // status button color
                                    $btnClass = $statusText==='resolved' ? 'btn-success'
                                        : ($statusText==='chatting' ? 'btn-warning text-dark'
                                            : 'btn-danger');
                                    ?>
                                    <tr class="<?= h($rowClass) ?>">
                                        <td class="text-center"><?= $i++ ?></td>

                                        <td class="text-center">
                                            <span class="badge bg-info"><?= h($r['teacher_username'] ?? '—') ?></span>
                                        </td>

                                        <td>
                                            <a class="fw-bold text-decoration-none"
                                               href="thread_view.php?type=teacher&id=<?= (int)$r['id'] ?>"
                                               onclick="window.open(
                                                    this.href,
                                                    'teacherTicket',
                                                    'width=1200,height=800,menubar=0,toolbar=0,location=0,status=0,scrollbars=1,resizable=1,top=80'
                                                ); return false;">
                                                <?= h($r['title'] ?? '') ?>
                                            </a>
                                        </td>

                                        <td>
                                            <?php if ($cm): ?>
                                                <?= h($studentName ?: '—') ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <?= $classId > 0 ? ('#'.(int)$classId) : '—' ?>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?= h(target_group_label_fa($tgRow)) ?></span>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge <?= h(badge_class($statusText)) ?>">
                                                <?= h(status_label_fa($statusText)) ?>
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            <span class="small" title="<?= h($updated_at ?: '') ?>"><?= h($jalali) ?></span>
                                        </td>

                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-2 position-relative">

                                                <!-- گفتگو -->
                                                <a class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 position-relative"
                                                   href="thread_view.php?type=teacher&id=<?= (int)$r['id'] ?>"
                                                   onclick="window.open(
                                                        this.href,
                                                        'teacherTicket',
                                                        'width=1200,height=800,menubar=0,toolbar=0,location=0,status=0,scrollbars=1,resizable=1,top=80'
                                                    ); return false;">
                                                    <i class="fe fe-eye"></i><span>گفتگو</span>
                                                    <?php if ($unseen > 0): ?>
                                                        <span class="unseen-dot"><?= (int)$unseen ?></span>
                                                    <?php endif; ?>
                                                </a>

                                                <!-- تغییر وضعیت (everyone who can see ticket EXCEPT teacher) -->
                                                <?php if (!$isTeacher): ?>
                                                    <?php if ($statusText === 'resolved'): ?>
                                                        <button class="btn btn-sm <?= h($btnClass) ?>" disabled><?= h(status_label_fa($statusText)) ?></button>
                                                    <?php else: ?>
                                                        <button
                                                                class="btn btn-sm <?= h($btnClass) ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#statusModal"
                                                                data-ticket-id="<?= (int)$r['id'] ?>"
                                                                data-status="<?= h($statusText) ?>">
                                                            وضعیت: <?= h(status_label_fa($statusText)) ?>
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
                                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                                <select name="status" class="form-select">
                                    <option value="open">باز</option>
                                    <option value="chatting">در حال گفتگو</option>
                                    <option value="resolved">حل‌شده</option>
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
<?php if ($isTeacher): ?>
    <div class="modal fade" id="createTicketModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="" class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">ایجاد تیکت جدید</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="create_teacher_ticket">
                    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

                    <div class="mb-2">
                        <label class="form-label">گروه مقصد</label>
                        <select name="target_group" class="form-select" required>
                            <option value="management">مدیر </option>
                            <option value="admins">مدیر میانی</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">عنوان تیکت</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">ثبت</button>
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">بستن</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    // Prefill modal fields from the clicked button
    document.getElementById('statusModal')?.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const id = btn?.getAttribute('data-ticket-id') || '';
        const st = btn?.getAttribute('data-status') || 'open';
        const form = this.querySelector('form');
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('select[name="status"]').value = st;
    });
</script>
</body>
</html>
