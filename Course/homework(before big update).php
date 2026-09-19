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
$stmt = $conn->prepare("
    SELECT *
    FROM homework_admin
    WHERE codecourse = ?
      AND status = 'approved'
    ORDER BY deadline DESC
");
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
            $file_path = "uploads-student/homeworks/" . $file_name;
        }
    }

    // بررسی اینکه قبلاً ارسال کرده یا نه
    $check = $conn->prepare("SELECT * FROM homework_student WHERE homework_id = ? AND student_username = ?");
    $check->execute([$homework_id, $student_username]);

    $msg = "✅ تکلیف شما با موفقیت ارسال شد.";

    $_SESSION['msg'] = $msg;

    $redirect = strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query([
            'code' => $_GET['code'] ?? ''
        ]);
    header('Location: ' . $redirect);
    exit;
}

function deadline_jalali_date_only($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '—';

    // Extract Y-M-D safely from beginning of string
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];

        // If year looks like Jalali (mis-saved), show it directly (no conversion)
        if ($y >= 1200 && $y <= 1600) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }

        // Normal case: Gregorian -> Jalali
        if (function_exists('gregorian_to_jalali')) {
            [$jy, $jm, $jd] = gregorian_to_jalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    // Fallback parse
    $ts = strtotime($raw);
    if ($ts === false) return '—';

    $gy = (int)date('Y', $ts);
    $gm = (int)date('m', $ts);
    $gd = (int)date('d', $ts);

    if (function_exists('gregorian_to_jalali')) {
        [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    return date('Y-m-d', $ts);
}

function deadline_to_ts($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return false;

    // Parse: YYYY-MM-DD [HH:MM[:SS]]
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $m)) {
        $y  = (int)$m[1];
        $mo = (int)$m[2];
        $d  = (int)$m[3];
        $hh = isset($m[4]) ? (int)$m[4] : 0;
        $ii = isset($m[5]) ? (int)$m[5] : 0;
        $ss = isset($m[6]) ? (int)$m[6] : 0;

        // If year looks like Jalali but stored as YYYY-MM-DD, convert to Gregorian for comparing
        if ($y >= 1200 && $y <= 1600 && function_exists('jalali_to_gregorian')) {
            [$gy, $gm, $gd] = jalali_to_gregorian($y, $mo, $d);
            return strtotime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $hh, $ii, $ss));
        }

        return strtotime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $hh, $ii, $ss));
    }

    // fallback
    return strtotime($raw);
}


include_once '../temp.html';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تکالیف دانش آموز</title>
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
                                            <th>جواب تکلیف</th>
                                            <th>ارسال تکلیف</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($homeworks) > 0): ?>
                                                <?php foreach ($homeworks as $index => $hw): ?>
                                                    <?php
                                                    // بررسی ارسال قبلی دانشجو
                                                    $stmt = $conn->prepare("   SELECT *
                                                                                        FROM homework_admin
                                                                                        WHERE codecourse = ?
                                                                                          AND status = 'approved'
                                                                                        ORDER BY deadline DESC");
                                                    $stmt->execute([$hw->id, $student_username]);
                                                    $submission = $stmt->fetch(PDO::FETCH_OBJ);
                                                    $hasSubmission = ($submission && (
                                                            !empty($submission->submission_text) ||
                                                            !empty($submission->file_path) ||
                                                            !empty($submission->submitted_at) ||
                                                            !empty($submission->updated_at)
                                                        ));
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
                                                        <td>
                                                            <?php
                                                            $out = deadline_jalali_date_only($hw->deadline);
                                                            echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out) . '</span>';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $ans = $submission->professors_answer ?? '';
                                                            echo ($ans !== '')
                                                                ? htmlspecialchars($ans, ENT_QUOTES, 'UTF-8')
                                                                : '—';
                                                            ?>
                                                        </td>

                                                        <td>
                                                            <?php if ($hasSubmission): ?>
                                                                تکلیف شما ارسال شده
                                                                <?php if (!empty($submission->file_path)): ?>
                                                                    (<?= htmlspecialchars(basename($submission->file_path), ENT_QUOTES, 'UTF-8') ?>)
                                                                <?php endif; ?>
                                                            <?php endif; ?>

                                                            <?php
                                                            $deadlineTs = deadline_to_ts($hw->deadline);

                                                            if ($deadlineTs !== false && $deadlineTs >= time()) { ?>
                                                                <button type="button"
                                                                        class="btn btn-sm btn-success"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#submitHomeworkModal"
                                                                        data-hw-id="<?= (int)$hw->id ?>"
                                                                        data-sub-text="<?= htmlspecialchars($submission->submission_text ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                                    ارسال / ویرایش
                                                                </button>
                                                            <?php } else { ?>
                                                                <p>مهلت ارسال تمام شده است</p>
                                                            <?php } ?>
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
        <div class="modal fade" id="submitHomeworkModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ارسال / ویرایش تکلیف</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="homework_id" id="modal_homework_id">

                        <div class="mb-2">
                            <label>توضیحات شما:</label>
                            <input type="text" name="submission_text" id="modal_submission_text" class="form-control">
                        </div>

                        <div class="mb-2">
                            <label>فایل پاسخ (PDF):</label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>

                        <small class="text-muted">اگر فایل انتخاب نکنید، فایل قبلی (اگر داشته باشید) تغییر نمی‌کند.</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                        <button class="btn btn-primary" name="submit_homework">ارسال تکلیف</button>
                    </div>
                </form>
            </div>
        </div>


        <?php include '../footers.php'; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('submitHomeworkModal');
            var hwId  = document.getElementById('modal_homework_id');
            var text  = document.getElementById('modal_submission_text');

            modal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                hwId.value = btn ? (btn.getAttribute('data-hw-id') || '') : '';
                text.value = btn ? (btn.getAttribute('data-sub-text') || '') : '';
            });

            // optional: clear file input each open
            modal.addEventListener('shown.bs.modal', function () {
                var f = modal.querySelector('input[type="file"]');
                if (f) f.value = '';
            });
        });
    </script>

</body>

</html>