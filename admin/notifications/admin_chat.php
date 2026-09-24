<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }
if (!isset($_SESSION['admin'])) { header('location: ../login.php'); exit; }

// Protect against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset(); header('location: ../login.php'); exit;
}

// Ensure single active session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset(); header('location: ' . url('admin/login.php')); exit;
}

/* =========================
   Helpers
   ========================= */
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function to_jalali_datetime(string $ts): string {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts);
    if (!$dt) return h($ts);
    [$gy,$gm,$gd] = explode('-', $dt->format('Y-m-d'));
    $jal = gregorian_to_jalali((int)$gy,(int)$gm,(int)$gd);
    return implode('/', $jal) . ' - ' . $dt->format('H:i:s');
}
function ticket_status_fa(int $s): string {
    return $s === 0 ? 'باز'
        : ($s === 1 ? 'انجام شد'
            : ($s === 2 ? 'بررسی شود'
                : ($s === 3 ? 'در حال مکالمه'
                    : ($s === 4 ? 'رد شد' : (string)$s))));
}
function ticket_badge_class(int $s): string {
    if ($s === 1) return 'bg-success';
    if ($s === 3) return 'bg-warning text-dark';
    if ($s === 2) return 'bg-info text-dark';
    if ($s === 4) return 'bg-secondary';
    return 'bg-danger'; // 0=open etc
}

/* =========================
   CSRF
   ========================= */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$csrf_ok = function(): bool {
    return isset($_SESSION['csrf'], $_POST['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
};

/* =========================
   Current user / admin flag
   ========================= */
$me = (string)($_SESSION['admin']['user'] ?? '');

$st = $conn->prepare("SELECT administrator FROM dbadmin WHERE username=? LIMIT 1");
$st->execute([$me]);
$row = $st->fetch(PDO::FETCH_ASSOC);
$isAdministrator = !empty($row) && (int)$row['administrator'] === 1;

/* =========================
   Ticket id
   ========================= */
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticketId <= 0) {
    http_response_code(400);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>شناسه تیکت نامعتبر است.</div>";
    exit;
}

/* =========================
   Load ticket (with student info)
   ========================= */
$tStmt = $conn->prepare("
    SELECT
        t.*,
        p.fname AS student_fname,
        p.lname AS student_lname
    FROM admin_tickets t
    LEFT JOIN dbpersonal p ON p.codemeli = t.student_username
    WHERE t.id = ?
    LIMIT 1
");
$tStmt->execute([$ticketId]);
$ticket = $tStmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    http_response_code(404);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>❌ تیکت یافت نشد.</div>";
    exit;
}

/* =========================
   Access control
   - Admin: can view all
   - Non-admin: only own (creator OR assigned_to)
   ========================= */
$isMine = ((string)($ticket['ticket_creator'] ?? '') === $me) || ((string)($ticket['ticket_assigned_to'] ?? '') === $me);
if (!$isAdministrator && !$isMine) {
    http_response_code(403);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ دسترسی ندارید.</div>";
    exit;
}

/* =========================
   Mark messages as seen (simple scheme: seen_by/seen_at)
   NOTE: this schema only stores ONE "seen_by" at a time.
   We overwrite seen_by so unseen counts work for the current viewer.
   ========================= */
$updSeen = $conn->prepare("
    UPDATE admin_messages
    SET seen_by = ?, seen_at = NOW()
    WHERE admin_ticket_id = ?
      AND sender_username <> ?
      AND (seen_by IS NULL OR seen_by <> ?)
");
$updSeen->execute([$me, $ticketId, $me, $me]);

/* =========================
   POST actions
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // change status
    if (($_POST['action'] ?? '') === 'set_status') {
        $newStatus = isset($_POST['status']) ? (int)$_POST['status'] : -1;
        $allowed = [0,1,2,3];

        if (!$csrf_ok()) {
            $_SESSION['flash'] = 'اعتبارسنجی CSRF نامعتبر است.';
            $_SESSION['flash_type'] = 'danger';
        } elseif (!in_array($newStatus, $allowed, true)) {
            $_SESSION['flash'] = 'وضعیت نامعتبر است.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            // non-admin can only change their own tickets (creator/assigned)
            if (!$isAdministrator && !$isMine) {
                $_SESSION['flash'] = 'شما اجازه تغییر وضعیت این تیکت را ندارید.';
                $_SESSION['flash_type'] = 'danger';
            } else {
                $u = $conn->prepare("UPDATE admin_tickets SET ticket_status=?, updated_at=NOW() WHERE id=? LIMIT 1");
                $u->execute([$newStatus, $ticketId]);
                $_SESSION['flash'] = 'وضعیت بروزرسانی شد.';
                $_SESSION['flash_type'] = 'success';
            }
        }

        header("Location: admin_chat.php?id=".(int)$ticketId);
        exit;
    }

    // send message
    if (($_POST['action'] ?? '') === 'send_message') {
        $body = trim((string)($_POST['body'] ?? ''));

        if (!$csrf_ok()) {
            $_SESSION['flash'] = 'اعتبارسنجی CSRF نامعتبر است.';
            $_SESSION['flash_type'] = 'danger';
            header("Location: admin_chat.php?id=".(int)$ticketId);
            exit;
        }

        // reload ticket status quickly (avoid stale)
        $cur = $conn->prepare("SELECT ticket_status, ticket_assigned_to FROM admin_tickets WHERE id=? LIMIT 1");
        $cur->execute([$ticketId]);
        $curT = $cur->fetch(PDO::FETCH_ASSOC) ?: [];

        $curStatus = (int)($curT['ticket_status'] ?? 0);

        if ($curStatus === 1) {
            $_SESSION['flash'] = 'این تیکت بسته شده است و امکان ارسال پیام وجود ندارد.';
            $_SESSION['flash_type'] = 'danger';
            header("Location: admin_chat.php?id=".(int)$ticketId);
            exit;
        }

        if ($body === '') {
            $_SESSION['flash'] = 'متن پیام خالی است.';
            $_SESSION['flash_type'] = 'danger';
            header("Location: admin_chat.php?id=".(int)$ticketId);
            exit;
        }

        $ins = $conn->prepare("INSERT INTO admin_messages (admin_ticket_id, sender_username, body, created_at) VALUES (?, ?, ?, NOW())");
        $ins->execute([$ticketId, $me, $body]);

        // update ticket: bump updated_at + set ongoing if open
        // also: if admin replies and assigned_to is empty -> auto-assign to admin (optional but useful)
        if ($isAdministrator) {
            $conn->prepare("
                UPDATE admin_tickets
                SET
                    ticket_assigned_to = COALESCE(ticket_assigned_to, ?),
                    ticket_status      = CASE WHEN ticket_status = 0 THEN 3 ELSE ticket_status END,
                    updated_at         = NOW()
                WHERE id = ?
                LIMIT 1
            ")->execute([$me, $ticketId]);
        } else {
            $conn->prepare("
                UPDATE admin_tickets
                SET
                    ticket_status = CASE WHEN ticket_status = 0 THEN 3 ELSE ticket_status END,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ")->execute([$ticketId]);
        }

        $_SESSION['flash'] = 'پیام ارسال شد.';
        $_SESSION['flash_type'] = 'success';
        header("Location: admin_chat.php?id=".(int)$ticketId);
        exit;
    }
}

/* =========================
   Load messages
   ========================= */
$mStmt = $conn->prepare("
    SELECT
        id, sender_username, body, created_at, seen_at, seen_by
    FROM admin_messages
    WHERE admin_ticket_id = ?
    ORDER BY created_at ASC
");
$mStmt->execute([$ticketId]);
$messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);

// refresh ticket for header
$tStmt->execute([$ticketId]);
$ticket = $tStmt->fetch(PDO::FETCH_ASSOC) ?: $ticket;

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>گفتگو تیکت | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .msg-row { display:flex; gap:12px; margin-bottom:14px; }
        .msg-me  { justify-content:flex-end; }
        .bubble  { max-width: 72%; padding:10px 12px; border-radius:12px; }
        .bubble-other { background:#e9f5ff; border:1px solid #b6e0ff; }
        .bubble-admin { background:#fff3cd; border:1px solid #ffe69c; }
        .bubble-me    { background:lightskyblue; }
        .small-muted  { font-size:12px; color:#6c757d; }
        .badge { font-size:0.75rem; }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">

    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">

                            <?php if (!empty($_SESSION['flash'])):
                                $ft = $_SESSION['flash_type'] ?? 'success';
                                $fm = $_SESSION['flash'];
                                unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
                                <div class="alert alert-<?= h($ft) ?> py-2 mb-2"><?= h($fm) ?></div>
                            <?php endif; ?>

                            <div class="card-header">
                                <?php
                                $studentFull = trim((string)($ticket['student_fname'] ?? '').' '.(string)($ticket['student_lname'] ?? ''));
                                $studentUser = (string)($ticket['student_username'] ?? '');
                                $assignedTo  = (string)($ticket['ticket_assigned_to'] ?? '');
                                $creator     = (string)($ticket['ticket_creator'] ?? '');
                                $type        = (string)($ticket['type'] ?? '');
                                $subject     = trim((string)($ticket['subject'] ?? ''));
                                $st          = (int)($ticket['ticket_status'] ?? 0);
                                $updated_at  = (string)($ticket['updated_at'] ?? '');
                                ?>
                                <h5 class="mb-2">
                                    تیکت #<?= (int)$ticket['id'] ?> — <?= h($subject !== '' ? $subject : '—') ?>
                                </h5>

                                <div class="d-flex flex-wrap gap-3">

                                    <div>
                                        نوع:
                                        <span class="badge bg-secondary"><?= h($type ?: '—') ?></span>
                                    </div>

                                    <div>
                                        ارسال‌کننده:
                                        <span class="badge bg-info"><?= h($creator ?: '—') ?></span>
                                    </div>

                                    <div>
                                        مسئول:
                                        <span class="badge bg-secondary"><?= h($assignedTo ?: '—') ?></span>
                                    </div>

                                    <div>
                                        دانش آموز:
                                        <?php if ($studentFull !== ''): ?>
                                            <strong><?= h($studentFull) ?></strong>
                                            <?php if ($studentUser !== ''): ?>
                                                <span class="text-muted small">| <?= h($studentUser) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        وضعیت:
                                        <span class="badge <?= h(ticket_badge_class($st)) ?>"><?= h(ticket_status_fa($st)) ?></span>
                                    </div>

                                    <div class="small-muted">
                                        آخرین بروزرسانی:
                                        <?= $updated_at ? h(jdate('Y/m/d H:i', strtotime($updated_at))) : '—' ?>
                                    </div>

                                </div>
                            </div>

                            <div class="card-body d-flex flex-column" style="height:70vh;">

                                <div id="ChatBody"
                                     class="flex-grow-1 overflow-auto pe-2"
                                     style="min-height:0; padding:12px;background: linear-gradient(135deg,rgba(255,154,68,.20) 0%,rgba(255,61,119,.20) 50%,rgba(179,60,255,.20) 100%),#fafafa;">

                                    <?php if (!$messages): ?>
                                        <div class="text-center text-muted py-4">هنوز پیامی ثبت نشده است.</div>
                                    <?php else: foreach ($messages as $m):
                                        $sender = (string)($m['sender_username'] ?? '');
                                        $isMeMsg = ($sender === $me);

                                        // label
                                        $whoFa = ($sender === $creator) ? 'ارسال‌کننده'
                                            : (($sender === $assignedTo && $assignedTo !== '') ? 'مسئول' : 'کاربر');

                                        // bubble style
                                        // if sender is assignedTo -> admin style (yellow-ish), else other style (blue-ish)
                                        $bubbleClass = $isMeMsg ? 'bubble-me'
                                            : (($sender === $assignedTo && $assignedTo !== '') ? 'bubble-admin' : 'bubble-other');
                                        ?>
                                        <div class="msg-row <?= $isMeMsg ? 'msg-me' : '' ?>">
                                            <div class="bubble <?= h($bubbleClass) ?>">
                                                <div class="small-muted mb-1"><?= h($whoFa) ?>: <?= h($sender) ?></div>
                                                <div><?= nl2br(h($m['body'] ?? '')) ?></div>
                                                <div class="small-muted mt-1"><?= !empty($m['created_at']) ? to_jalali_datetime($m['created_at']) : '—' ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>

                                </div>

                                <?php if ((int)$ticket['ticket_status'] !== 1 && (int)$ticket['ticket_status'] !== 4) : ?>
                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="send_message">
                                        <div class="input-group">
                                            <textarea name="body" class="form-control" rows="2" placeholder="پیام خود را بنویسید..." required></textarea>
                                            <button class="btn btn-primary" type="submit">ارسال</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">این تیکت بسته شده است و امکان ارسال پیام وجود ندارد.</div>
                                <?php endif; ?>
                            </div>

                            <?php

                            // status change: admin OR owner (creator/assigned)
                            $canChange = $isAdministrator;
                            ?>
                            <?php if ($canChange): ?>
                                <?php if ($type !== 'reactivate_temp_user') { ?>
                                    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                                        <form method="post" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="action" value="set_status">
                                            <label class="mb-0">تغییر وضعیت:</label>
                                            <select name="status" class="form-select form-select-sm w-auto">
                                                <option value="0" <?= $st===0?'selected':'' ?>>باز</option>
                                                <option value="3" <?= $st===3?'selected':'' ?>>در حال مکالمه</option>
                                                <option value="2" <?= $st===2?'selected':'' ?>>بررسی شود</option>
                                                <option value="1" <?= $st===1?'selected':'' ?>>انجام شد</option>
                                            </select>
                                            <button class="btn btn-outline-secondary btn-sm" type="submit">ثبت</button>
                                        </form>
                                    </div>
                                <?php } ?>
                            <?php endif; ?>

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
        const chat = document.getElementById('ChatBody');
        if (chat) chat.scrollTop = chat.scrollHeight;
    });
</script>
</body>
</html>
