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

// جلوگیری از Session Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// بررسی تک سشن بودن
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// بررسی سطح دسترسی

$currentUser = $_SESSION['admin']['user'] ?? '';
$error_msg = '';

$stmtAdmins = $conn->prepare("SELECT username, fname, lname FROM dbadmin WHERE username != ? ORDER BY fname, lname");
$stmtAdmins->execute([$currentUser]);
$colleagues = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

// پردازش فرم ایجاد اطلاعیه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    $title       = trim($_POST['title'] ?? '');
    $type        = trim($_POST['type'] ?? 'announcement');
    $targetUsers = $_POST['target_users'] ?? [];
    if (!is_array($targetUsers)) {
        $targetUsers = [$targetUsers];
    }
    $targetUsers = array_values(array_unique(array_filter(array_map(
        static fn($username): string => trim((string)$username),
        $targetUsers
    ))));

    $content     = trim($_POST['content'] ?? '');
    $created_by  = $currentUser;
    $published_at = date('Y-m-d H:i:s');

    // آپلود فایل پیوست در صورت وجود
    $attachment = '';
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $maxAttachmentSize = 4 * 1024 * 1024;
        $uploadedFile = $_FILES['attachment'];
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK || (int)$uploadedFile['size'] > $maxAttachmentSize) {
            $error_msg = 'حجم فایل نباید بیشتر از ۴ مگابایت باشد.';
        } else {
            $ext = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $file_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_dir = '../../uploads/announcements/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (move_uploaded_file($uploadedFile['tmp_name'], $upload_dir . $file_name)) {
                $attachment = 'uploads/announcements/' . $file_name;
            } else {
                $error_msg = 'فایل پیوست بارگذاری نشد؛ دوباره تلاش کنید.';
            }
        }
    }

    if ($error_msg !== '') {
    } elseif (empty($title) || empty($content)) {
        $error_msg = 'لطفاً عنوان و متن اطلاعیه یا بخشنامه را وارد کنید.';
    } elseif ($targetUsers === []) {
        $error_msg = 'لطفاً حداقل یک همکار را انتخاب کنید.';
    } elseif (in_array($currentUser, $targetUsers, true)) {
        $error_msg = 'نمی‌توانید خودتان را به‌عنوان مخاطب انتخاب کنید.';
    } else {
        $stmt = $conn->prepare("INSERT INTO school_notifications
            (event_type, title, body, actor_username, recipient_username, attachment,
             target_type, target_value, status, source_type, created_at, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', 'school_announcement', NOW(), ?)");

        $conn->beginTransaction();
        try {
            foreach ($targetUsers as $recipient) {
                $stmt->execute([$type, $title, $content, $created_by, $recipient, $attachment, 'user', '', $published_at]);
            }
            $conn->commit();
            $_SESSION['announcement_notice'] = [
                'type' => 'success',
                'message' => ($type === 'circular' ? 'بخشنامه' : 'اطلاعیه') . ' با موفقیت برای ' . count($targetUsers) . ' همکار ثبت شد.'
            ];
            header('Location: announcements.php');
            exit;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error_msg = 'خطا در ذخیره اطلاعیه یا بخشنامه.';
        }
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
    <meta name="description" content="ایجاد اطلاعیه یا بخشنامه جدید">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>ایجاد اطلاعیه جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">

<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->

<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- سربرگ صفحه -->
                <div class="page-header my-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">ایجاد اطلاعیه / بخشنامه جدید</h2>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">مستندات</a></li>
                            <li class="breadcrumb-item"><a href="announcements.php">اطلاعیه‌ها و بخشنامه‌ها</a></li>
                            <li class="breadcrumb-item active" aria-current="page">ایجاد جدید</li>
                        </ol>
                    </div>
                    <div>
                        <a href="announcements.php" class="btn btn-secondary ripple">
                            <i class="fe fe-arrow-right me-1"></i> بازگشت به لیست
                        </a>
                    </div>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $error_msg ?>
                        <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- فرم ثبت اطلاعیه -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0 pb-0">
                                <h6 class="main-content-label mb-1">مشخصات اطلاعیه یا بخشنامه</h6>
                                <p class="text-muted card-sub-title">اطلاعات را وارد نموده و سپس روی دکمه ذخیره کلیک کنید.</p>
                            </div>
                            <div class="card-body">
                                <form action="" method="post" enctype="multipart/form-data">
                                    <div class="row row-sm">
                                        
                                        <!-- عنوان -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label font-weight-bold">عنوان: <span class="text-danger">*</span></label>
                                            <input type="text" name="title" class="form-control" placeholder="عنوان اطلاعیه یا بخشنامه..." required>
                                        </div>

                                        <!-- نوع -->
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label font-weight-bold">نوع مدرک:</label>
                                            <select name="type" class="form-control">
                                                <option value="announcement">اطلاعیه</option>
                                                <option value="circular">بخشنامه</option>
                                            </select>
                                        </div>

                                        <!-- گیرنده‌ها از فهرست همکاران خوانده می‌شوند -->
                                        <div class="col-md-6 mb-3" id="target_users_group">
                                            <label class="form-label font-weight-bold">گیرنده (همکاران):</label>
                                            <select name="target_users[]" id="target_users" class="form-control select2" multiple required>
                                                <?php foreach ($colleagues as $col):
                                                    $colName = trim(($col['fname'] ?? '') . ' ' . ($col['lname'] ?? ''));
                                                    if ($colName === '') $colName = $col['username'];
                                                ?>
                                                    <option value="<?= htmlspecialchars($col['username'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($colName) ?> (<?= htmlspecialchars($col['username']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">یک یا چند همکار را انتخاب کنید.</small>
                                        </div>

                                        <!-- فایل پیوست -->
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label font-weight-bold">فایل پیوست (PDF / تصویر):</label>
                                            <input type="file" id="announcementAttachment" name="attachment" class="form-control">
                                            <div id="announcementUploadNotice" class="upload-notice" role="status">حجم فایل نباید بیشتر از ۴ مگابایت باشد.</div>
                                        </div>

                                        <!-- متن کامل -->
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label font-weight-bold">متن اطلاعیه / بخشنامه: <span class="text-danger">*</span></label>
                                            <textarea name="content" class="form-control" rows="6" placeholder="متن کامل را اینجا بنویسید..." required></textarea>
                                        </div>

                                        <!-- دکمه‌های عملیاتی -->
                                        <div class="col-md-12 d-flex justify-content-end" style="gap:10px;">
                                            <a href="announcements.php" class="btn btn-light ripple">انصراف</a>
                                            <button type="submit" name="save_announcement" class="btn btn-primary ripple px-4">
                                                <i class="fe fe-check-circle me-1"></i> ذخیره و ثبت
                                            </button>
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->
</div>

<style>
    .upload-notice {display:none;margin-top:8px;padding:9px 12px;border-right:3px solid #dc3545;border-radius:6px;background:#fff1f2;color:#842029;font-size:13px;}
</style>
<script>
document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function (event) {
    const input = document.getElementById('announcementAttachment');
    const notice = document.getElementById('announcementUploadNotice');
    if (input?.files[0] && input.files[0].size > 4 * 1024 * 1024) {
        event.preventDefault();
        notice.style.display = 'block';
        input.value = '';
    }
});
</script>
</body>
</html>
