<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

if (isset($_POST['upload'])) {
    $groupId = $_POST['group_id'];
    $description = htmlspecialchars($_POST['description']);
    $file = $_FILES['group_file'];

    // 1. Basic Validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("خطا در بارگذاری فایل.");
    }

    // 2. Extension Validation
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);

    if (!in_array($extension, $allowedExtensions)) {
        die("پسوند فایل مجاز نیست. (مجاز: PDF, JPG, PNG)");
    }

    // 3. Create Unique Name & Path
    // Make sure this folder exists and is writable (chmod 777)
    $uploadDir = '../assets/group_files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    // 4. Move File and Save to DB
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        try {
            date_default_timezone_set('Asia/Tehran');
            $sql = "INSERT INTO handhug_uploads (group_id, file_path, description, useradder, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $groupId,
                $destination,
                $description,
                $_SESSION['auth']['user'],
                'pending',
                date("Y-m-d H:i:s")
            ]);
            $_SESSION['upload_success'] = 'فایل موردنظر بارگزاری شد و منتظر تایید پشتیبانی می باشد';
            // Redirect to refresh the count
            header("Location: " . $_SERVER['PHP_SELF'] . "?group=" . $groupId . "&success=1");
            exit;
        } catch (PDOException $e) {
            die("خطا در دیتابیس: " . $e->getMessage());
        }
    } else {
        die("خطا در انتقال فایل به پوشه مقصد.");
    }
}

//* End Check Session JUST ONE Session
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> ارسال و دریافت فایل | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">

        <div class="col-12 mb-3">
            <div class="card h-100">
                <div class="card-body d-flex flex-column mt-5">
                    <?php if (isset($_SESSION['upload_success'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['upload_success'] ?>
                        </div>
                        <?php unset($_SESSION['upload_success']);
                    } ?>
                    <!-- Top -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center"
                             style="width:50px;height:50px;">
                            <i class="fa fa-file-upload"></i>
                        </div>
                        <div class="ml-3">
                            <strong>ارسال و دریافت فایل</strong>
                            <div class="text-muted small">مدیریت فایل‌های ارسالی گروه</div>
                        </div>
                    </div>
                    <?php
                    $sql = 'SELECT COUNT(*) AS count FROM handhug_uploads WHERE group_id = ? AND status = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_GET['group'], 'accepted']);
                    $countFiles = $stmt->fetch(PDO::FETCH_OBJ);
                    ?>
                    <!-- Stats / Progress (اختیاری) -->
                    <div class="mb-3">
                        <small class="text-muted">فایل‌های ارسال‌شده: <?= $countFiles->count ?></small>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-info" style="width:60%"></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-auto d-flex flex-column flex-sm-row justify-content-center justify-content-sm-between">
                        <button class="btn btn-sm btn-outline-primary mb-2 mb-sm-0"
                                data-bs-toggle="modal"
                                data-bs-target="#uploadFileModal">
                            آپلود فایل
                        </button>

                        <button class="btn btn-sm btn-outline-secondary ml-sm-2"
                                data-bs-toggle="modal"
                                data-bs-target="#filesListModal">
                            مشاهده فایل‌ها
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Upload File Modal -->
        <div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header">
                        <h6 class="modal-title">آپلود فایل جدید</h6>
                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <!-- Form -->
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label small">(*پسوند های مجاز : .pdf,.jpg,)انتخاب فایل</label>
                                <input type="file"
                                       name="group_file"
                                       class="form-control form-control-sm"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small">توضیح (اجباری)</label>
                                <input type="text"
                                       name="description"
                                       class="form-control form-control-sm"
                                       placeholder="مثلاً جزوه جلسه سوم">
                            </div>

                            <input type="hidden" name="group_id" value="<?= $_GET['group'] ?>">

                        </div>

                        <!-- Footer -->
                        <div class="modal-footer justify-content-center">
                            <button type="submit" name="upload" class="btn btn-sm btn-info">
                                آپلود
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-light"
                                    data-bs-dismiss="modal">
                                انصراف
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>


        <!-- Files List Modal -->
        <div class="modal fade" id="filesListModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">

                    <!-- Header -->
                    <div class="modal-header">
                        <h6 class="modal-title">فایل‌های گروه</h6>
                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <!-- Body -->
                    <div class="modal-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php
                            $sqlFiles = 'SELECT * FROM handhug_uploads WHERE group_id = ? AND status = ? ORDER BY id DESC';
                            $stmtFiles = $conn->prepare($sqlFiles);
                            $stmtFiles->execute([$_GET['group'], 'accepted']);
                            $files = $stmtFiles->fetchAll(PDO::FETCH_OBJ);

                            if ($files):
                                foreach ($files as $f): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fa fa-file text-info me-1"></i>
                                            <?= htmlspecialchars($f->description) ?>
                                            <div class="small text-muted">
                                                <?php
                                                $user = retPerson($f->useradder);
                                                ?>
                                                توسط: <?= $user->fname . ' ' . $user->lname ?>
                                            </div>
                                        </div>
                                        <a href="<?= $f->file_path ?>" download class="btn btn-sm btn-outline-success">
                                            دانلود
                                        </a>
                                    </li>
                                <?php endforeach;
                            else: ?>
                                <li class="list-group-item text-center text-muted">فایلی وجود ندارد</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer justify-content-center">
                        <button type="button"
                                class="btn btn-sm btn-light"
                                data-bs-dismiss="modal">
                            بستن
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php include '../footers.php'; ?>
    <!--End Footer-->
</body>

</html>