<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';

/* ===================== Security / Session ===================== */
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}

if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}

if ($_SESSION['auth']['ip'] != GetIp() && $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

$student_username = $_SESSION['auth']['user'] ?? '';
if ($student_username === '') {
    header('location: ' . url('login.php'));
    die();
}

/* ===================== Get codecourse from link ===================== */
$codecourse = (isset($_GET['code']) && ctype_digit((string)$_GET['code'])) ? (int)$_GET['code'] : 0;
if ($codecourse <= 0) {
    die('کد درس (codecourse) نامعتبر است.');
}

/* ===================== CSRF token for submit/edit ===================== */
if (empty($_SESSION['token_hw_submit'])) {
    $_SESSION['token_hw_submit'] = bin2hex(random_bytes(32));
}
$token_hw_submit = $_SESSION['token_hw_submit'];

/* ===================== Helpers ===================== */

function deadline_jalali_date_only($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '—';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        $y  = (int)$m[1];
        $mo = (int)$m[2];
        $d  = (int)$m[3];

        if ($y >= 1200 && $y <= 1600) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }

        if (function_exists('gregorian_to_jalali')) {
            [$jy, $jm, $jd] = gregorian_to_jalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

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

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/', $raw, $m)) {
        $y  = (int)$m[1];
        $mo = (int)$m[2];
        $d  = (int)$m[3];
        $hh = isset($m[4]) ? (int)$m[4] : 0;
        $ii = isset($m[5]) ? (int)$m[5] : 0;
        $ss = isset($m[6]) ? (int)$m[6] : 0;

        if ($y >= 1200 && $y <= 1600 && function_exists('jalali_to_gregorian')) {
            [$gy, $gm, $gd] = jalali_to_gregorian($y, $mo, $d);
            return strtotime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $hh, $ii, $ss));
        }

        return strtotime(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $hh, $ii, $ss));
    }

    return strtotime($raw);
}

function safe_filename($name)
{
    $name = (string)$name;
    $name = preg_replace('~[^\pL\pN\._-]+~u', '-', $name);
    $name = trim($name, '-');
    if ($name === '') $name = 'file';
    return $name;
}

/* ===================== SUBMIT / EDIT homework (Option A) ===================== */
if (isset($_POST['submit_homework'])) {

    // CSRF
    if (!isset($_POST['token_hw_submit']) || $_POST['token_hw_submit'] !== $_SESSION['token_hw_submit']) {
        $_SESSION['msg'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
        exit;
    }

    $homework_id     = (int)($_POST['homework_id'] ?? 0);
    $submission_text = trim((string)($_POST['submission_text'] ?? ''));

    if ($homework_id <= 0) {
        $_SESSION['msg'] = 'شناسه تکلیف نامعتبر است.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
        exit;
    }

    // ✅ Validate: homework must belong to one of student's classes in this codecourse AND be approved
    $hwCheck = $conn->prepare("
        SELECT h.id, h.deadline
        FROM homework_admin h
        INNER JOIN classes c ON c.id = h.class_id
        INNER JOIN dbcourse d ON d.class_id = c.id AND d.username = ?
        WHERE h.id = ?
          AND c.codecourse = ?
          AND h.status = 'approved'
        LIMIT 1
    ");
    $hwCheck->execute([$student_username, $homework_id, $codecourse]);
    $hwRow = $hwCheck->fetch(PDO::FETCH_OBJ);

    if (!$hwRow) {
        $_SESSION['msg'] = 'این تکلیف برای شما/این درس معتبر نیست.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
        exit;
    }

    // Deadline rule: after deadline -> cannot submit/edit
    $deadlineTs = deadline_to_ts($hwRow->deadline);
    if ($deadlineTs !== false && $deadlineTs < time()) {
        $_SESSION['msg'] = 'مهلت ارسال این تکلیف تمام شده است.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
        exit;
    }

    // Upload rules
    $UPLOAD_DIR_ABS = __DIR__ . '/../uploads-student/homeworks/';
    $UPLOAD_DIR_REL = 'uploads-student/homeworks/';
    $MAX_FILE_SIZE_BYTES = 15 * 1024 * 1024; // 15MB
    $ALLOWED_EXT = ['pdf', 'doc', 'docx', 'jpg', 'png', 'zip', 'rar'];

    if (!is_dir($UPLOAD_DIR_ABS)) {
        @mkdir($UPLOAD_DIR_ABS, 0777, true);
    }

    // Existing submission?
    $check = $conn->prepare("SELECT id, file_path FROM homework_student WHERE homework_id = ? AND student_username = ? LIMIT 1");
    $check->execute([$homework_id, $student_username]);
    $existing = $check->fetch(PDO::FETCH_OBJ);

    // File upload is optional on edit
    $new_file_path = null;

    if (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {

        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['msg'] = 'خطا در آپلود فایل.';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
            exit;
        }

        if ((int)$_FILES['file']['size'] > $MAX_FILE_SIZE_BYTES) {
            $_SESSION['msg'] = 'حجم فایل بیش از حد مجاز است (15MB).';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
            exit;
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT, true)) {
            $_SESSION['msg'] = 'پسوند فایل مجاز نیست.';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
            exit;
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['file']['tmp_name']);

            $allowedMime = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
                'application/x-rar',
                'application/vnd.rar',
                'application/octet-stream', // some hosts report zip/rar like this
            ];

            if ($mime === false || !in_array($mime, $allowedMime, true)) {
                $_SESSION['msg'] = 'نوع فایل مجاز نیست.';
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
                exit;
            }
        }



        $safe = safe_filename(basename($_FILES['file']['name']));
        $fileName = bin2hex(random_bytes(8)) . '_' . time() . '_' . $safe;
        $targetAbs = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetAbs)) {
            $_SESSION['msg'] = 'انتقال فایل آپلود شده ناموفق بود.';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
            exit;
        }

        $new_file_path = $UPLOAD_DIR_REL . $fileName;
    }

    // Insert or Update (Option A)
    if ($existing) {
        if ($new_file_path === null) {
            $new_file_path = $existing->file_path; // keep old
        }

        $upd = $conn->prepare("
            UPDATE homework_student
            SET submission_text = ?, file_path = ?, updated_at = NOW()
            WHERE id = ? LIMIT 1
        ");
        $upd->execute([$submission_text, $new_file_path, (int)$existing->id]);

        $_SESSION['msg'] = '✅ تکلیف شما با موفقیت ویرایش شد.';
    } else {
        // file_path is NOT NULL in your table, so we must store something.
        // If you want file optional, you must change DB (file_path nullable).
        // For now: require file on first submit.
        if ($new_file_path === null) {
            $_SESSION['msg'] = 'آپلود فایل برای ارسال اولیه الزامی است.';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
            exit;
        }

        $ins = $conn->prepare("
            INSERT INTO homework_student (homework_id, student_username, submission_text, file_path, submitted_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NULL)
        ");
        $ins->execute([$homework_id, $student_username, ($submission_text === '' ? null : $submission_text), $new_file_path]);

        $_SESSION['msg'] = '✅ تکلیف شما با موفقیت ارسال شد.';
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['code' => $codecourse]));
    exit;
}

/* ===================== FETCH: student's classes for this codecourse, then all homeworks for those classes ===================== */
/*
    Requirements:
    - link has ?code=codecourse
    - student is in dbcourse (username + class_id)
    - show homeworks for all those class_ids
    - show class name (classes.name)
*/
$stmt = $conn->prepare("
    SELECT
        h.*,
        c.id AS class_id,
        c.name AS class_name,
        s.id AS submission_id,
        s.submission_text,
        s.file_path AS student_file_path,
        s.submitted_at,
        s.updated_at,
        s.professors_answer

    FROM dbcourse d
    INNER JOIN classes c
        ON c.id = d.class_id
    INNER JOIN homework_admin h
        ON h.class_id = c.id
    LEFT JOIN homework_student s
        ON s.homework_id = h.id AND s.student_username = d.username

    WHERE d.username = ?
      AND c.codecourse = ?
      AND h.status = 'approved'

    ORDER BY c.id DESC, h.deadline DESC, h.created_at DESC
");
$stmt->execute([$student_username, $codecourse]);
$homeworks = $stmt->fetchAll(PDO::FETCH_OBJ);
// Collect all private homework IDs
$privateHomeworkIds = [];
foreach ($homeworks as $hw) {
    if (isset($hw->homework_type) && $hw->homework_type == 1) {
        $privateHomeworkIds[] = $hw->id;
    }
}

// If there are private homeworks, fetch assigned students for current user
$assignedHomeworkIds = [];
if (!empty($privateHomeworkIds)) {
    $placeholders = implode(',', array_fill(0, count($privateHomeworkIds), '?'));

    $stmt_private_assignments = $conn->prepare("
        SELECT homework_id
        FROM private_homework_students
        WHERE homework_id IN ($placeholders)
        AND username = ?
    ");

    $params = array_merge($privateHomeworkIds, [$student_username]);
    $stmt_private_assignments->execute($params);
    $assigned = $stmt_private_assignments->fetchAll(PDO::FETCH_COLUMN);

    $assignedHomeworkIds = $assigned; // list of homework_ids assigned to current user
}

// Filter out homework that are private and not assigned
$filteredHomeworks = [];
foreach ($homeworks as $hw) {
    if (isset($hw->homework_type) && $hw->homework_type == 1) {
        if (in_array($hw->id, $assignedHomeworkIds)) {
            $filteredHomeworks[] = $hw;
        }
        // else skip this homework
    } else {
        // not private, include directly
        $filteredHomeworks[] = $hw;
    }
}

// Now use $filteredHomeworks instead of $homeworks for rendering

include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تکالیف دانش آموز</title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        .td-description {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
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

                <?php if (isset($_SESSION['msg'])): ?>
                    <div class="alert alert-info text-center">
                        <?= htmlspecialchars($_SESSION['msg'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php unset($_SESSION['msg']); ?>
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
                                        <th>کلاس</th>
                                        <th>عنوان</th>
                                        <th>توضیحات</th>
                                        <th>فایل تکلیف</th>
                                        <th>ویدیو تکلیف</th>
                                        <th>مهلت ارسال</th>
                                        <th>جواب تکلیف</th>
                                        <th>ارسال تکلیف</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($filteredHomeworks)): ?>
                                        <?php foreach ($filteredHomeworks as $index => $hw): ?>
                                            <?php
                                            $hasSubmission = !empty($hw->submission_id);
                                            $deadlineTs = deadline_to_ts($hw->deadline);
                                            $deadlineReached = ($deadlineTs !== false && $deadlineTs < time());
                                            ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($hw->class_name ?? ('کلاس #' . (int)$hw->class_id), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($hw->title ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="td-description">
                                                    <?php if (!empty($hw->description)): ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-link p-0 me-1 text-decoration-none"
                                                                data-bs-toggle="popover"
                                                                data-bs-placement="top"
                                                                data-bs-content="<?= htmlspecialchars($hw->description, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fe fe-info"></i> <!-- Or any icon your theme uses -->
                                                        </button>
                                                        <?= htmlspecialchars($hw->description, ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($hw->file_path)): ?>
                                                        <a href="<?= url($hw->file_path) ?>" target="_blank" class="btn btn-sm btn-primary">دانلود</a>
                                                    <?php else: ?>
                                                        ندارد
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($hw->video_link)): ?>
                                                        <a href="<?= htmlspecialchars((string)$hw->video_link, ENT_QUOTES, 'UTF-8') ?>"
                                                           target="_blank" rel="noopener"
                                                           class="btn btn-sm btn-warning">
                                                            مشاهده
                                                        </a>
                                                    <?php else: ?>
                                                        ندارد
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $out = deadline_jalali_date_only($hw->deadline);
                                                    echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $ans = $hw->professors_answer ?? '';
                                                    echo ($ans !== '' && $ans !== null)
                                                        ? htmlspecialchars($ans, ENT_QUOTES, 'UTF-8')
                                                        : '—';
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php if ($hasSubmission): ?>
                                                        <div class="mb-1">تکلیف شما ارسال شده</div>
                                                        <?php if (!empty($hw->student_file_path)): ?>
                                                            <div class="small text-muted">
                                                                (<?= htmlspecialchars(basename($hw->student_file_path), ENT_QUOTES, 'UTF-8') ?>)
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if ($deadlineReached): ?>
                                                        <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                            مهلت تمام شده
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-success"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#submitHomeworkModal"
                                                                data-hw-id="<?= (int)$hw->id ?>"
                                                                data-sub-text="<?= htmlspecialchars((string)($hw->submission_text ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            ارسال / ویرایش
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8">هیچ تکلیفی برای کلاس‌های شما در این درس تعریف نشده است.</td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div><!-- card-body -->
                        </div><!-- card -->
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Submit Modal -->
    <div class="modal fade" id="submitHomeworkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ارسال / ویرایش تکلیف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="homework_id" id="modal_homework_id">
                    <input type="hidden" name="token_hw_submit" value="<?= htmlspecialchars($token_hw_submit, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-2">
                        <label>توضیحات شما:</label>
                        <input type="text" name="submission_text" id="modal_submission_text" class="form-control">
                    </div>

                    <div class="mb-2">
                        <label>فایل پاسخ (pdf/doc/docx/jpg/png/rar/zip) - حداکثر 15MB:</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.png,.zip,.rar">
                    </div>

                    <small class="text-muted">
                        می‌توانید فایل پاسخ را به صورت ZIP یا RAR هم ارسال کنید (برای چند فایل یا عکس). فقط فایل‌های داخل آن باید مربوط به تکلیف باشد.
                        اگر فایل انتخاب نکنید، فایل قبلی (اگر داشته باشید) تغییر نمی‌کند.
                    </small>
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

        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                trigger: 'click focus' // Closes automatically when clicking elsewhere
            })
        });

        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            hwId.value = btn ? (btn.getAttribute('data-hw-id') || '') : '';
            text.value = btn ? (btn.getAttribute('data-sub-text') || '') : '';
        });

        // clear file each open
        modal.addEventListener('shown.bs.modal', function () {
            var f = modal.querySelector('input[type="file"]');
            if (f) f.value = '';
        });
    });
</script>

</body>
</html>
