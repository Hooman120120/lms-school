<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

// Calendar groups are the پایه‌ها: the columns of the weekly view and the stacked months.
$gw = 'پایه';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
// Admin ID directly from session – no extra DB query
$adminId = $_SESSION['admin']['idadmin'];
calendarSeedDefaultGrades($conn, (int)$adminId);

// Flash message container
$message = ['success' => false, 'text' => ''];

// ---------- POST: Add Group ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_group') {
    $title = trim($_POST['title'] ?? '');
    $color = $_POST['color'] ?? '#3788d8';
    $color2 = !empty($_POST['use_color2']) ? ($_POST['color2'] ?? null) : null;
    $description = $_POST['description'] ?? null;

    if (empty($title)) {
        $message = ['success' => false, 'text' => 'عنوان ' . $gw . ' اجباری است.'];
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO calendar_groups (title, color, color2, description, created_by, is_grade) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$title, $color, $color2, $description, $adminId]);
            $message = ['success' => true, 'text' => $gw . ' با موفقیت اضافه شد.'];
        } catch (Exception $e) {
            $message = ['success' => false, 'text' => 'خطا: ' . $e->getMessage()];
        }
    }
    $_SESSION['group_message'] = $message;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ---------- POST: Update Group ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_group') {
    $id = $_POST['group_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $color = $_POST['color'] ?? '#3788d8';
    $color2 = !empty($_POST['use_color2']) ? ($_POST['color2'] ?? null) : null;
    $description = $_POST['description'] ?? null;

    if (empty($title) || !$id) {
        $message = ['success' => false, 'text' => 'عنوان و شناسه ' . $gw . ' الزامی است.'];
    } else {
        try {
            $stmt = $conn->prepare("UPDATE calendar_groups SET title = ?, color = ?, color2 = ?, description = ?, updated_by = ? WHERE id = ? AND is_grade = 1");
            $stmt->execute([$title, $color, $color2, $description, $adminId, $id]);
            $message = ['success' => true, 'text' => $gw . ' با موفقیت ویرایش شد.'];
        } catch (Exception $e) {
            $message = ['success' => false, 'text' => 'خطا: ' . $e->getMessage()];
        }
    }
    $_SESSION['group_message'] = $message;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ---------- POST: Delete Group ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $id = $_POST['group_id'] ?? 0;
    if (!$id) {
        $message = ['success' => false, 'text' => 'شناسه ' . $gw . ' نامعتبر است.'];
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM calendar_groups WHERE id = ? AND is_grade = 1");
            $stmt->execute([$id]);
            $message = ['success' => true, 'text' => $gw . ' با موفقیت حذف شد.'];
        } catch (Exception $e) {
            $message = ['success' => false, 'text' => 'خطا: ' . $e->getMessage()];
        }
    }
    $_SESSION['group_message'] = $message;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve flash message after redirect
if (isset($_SESSION['group_message'])) {
    $message = $_SESSION['group_message'];
    unset($_SESSION['group_message']);
}

// Fetch all groups
$groups = $conn->query("SELECT id, title, color, color2, description, created_at, updated_at FROM calendar_groups WHERE is_grade = 1 ORDER BY " . calendarGroupsOrderSql())->fetchAll(PDO::FETCH_ASSOC);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>مدیریت <?= $gw ?>‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .color-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 6px;
            vertical-align: middle;
        }
        .form-control-color {
            width: 60px;
            padding: 5px;
        }
    </style>
</head>
<body class="main-body  leftmenu">
<div class="page">

    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-4">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">مدیریت <?= $gw ?>‌ها</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="resetForm()">
                    <i class="fa fa-plus"></i> افزودن <?= $gw ?> جدید
                </button>
            </div>

            <?php if (!empty($message['text'])): ?>
                <div class="alert alert-<?= $message['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example1" class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                            <tr>
                                <th style="width:5%">#</th>
                                <th style="width:25%">عنوان <?= $gw ?></th>
                                <th style="width:15%">رنگ</th>
                                <th style="width:30%">توضیحات</th>
                                <th style="width:25%">عملیات</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($groups)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">هیچ <?= $gw ?>ی وجود ندارد.</td>
                                </tr>
                            <?php else: foreach ($groups as $idx => $group): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="color-dot" style="background: <?= htmlspecialchars(calendarGradeBackground($group['color'], $group['color2'] ?? null)) ?>;"></span>
                                        <?= htmlspecialchars($group['title']) ?>
                                    </td>
                                    <td>
                                            <span class="badge" style="background:<?= htmlspecialchars(calendarGradeBackground($group['color'], $group['color2'] ?? null)) ?>; color:#fff; padding:4px 10px;">
                                                <?= htmlspecialchars($group['color']) ?><?= !empty($group['color2']) ? ' → ' . htmlspecialchars($group['color2']) : '' ?>
                                            </span>
                                    </td>
                                    <td><?= nl2br(htmlspecialchars($group['description'] ?? '—')) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                                onclick="editGroup(<?= $group['id'] ?>, '<?= htmlspecialchars($group['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($group['color']) ?>', '<?= htmlspecialchars($group['description'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($group['color2'] ?? '') ?>')"
                                                data-bs-toggle="modal" data-bs-target="#groupModal">
                                            <i class="fa fa-edit"></i> ویرایش
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('آیا از حذف این <?= $gw ?> اطمینان دارید؟');">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fa fa-trash"></i> حذف
                                            </button>
                                        </form>
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

    <!-- Add / Edit Group Modal -->
    <div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="groupForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupModalLabel">افزودن <?= $gw ?> جدید</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add_group">
                        <input type="hidden" name="group_id" id="editGroupId" value="">
                        <div class="mb-3">
                            <label for="title" class="form-label">عنوان *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="color" class="form-label">رنگ</label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#3788d8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-flex align-items-center gap-2" for="useColor2">
                                <input type="checkbox" id="useColor2" name="use_color2" value="1"> رنگ دوم (طیف از رنگ اول به رنگ دوم)
                            </label>
                            <input type="color" class="form-control form-control-color" id="color2" name="color2" value="#128710">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">توضیحات</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

<script>
    function resetForm() {
        document.getElementById('groupForm').reset();
        document.getElementById('formAction').value = 'add_group';
        document.getElementById('editGroupId').value = '';
        document.getElementById('groupModalLabel').innerText = 'افزودن <?= $gw ?> جدید';
    }

    function editGroup(id, title, color, description, color2) {
        document.getElementById('useColor2').checked = !!color2;
        document.getElementById('color2').value = color2 || '#128710';
        document.getElementById('formAction').value = 'update_group';
        document.getElementById('editGroupId').value = id;
        document.getElementById('title').value = title;
        document.getElementById('color').value = color;
        document.getElementById('description').value = description;
        document.getElementById('groupModalLabel').innerText = 'ویرایش <?= $gw ?>';
    }
</script>

</body>
</html>