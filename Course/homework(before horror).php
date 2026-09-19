<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';

// جلوگیری از آی‌پی‌های مسدودشده
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}

// بررسی ورود کاربر
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}

// بررسی صحت نشست
if ($_SESSION['auth']['ip'] != GetIp() && $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

$student_username = $_SESSION['auth']['user'];

// دریافت لیست تمام تکالیف فعال
$stmt = $conn->prepare("SELECT * FROM homework_admin WHERE codecourse = ? ORDER BY deadline DESC");
$stmt->execute([$_GET['code']]);
$homeworks = $stmt->fetchAll(PDO::FETCH_OBJ);

// ارسال تکلیف دانشجو
if (isset($_POST['submit_homework'])) {
    $homework_id = intval($_POST['homework_id']);
    $submission_text = trim($_POST['submission_text']);

    // مسیر ذخیره فایل
    $upload_dir = "../uploads-student/homeworks/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = "";
    if (!empty($_FILES['file']['name'])) {
        $file_name = time() . "_" . basename($_FILES["file"]["name"]);
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_path)) {
            $file_path = "uploads/homeworks/" . $file_name;
        }
    }

    // بررسی اینکه قبلاً ارسال کرده یا نه
    $check = $conn->prepare("SELECT * FROM homework_student WHERE homework_id = ? AND student_username = ?");
    $check->execute([$homework_id, $student_username]);

    if ($check->rowCount() > 0) {
        // آپدیت ارسال قبلی
        $stmt = $conn->prepare("UPDATE homework_student SET submission_text = ?, file_path = ?, updated_at = NOW() WHERE homework_id = ? AND student_username = ?");
        $stmt->execute([$submission_text, $file_path, $homework_id, $student_username]);
        $msg = "✅ تکلیف شما با موفقیت ویرایش شد.";
    } else {
        // ثبت ارسال جدید
        $stmt = $conn->prepare("INSERT INTO homework_student (homework_id, student_username, submission_text, file_path, submitted_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$homework_id, $student_username, $submission_text, $file_path]);
        $msg = "✅ تکلیف شما با موفقیت ارسال شد.";
    }
}

include_once '../temp.html';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تکالیف دانشجو</title>
    <?php include_once '../inc/link.php'; ?>
</head>

<body class="main-body leftmenu">
    <div id="global-loader">
        <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
    </div>

    <div class="page">
        <?php
        include_once '../menu.php';
        include_once '../header.php';
        ?>

        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">

                    <?php if (isset($msg)): ?>
                        <div class="alert alert-info text-center"><?= $msg ?></div>
                    <?php endif; ?>

                    <div class="row row-sm">
                        <div class="col-xl-12">
                            <div class="card custom-card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">لیست تکالیف تعریف‌شده</h5>
                                </div>
                                <div class="card-body table-responsive">
                                    <table class="table table-bordered text-center">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>عنوان</th>
                                                <th>توضیحات</th>
                                                <th>فایل ادمین</th>
                                                <th>مهلت ارسال</th>
                                                <th>ارسال تکلیف</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($homeworks) > 0): ?>
                                                <?php foreach ($homeworks as $index => $hw): ?>
                                                    <?php
                                                    // بررسی ارسال قبلی دانشجو
                                                    $stmt = $conn->prepare("SELECT * FROM homework_student WHERE homework_id = ? AND student_username = ?");
                                                    $stmt->execute([$hw->id, $student_username]);
                                                    $submission = $stmt->fetch(PDO::FETCH_OBJ);
                                                    ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($hw->title) ?></td>
                                                        <td><?= htmlspecialchars($hw->description) ?></td>
                                                        <td>
                                                            <?php if (!empty($hw->file_path)): ?>
                                                                <a href="../<?= htmlspecialchars($hw->file_path) ?>" target="_blank" class="btn btn-sm btn-primary">دانلود</a>
                                                            <?php else: ?>
                                                                ندارد
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php
                                                            $date = explode(" ", $hw->deadline)[0];
                                                            $time = explode(" ", $hw->deadline)[1];
                                                            [$gy, $gm, $gd] = explode("-", $date);
                                                            $jDate = gregorian_to_jalali($gy, $gm, $gd);
                                                            $jDate = implode('/', $jDate);
                                                            echo $jDate . ' ' . $time;
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php $now = date('Y-m-d H:i:s');
                                                            if (strtotime($hw->deadline) >= strtotime($now)) { ?>
                                                                <button class="btn btn-sm btn-success" data-bs-toggle="collapse"
                                                                    data-bs-target="#submitForm<?= $hw->id ?>">ارسال / ویرایش
                                                                </button>
                                                            <?php } else { ?>
                                                                <p>مهلت ارسال تمام شده است</p>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                    <tr class="collapse" id="submitForm<?= $hw->id ?>">
                                                        <td colspan="6">
                                                            <form method="post" enctype="multipart/form-data">
                                                                <input type="hidden" name="homework_id" value="<?= $hw->id ?>">
                                                                <div class="mb-2">
                                                                    <label>توضیحات شما:</label>
                                                                    <input type="text" name="submission_text" class="form-control"
                                                                        value="<?= htmlspecialchars($submission->submission_text ?? '') ?>">
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label>فایل پاسخ (PDF یا ZIP):</label>
                                                                    <input type="file" name="file" class="form-control">
                                                                    <?php if (!empty($submission->file_path)): ?>
                                                                        <small>فایل فعلی: <a href="../<?= htmlspecialchars($submission->file_path) ?>" target="_blank">دانلود</a></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <button class="btn btn-primary mt-2" name="submit_homework">ارسال تکلیف</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6">هیچ تکلیفی تعریف نشده است.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php include '../footers.php'; ?>
    </div>
</body>

</html>