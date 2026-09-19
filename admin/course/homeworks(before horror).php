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
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
//if (!Permision('permision_viewcourse')) {
//    header('location: ' . url('admin/index.php'));
//    die();
//}

if (isset($_GET['act_class'])) {
    $sql = 'UPDATE classes SET act_class=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_class']]);
    $classId = $_GET['act_class'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_class = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_class = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['act_general'])) {
    $sql = 'UPDATE classes SET act_general=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_general']]);
    $classId = $_GET['act_general'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_general = 1');
        $stmt->execute([$codecourse]);
        $activeGenerals = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalGenerals = $stmtt->fetchColumn();

        $courseStatus = $activeGenerals == $totalGenerals ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_general = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['act_quiz'])) {
    $sql = 'UPDATE classes SET act_quiz=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_quiz']]);
    $classId = $_GET['act_quiz'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_quiz = 1');
        $stmt->execute([$codecourse]);
        $activeQuizzes = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalQuizzes = $stmtt->fetchColumn();

        $courseStatus = $activeQuizzes == $totalQuizzes ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_quiz = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_class'])) {
    $sql = 'UPDATE classes SET act_class=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_class']]);
    $classId = $_GET['expire_class'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_class = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_class = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_general'])) {
    $sql = 'UPDATE classes SET act_general=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_general']]);
    $classId = $_GET['expire_general'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_general = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_general = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_quiz'])) {
    $sql = 'UPDATE classes SET act_quiz=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_quiz']]);
    $classId = $_GET['expire_quiz'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_quiz = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_quiz = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}

if (isset($_GET['unactive'])) {
    $sql = "DELETE FROM classes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['unactive']]);
    $code = $_GET['code'];
    header("Location: list-class.php?code=$code");
    die();
}

/* ======================================================================
   INSERT-ONLY HOMEWORKS (homework_admin)
   ====================================================================== */

// resolve codecourse once (prefer ?code, else derive from ?class_id)
$resolvedCode = 0;
if (isset($_GET['code']) && ctype_digit((string)$_GET['code'])) {
    $resolvedCode = (int)$_GET['code'];
} elseif (isset($_GET['class_id']) && ctype_digit((string)$_GET['class_id'])) {
    $tmp = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $tmp->execute([(int)$_GET['class_id']]);
    $resolvedCode = (int)($tmp->fetchColumn() ?: 0);
}

$HOMEWORKS_TABLE    = 'homework_admin';
$SUBMISSIONS_TABLE  = 'homework_student'; // <-- change if your actual table name differs
$UPLOAD_DIR_ABS     = __DIR__ . '/../../uploads/homeworks/'; // filesystem path
$UPLOAD_DIR_PUBLIC  = 'uploads/homeworks/';                   // URL path relative to webroot
$MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024;                       // 25MB
$ALLOWED_EXT        = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

// ensure upload dir exists
if (!is_dir($UPLOAD_DIR_ABS)) {
    @mkdir($UPLOAD_DIR_ABS, 0775, true);
}

$currentUser = $_SESSION['admin']['user'] ?? null;

/* digits: Persian/Arabic-Indic -> ASCII */
function fa_to_en_digits($str)
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($persian, $latin, $str);
}

/* ---- helper: parse deadline (Gregorian first, fallback to Jalali YYYY/MM/DD [HH:MM]) ---- */
function normalize_deadline($raw)
{
    $raw = fa_to_en_digits(trim($raw));
    $ts = strtotime($raw);
    if ($ts !== false) return date('Y-m-d H:i:s', $ts);

    if (preg_match('~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$~u', $raw, $m)) {
        $jy = (int)$m[1];
        $jm = (int)$m[2];
        $jd = (int)$m[3];
        $hh = isset($m[4]) ? (int)$m[4] : 0;
        $ii = isset($m[5]) ? (int)$m[5] : 0;
        if (function_exists('jalali_to_gregorian')) {
            [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $hh, $ii);
        }
    }
    return date('Y-m-d H:i:s');
}

/* ---- helper: save upload (returns relative public path) ---- */
function save_homework_upload($file, $UPLOAD_DIR_ABS, $UPLOAD_DIR_PUBLIC, $ALLOWED_EXT, $MAX_FILE_SIZE_BYTES)
{
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('خطا در آپلود فایل.');
    if ($file['size'] > $MAX_FILE_SIZE_BYTES) throw new RuntimeException('حجم فایل بیش از حد مجاز است.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) throw new RuntimeException('پسوند فایل مجاز نیست.');

    $basename = bin2hex(random_bytes(8)) . '-' . preg_replace('~[^a-zA-Z0-9\._-]+~u', '-', $file['name']);
    $destAbs  = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . $basename;
    $destPub  = rtrim($UPLOAD_DIR_PUBLIC, '/\\') . '/' . $basename;

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        throw new RuntimeException('انتقال فایل آپلود شده ناموفق بود.');
    }
    return $destPub;
}

/* ---------- Handle INSERT (add only) ---------- */
if (isset($_POST['addhomework'])) {
    // CSRF
    if (!isset($_POST['token'], $_SESSION['token']) || $_POST['token'] !== $_SESSION['token']) {
        $_SESSION['message'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineRaw = trim($_POST['deadline'] ?? '');
    $deadline    = normalize_deadline($deadlineRaw);
    $jalali = explode(' ', $deadline);
    [$jy, $jm, $jd] = explode('-', $jalali[0]);
    $date = jalali_to_gregorian($jy, $jm, $jd);
    $date = implode('-', $date);
    $finalDate = $date . ' ' . $jalali[1];
    $codecourse  = (int)$resolvedCode; // source of truth

    if ($title === '' || $description === '' || !$codecourse) {
        $_SESSION['message'] = 'لطفا همه فیلدهای الزامی را تکمیل کنید.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        // file is required
        $filePath = save_homework_upload($_FILES['file'] ?? [], $UPLOAD_DIR_ABS, $UPLOAD_DIR_PUBLIC, $ALLOWED_EXT, $MAX_FILE_SIZE_BYTES);
        if ($filePath === null) {
            throw new RuntimeException('آپلود فایل الزامی است.');
        }

        $sql = "INSERT INTO {$HOMEWORKS_TABLE}
                    (title, admin_username, description, codecourse, deadline, file_path, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$title, $currentUser, $description, $codecourse, $finalDate, $filePath]);

        $_SESSION['message'] = 'تکلیف با موفقیت ثبت شد.';
    } catch (Throwable $e) {
        $_SESSION['message'] = 'خطا: ' . $e->getMessage();
    }

    // refresh same page
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* ---- delete homework here (same file), refresh same page ---- */
if (isset($_GET['delete_hw']) && ctype_digit((string)$_GET['delete_hw'])) {
    $id = (int)$_GET['delete_hw'];

    // fetch to delete file from disk too
    $stmt = $conn->prepare("SELECT file_path FROM {$HOMEWORKS_TABLE} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_OBJ);

    $stmt = $conn->prepare("DELETE FROM {$HOMEWORKS_TABLE} WHERE id = ?");
    $stmt->execute([$id]);

    if ($row && !empty($row->file_path)) {
        $abs = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . basename($row->file_path);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    $_SESSION['message'] = 'تکلیف حذف شد.';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_diff_key($_GET, ['delete_hw' => true])));
    exit;
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <title> لیست تکالیف | <?php echo __TITLE ?></title>
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
                    <div class="row row-sm">

                        <?php if (isset($_SESSION['message'])) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?php echo $_SESSION['message'] ?>
                            </div>
                        <?php unset($_SESSION['message']);
                        } ?>

                        <!-- ADD FORM (INSERT ONLY) -->
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xl-12">
                                            <div>
                                                <h6 class="main-content-label mb-1">
                                                    افزودن تکلیف برای کلاس به شناسه
                                                    <span class="text-dark">
                                                        <?php echo htmlspecialchars($_GET['class_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $hash = bin2hex(random_bytes(32));
                                    $_SESSION['token'] = $hash;
                                    ?>
                                    <form method="post" enctype="multipart/form-data" data-parsley-validate>
                                        <div class="row">
                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">نام تکلیف:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="title" placeholder="نام را وارد کنید ..." required>

                                                <br>
                                                <p class="mg-b-10">توضیحات:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="description" placeholder="توضیحات را وارد کنید ..." required>

                                                <br>
                                                <p class="mg-b-10">مهلت تحویل:<span class="tx-danger">*</span></p>
                                                <!-- Jalali picker is fine; backend converts to Gregorian -->
                                                <input class="form-control fc-datepicker" name="deadline" placeholder="YYYY-MM-DD HH:MM" required>

                                                <br>
                                                <p class="mg-b-10">فایل تکلیف<span class="tx-danger">*</span></p>
                                                <input type="file" class="form-control" name="file" required
                                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png">

                                                <input type="hidden" name="token" value="<?= $hash ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="text-center mg-t-30">
                                                <button class="btn ripple btn-success pd-x-20" type="submit" name="addhomework">ثبت تکلیف</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- LIST -->
                        <div class="col-lg-9">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-xl-12 mb-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="main-content-label mb-1">
                                                لیست تکالیف کلاس به شناسه
                                                <span class="text-dark">
                                                    <?php echo htmlspecialchars($_GET['class_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </h6>
                                            <?php
                                            // fetch homeworks + answers_count
                                            $homeworks = [];
                                            if ($resolvedCode > 0) {
                                                $stmt = $conn->prepare("
                                                SELECT h.*,
                                                       (SELECT COUNT(*) FROM {$SUBMISSIONS_TABLE} s WHERE s.homework_id = h.id) AS answers_count
                                                FROM {$HOMEWORKS_TABLE} h
                                                WHERE h.codecourse = ?
                                                ORDER BY h.created_at DESC
                                            ");
                                                $stmt->execute([$resolvedCode]);
                                                $homeworks = $stmt->fetchAll(PDO::FETCH_OBJ);
                                            }
                                            ?>
                                            <?php if (Permision('permision_viewcourse')) { ?>
                                                <button class="btn ripple btn-dark pd-x-20"
                                                    onclick="window.location.href='dars.php?code=<?= (int)$resolvedCode ?>'">
                                                    <i class="fe fe-bar-chart-2"></i>
                                                    مدیریت درس ها
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>عنوان</th>
                                                    <th>مهلت تحویل (شمسی)</th>
                                                    <th>فایل</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($homeworks): $i = 1;
                                                    foreach ($homeworks as $h): ?>
                                                        <tr>
                                                            <td class="wd-15"><?= $i ?></td>
                                                            <td class="wd-25">
                                                                <?= htmlspecialchars($h->title, ENT_QUOTES, 'UTF-8') ?>
                                                                <?php if (!empty($h->description)): ?>
                                                                    <div class="small text-muted mt-1">
                                                                        <?= htmlspecialchars($h->description, ENT_QUOTES, 'UTF-8') ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <?php
                                                                // Expecting a Gregorian DATETIME like "YYYY-MM-DD HH:MM:SS"
                                                                $raw = trim((string)$h->deadline);
                                                                if ($raw === '' || $raw === '0000-00-00 00:00:00') {
                                                                    echo '—';
                                                                } else {
                                                                    $gY = (int)substr($raw, 0, 4);
                                                                    $gM = (int)substr($raw, 5, 2);
                                                                    $gD = (int)substr($raw, 8, 2);
                                                                    if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
                                                                        list($jY, $jM, $jD) = gregorian_to_jalali($gY, $gM, $gD);
                                                                        $out = sprintf('%04d/%02d/%02d', $jY, $jM, $jD); // ASCII digits
                                                                        echo '<span style="direction:ltr; unicode-bidi:embed;">'
                                                                            . htmlspecialchars($out, ENT_QUOTES, 'UTF-8')
                                                                            . '</span>';
                                                                    } else {
                                                                        $out = substr($raw, 0, 10); // fallback: Gregorian date only
                                                                        echo '<span style="direction:ltr; unicode-bidi:embed;">'
                                                                            . htmlspecialchars($out, ENT_QUOTES, 'UTF-8')
                                                                            . '</span>';
                                                                    }
                                                                }
                                                                ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <?php if (!empty($h->file_path)): ?>
                                                                    <a href="<?= url($h->file_path) ?>" target="_blank" rel="noopener">دانلود</a>
                                                                <?php else: ?>
                                                                    —
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <a class="btn btn-sm btn-outline-danger"
                                                                    href="homeworks.php?delete_hw=<?= (int)$h->id ?>&class_id=<?= (int)($_GET['class_id'] ?? 0) ?>&code=<?= (int)$resolvedCode ?>"
                                                                    onclick="return confirm('حذف تکلیف؟');">
                                                                    حذف
                                                                </a>
                                                                <a class="btn btn-sm btn-outline-primary ms-2"
                                                                    href="homeworks.php?answers=<?= (int)$h->id ?>&class_id=<?= (int)($_GET['class_id'] ?? 0) ?>&code=<?= (int)$resolvedCode ?>#answers">
                                                                    پاسخ‌ها
                                                                    <?php if ((int)$h->answers_count > 0): ?>
                                                                        <span class="badge bg-primary ms-1"><?= (int)$h->answers_count ?></span>
                                                                    <?php endif; ?>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php $i++;
                                                    endforeach;
                                                else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">تکلیفی ثبت نشده است.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php
                                    // ---------- Answers panel (same page) ----------
                                    if (isset($_GET['answers']) && ctype_digit((string)$_GET['answers'])):
                                        $hwId = (int)$_GET['answers'];

                                        // Fetch homework (to show title)
                                        $hwStmt = $conn->prepare("SELECT id, title FROM {$HOMEWORKS_TABLE} WHERE id = ? AND codecourse = ?");
                                        $hwStmt->execute([$hwId, $resolvedCode]);
                                        $hwRow = $hwStmt->fetch(PDO::FETCH_OBJ);

                                        if ($hwRow):
                                            // Pull all submissions for this homework
                                            $subStmt = $conn->prepare("
                                            SELECT id, homework_id, student_username, submission_text, file_path, submitted_at, updated_at
                                            FROM {$SUBMISSIONS_TABLE}
                                            WHERE homework_id = ?
                                            ORDER BY submitted_at DESC
                                        ");
                                            $subStmt->execute([$hwId]);
                                            $subs = $subStmt->fetchAll(PDO::FETCH_OBJ);
                                    ?>
                                            <div id="answers" class="mt-4">
                                                <h6 class="main-content-label mb-2">
                                                    پاسخ‌های تکلیف:
                                                    <span class="text-dark"><?= htmlspecialchars($hwRow->title, ENT_QUOTES, 'UTF-8') ?></span>
                                                </h6>

                                                <div class="table-responsive">
                                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>دانش آموز</th>
                                                                <th>متن پاسخ</th>
                                                                <th>فایل</th>
                                                                <th>زمان ارسال</th>
                                                                <th>آخرین بروزرسانی</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if ($subs): $k = 1;
                                                                foreach ($subs as $s): ?>
                                                                    <tr>
                                                                        <td><?= $k ?></td>
                                                                        <td><?php
                                                                            $person = retPerson($s->student_username);
                                                                            echo $person->fname . ' ' . $person->lname;
                                                                            // htmlspecialchars($s->student_username, ENT_QUOTES, 'UTF-8') ?></td>
                                                                        <td><?= htmlspecialchars((string)$s->submission_text, ENT_QUOTES, 'UTF-8') ?></td>
                                                                        <td>
                                                                            <?php if (!empty($s->file_path)): ?>
                                                                                <a href="<?= url($s->file_path) ?>" target="_blank" rel="noopener">دانلود</a>
                                                                            <?php else: ?>
                                                                                —
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <?php
                                                                            $raw = trim((string)$s->submitted_at);
                                                                            if ($raw !== '' && $raw !== '0000-00-00 00:00:00') {
                                                                                $gY = (int)substr($raw, 0, 4);
                                                                                $gM = (int)substr($raw, 5, 2);
                                                                                $gD = (int)substr($raw, 8, 2);
                                                                                if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
                                                                                    list($jY, $jM, $jD) = gregorian_to_jalali($gY, $gM, $gD);
                                                                                    $out = sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
                                                                                } else {
                                                                                    $out = substr($raw, 0, 10);
                                                                                }
                                                                                echo '<span style="direction:ltr; unicode-bidi:embed;">'
                                                                                    . htmlspecialchars($out, ENT_QUOTES, 'UTF-8')
                                                                                    . '</span>';
                                                                            } else {
                                                                                echo '—';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td>
                                                                            <?php
                                                                            $raw = trim((string)$s->updated_at);
                                                                            if ($raw !== '' && $raw !== '0000-00-00 00:00:00') {
                                                                                $gY = (int)substr($raw, 0, 4);
                                                                                $gM = (int)substr($raw, 5, 2);
                                                                                $gD = (int)substr($raw, 8, 2);
                                                                                if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
                                                                                    list($jY, $jM, $jD) = gregorian_to_jalali($gY, $gM, $gD);
                                                                                    $out = sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
                                                                                } else {
                                                                                    $out = substr($raw, 0, 10);
                                                                                }
                                                                                echo '<span style="direction:ltr; unicode-bidi:embed;">'
                                                                                    . htmlspecialchars($out, ENT_QUOTES, 'UTF-8')
                                                                                    . '</span>';
                                                                            } else {
                                                                                echo '—';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php $k++;
                                                                endforeach;
                                                            else: ?>
                                                                <tr>
                                                                    <td colspan="6" class="text-center">پاسخی ثبت نشده است.</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                    <?php
                                        endif; // $hwRow
                                    endif; // answers GET
                                    ?>

                                </div>
                            </div>
                        </div>

                    </div><!-- row -->
                </div><!-- inner-body -->
            </div><!-- container-fluid -->
        </div><!-- main-content -->

        <?php include '../../footers.php'; ?>
    </div><!-- page -->
</body>

</html>