<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
AdminDeny('این بخش از پنل حذف شده است.');
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    exit;
}
if (($_SESSION['admin']['ip'] ?? '') !== GetIp() && ($_SESSION['admin']['os'] ?? '') != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
$sessionRow = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}

if (empty($_SESSION['assigned_tasks_csrf'])) {
    $_SESSION['assigned_tasks_csrf'] = bin2hex(random_bytes(24));
}

$currentUser = (string)$_SESSION['admin']['user'];
$message = '';
$messageType = 'info';
$validStatuses = ['pending', 'in_progress', 'done', 'cancelled'];
$validPriorities = ['low', 'normal', 'high'];

function assignedTaskUserExists(PDO $conn, string $username): bool
{
    if ($username === '') {
        return true;
    }
    $stmt = $conn->prepare('SELECT 1 FROM dbadmin WHERE username = ? AND adminactive = 1 LIMIT 1');
    $stmt->execute([$username]);
    return (bool)$stmt->fetchColumn();
}

function assignedTaskExists(PDO $conn, int $taskId): bool
{
    if ($taskId <= 0) {
        return false;
    }
    $stmt = $conn->prepare('SELECT 1 FROM school_assigned_tasks WHERE id = ? LIMIT 1');
    $stmt->execute([$taskId]);
    return (bool)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['assigned_tasks_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('درخواست نامعتبر است.');
    }

    $taskId = (int)($_POST['task_id'] ?? 0);

    if (isset($_POST['delete_task'])) {
        if (!assignedTaskExists($conn, $taskId)) {
            $message = 'کار موردنظر یافت نشد.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('DELETE FROM school_assigned_tasks WHERE id = ?');
            $stmt->execute([$taskId]);
            $message = 'کار حذف شد.';
        }
    } elseif (isset($_POST['change_status'])) {
        $status = (string)($_POST['status'] ?? '');
        if (!assignedTaskExists($conn, $taskId) || !in_array($status, $validStatuses, true)) {
            $message = 'درخواست تغییر وضعیت معتبر نیست.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('UPDATE school_assigned_tasks SET status = ? WHERE id = ?');
            $stmt->execute([$status, $taskId]);
            $message = 'وضعیت کار تغییر کرد.';
        }
    } elseif (isset($_POST['save_task'])) {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $assignedTo = trim((string)($_POST['assigned_to'] ?? ''));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'normal');

        if ($title === '') {
            $message = 'عنوان کار الزامی است.';
            $messageType = 'danger';
        } elseif (mb_strlen($title, 'UTF-8') > 190) {
            $message = 'عنوان کار نباید بیشتر از ۱۹۰ نویسه باشد.';
            $messageType = 'danger';
        } elseif (!assignedTaskUserExists($conn, $assignedTo)) {
            $message = 'همکار انتخاب‌شده معتبر یا فعال نیست.';
            $messageType = 'danger';
        } elseif ($dueDate !== '' && DateTime::createFromFormat('Y-m-d\TH:i', $dueDate) === false) {
            $message = 'موعد انجام معتبر نیست.';
            $messageType = 'danger';
        } else {
            $priority = in_array($priority, $validPriorities, true) ? $priority : 'normal';
            $databaseDueDate = $dueDate !== '' ? str_replace('T', ' ', $dueDate) . ':00' : null;
            $assignedTo = $assignedTo !== '' ? $assignedTo : null;

            if ($taskId > 0) {
                if (!assignedTaskExists($conn, $taskId)) {
                    $message = 'کار موردنظر برای ویرایش یافت نشد.';
                    $messageType = 'danger';
                } else {
                    $stmt = $conn->prepare('UPDATE school_assigned_tasks
                        SET title = ?, description = ?, assigned_to = ?, due_date = ?, priority = ?
                        WHERE id = ?');
                    $stmt->execute([$title, $description, $assignedTo, $databaseDueDate, $priority, $taskId]);
                    $message = 'کار با موفقیت ویرایش شد.';
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO school_assigned_tasks
                    (title, description, assigned_to, due_date, priority, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $description, $assignedTo, $databaseDueDate, $priority, 'pending', $currentUser]);
                $message = 'کار جدید ثبت و محول شد.';
            }
        }
    }
}

$staff = $conn->query("SELECT username, CONCAT(COALESCE(fname,''),' ',COALESCE(lname,'')) full_name
    FROM dbadmin WHERE adminactive = 1 ORDER BY lname, fname")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$filterStatus = (string)($_GET['status'] ?? '');
$filterAssignee = trim((string)($_GET['assigned_to'] ?? ''));
$where = [];
$params = [];
if (in_array($filterStatus, $validStatuses, true)) {
    $where[] = 't.status = ?';
    $params[] = $filterStatus;
}
if ($filterAssignee !== '') {
    $where[] = 't.assigned_to = ?';
    $params[] = $filterAssignee;
}
$sql = "SELECT t.*,
        CONCAT(COALESCE(a.fname,''),' ',COALESCE(a.lname,'')) assignee_name,
        CONCAT(COALESCE(c.fname,''),' ',COALESCE(c.lname,'')) creator_name
    FROM school_assigned_tasks t
    LEFT JOIN dbadmin a ON a.username = t.assigned_to
    LEFT JOIN dbadmin c ON c.username = t.created_by";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(t.status,'pending','in_progress','done','cancelled'),
    t.due_date IS NULL, t.due_date, t.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$statusLabels = ['pending' => 'در انتظار', 'in_progress' => 'در حال انجام', 'done' => 'انجام‌شده', 'cancelled' => 'لغوشده'];
$priorityLabels = ['low' => 'کم', 'normal' => 'عادی', 'high' => 'فوری'];
$calendarColors = ['pending' => '#f59e0b', 'in_progress' => '#3b82f6', 'done' => '#16a34a', 'cancelled' => '#6b7280'];
$calendarEvents = [];
foreach ($rows as $row) {
    if (empty($row['due_date'])) {
        continue;
    }
    $calendarEvents[] = [
        'id' => (string)$row['id'],
        'title' => (string)$row['title'],
        'start' => str_replace(' ', 'T', (string)$row['due_date']),
        'backgroundColor' => $calendarColors[$row['status']] ?? '#5b6acf',
        'borderColor' => $calendarColors[$row['status']] ?? '#5b6acf',
        'extendedProps' => [
            'status' => $statusLabels[$row['status']] ?? '',
            'assignee' => trim((string)$row['assignee_name']) ?: 'تعیین‌نشده',
        ],
    ];
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>کارهای محول‌شده | <?= __TITLE ?></title>
    <?php include '../link.php'; ?>
    <style>
        .task-board { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .task-card { border-right:4px solid #5b6acf; transition:box-shadow .2s,transform .2s; }
        .task-card.priority-high { border-right-color:#dc3545; }
        .task-card.done { opacity:.72; }
        .task-card.task-focus { box-shadow:0 0 0 3px rgba(91,106,207,.25); transform:translateY(-2px); }
        #assignedTasksCalendar { min-height:560px; }
        .fc .fc-toolbar-title { font-size:1.1rem; }
        @media(max-width:900px) { .task-board { grid-template-columns:1fr; } }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include '../menu.php'; include '../header.php'; ?>
    <div class="main-content side-content pt-0"><div class="container-fluid"><div class="inner-body">
        <div class="page-header"><div>
            <h2 class="main-content-title tx-24">کارهای محول‌شده</h2>
            <p class="text-muted">ثبت، زمان‌بندی و پیگیری کارها در نمای تقویمی</p>
        </div></div>

        <?php if ($message): ?><div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="card custom-card mb-3"><div class="card-body">
            <h5 id="taskFormTitle">ثبت کار جدید</h5>
            <form method="post" class="row" id="taskForm">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['assigned_tasks_csrf']) ?>">
                <input type="hidden" name="task_id" id="taskId" value="0">
                <div class="col-md-4 mb-2"><label>عنوان کار</label><input class="form-control" name="title" id="taskTitle" maxlength="190" required></div>
                <div class="col-md-3 mb-2"><label>محول به</label><select class="form-control" name="assigned_to" id="taskAssignee"><option value="">بدون مسئول مشخص</option><?php foreach ($staff as $person): ?><option value="<?= htmlspecialchars($person['username']) ?>"><?= htmlspecialchars(trim($person['full_name']) . ' - ' . $person['username']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-2"><label>موعد انجام</label><input type="datetime-local" class="form-control" name="due_date" id="taskDueDate"></div>
                <div class="col-md-2 mb-2"><label>اولویت</label><select class="form-control" name="priority" id="taskPriority"><option value="normal">عادی</option><option value="high">فوری</option><option value="low">کم</option></select></div>
                <div class="col-md-10 mb-2"><label>توضیحات</label><textarea class="form-control" name="description" id="taskDescription" rows="2"></textarea></div>
                <div class="col-md-2 d-flex align-items-end mb-2"><button class="btn btn-primary btn-block" name="save_task" value="1" id="taskSaveButton">ثبت کار</button></div>
                <div class="col-12 d-none" id="cancelEditWrap"><button type="button" class="btn btn-sm btn-light" id="cancelTaskEdit">لغو ویرایش</button></div>
            </form>
        </div></div>

        <div class="card custom-card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">تقویم موعد کارها</h5><small class="text-muted">برای رفتن به کارت هر کار، روی رویداد کلیک کنید.</small></div>
            <div id="assignedTasksCalendar"></div>
        </div></div>

        <div class="card custom-card mb-3"><div class="card-body">
            <form method="get" class="row align-items-end">
                <div class="col-md-4 mb-2"><label>وضعیت</label><select class="form-control" name="status"><option value="">همه وضعیت‌ها</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                <div class="col-md-5 mb-2"><label>مسئول</label><select class="form-control" name="assigned_to"><option value="">همه همکاران</option><?php foreach ($staff as $person): ?><option value="<?= htmlspecialchars($person['username']) ?>" <?= $filterAssignee === $person['username'] ? 'selected' : '' ?>><?= htmlspecialchars(trim($person['full_name']) . ' - ' . $person['username']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-2"><button class="btn btn-outline-primary">اعمال فیلتر</button> <a class="btn btn-light" href="assigned-tasks.php">پاک‌کردن</a></div>
            </form>
        </div></div>

        <div class="task-board">
            <?php foreach ($rows as $row): ?>
                <?php $taskJson = htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>
                <div id="task-card-<?= (int)$row['id'] ?>" class="card custom-card task-card priority-<?= htmlspecialchars($row['priority']) ?> <?= $row['status'] === 'done' ? 'done' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between"><h5><?= htmlspecialchars($row['title']) ?></h5><span class="badge badge-<?= $row['priority'] === 'high' ? 'danger' : 'light' ?>"><?= $priorityLabels[$row['priority']] ?? '-' ?></span></div>
                        <p><?= nl2br(htmlspecialchars((string)$row['description'])) ?></p>
                        <div class="small text-muted mb-2">مسئول: <?= htmlspecialchars(trim((string)$row['assignee_name']) ?: 'تعیین‌نشده') ?></div>
                        <div class="small text-muted mb-2">ثبت‌کننده: <?= htmlspecialchars(trim((string)$row['creator_name']) ?: $row['created_by']) ?></div>
                        <div class="small text-muted mb-3">موعد: <?= $row['due_date'] ? htmlspecialchars(toJalali($row['due_date'], 'Y/m/d H:i', 'fa')) : 'بدون موعد' ?></div>
                        <form method="post" class="d-flex mb-2" style="gap:6px">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['assigned_tasks_csrf']) ?>">
                            <input type="hidden" name="task_id" value="<?= (int)$row['id'] ?>">
                            <select class="form-control form-control-sm" name="status"><?php foreach ($statusLabels as $key => $label): ?><option value="<?= $key ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm btn-outline-primary" name="change_status" value="1">ثبت وضعیت</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-warning edit-task" data-task="<?= $taskJson ?>">ویرایش</button>
                        <form method="post" class="d-inline" onsubmit="return confirm('این کار حذف شود؟')">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['assigned_tasks_csrf']) ?>">
                            <input type="hidden" name="task_id" value="<?= (int)$row['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" name="delete_task" value="1">حذف</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><div class="alert alert-light">کاری با فیلتر انتخاب‌شده یافت نشد.</div><?php endif; ?>
        </div>
    </div></div></div>
</div>
<?php include '../../footers.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('taskForm');
    const title = document.getElementById('taskFormTitle');
    const taskId = document.getElementById('taskId');
    const cancelWrap = document.getElementById('cancelEditWrap');

    function resetTaskForm() {
        form.reset();
        taskId.value = '0';
        title.textContent = 'ثبت کار جدید';
        document.getElementById('taskSaveButton').textContent = 'ثبت کار';
        cancelWrap.classList.add('d-none');
    }

    document.querySelectorAll('.edit-task').forEach(function (button) {
        button.addEventListener('click', function () {
            const task = JSON.parse(this.dataset.task);
            taskId.value = task.id;
            document.getElementById('taskTitle').value = task.title || '';
            document.getElementById('taskDescription').value = task.description || '';
            document.getElementById('taskAssignee').value = task.assigned_to || '';
            document.getElementById('taskPriority').value = task.priority || 'normal';
            document.getElementById('taskDueDate').value = task.due_date ? String(task.due_date).replace(' ', 'T').slice(0, 16) : '';
            title.textContent = 'ویرایش کار';
            document.getElementById('taskSaveButton').textContent = 'ذخیره ویرایش';
            cancelWrap.classList.remove('d-none');
            form.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    });
    document.getElementById('cancelTaskEdit').addEventListener('click', resetTaskForm);

    const calendarElement = document.getElementById('assignedTasksCalendar');
    if (calendarElement && window.FullCalendar) {
        const calendar = new FullCalendar.Calendar(calendarElement, {
            locale: 'fa',
            direction: 'rtl',
            initialView: 'dayGridMonth',
            height: 'auto',
            firstDay: 6,
            headerToolbar: {left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth'},
            buttonText: {today: 'امروز', month: 'ماه', list: 'فهرست'},
            noEventsText: 'کاری برای این بازه ثبت نشده است.',
            events: <?= json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            eventClick: function (info) {
                const card = document.getElementById('task-card-' + info.event.id);
                if (!card) return;
                document.querySelectorAll('.task-focus').forEach(function (item) { item.classList.remove('task-focus'); });
                card.classList.add('task-focus');
                card.scrollIntoView({behavior: 'smooth', block: 'center'});
                window.setTimeout(function () { card.classList.remove('task-focus'); }, 2500);
            },
            eventDidMount: function (info) {
                info.el.title = info.event.extendedProps.status + ' - ' + info.event.extendedProps.assignee;
            }
        });
        calendar.render();
    }
});
</script>
</body>
</html>
