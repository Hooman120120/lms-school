<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    exit;
}
$currentUser = (string)($_SESSION['admin']['user'] ?? '');

if (empty($_SESSION['special_letter_csrf'])) {
    $_SESSION['special_letter_csrf'] = bin2hex(random_bytes(24));
}

$successMessage = '';
$errorMessage = '';

// مدیریت ارسال نامه
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminRequire('documents.special-letters.send');
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['special_letter_csrf'], $token)) {
        $errorMessage = 'خطای امنیتی (CSRF Token نامعتبر). لطفا دوباره تلاش کنید.';
    } else {
        $targetUsernames = $_POST['target_usernames'] ?? [];
        if (!is_array($targetUsernames)) {
            $targetUsernames = [$targetUsernames];
        }
        $targetUsernames = array_values(array_unique(array_filter(array_map(
            static fn($username): string => trim((string)$username),
            $targetUsernames
        ))));
        $subject = trim($_POST['subject'] ?? '');
        $priority = trim($_POST['priority'] ?? 'عادی');
        $message = trim($_POST['message'] ?? '');
        
        if ($targetUsernames === [] || $subject === '' || $message === '') {
            $errorMessage = 'لطفا حداقل یک گیرنده، موضوع و متن نامه را پر کنید.';
        } elseif (in_array($currentUser, $targetUsernames, true)) {
            $errorMessage = 'نمی‌توانید به خودتان نامه اختصاصی ارسال کنید!';
        } else {
            // مدیریت فایل پیوست (اختیاری)
            $attachmentPath = '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                $maxAttachmentSize = 4 * 1024 * 1024;
                $uploadedFile = $_FILES['attachment'];
                if ($uploadedFile['error'] !== UPLOAD_ERR_OK || (int)$uploadedFile['size'] > $maxAttachmentSize) {
                    $errorMessage = 'حجم فایل نباید بیشتر از ۴ مگابایت باشد.';
                } else {
                    $fileTmp = $uploadedFile['tmp_name'];
                    $fileName = time() . '_' . basename($uploadedFile['name']);
                    $uploadDir = '../../uploads/special_letters/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $destination = $uploadDir . $fileName;
                    if (move_uploaded_file($fileTmp, $destination)) {
                        $attachmentPath = 'uploads/special_letters/' . $fileName;
                    } else {
                        $errorMessage = 'فایل پیوست بارگذاری نشد؛ دوباره تلاش کنید.';
                    }
                }
            }

            if ($errorMessage !== '') {
                $stmt = null;
            } else {
                $stmt = $conn->prepare("INSERT INTO school_notifications
                (event_type, title, body, actor_username, recipient_username, attachment, priority,
                 target_type, status, source_type, created_at, published_at)
                VALUES ('special_letter', ?, ?, ?, ?, ?, ?, 'user', 'published', 'special_letter', NOW(), NOW())");

                $conn->beginTransaction();
                try {
                    foreach ($targetUsernames as $targetUsername) {
                        $stmt->execute([$subject, $message, $currentUser, $targetUsername, $attachmentPath, $priority]);
                    }
                    $conn->commit();
                    $_SESSION['letter_success'] = 'نامه اختصاصی برای ' . count($targetUsernames) . ' همکار ارسال شد.';
                    header('Location: ' . url('admin/Doc/special-letters.php'));
                    exit;
                } catch (Throwable $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $errorMessage = 'خطا در ثبت نامه در پایگاه داده.';
                }
            }
        }
    }
}

if (isset($_SESSION['letter_success'])) {
    $successMessage = $_SESSION['letter_success'];
    unset($_SESSION['letter_success']);
}

// دریافت لیست همکاران از dbadmin به جز کاربر جاری
$stmtAdmins = $conn->prepare("SELECT username, fname, lname FROM dbadmin WHERE username != ?");
$stmtAdmins->execute([$currentUser]);
$colleagues = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ارسال نامه اختصاصی به همکار | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body pt-4">
                <div class="page-header">
                    <h2 class="main-content-title tx-24 mg-b-5">ارسال نامه اختصاصی به همکار</h2>
                </div>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                <?php endif; ?>

                <div class="card custom-card">
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['special_letter_csrf']) ?>">
                            
                            <div class="form-group">
                                <label>انتخاب همکاران (گیرنده): <span class="text-danger">*</span></label>
                                <select name="target_usernames[]" class="form-control select2" multiple required>
                                    <?php foreach ($colleagues as $col): 
                                        $colName = trim(($col['fname'] ?? '') . ' ' . ($col['lname'] ?? ''));
                                        if ($colName === '') $colName = $col['username'];
                                    ?>
                                        <option value="<?= htmlspecialchars($col['username'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($colName) ?> (<?= htmlspecialchars($col['username']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">با نگه‌داشتن Ctrl یا انتخاب چند گزینه، نامه برای چند همکار ارسال می‌شود.</small>
                            </div>

                            <div class="form-group">
                                <label>موضوع نامه: <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" placeholder="عنوان نامه یا موضوع پیام..." required>
                            </div>

                            <div class="form-group">
                                <label>اولویت نامه:</label>
                                <select name="priority" class="form-control">
                                    <option value="عادی">عادی</option>
                                    <option value="مهم">مهم</option>
                                    <option value="فوری / محرمانه">فوری / محرمانه</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>متن نامه / پیام: <span class="text-danger">*</span></label>
                                <textarea name="message" rows="5" class="form-control" placeholder="متن کامل نامه را اینجا بنویسید..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>فایل پیوست (اختیاری):</label>
                                <input type="file" id="specialLetterAttachment" name="attachment" class="form-control-file">
                                <div id="specialLetterUploadNotice" class="upload-notice" role="status">حجم فایل نباید بیشتر از ۴ مگابایت باشد.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">ارسال نامه اختصاصی</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../footers.php'; ?>
<style>
    .upload-notice {display:none;margin-top:8px;padding:9px 12px;border-right:3px solid #dc3545;border-radius:6px;background:#fff1f2;color:#842029;font-size:13px;}
</style>
<script>
document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function (event) {
    const input = document.getElementById('specialLetterAttachment');
    const notice = document.getElementById('specialLetterUploadNotice');
    if (input?.files[0] && input.files[0].size > 4 * 1024 * 1024) {
        event.preventDefault();
        notice.style.display = 'block';
        input.value = '';
    }
});
</script>
</body>
</html>
