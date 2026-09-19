<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }
if (!isset($_SESSION['admin'])) { header('location: ../login.php'); exit; }
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset(); header('location: ../login.php'); exit;
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset(); header('location: ' . url('admin/login.php')); exit;
}

// ---- helpers ----
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function to_jalali_datetime(string $ts): string {
    // expects 'Y-m-d H:i:s'
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts);
    if (!$dt) return h($ts);
    [$gy,$gm,$gd] = explode('-', $dt->format('Y-m-d'));
    $jal = gregorian_to_jalali((int)$gy,(int)$gm,(int)$gd);
    return implode('/', $jal) . ' - ' . $dt->format('H:i:s');
}

// ---- who am I / role ----
$currentUser = $_SESSION['admin']['user'];
$meStmt = $conn->prepare("SELECT username, permision_suporter, permision_viewalert FROM dbadmin WHERE username = ? LIMIT 1");
$meStmt->execute([$currentUser]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC) ?: ['permision_suporter'=>0,'permision_viewalert'=>0];
$isOperator = !empty($me['permision_suporter']);
$isManager  = !empty($me['permision_viewalert']);
$canSeeAll  = $isManager && !empty($me['permision_viewemploy']);
// ---- load thread ----
$threadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tStmt = $conn->prepare("
  SELECT t.*, p.fname, p.lname
  FROM report_threads t
  LEFT JOIN dbpersonal p ON p.codemeli = t.codemeli
  WHERE t.id = ?
  LIMIT 1
");
$tStmt->execute([$threadId]);
$thread = $tStmt->fetch(PDO::FETCH_ASSOC);

if (!$thread) {
    http_response_code(404);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>❌ تیکت یافت نشد.</div>";
    exit;
}

// ---- access control ----
// Managers: can view all. Operators: can only view threads they created.
// Managers: see all if canSeeAll; otherwise only unassigned or owned by self
if ($isManager) {
    if (!$canSeeAll) {
        $claimed   = !empty($thread['responder_username']);
        $ownedByMe = ($thread['responder_username'] === $currentUser);
        if ($claimed && !$ownedByMe) {
            http_response_code(403);
            echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ این تیکت در اختیار مدیر دیگری است.</div>";
            exit;
        }
    }
} else {
    // Operators: only their own threads
    if ($thread['operator_username'] !== $currentUser) {
        http_response_code(403);
        echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ دسترسی ندارید.</div>";
        exit;
    }
}


// ---- actions (POST) ----
$errors = [];
$notice = null;

// CSRF token simple
if (empty($_SESSION['token'])) { $_SESSION['token'] = bin2hex(random_bytes(32)); }
$token_ok = function() {
    return isset($_POST['token'], $_SESSION['token']) && hash_equals($_SESSION['token'], $_POST['token']);
};
// Manager: set status
if ($isManager && isset($_POST['action']) && $_POST['action']==='set_status' && $token_ok()) {
$newStatus = $_POST['status'] ?? '';
  if (!in_array($newStatus, ['open','chatting','resolved','rejected'], true)) {
        $errors[] = 'وضعیت نامعتبر است.';
    } else {
        $u = $conn->prepare("UPDATE report_threads SET status=?, updated_at=NOW() WHERE id=?");
        $u->execute([$newStatus, $threadId]);
        $notice = 'وضعیت بروزرسانی شد.';
        // refresh thread row
        $tStmt->execute([$threadId]);
        $thread = $tStmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Send message (both roles while NOT resolved)
if (isset($_POST['action']) && $_POST['action']==='send_message' && $token_ok()) {
      if (in_array($thread['status'], ['resolved','rejected'], true)) {
        $_SESSION['flash'] = 'این تیکت بسته شده است.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: thread_view.php?id='.(int)$threadId);
        exit;
    }

    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        $_SESSION['flash'] = 'متن پیام خالی است.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: thread_view.php?id='.(int)$threadId);
        exit;
    }

    // Insert message
    $ins = $conn->prepare("INSERT INTO report_messages (thread_id, sender_username, body, created_at)
                           VALUES (?, ?, ?, NOW())");
    $ins->execute([$threadId, $currentUser, $body]);

    // If manager replies first time: set responder & status=chatting
    if ($isManager && empty($thread['responder_username'])) {
        $conn->prepare("UPDATE report_threads SET responder_username=?, status='chatting', updated_at=NOW() WHERE id=?")
            ->execute([$currentUser, $threadId]);
    } else {
        if ($thread['status']==='open') {
            $conn->prepare("UPDATE report_threads SET status='chatting', updated_at=NOW() WHERE id=?")
                ->execute([$threadId]);
        } else {
            $conn->prepare("UPDATE report_threads SET updated_at=NOW() WHERE id=?")
                ->execute([$threadId]);
        }
    }

    // PRG: redirect then exit to prevent resubmission on refresh
    $_SESSION['flash'] = 'پیام ارسال شد.';
    $_SESSION['flash_type'] = 'success';
    header('Location: thread_view.php?id='.(int)$threadId);
    exit;
}


// ---- load messages ----
$mStmt = $conn->prepare("
    SELECT m.id, m.sender_username, m.body, m.created_at,
           da.permision_viewalert AS sender_is_manager,
           da.permision_suporter AS sender_is_operator
    FROM report_messages m
    LEFT JOIN dbadmin da ON da.username = m.sender_username
    WHERE m.thread_id = ?
    ORDER BY m.created_at ASC
");
$mStmt->execute([$threadId]);
$messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);

// Style cues
function status_badge_class(string $s): string {
     return $s==='resolved' ? 'bg-success'
              : ($s==='chatting' ? 'bg-warning text-dark'
              : ($s==='rejected' ? 'bg-secondary' : 'bg-danger'));
}
function status_label_fa(string $s): string {
    return $s==='open' ? 'باز'
        : ($s==='chatting' ? 'در حال گفتگو'
            : ($s==='resolved' ? 'حل‌شده'
                : ($s==='rejected' ? 'تایید نشد' : $s)));
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>مشاهده تیکت | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .msg-row { display:flex; gap:12px; margin-bottom:14px; }
        .msg-me  { flex-direction: row-reverse; }
        .bubble  { max-width: 70%; padding:10px 12px; border-radius:12px; }
        .bubble-operator { background:#e9f5ff; border:1px solid #b6e0ff; }
        .bubble-manager  { background:#fff3cd; border:1px solid #ffe69c; }
        .bubble-me       { background:lightskyblue; }
        .small-muted { font-size:12px; color:#6c757d; }
        .badge { font-size:0.75rem; }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm">
                    <div class="col-lg-12 ">
                        <div class="card custom-card " >
                            <?php if (!empty($_SESSION['flash'])):
                                $type = $_SESSION['flash_type'] ?? 'success';
                                $msg  = $_SESSION['flash'];
                                unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
                                <div class="alert alert-<?= h($type) ?> py-2 mb-2"><?= h($msg) ?></div>
                            <?php endif; ?>

                            <div class="card-header">
                                <h5 class="mb-2">
                                    تیکت #<?= (int)$thread['id'] ?> — <?= h($thread['title']) ?>
                                </h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        دانش‌آموز: <strong><?= h(trim(($thread['fname']??'').' '.($thread['lname']??''))) ?></strong>
                                        <span class="text-muted small">| <?= h($thread['codemeli']) ?></span>
                                    </div>
                                    <div>
                                        اپراتور: <span class="badge bg-info"><?= h($thread['operator_username']) ?></span>
                                    </div>
                                    <div>
                                        پاسخ‌دهنده:
                                        <span class="badge bg-secondary"><?= h($thread['responder_username'] ?: '—') ?></span>
                                    </div>
                                    <div>
                                        وضعیت:
                                         <span class="badge <?= status_badge_class($thread['status']) ?>"><?= h(status_label_fa($thread['status'])) ?></span>
                                    </div>
                                    <div class="small-muted">
                                       آخرین بروزرسانی: <?= h(jdate('Y/m/d H:i', strtotime($thread['updated_at']))) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column " style="height:70vh;">  <!-- cap total height -->

                                <?php if ($notice): ?>
                                    <div class="alert alert-success py-2"><?= h($notice) ?></div>
                                <?php endif; ?>
                                <?php if ($errors): ?>
                                    <div class="alert alert-danger py-2">
                                        <?= h(implode(' | ', $errors)) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Messages -->
                                <div id="ChatBody"
                                     class="flex-grow-1 overflow-auto pe-2"
                                     style="min-height:0; padding:12px;background: linear-gradient(135deg,rgba(255,154,68,.20) 0%,rgba(255,61,119,.20) 50%,rgba(179,60,255,.20) 100%),#fafafa;">
                                <?php if (!$messages): ?>
                                        <div class="text-center text-muted py-4">هنوز پیامی ثبت نشده است.</div>
                                    <?php else: foreach ($messages as $m):
                                        $isMe = ($m['sender_username'] === $currentUser);
                                        $who  = $m['sender_username'] === $thread['operator_username'] ? 'اپراتور'
                                            : ($m['sender_is_manager'] ? 'مدیر' : 'کاربر');
                                        $bubbleClass = $isMe ? 'bubble-me' : ($who==='مدیر' ? 'bubble-manager' : 'bubble-operator');
                                        ?>
                                        <div class="msg-row <?= !$isMe ? 'msg-me' : '' ?>">
                                            <div class="bubble <?= $bubbleClass ?>">
                                                <div class="small-muted mb-1">
                                                    <?= h($who) ?>: <?= h($m['sender_username']) ?>
                                                </div>
                                                <div><?= nl2br(h($m['body'])) ?></div>
                                                <div class="small-muted mt-1">
                                                    <?= to_jalali_datetime($m['created_at']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>

                                <!-- Reply box -->
                                <?php if (!in_array($thread['status'], ['resolved','rejected'], true)): ?>                                    <form method="post" class="mt-2">
                                        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                        <input type="hidden" name="action" value="send_message">
                                        <div class="input-group">
                                            <textarea name="body" class="form-control" rows="2" placeholder="پیام خود را بنویسید..." required></textarea>
                                            <button class="btn btn-primary" type="submit">ارسال</button>
                                        </div>
                                    </form>                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">این تیکت بسته شده است و امکان ارسال پیام وجود ندارد.</div>
                                <?php endif; ?>
                            </div>

                            <?php if ($isManager): ?>
                                <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                                    <form method="post" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <label class="mb-0">تغییر وضعیت:</label>
                                        <select name="status" class="form-select form-select-sm w-auto">
                                            <option value="open"     <?= $thread['status']==='open'?'selected':'' ?>>باز</option>
                                            <option value="chatting" <?= $thread['status']==='chatting'?'selected':'' ?>>در حال گفتگو</option>
                                            <option value="resolved" <?= $thread['status']==='resolved'?'selected':'' ?>>حل‌شده</option>
                                            <option value="rejected" <?= $thread['status']==='rejected'?'selected':'' ?>>تایید نشد</option>
                                        </select>
                                        <button class="btn btn-outline-secondary btn-sm" type="submit">ثبت</button>
                                    </form>

                                </div>
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
