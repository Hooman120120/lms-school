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
    exit;
}
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset();
    header('location: ../login.php');
    exit;
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}

/* =========================
   Helpers
   ========================= */
if (!function_exists('h')) {
    function h($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
function to_jalali_datetime(string $ts): string
{
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $ts);
    if (!$dt) return h($ts);
    [$gy, $gm, $gd] = explode('-', $dt->format('Y-m-d'));
    $jal = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
    return implode('/', $jal) . ' - ' . $dt->format('H:i:s');
}

function status_badge_class(string $s): string
{
    return $s === 'resolved' ? 'bg-success'
        : ($s === 'chatting' ? 'bg-warning text-dark'
            : ($s === 'rejected' ? 'bg-secondary' : 'bg-danger'));
}

function status_label_fa(string $s): string
{
    return $s === 'open' ? 'باز'
        : ($s === 'chatting' ? 'در حال گفتگو'
            : ($s === 'resolved' ? 'حل‌شده'
                : ($s === 'rejected' ? 'تایید نشد' : $s)));
}

function teacher_status_badge_class(string $s): string
{
    return $s === 'resolved' ? 'bg-success'
        : ($s === 'chatting' ? 'bg-warning text-dark' : 'bg-danger');
}

function teacher_status_label_fa(string $s): string
{
    return $s === 'open' ? 'باز'
        : ($s === 'chatting' ? 'در حال گفتگو'
            : ($s === 'resolved' ? 'حل‌شده' : $s));
}

function tg_label_fa(string $tg): string
{
    return $tg === 'management' ? 'مدیر کل'
        : ($tg === 'admins' ? 'مدیر میانی'
            : ($tg === 'operators' ? 'پشتیبان' : $tg));
}

/* =========================
   Current user / roles
   ========================= */
$currentUser = (string)($_SESSION['admin']['user'] ?? '');
$sessionRole = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;


$meStmt = $conn->prepare("SELECT username, administrator, permision_suporter, permision_viewalert, permision_viewemploy FROM dbadmin WHERE username=? LIMIT 1");
$meStmt->execute([$currentUser]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$administrator = AdminCan('notifications.all-records') ? 1 : 0;

$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$isSchoolManager = in_array($schoolRoleKey, ['manager', 'general_deputy'], true);
$isOperator = AdminCan(AdminNotificationCode('information.tickets.own'));
$isManager = AdminCan(AdminNotificationCode('information.tickets.manage'));
$canSeeAll = AdminCan(AdminNotificationCode('information.tickets.all'));
if (!$isOperator && !$isManager && $sessionRole !== 3) {
    http_response_code(403);
    exit('دسترسی مشاهده این تیکت برای سمت شما تعریف نشده است.');
}

/* =========================
   Identify which system
   - Default: report_threads
   - If not found, try teacher_ticket
   ========================= */
/* =========================
   Identify which system (NO collision)
   ========================= */
$threadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($threadId <= 0) {
    http_response_code(400);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>شناسه تیکت نامعتبر است.</div>";
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? '')));

/*
  If type=teacher -> ONLY teacher_ticket
  Else -> ONLY report_threads (default keeps old behavior)
*/
$MODE = ($type === 'teacher') ? 'teacher' : 'report';

$thread = null;
$teacherTicket = null;

if ($MODE === 'report') {
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

} else { // teacher
    $ttStmt = $conn->prepare("
        SELECT
            tt.*,
            p.fname, p.lname,
            c.name AS class_name,
            c.codecourse,
            co.coursename AS course_name
        FROM teacher_ticket tt
        LEFT JOIN dbpersonal p ON p.codemeli = tt.codemeli
        LEFT JOIN classes c ON c.id = tt.class_id
        LEFT JOIN codtbl_course co ON co.codecourse = c.codecourse
        WHERE tt.id = ?
        LIMIT 1
    ");
    $ttStmt->execute([$threadId]);
    $teacherTicket = $ttStmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacherTicket) {
        http_response_code(404);
        echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>❌ تیکت یافت نشد.</div>";
        exit;
    }
}

if ($MODE === 'report' && !$thread) {
    http_response_code(404);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>❌ تیکت یافت نشد.</div>";
    exit;
}
if ($MODE === 'teacher' && !$teacherTicket) {
    http_response_code(404);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>❌ تیکت یافت نشد.</div>";
    exit;
}

/* =========================
   CSRF token
   ========================= */
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token_ok = function () {
    return isset($_POST['token'], $_SESSION['token']) && hash_equals($_SESSION['token'], $_POST['token']);
};

/* =========================
   MODE: REPORT (existing)
   ========================= */
if ($MODE === 'report') {

    // Access control
    if ($isManager) {
        if (!$canSeeAll) {
            $claimed = !empty($thread['responder_username']);
            $ownedByMe = ($thread['responder_username'] === $currentUser);
            if ($claimed && !$ownedByMe) {
                http_response_code(403);
                echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ این تیکت در اختیار مدیر دیگری است.</div>";
                exit;
            }
        }
    } else {
        if (($thread['operator_username'] ?? '') !== $currentUser) {
            http_response_code(403);
            echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ دسترسی ندارید.</div>";
            exit;
        }
    }

    // Mark messages as seen
    if ($isManager) {
        $upd = $conn->prepare("
            UPDATE report_messages
            SET seen_by_manager = 1, seen_at_manager = NOW()
            WHERE thread_id = ?
              AND sender_username <> ?
              AND seen_by_manager = 0
        ");
        $upd->execute([$threadId, $currentUser]);
    } else {
        $upd = $conn->prepare("
            UPDATE report_messages
            SET seen_by_operator = 1, seen_at_operator = NOW()
            WHERE thread_id = ?
              AND sender_username <> ?
              AND seen_by_operator = 0
        ");
        $upd->execute([$threadId, $currentUser]);
    }

    // POST actions
    $errors = [];
    $notice = null;

    if ($isManager && isset($_POST['action']) && $_POST['action'] === 'set_status' && $token_ok()) {
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, ['open', 'chatting', 'resolved', 'rejected'], true)) {
            $errors[] = 'وضعیت نامعتبر است.';
        } else {
            $u = $conn->prepare("UPDATE report_threads SET status=?, updated_at=NOW() WHERE id=?");
            $u->execute([$newStatus, $threadId]);
            $notice = 'وضعیت بروزرسانی شد.';
            $tStmt->execute([$threadId]);
            $thread = $tStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'send_message' && $token_ok()) {
        if (in_array(($thread['status'] ?? ''), ['resolved', 'rejected'], true)) {
            $_SESSION['flash'] = 'این تیکت بسته شده است.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: thread_view.php?id=' . (int)$threadId);
            exit;
        }

        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            $_SESSION['flash'] = 'متن پیام خالی است.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: thread_view.php?id=' . (int)$threadId);
            exit;
        }

        $ins = $conn->prepare("INSERT INTO report_messages (thread_id, sender_username, body, created_at) VALUES (?, ?, ?, NOW())");
        $ins->execute([$threadId, $currentUser, $body]);

        if ($isManager && empty($thread['responder_username'])) {
            $conn->prepare("UPDATE report_threads SET responder_username=?, status='chatting', updated_at=NOW() WHERE id=?")
                ->execute([$currentUser, $threadId]);
        } else {
            if (($thread['status'] ?? '') === 'open') {
                $conn->prepare("UPDATE report_threads SET status='chatting', updated_at=NOW() WHERE id=?")
                    ->execute([$threadId]);
            } else {
                $conn->prepare("UPDATE report_threads SET updated_at=NOW() WHERE id=?")
                    ->execute([$threadId]);
            }
        }

        $_SESSION['flash'] = 'پیام ارسال شد.';
        $_SESSION['flash_type'] = 'success';
        header('Location: thread_view.php?id=' . (int)$threadId);
        exit;
    }

    // Load messages
    $mStmt = $conn->prepare("
        SELECT m.id, m.sender_username, m.body, m.created_at,
               m.seen_by_operator, m.seen_by_manager, m.seen_at_operator, m.seen_at_manager,
               da.permision_viewalert AS sender_is_manager,
               da.permision_suporter AS sender_is_operator
        FROM report_messages m
        LEFT JOIN dbadmin da ON da.username = m.sender_username
        WHERE m.thread_id = ?
        ORDER BY m.created_at ASC
    ");
    $mStmt->execute([$threadId]);
    $messages = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    include_once '../../temp.html';
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
        <title>مشاهده تیکت | <?php echo __TITLE ?></title>
        <?php include_once '../link.php'; ?>
        <style>
            .msg-row {
                display: flex;
                gap: 12px;
                margin-bottom: 14px;
            }

            .msg-me {
                justify-content: flex-end;
            }

            .bubble {
                max-width: 70%;
                padding: 10px 12px;
                border-radius: 12px;
            }

            .bubble-operator {
                background: #e9f5ff;
                border: 1px solid #b6e0ff;
            }

            .bubble-manager {
                background: #fff3cd;
                border: 1px solid #ffe69c;
            }

            .bubble-me {
                background: lightskyblue;
            }

            .small-muted {
                font-size: 12px;
                color: #6c757d;
            }

            .badge {
                font-size: 0.75rem;
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

                    <div class="row row-sm">
                        <div class="col-lg-12 ">
                            <div class="card custom-card ">
                                <?php if (!empty($_SESSION['flash'])):
                                    $type = $_SESSION['flash_type'] ?? 'success';
                                    $msg = $_SESSION['flash'];
                                    unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
                                    <div class="alert alert-<?= h($type) ?> py-2 mb-2"><?= h($msg) ?></div>
                                <?php endif; ?>

                                <div class="card-header">
                                    <h5 class="mb-2">
                                        تیکت #<?= (int)$thread['id'] ?> — <?= h($thread['title']) ?>
                                    </h5>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div>
                                            دانش‌آموز:
                                            <strong><?= h(trim(($thread['fname'] ?? '') . ' ' . ($thread['lname'] ?? ''))) ?></strong>
                                            <span class="text-muted small">| <?= h($thread['codemeli']) ?></span>
                                        </div>
                                        <div>
                                            اپراتور: <span
                                                    class="badge bg-info"><?= h($thread['operator_username']) ?></span>
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
                                            آخرین
                                            بروزرسانی: <?= h(jdate('Y/m/d H:i', strtotime($thread['updated_at']))) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body d-flex flex-column" style="height:70vh;">
                                    <?php if (!empty($notice)): ?>
                                        <div class="alert alert-success py-2"><?= h($notice) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger py-2"><?= h(implode(' | ', $errors)) ?></div>
                                    <?php endif; ?>

                                    <div id="ChatBody"
                                         class="flex-grow-1 overflow-auto pe-2"
                                         style="min-height:0; padding:12px;background: linear-gradient(135deg,rgba(255,154,68,.20) 0%,rgba(255,61,119,.20) 50%,rgba(179,60,255,.20) 100%),#fafafa;">
                                        <?php if (!$messages): ?>
                                            <div class="text-center text-muted py-4">هنوز پیامی ثبت نشده است.</div>
                                        <?php else: foreach ($messages as $m):
                                            $isMe = ($m['sender_username'] === $currentUser);
                                            $who = ($m['sender_username'] === $thread['operator_username']) ? 'اپراتور' : (!empty($m['sender_is_manager']) ? 'مدیر' : 'کاربر');
                                            $bubbleClass = $isMe ? 'bubble-me' : ($who === 'مدیر' ? 'bubble-manager' : 'bubble-operator');
                                            ?>
                                            <div class="msg-row <?= $isMe ? 'msg-me' : '' ?>">
                                                <div class="bubble <?= $bubbleClass ?>">
                                                    <div class="small-muted mb-1"><?= h($who) ?>
                                                        : <?= h($m['sender_username']) ?></div>
                                                    <div><?= nl2br(h($m['body'])) ?></div>
                                                    <div class="small-muted mt-1"><?= to_jalali_datetime($m['created_at']) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </div>

                                    <?php if (!in_array(($thread['status'] ?? ''), ['resolved', 'rejected'], true)): ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                            <input type="hidden" name="action" value="send_message">
                                            <div class="input-group">
                                                <textarea name="body" class="form-control" rows="2"
                                                          placeholder="پیام خود را بنویسید..." required></textarea>
                                                <button class="btn btn-primary" type="submit">ارسال</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert alert-secondary mb-0">این تیکت بسته شده است و امکان ارسال پیام
                                            وجود ندارد.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($isManager): ?>
                                    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                                        <form method="post" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                            <input type="hidden" name="action" value="set_status">
                                            <label class="mb-0">تغییر وضعیت:</label>
                                            <select name="status" class="form-select form-select-sm w-auto">
                                                <option value="open" <?= ($thread['status'] ?? '') === 'open' ? 'selected' : '' ?>>
                                                    باز
                                                </option>
                                                <option value="chatting" <?= ($thread['status'] ?? '') === 'chatting' ? 'selected' : '' ?>>
                                                    در حال گفتگو
                                                </option>
                                                <option value="resolved" <?= ($thread['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>
                                                    حل‌شده
                                                </option>
                                                <option value="rejected" <?= ($thread['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>
                                                    تایید نشد
                                                </option>
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
    <?php
    exit;
}

/* =========================
   MODE: TEACHER TICKET
   ========================= */

// Access control for teacher_ticket
$tg = (string)($teacherTicket['target_group'] ?? '');
$codemeli = trim((string)($teacherTicket['codemeli'] ?? ''));

// Option B: supporter lookup ONLY by codemeli
$suporter = '';
if ($codemeli !== '') {
    $st = $conn->prepare("
        SELECT suporter
        FROM dbcourse
        WHERE username=?
          AND suporter IS NOT NULL
          AND suporter <> ''
        LIMIT 1
    ");
    $st->execute([$codemeli]);
    $suporter = (string)($st->fetchColumn() ?? '');
}

$mStmt = $conn->prepare("
        SELECT m.id, m.sender_username, m.body, m.created_at,
               m.seen_by_operator, m.seen_by_manager, m.seen_at_operator, m.seen_at_manager,
               da.permision_viewalert AS sender_is_manager,
               da.permision_suporter AS sender_is_operator
        FROM report_messages m
        LEFT JOIN dbadmin da ON da.username = m.sender_username
        WHERE m.thread_id = ?
        ORDER BY m.created_at ASC
    ");
$mStmt->execute([$threadId]);
$messages = $mStmt->fetchAll(PDO::FETCH_ASSOC); //test


// ✅ Ticket owner ALWAYS has access (teacher can always view their own ticket)
$teachername = $conn->prepare("SELECT fname,lname FROM dbadmin WHERE username = ?");
$teachername->execute([$teacherTicket['teacher_username']]);
$teacherfullname = $teachername->fetchAll(PDO::FETCH_ASSOC);
$ticketOwner = (string)($teacherTicket['teacher_username'] ?? '');
if ($ticketOwner !== '' && $ticketOwner === $currentUser) {
    $allowed = true;
} else {
    // Others access by target_group rules
    $allowed = false;

    if ($tg === 'management') {
        $allowed = ($administrator === 1);
    } elseif ($tg === 'admins') {
        // middle admin + management can view
        $allowed = ($sessionRole === 2 || $administrator === 1);
    } elseif ($tg === 'operators') {
        // operator supporter OR middle admin OR management can view
        if ($administrator === 1 || $sessionRole === 2) {
            $allowed = true;
        } else {
            $allowed = ($sessionRole === 1 && $administrator !== 1 && $suporter !== '' && $suporter === $currentUser);
        }
    }
}


if (!$allowed) {
    http_response_code(403);
    echo "<!doctype html><meta charset='utf-8'><div style='padding:20px'>⛔ دسترسی ندارید.</div>";
    exit;
}

// Viewer side: teacher vs responder
$isTeacherViewer = ((string)($teacherTicket['teacher_username'] ?? '') === $currentUser);

// Mark messages as seen for this viewer
if ($isTeacherViewer) {
    $upd = $conn->prepare("
        UPDATE teacher_ticket_messages
        SET seen_by_teacher = 1,
            seen_at_teacher = NOW()
        WHERE teacher_ticket_id = ?
          AND sender_username <> ?
          AND seen_by_teacher = 0
    ");
    $upd->execute([$threadId, $currentUser]);
} else {
    $upd = $conn->prepare("
        UPDATE teacher_ticket_messages
        SET seen_by_respounder = 1,
            seen_at_respounder = NOW()
        WHERE teacher_ticket_id = ?
          AND sender_username <> ?
          AND seen_by_respounder = 0
    ");
    $upd->execute([$threadId, $currentUser]);
}

// POST actions
$errors = [];
$notice = null;

// Status update (only non-teacher, and only open/chatting/resolved)
if (!$isTeacherViewer && isset($_POST['action']) && $_POST['action'] === 'set_status' && $token_ok()) {
    $newStatus = trim((string)($_POST['status'] ?? ''));
    if (!in_array($newStatus, ['open', 'chatting', 'resolved'], true)) {
        $errors[] = 'وضعیت نامعتبر است.';
    } else {
        $u = $conn->prepare("UPDATE teacher_ticket SET status=?, updated_at=NOW() WHERE id=? LIMIT 1");
        $u->execute([$newStatus, $threadId]);
        $notice = 'وضعیت بروزرسانی شد.';
        // refresh ticket
        $ttStmt = $conn->prepare("
            SELECT
                tt.*,
                p.fname, p.lname,
                c.name AS class_name,
                c.codecourse,
                co.coursename AS course_name
            FROM teacher_ticket tt
            LEFT JOIN dbpersonal p ON p.codemeli = tt.codemeli
            LEFT JOIN classes c ON c.id = tt.class_id
            LEFT JOIN codtbl_course co ON co.codecourse = c.codecourse
            WHERE tt.id = ?
            LIMIT 1
        ");
        $ttStmt->execute([$threadId]);
        $teacherTicket = $ttStmt->fetch(PDO::FETCH_ASSOC) ?: $teacherTicket;
    }
}

// Send message (teacher + responders) while NOT resolved
if (isset($_POST['action']) && $_POST['action'] === 'send_message' && $token_ok()) {

    if (isset($_FILES['fileto']) && $_FILES['fileto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $maxSize = 4 * 1024 * 1024;
        $file = $_FILES['fileto'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash'] = 'خطا در بارگذاری فایل؛ حجم فایل را بررسی کنید.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
            exit;
        }
        if ((int)$file['size'] > $maxSize) {
            $_SESSION['flash'] = 'حجم فایل نباید بیشتر از ۴ مگابایت باشد.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
            exit;
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/teacher_file';
        $fileName = uniqid() . '_' . basename($file['name']);
        $uploadPath = $uploadDir . '/' . $fileName;
        $upload_path_for_db = '/assets/teacher_file/' . $fileName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $ins = $conn->prepare("
        INSERT INTO teacher_ticket_messages (teacher_ticket_id, sender_username, body, file_path, created_at)
        VALUES (?, ?, ?,?, NOW())
    ");
        } else {
            $_SESSION['flash'] = 'فایل بارگذاری نشد؛ دوباره تلاش کنید.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
            exit;
        }
    }





    if (($teacherTicket['status'] ?? '') === 'resolved') {
        $_SESSION['flash'] = 'این تیکت بسته شده است.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
        exit;
    }

    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '') {
        $_SESSION['flash'] = 'متن پیام خالی است.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
        exit;
    }


    if (isset($upload_path_for_db) ) {
        $ins->execute([$threadId, $currentUser, $body, $upload_path_for_db]);

    } else {
        $ins = $conn->prepare("
        INSERT INTO teacher_ticket_messages (teacher_ticket_id, sender_username, body, created_at)
        VALUES (?, ?, ?, NOW())
    ");
        $ins->execute([$threadId, $currentUser, $body]);

    }

    // If responder replies first time: set responder_username & status=chatting
    if (!$isTeacherViewer && empty($teacherTicket['responder_username'])) {
        $conn->prepare("UPDATE teacher_ticket SET responder_username=?, status='chatting', updated_at=NOW() WHERE id=?")
            ->execute([$currentUser, $threadId]);
    } else {
        // If ticket is still open, move to chatting
        if (($teacherTicket['status'] ?? '') === 'open') {
            $conn->prepare("UPDATE teacher_ticket SET status='chatting', updated_at=NOW() WHERE id=?")
                ->execute([$threadId]);
        } else {
            $conn->prepare("UPDATE teacher_ticket SET updated_at=NOW() WHERE id=?")
                ->execute([$threadId]);
        }
    }

    $_SESSION['flash'] = 'پیام ارسال شد.';
    $_SESSION['flash_type'] = 'success';
    header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
    exit;
}

// Load messages (also get sender roles for nicer labels)
$mStmt = $conn->prepare("
    SELECT
        m.id, m.sender_username, m.body, m.created_at,
        m.seen_by_teacher, m.seen_by_respounder, m.seen_at_teacher, m.seen_at_respounder,m.file_path,
        da.administrator AS sender_administrator,
        da.adminrole      AS sender_adminrole
    FROM teacher_ticket_messages m
    LEFT JOIN dbadmin da ON da.username = m.sender_username
    WHERE m.teacher_ticket_id = ?
    ORDER BY m.created_at ASC
");
$mStmt->execute([$threadId]);
$messages = $mStmt->fetchAll(PDO::FETCH_OBJ);

// Label sender by role
$teacherUsername = (string)($teacherTicket['teacher_username'] ?? '');
function sender_label_fa(object $m, string $teacherUsername): string
{
    $u = (string)($m->sender_username ?? '');
    if ($u === $teacherUsername) return t('teacher');
    $admin = (int)($m->sender_administrator ?? 0);
    $role = (int)($m->sender_adminrole ?? 0);
    if ($admin === 1) return 'مدیر کل';
    if ($role === 2) return 'مدیر میانی';
    if ($role === 1) return 'پشتیبان';
    return 'پاسخ‌دهنده';
}

if (isset($_GET['delm'])) {
    $sql = 'SELECT * FROM teacher_ticket_messages WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delm']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    $datetime = $message->created_at;

    if (time() - strtotime($datetime) <= 86400 or AdminCan(AdminNotificationCode('information.tickets.manage'))) {
        if ($message && !empty($message->file_path)) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = 'DELETE FROM teacher_ticket_messages WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['delm']]);
        header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
        die();
    } else {
        $_SESSION['error_message'] = 'امکان حذف پیام بعد از یک ساعت وجود ندارد.';
        header('Location: thread_view.php?type=teacher&id=' . (int)$threadId);
        die();
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title><?php echo m('page.view_teacher_ticket') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .msg-row {
            display: flex;
            gap: 12px;
            margin-bottom: 14px;
        }

        .msg-me {
            justify-content: flex-end;
        }

        .bubble {
            max-width: 70%;
            padding: 10px 12px;
            border-radius: 12px;
        }

        .bubble-teacher {
            background: #e9f5ff;
            border: 1px solid #b6e0ff;
        }

        .bubble-responder {
            background: #fff3cd;
            border: 1px solid #ffe69c;
        }

        .bubble-me {
            background: lightskyblue;
        }

        .small-muted {
            font-size: 12px;
            color: #6c757d;
        }

        .badge {
            font-size: 0.75rem;
        }

        .chat-att achment-card {
            background: rgba(255, 255, 255, 0.2);
            /* Semi-transparent */
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 10px;
            margin-top: 8px;
            transition: all 0.3s ease;
            max-width: 250px;
            margin-bottom: 10px;
        }

        .chat-attachment-card:hover {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .chat-attachment-card a {
            text-decoration: none !important;
            color: inherit;
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            background: #5eb95e;
            /* Success green to match your theme */
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-left: 10px;
            /* Space for RTL */
        }

        .attachment-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            text-align: right;
        }

        .file-name {
            font-size: 13px;
            font-weight: bold;
            display: block;
        }

        .file-size {
            font-size: 11px;
        }

        .download-arrow {
            font-size: 18px;
            color: #888;
            margin-right: 5px;
        }

        /* Specific styling for admin vs student bubbles */
        .media.flex-row-reverse .attachment-icon {
            background: #3858f9;
            /* Blue for students */
        }

        .main-msg-wrapper {
            /* Limit the bubble width so it doesn't hit the other side */
            max-width: 80% !important;

            /* Ensure the bubble itself behaves as a block for the text */
            display: flex !important;
            flex-direction: column !important;

            /* Add word-wrap to handle long strings like links or "sssssssss" */
            word-break: break-word !important;
            overflow-wrap: break-word !important;

            /* Polish the padding and shape */
            padding: 10px 15px !important;
            border-radius: 12px !important;
            margin-bottom: 5px !important;
        }

        /* Fix for the text inside the bubble */
        .main-msg-wrapper span {
            display: block !important;
            line-height: 1.6;
        }
        .hovershow{

        }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">


    <div class="main-content  pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">

                            <?php if (!empty($_SESSION['flash'])):
                                $type = $_SESSION['flash_type'] ?? 'success';
                                $msg = $_SESSION['flash'];
                                unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
                                <div class="alert alert-<?= h($type) ?> py-2 mb-2"><?= h($msg) ?></div>
                            <?php endif; ?>

                            <div class="card-header">
                                <div class="d-flex flex-row justify-content-between" >
                                    <div class="h5">
                                    <?php echo m('label.teacher_ticket') ?> #<?= (int)$teacherTicket['id'] ?>
                                    — <?= h($teacherTicket['title'] ?? '—') ?>
                                    </div>
                                    <div ><a class="nav-link" data-bs-toggle="tooltip" href="#" title="محدودیت حجم آپلود 4 مگابایت است و فایل های قابل آپلود به این صورت هستند: .pdf,doc,docx,jpg,png,zip,rar">
                                        <i class="fe fe-info fs-4" style="color: gray;" title="">
                                        </i>
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-3">
                                    <div>
                                        <?php echo m('label.teacher_colon') ?> <span
                                                class="badge bg-info"><?php echo $teacherfullname[0]['fname'].' '.$teacherfullname[0]['lname'] ?></span>
                                    </div>

                                    <div>
                                        گروه مقصد:
                                        <span class="badge bg-dark"><?= h(tg_label_fa((string)($teacherTicket['target_group'] ?? ''))) ?></span>
                                    </div>

                                    <div>
                                        پاسخ‌دهنده:
                                        <span class="badge bg-secondary"><?= h(($teacherTicket['responder_username'] ?? '') ?: '—') ?></span>
                                    </div>

                                    <div>
                                        وضعیت:
                                        <span class="badge <?= teacher_status_badge_class((string)($teacherTicket['status'] ?? 'open')) ?>">
                                            <?= h(teacher_status_label_fa((string)($teacherTicket['status'] ?? 'open'))) ?>
                                        </span>
                                    </div>

                                    <div class="small-muted">
                                        آخرین بروزرسانی:
                                        <?php
                                        $upd = $teacherTicket['updated_at'] ?? '';
                                        echo $upd ? h(jdate('Y/m/d H:i', strtotime($upd))) : '—';
                                        ?>
                                    </div>
                                </div>

                                <div class="mt-2 d-flex flex-wrap gap-3">
                                    <div>
                                        کلاس:
                                        <strong><?= h(($teacherTicket['class_name'] ?? '') ?: ('کلاس ' . $teacherTicket['class_id'])) ?></strong>
                                        <?php if (!empty($teacherTicket['course_name'])): ?>
                                            <span class="text-muted small">| <?= h($teacherTicket['course_name']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        دانش‌آموز:
                                        <strong><?= h(trim(($teacherTicket['fname'] ?? '') . ' ' . ($teacherTicket['lname'] ?? ''))) ?></strong>
                                    </div>

                                    <?php if (!$isTeacherViewer && $tg === 'operators' && $suporter !== ''): ?>
                                        <div class="text-muted small">اپراتور دانش‌آموز: <span
                                                    class="badge bg-light text-dark"><?= h($suporter) ?></span></div>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="card-body d-flex flex-column" style="height:70vh;">
                                <?php if (!empty($notice)): ?>
                                    <div class="alert alert-success py-2"><?= h($notice) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger py-2"><?= h(implode(' | ', $errors)) ?></div>
                                <?php endif; ?>

                                <div id="ChatBody"
                                     class="flex-grow-1 overflow-auto pe-2"
                                     style="min-height:0; padding:12px;background: linear-gradient(135deg,rgba(255,154,68,.20) 0%,rgba(255,61,119,.20) 50%,rgba(179,60,255,.20) 100%),#fafafa;">
                                    <?php if (!$messages): ?>
                                        <div class="text-center text-muted py-4">هنوز پیامی ثبت نشده است.</div>
                                    <?php else: foreach ($messages as $m):

                                        $isMe = ((string)$m->sender_username === $currentUser);
                                        $label = sender_label_fa($m, $teacherUsername);

                                        // styling: teacher vs responder; my messages override
                                        $bubbleClass = 'bubble-responder';
                                        if ((string)$m->sender_username === $teacherUsername) $bubbleClass = 'bubble-teacher';
                                        if ($isMe) $bubbleClass = 'bubble-me';

                                        ?>
                                        <div class="msg-row <?= $isMe ? 'msg-me' : '' ?>">
                                            <div class="bubble <?= $bubbleClass ?>">

                                                <div class="small-muted mb-1">   <?= h($label) ?> :
                                                    <?= h($m->sender_username) ?>
                                                </div>
                                                <div><?= nl2br(h($m->body)) ?>  </div>
                                                <?php if (!empty($m->file_path)) { ?>
                                                    <div class="chat-attachment-card">
                                                        <a href="<?= url('Support/attachments-teacher.php') . '?fileChat=' . urlencode($m->id) ?>"
                                                           class="d-flex align-items-center">
                                                            <div class="attachment-icon">
                                                                <i class="fe fe-file-text"></i>
                                                            </div>
                                                            <div class="attachment-info">
                                                                <span class="file-name">مشاهده پیوست</span>
                                                                <small class="file-size text-muted">برای دانلود کلیک
                                                                    کنید</small>
                                                            </div>
                                                            <div class="download-arrow">
                                                                <i class="fe fe-download"></i>
                                                            </div>
                                                        </a>
                                                    </div>
                                                <?php } ?>
                                                <div class="d-flex flex-row justify-content-between">
                                                <div class="small-muted mt-1"><?= to_jalali_datetime($m->created_at) ?></div>
                                                <div class=" mt-1">
                                                <?php
                                                $sql = "SELECT * FROM teacher_ticket_messages WHERE id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$m->id]);
                                                $message = $stmt->fetch(PDO::FETCH_OBJ);
                                                $datetime = $message->created_at;
                                                if($isMe == true && !permision('administrator') && time() - strtotime($datetime) <= 86400 ) : ?>
                                                    <a href="thread_view.php?type=teacher&id=<?php echo $_GET['id']; ?> &delm=<?php echo $m->id ?> "
                                                       onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                       title="حذف">
                                                        <i class="fe fe-trash-2" style="font-size: large;"></i>
                                                    </a>
                                                <?php elseif(permision('administrator')) : ?>
                                                    <a href="thread_view.php?type=teacher&id=<?php echo $_GET['id']; ?> &delm=<?php echo $m->id ?> "
                                                       onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                       title="حذف">
                                                        <i class="fe fe-trash-2" style="font-size: large;"></i>
                                                    </a>
                                                <?php endif; ?>
                                                </div>
                                              </div>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>

                                <?php if (($teacherTicket['status'] ?? '') !== 'resolved'): ?>
                                    <form id="teacherMessageForm" method="post" class="mt-2" enctype="multipart/form-data">
                                        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                        <input type="hidden" name="action" value="send_message">
                                        <div class="input-group">
                                            <textarea name="body" class="form-control" rows="2"
                                                      placeholder="پیام خود را بنویسید.. (در صورت آپلود فایل این قسمت را نیز خالی نزارید و سپس دکمه ارسال را بزنید)" required></textarea>
                                            <button class="btn btn-primary" type="submit">ارسال</button>
                                            <nav class="nav">

                                                <a class="nav-link" data-bs-toggle="tooltip" href="#"
                                                   title="یک فایل را پیوست کنید">
                                                <label class="input-group-btn">

                                                    <p style="background-color: green; padding: 5px; border-radius: 10px; color: white; ">
                                                        آپلود فایل</p>

                                                    <input id="teacherMessageFile" type="file" name="fileto" style="display: none;" multiple="" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar" >
                                                </a>
                                                </label>
                                            </nav>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-secondary mb-0">این تیکت بسته شده است و امکان ارسال پیام
                                        وجود ندارد.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!$isTeacherViewer): ?>
                                <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
                                    <form method="post" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <label class="mb-0">تغییر وضعیت:</label>
                                        <select name="status" class="form-select form-select-sm w-auto">
                                            <option value="open" <?= (($teacherTicket['status'] ?? '') === 'open' ? 'selected' : '') ?>>
                                                باز
                                            </option>
                                            <option value="chatting" <?= (($teacherTicket['status'] ?? '') === 'chatting' ? 'selected' : '') ?>>
                                                در حال گفتگو
                                            </option>
                                            <option value="resolved" <?= (($teacherTicket['status'] ?? '') === 'resolved' ? 'selected' : '') ?>>
                                                حل‌شده
                                            </option>
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

        const messageForm = document.getElementById('teacherMessageForm');
        const messageFile = document.getElementById('teacherMessageFile');
        if (messageForm && messageFile) {
            messageForm.addEventListener('submit', function (event) {
                if (messageFile.files[0] && messageFile.files[0].size > 4 * 1024 * 1024) {
                    event.preventDefault();
                    alert('حجم فایل نباید بیشتر از ۴ مگابایت باشد.');
                    messageFile.value = '';
                }
            });
        }
    });
</script>
</body>
</html>
