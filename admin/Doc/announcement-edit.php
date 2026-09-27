<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

// بررسی IP در Blacklist
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}

// بررسی لاگین بودن ادمین
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}

// بررسی دسترسی
if (!Permision('permision_viewdocument')) {
    header('location: ' . url('admin/index.php'));
    die();
}

$currentUser = (string)($_SESSION['admin']['user'] ?? '');
$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$canManageAllAnnouncements = false;
$announcementOwnerClause = ' AND (recipient_username = ? OR actor_username = ?)';

// دریافت شناسه اطلاعیه
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: announcements.php');
    exit;
}

// دریافت اطلاعات اطلاعیه از دیتابیس
$stmt = $conn->prepare("SELECT n.*, n.event_type AS type, n.body AS content
    FROM school_notifications n
    WHERE n.id = ? AND n.event_type IN ('announcement', 'circular'){$announcementOwnerClause} LIMIT 1");
$selectParams = [$id];
$selectParams[] = $currentUser;
$selectParams[] = $currentUser;
$stmt->execute($selectParams);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {
    header('Location: announcements.php');
    exit;
}

$errors = [];
$success = '';

// پردازش فرم ویرایش
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $target_type = (string)$announcement['target_type'];
    $target_value = $announcement['target_value'];
    $status      = trim($_POST['status'] ?? '');

    if (empty($title)) {
        $errors[] = 'عنوان اطلاعیه نمی‌تواند خالی باشد.';
    } elseif ($content === '') {
        $errors[] = 'متن اطلاعیه نمی‌تواند خالی باشد.';
    } elseif (!in_array($type, ['announcement', 'circular'], true)) {
        $errors[] = 'نوع اطلاعیه نامعتبر است.';
    } elseif (!in_array($status, ['draft', 'published'], true)) {
        $errors[] = 'وضعیت اطلاعیه نامعتبر است.';
    }

    if (empty($errors)) {
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $update_stmt = $conn->prepare("UPDATE school_notifications
            SET title = ?, event_type = ?, body = ?, target_type = ?, target_value = ?,
                status = ?, published_at = ?
            WHERE id = ? AND event_type IN ('announcement', 'circular'){$announcementOwnerClause}");
        $updateParams = [$title, $type, $content, $target_type, $target_value, $status, $publishedAt, $id];
        $updateParams[] = $currentUser;
        $updateParams[] = $currentUser;
        
        try {
            $updated = $update_stmt->execute($updateParams);
        } catch (Throwable $e) {
            error_log('Announcement edit failed: ' . $e->getMessage());
            $updated = false;
        }

        if ($updated) {
            $success = 'اطلاعیه با موفقیت ویرایش شد.';
            // به‌روزرسانی آرایه برای نمایش مقادیر جدید در فرم
            $stmt = $conn->prepare("SELECT n.*, n.event_type AS type, n.body AS content
                FROM school_notifications n
                WHERE n.id = ? AND n.event_type IN ('announcement', 'circular'){$announcementOwnerClause} LIMIT 1");
            $refreshParams = [$id];
            $refreshParams[] = $currentUser;
            $refreshParams[] = $currentUser;
            $stmt->execute($refreshParams);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $errors[] = 'خطا در ویرایش اطلاعات. لطفاً دوباره تلاش کنید.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>ویرایش اطلاعیه | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                
                <div class="page-header my-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">ویرایش اطلاعیه / بخشنامه</h2>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="announcements.php">اطلاعیه‌ها و بخشنامه‌ها</a></li>
                            <li class="breadcrumb-item active" aria-current="page">ویرایش</li>
                        </ol>
                    </div>
                    <div>
                        <a href="announcements.php" class="btn btn-secondary ripple">
                            <i class="fe fe-arrow-right me-1"></i> بازگشت به لیست
                        </a>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label font-weight-bold">عنوان:</label>
                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label font-weight-bold">نوع:</label>
                                            <select name="type" class="form-control">
                                                <option value="announcement" <?= $announcement['type'] === 'announcement' ? 'selected' : '' ?>>اطلاعیه</option>
                                                <option value="circular" <?= $announcement['type'] === 'circular' ? 'selected' : '' ?>>بخشنامه</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label font-weight-bold">وضعیت:</label>
                                            <select name="status" class="form-control">
                                                <option value="published" <?= $announcement['status'] === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                                                <option value="draft" <?= $announcement['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label font-weight-bold">گیرنده:</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($announcement['recipient_username'] ?? 'همه', ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label font-weight-bold">متن اطلاعیه:</label>
                                            <textarea name="content" class="form-control" rows="5"><?= htmlspecialchars($announcement['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary ripple">ذخیره تغییرات</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
