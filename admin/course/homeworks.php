<?Php
//phpinfo();
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
// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
// End Check Session JUST ONE Session

/* ======================================================================
   CLASS ACTIONS (unchanged)
   ====================================================================== */
$adminRole = $_SESSION['admin']['adminrole'] ?? null;

$requestedClassId = (isset($_GET['class_id']) && ctype_digit((string)$_GET['class_id']))
    ? (int)$_GET['class_id']
    : 0;
RequireTeacherPermission('teacher_perm_homework', url('admin/course/Active-course.php'));
if ((int)$adminRole === 3) {
    if ($requestedClassId <= 0 || !TeacherHasClassAccess($requestedClassId)) {
        http_response_code(403);
        exit('شما به تکالیف این کلاس دسترسی ندارید.');
    }
    foreach (['act_class', 'act_general', 'act_quiz', 'expire_class', 'expire_general', 'expire_quiz', 'unactive'] as $blockedAction) {
        if (isset($_GET[$blockedAction])) {
            http_response_code(403);
            exit('معلم مجوز تغییر تنظیمات کلاس را ندارد.');
        }
    }
} elseif (StaffCourseScopeRequired() && !StaffHasClassAccess($requestedClassId)) {
    http_response_code(403);
    exit('شما به تکالیف این پایه و کلاس دسترسی ندارید.');
}

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

    try {
        $stmt->execute([$_GET['unactive']]);
    } catch (Throwable $e) {
        // if FK restrict blocks delete, show message
        $_SESSION['message'] = 'خطا در حذف کلاس: ' . $e->getMessage();
    }

    $code = $_GET['code'] ?? '';
    header("Location: list-class.php?code=$code");
    die();
}

/* ======================================================================
   HOMEWORKS (homework_admin) - NOW BY class_id (NO codecourse)
   ====================================================================== */

$HOMEWORKS_TABLE    = 'homework_admin';
$SUBMISSIONS_TABLE  = 'homework_student'; // <-- change if your actual table differs

$UPLOAD_DIR_ABS      = __DIR__ . '/../../uploads/homeworks/'; // filesystem path
$UPLOAD_DIR_PUBLIC   = 'uploads/homeworks/';                   // URL path relative to webroot
$MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024;                       // 25MB
$ALLOWED_EXT         = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

$currentUser = $_SESSION['admin']['user'] ?? null;

// class_id is required for this page
$class_id = (isset($_GET['class_id']) && ctype_digit((string)$_GET['class_id']))
    ? (int)$_GET['class_id']
    : 0;

if ($class_id <= 0) {
    die('کلاس مشخص نشده است (class_id).');
}

// validate class exists + get codecourse (only for optional buttons/redirects)
$tmp = $conn->prepare('SELECT id, codecourse FROM classes WHERE id = ?');
$tmp->execute([$class_id]);
$classRow = $tmp->fetch(PDO::FETCH_ASSOC);
if (!$classRow) {
    die('کلاس یافت نشد.');
}
$codecourse = (int)($classRow['codecourse'] ?? 0);

// ensure upload dir exists
if (!is_dir($UPLOAD_DIR_ABS)) {
    @mkdir($UPLOAD_DIR_ABS, 0775, true);
}

/* digits: Persian/Arabic-Indic -> ASCII */
function fa_to_en_digits($str)
{
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $latin   = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($persian, $latin, $str);
}

/* ---- helper: normalize video link (optional) ---- */
function normalize_video_link($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return null;

    // allow users to paste without scheme
    $p = @parse_url($raw);
    if ($p !== false && empty($p['scheme'])) {
        $raw = 'https://' . ltrim($raw, '/');
    }
    // validate URL
    if (!filter_var($raw, FILTER_VALIDATE_URL)) return false;
    return $raw;
}

/* ---- helper: parse deadline (Gregorian first, fallback to Jalali YYYY/MM/DD [HH:MM]) ---- */
function normalize_deadline($raw)
{
    $raw = fa_to_en_digits(trim($raw));
    
    // اول تلاش برای تشخیص فرمت شمسی (سال ۱۳۰۰ تا ۱۵۰۰)
    if (preg_match('~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$~u', $raw, $m)) {
        $jy = (int)$m[1];
        $jm = (int)$m[2];
        $jd = (int)$m[3];
        $hh = isset($m[4]) ? (int)$m[4] : 0;
        $ii = isset($m[5]) ? (int)$m[5] : 0;
        
        // اگر سال شمسی معقول است و تابع تبدیل وجود دارد
        if ($jy >= 1300 && $jy <= 1500 && function_exists('jalali_to_gregorian')) {
            [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $hh, $ii);
        }
    }
    
    // در غیر این صورت، به عنوان تاریخ میلادی استاندارد تفسیر کن
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }
    
    // در نهایت، تاریخ فعلی
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


if (isset($_POST['addhomework'])) {

    // CSRF
    if (!isset($_POST['token'], $_SESSION['token']) || $_POST['token'] !== $_SESSION['token']) {
        $_SESSION['message'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadlineRaw = trim($_POST['deadline'] ?? '');
    $deadline    = normalize_deadline($deadlineRaw); // final (Gregorian DATETIME)

    // Read homework type: 0 = Normal, 1 = Private
    $homework_type = isset($_POST['homework_type']) ? (int)$_POST['homework_type'] : 0;
    if ($homework_type !== 1) {
        $homework_type = 0;
    }

    // Capture usernames array if it's a private assignment
    $selected_students = isset($_POST['selected_students']) && is_array($_POST['selected_students'])
        ? $_POST['selected_students']
        : [];

    $video_link_norm = normalize_video_link($_POST['video_link'] ?? '');
    if ($video_link_norm === false) {
        $_SESSION['message'] = 'لینک ویدیو معتبر نیست.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }
    $video_link = $video_link_norm; // null or valid url

    if ($title === '' || $description === '' || $class_id <= 0) {
        $_SESSION['message'] = 'لطفا همه فیلدهای الزامی را تکمیل کنید.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    // Stop execution early if they chose Private but ticked zero students
    if ($homework_type === 1 && empty($selected_students)) {
        $_SESSION['message'] = 'خطا: برای تکلیف اختصاصی، حداقل باید یک دانش آموز انتخاب شود.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $grade_type = $_POST['grade_type'] ?? 'numeric';
    $grade_type = ($grade_type === 'scale') ? 'scale' : 'numeric';

    $min_grade = null;
    $max_grade = null;

    if ($grade_type === 'numeric') {
        $min_grade = (int)fa_to_en_digits($_POST['min_grade'] ?? 0);
        $max_grade = (int)fa_to_en_digits($_POST['max_grade'] ?? 100);

        if ($min_grade < 0) $min_grade = 0;
        if ($max_grade < $min_grade) {
            $_SESSION['message'] = 'حداکثر نمره باید بزرگتر یا مساوی حداقل باشد.';
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
            exit;
        }
    }

    try {
        // File upload execution
        $filePath = save_homework_upload($_FILES['file'] ?? [], $UPLOAD_DIR_ABS, $UPLOAD_DIR_PUBLIC, $ALLOWED_EXT, $MAX_FILE_SIZE_BYTES);

        if ($filePath === null) $filePath = '';

        // 🔍 Check that all selected users are actually enrolled in this class
        if ($homework_type === 1 && !empty($selected_students)) {

            // 1. Clean the array to remove duplicates or empty entries
            $selected_students = array_unique(array_filter(array_map('trim', $selected_students)));

            // 2. Build a dynamic IN clause (?,?,?) matching the count of students
            $placeholders = implode(',', array_fill(0, count($selected_students), '?'));

            // 3. Query your dbcourse table to verify enrollment
            $check_sql = "SELECT COUNT(DISTINCT username) 
                          FROM dbcourse 
                          WHERE class_id = ? AND username IN ($placeholders)";

            // 4. Merge class_id into the parameters array for safe binding
            $check_params = array_merge([$class_id], $selected_students);

            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->execute($check_params);
            $valid_count = (int)$check_stmt->fetchColumn();

            // 5. If the count found doesn't match the unique count submitted, throw an error
            if ($valid_count !== count($selected_students)) {
                throw new RuntimeException('یک یا چند کاربر انتخاب شده در این کلاس عضو نیستند یا وجود ندارند.');
            }
        }

        // Start Transaction to guarantee both insertions execute smoothly together
        $conn->beginTransaction();

        $sql = "INSERT INTO {$HOMEWORKS_TABLE}
        (title, admin_username, status, description, class_id, deadline, file_path, video_link,
         grade_type, min_grade, max_grade, homework_type,
         approved_by, created_at, updated_at)
        VALUES
        (?, ?, 'pending', ?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         NULL, NOW(), NULL)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $title, $currentUser, $description, $class_id, $deadline, $filePath, $video_link,
            $grade_type, $min_grade, $max_grade, $homework_type
        ]);

        // Capture the primary key of our newly made homework row
        $new_homework_id = (int)$conn->lastInsertId();

        // If homework is type 1 (Private), write down the assigned students list
        if ($homework_type === 1 && !empty($selected_students)) {

            // Build placeholders for bulk insert query: (?, ?), (?, ?) etc.
            $placeholders = [];
            $bind_data    = [];

            foreach ($selected_students as $student_user) {
                $student_user = trim((string)$student_user);
                if ($student_user !== '') {
                    $placeholders[] = '(?, ?)';
                    $bind_data[]    = $new_homework_id;
                    $bind_data[]    = $student_user;
                }
            }

            if (!empty($placeholders)) {
                $bulk_sql = "INSERT INTO private_homework_students (homework_id, username) VALUES " . implode(', ', $placeholders);
                $bulk_stmt = $conn->prepare($bulk_sql);
                $bulk_stmt->execute($bind_data);
            }
        }

        // Commit transaction if both insertions are successful
        $conn->commit();
        $_SESSION['message'] = 'تکلیف با موفقیت ثبت شد.';

    } catch (Throwable $e) {
        // Rollback alterations if something triggers an exception
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['message'] = 'خطا: ' . $e->getMessage();
    }

    // Refresh page clear of state
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
    exit;
}
/* ---- delete homework (same file) ---- */
if (isset($_GET['delete_hw']) && ctype_digit((string)$_GET['delete_hw'])) {
    $id = (int)$_GET['delete_hw'];

    // fetch to delete file from disk too (AND enforce class ownership)
    $stmt = $conn->prepare("SELECT file_path FROM {$HOMEWORKS_TABLE} WHERE id = ? AND class_id = ? LIMIT 1");
    $stmt->execute([$id, $class_id]);
    $row = $stmt->fetch(PDO::FETCH_OBJ);

    if ($row) {
        $stmt = $conn->prepare("DELETE FROM {$HOMEWORKS_TABLE} WHERE id = ? AND class_id = ? LIMIT 1");
        $stmt->execute([$id, $class_id]);

        if (!empty($row->file_path)) {
            $abs = rtrim($UPLOAD_DIR_ABS, '/\\') . DIRECTORY_SEPARATOR . basename($row->file_path);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $_SESSION['message'] = 'تکلیف حذف شد.';
    } else {
        $_SESSION['message'] = 'تکلیف یافت نشد یا متعلق به این کلاس نیست.';
    }

    $qs = $_GET;
    unset($qs['delete_hw']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($qs));
    exit;
}

/* token for editing homework (deadline + video_link) */
if (empty($_SESSION['token_edit_hw'])) {
    $_SESSION['token_edit_hw'] = bin2hex(random_bytes(32));
}
$token_edit_hw = $_SESSION['token_edit_hw'];

/* helper: build jalali string for input (YYYY/MM/DD HH:MM) from stored gregorian DATETIME */
function deadline_to_jalali_input($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '';

    // Extract Y-m-d from "YYYY-MM-DD HH:MM:SS"
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $raw, $m)) {
        $y = (int)$m[1];
        $mo = (int)$m[2];
        $d = (int)$m[3];

        // If year is small (like 1402), it's very likely Jalali already stored in DB
        if ($y < 1700) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }

        // Otherwise assume Gregorian and convert to Jalali
        if (function_exists('gregorian_to_jalali')) {
            [$jy, $jm, $jd] = gregorian_to_jalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        // fallback (Gregorian) but still slash format
        return sprintf('%04d/%02d/%02d', $y, $mo, $d);
    }

    // If DB is already stored like 1402/10/08 or similar
    return str_replace('-', '/', substr($raw, 0, 10));
}


/* UPDATE homework (deadline + video_link) */
if (isset($_POST['update_hw'])) {

    if (!isset($_POST['token_edit_hw'], $_SESSION['token_edit_hw']) || $_POST['token_edit_hw'] !== $_SESSION['token_edit_hw']) {
        $_SESSION['message'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $hw_id      = (int)($_POST['hw_id'] ?? 0);
    $deadlineRaw = trim($_POST['deadline'] ?? '');
    $video_raw   = $_POST['video_link'] ?? '';

    if ($hw_id <= 0 || $class_id <= 0) {
        $_SESSION['message'] = 'اطلاعات ناقص است.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    if ($deadlineRaw === '') {
        $_SESSION['message'] = 'مهلت تحویل نمی‌تواند خالی باشد.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $deadline = normalize_deadline($deadlineRaw);

    $video_link_norm = normalize_video_link($video_raw);
    if ($video_link_norm === false) {
        $_SESSION['message'] = 'لینک ویدیو معتبر نیست.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }
    $video_link = $video_link_norm; // null or valid url

    // enforce "either file OR video must exist" even on update (cannot end up with both empty)
    $q = $conn->prepare("SELECT file_path FROM {$HOMEWORKS_TABLE} WHERE id = ? AND class_id = ? LIMIT 1");
    $q->execute([$hw_id, $class_id]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        $_SESSION['message'] = 'تکلیف یافت نشد یا متعلق به این کلاس نیست.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

//    $existing_file = trim((string)($existing['file_path'] ?? ''));
//    if (($existing_file === '') && ($video_link === null || $video_link === '')) {
//        $_SESSION['message'] = 'برای تکلیف باید حداقل «فایل» یا «ویدیو» وجود داشته باشد. (نمی‌توانید هر دو را خالی کنید)';
//        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
//        exit;
//    }

    $stmt = $conn->prepare("UPDATE {$HOMEWORKS_TABLE}
                            SET deadline = ?, video_link = ?, updated_at = NOW()
                            WHERE id = ? AND class_id = ?
                            LIMIT 1");
    $stmt->execute([$deadline, $video_link, $hw_id, $class_id]);

    $_SESSION['message'] = '✅ تکلیف بروزرسانی شد.';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
    exit;
}

// token for professor answer (separate)
if (empty($_SESSION['token_prof'])) {
    $_SESSION['token_prof'] = bin2hex(random_bytes(32));
}
$token_prof = $_SESSION['token_prof'];

if (isset($_POST['save_prof_answer'])) {

    if (!isset($_POST['token_prof'], $_SESSION['token_prof']) || $_POST['token_prof'] !== $_SESSION['token_prof']) {
        $_SESSION['message'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $subId = (int)($_POST['sub_id'] ?? 0);
    $ans   = trim((string)($_POST['professors_answer'] ?? ''));

    // find the homework + grading rules for this submission
    $q = $conn->prepare("
        SELECT h.grade_type, h.min_grade, h.max_grade
        FROM {$SUBMISSIONS_TABLE} s
        JOIN {$HOMEWORKS_TABLE} h ON h.id = s.homework_id
        WHERE s.id = ?
        LIMIT 1
    ");
    $q->execute([$subId]);
    $gr = $q->fetch(PDO::FETCH_ASSOC);

    if (!$gr) {
        $_SESSION['message'] = 'تکلیف مربوط به این پاسخ پیدا نشد.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $grade_type = $gr['grade_type'] ?? 'numeric';

    if ($ans !== '') {
        if ($grade_type === 'scale') {
            $allowed = ['خیلی خوب','خوب','قابل قبول','نیاز به تلاش بیشتر'];
            if (!in_array($ans, $allowed, true)) {
                $_SESSION['message'] = 'نمره کیفی نامعتبر است.';
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
                exit;
            }
        } else {
            $ansClean = fa_to_en_digits($ans);
            if (!preg_match('/^\d+$/', $ansClean)) {
                $_SESSION['message'] = 'نمره عددی باید فقط عدد باشد.';
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
                exit;
            }
            $num = (int)$ansClean;
            $min = (int)($gr['min_grade'] ?? 0);
            $max = (int)($gr['max_grade'] ?? 100);
            if ($num < $min || $num > $max) {
                $_SESSION['message'] = "نمره باید بین $min و $max باشد.";
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
                exit;
            }
            $ans = (string)$num;
        }
    }

    if ($subId <= 0) {
        $_SESSION['message'] = 'شناسه پاسخ نامعتبر است.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
        exit;
    }

    $st = $conn->prepare("UPDATE {$SUBMISSIONS_TABLE} SET professors_answer = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
    $st->execute([$ans === '' ? null : $ans, $subId]);

    $_SESSION['message'] = '✅ جواب تکلیف ثبت شد.';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($_GET));
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
                        <?php unset($_SESSION['message']); } ?>

                    <!-- ADD FORM -->
                    <div class="col-lg-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-xl-12">
                                        <div>
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    افزودن تکلیف برای کلاس به شناسه
                                                    <span class="text-dark">
                                                        <?php echo htmlspecialchars((string)$class_id, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید برای کلاس انتخاب‌شده تکلیف جدید تعریف کنید. تکلیف ثبت‌شده به همین شناسه کلاس اختصاص پیدا می‌کند و دانش آموزان عضو کلاس می‌توانند آن را مشاهده و انجام دهند."
                                                      aria-label="راهنمای افزودن تکلیف برای کلاس">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                            </div>
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
                                            <input class="form-control fc-datepicker" name="deadline" placeholder="YYYY/MM/DD HH:MM" required>

                                            <br>
                                            <p class="mg-b-10">نوع نمره‌دهی:<span class="tx-danger">*</span></p>
                                            <select class="form-control" name="grade_type" id="grade_type" required>
                                                <option value="numeric">عددی</option>
                                                <option value="scale">کیفی</option>
                                            </select>

                                            <div id="numeric_box" style="margin-top:15px;">
                                                <p class="mg-b-10">حداقل نمره:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="min_grade" type="number" value="0" min="0">

                                                <br>
                                                <p class="mg-b-10">حداکثر نمره:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="max_grade" type="number" value="100" min="0">
                                            </div>

                                            <div id="scale_box" style="margin-top:15px; display:none;">
                                                <div class="alert alert-info mb-0">
                                                    گزینه‌های کیفی ثابت هستند:
                                                    <br>۱) خیلی خوب
                                                    <br>۲) خوب
                                                    <br>۳) قابل قبول
                                                    <br>۴) نیاز به تلاش بیشتر
                                                </div>
                                            </div>

                                            <br>
                                            <p class="mg-b-10">نوع تکلیف:<span class="tx-danger">*</span></p>
                                            <select class="form-control" name="homework_type" id="homework_type_select" onchange="toggleHomeworkType()" required>
                                                <option value="0">عمومی (برای همه دانش آموزان کلاس)</option>
                                                <option value="1">اختصاصی (انتخاب دانش آموزان خاص)</option>
                                            </select>

                                            <div id="private_students_box" style="margin-top:15px; display:none;" class="p-3 bg-light rounded border">
                                                <p class="mg-b-10 text-primary fw-bold">انتخاب دانش آموزان کلاس:</p>
                                                <select class="form-control" id="student_selector" onchange="addStudentToList()">
                                                    <option value="">— یک دانش آموز انتخاب کنید —</option>
                                                    <?php
                                                    $enrolled = $conn->prepare("SELECT d.username, p.fname, p.lname 
                                                FROM dbcourse d
                                                INNER JOIN dbpersonal p ON d.username = p.codemeli
                                                WHERE d.class_id = ?");
                                                    $enrolled->execute([$class_id]);
                                                    $students = $enrolled->fetchAll(PDO::FETCH_ASSOC);

                                                    foreach ($students as $student):
                                                        ?>
                                                        <option value="<?= htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($student['fname'] . ' ' . $student['lname'] . ' (' . $student['username'] . ')') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <p class="mg-b-10 mg-t-15 text-muted small">دانش آموزان انتخاب شده برای این تکلیف:</p>
                                                <div id="selected_students_container" class="d-flex flex-wrap gap-2 border bg-white p-2 rounded" style="min-height: 45px;">
                                                    <small class="text-muted dynamic-placeholder">هیچ دانش آموزی انتخاب نشده است.</small>
                                                </div>
                                            </div>
                                            <!-- ====================================================================== -->

                                            <br>
                                            <p class="mg-b-10">فایل تکلیف (اختیاری)</p>
                                            <input type="file" class="form-control" name="file"
                                                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png">

                                            <br><br>
                                            <p class="mg-b-10">لینک ویدیو (اختیاری)</p>
                                            <input class="form-control" name="video_link" placeholder="https://..." dir="ltr">
                                            <small class="text-muted">برای ثبت تکلیف، فایل یا ویدیو (حداقل یکی) لازم است.</small>

                                            <input type="hidden" name="token" value="<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>">
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
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                لیست تکالیف کلاس به شناسه
                                                <span class="text-dark">
                                                    <?php echo htmlspecialchars((string)$class_id, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید تکالیف ثبت‌شده برای کلاس انتخاب‌شده را مشاهده و مدیریت کنید. اطلاعات هر تکلیف و جزئیات مربوط به آن از این قسمت قابل بررسی است."
                                                  aria-label="راهنمای لیست تکالیف کلاس">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <?php
                                        $homeworks = [];

                                        // Check if admin has full access (roles 0, 51, 52)
                                        $fullAccessRoles = [0, 51, 52];
                                        $hasFullAccess = in_array((int)$adminRole, $fullAccessRoles, true);

                                        if ($hasFullAccess) {
                                            // Admins with roles 0, 51, 52 see ALL homeworks for this class
                                            $stmt = $conn->prepare("
                                            SELECT h.*,
                                                   (SELECT COUNT(*) FROM {$SUBMISSIONS_TABLE} s WHERE s.homework_id = h.id) AS answers_count
                                            FROM {$HOMEWORKS_TABLE} h
                                            WHERE h.class_id = ?
                                            ORDER BY h.created_at DESC
                                        ");
                                            $stmt->execute([$class_id]);
                                        } else {
                                            // Other admins only see homeworks THEY created
                                            $stmt = $conn->prepare("
                                        SELECT h.*,
                                               (SELECT COUNT(*) FROM {$SUBMISSIONS_TABLE} s WHERE s.homework_id = h.id) AS answers_count
                                        FROM {$HOMEWORKS_TABLE} h
                                        WHERE h.class_id = ?
                                          AND h.admin_username = ?
                                        ORDER BY h.created_at DESC
                                    ");
                                            $stmt->execute([$class_id, $currentUser]);
                                        }

                                        $homeworks = $stmt->fetchAll(PDO::FETCH_OBJ);
                                        ?>

                                        <?php if (Permision('permision_viewcourse') && $codecourse > 0) { ?>
                                            <button class="btn ripple btn-dark pd-x-20"
                                                    onclick="window.location.href='dars.php?code=<?= (int)$codecourse ?>'">
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
                                            <th>نوع تکلیف</th>
                                            <th>مهلت تحویل (شمسی)</th>
                                            <th>فایل</th>
                                            <th>ویدیو</th>
                                            <th>وضعیت</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($homeworks): $i = 1; foreach ($homeworks as $h): ?>
                                            <?php
                                            $deadline_jalali = deadline_to_jalali_input($h->deadline ?? '');
                                            $video_attr = htmlspecialchars((string)($h->video_link ?? ''), ENT_QUOTES, 'UTF-8');
                                            $deadline_attr = htmlspecialchars($deadline_jalali, ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <tr>
                                                <td class="wd-15"><?= $i ?></td>
                                                <td class="wd-25">
                                                   <p class="font-weight-bold"> <?= htmlspecialchars($h->title, ENT_QUOTES, 'UTF-8') ?></p>
                                                    <br>
                                                    <?php if (!empty($h->description)): ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-link p-0 me-1 text-decoration-none"
                                                                data-bs-toggle="popover"
                                                                data-bs-placement="top"
                                                                data-bs-content="<?= htmlspecialchars($h->description, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fe fe-info"></i>
                                                        </button>
                                                        <span class="td-description d-inline-block" style="max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle;">
                                                            <?= htmlspecialchars($h->description, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="wd-15">
                                                <?php if ($h->homework_type == 0)
                                                {
                                                    echo "عادی ";
                                                }
                                                elseif ($h->homework_type == 1){
                                                     echo "اختصاصی";
                                                }
                                                ?>
                                                </td>
                                                <td class="wd-20">
                                                    <?php
                                                    $raw = trim((string)$h->deadline);
                                                    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
                                                        echo '—';
                                                    } else {
                                                        $gY = (int)substr($raw, 0, 4);
                                                        $gM = (int)substr($raw, 5, 2);
                                                        $gD = (int)substr($raw, 8, 2);
                                                        if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
                                                            [$jY, $jM, $jD] = gregorian_to_jalali($gY, $gM, $gD);
                                                            $out = sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
                                                            echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                                        } else {
                                                            $out = substr($raw, 0, 10);
                                                            echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td class="wd-15">
                                                    <?php if (!empty($h->file_path)): ?>
                                                        <a href="<?= url($h->file_path) ?>" target="_blank" rel="noopener">دانلود</a>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>

                                                <td class="wd-15">
                                                    <?php if (!empty($h->video_link)): ?>
                                                        <a href="<?= htmlspecialchars($h->video_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">مشاهده</a>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>

                                                <td class="wd-15">
                                                    <?php
                                                    $st = $h->status ?? 'pending';
                                                    if ($st === 'approved') {
                                                        echo '<span class="badge bg-success">تایید شده</span>';
                                                    } elseif ($st === 'rejected') {
                                                        echo '<span class="badge bg-danger">رد شده</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">در حال بررسی</span>';
                                                    }
                                                    ?>
                                                </td>

                                                <td class="wd-20">
                                                    <a class="btn btn-sm btn-outline-danger"
                                                       href="homeworks.php?delete_hw=<?= (int)$h->id ?>&class_id=<?= (int)$class_id ?>"
                                                       onclick="return confirm('حذف تکلیف؟');">
                                                        حذف
                                                    </a>

                                                    <a class="btn btn-sm btn-outline-primary ms-2"
                                                       href="homeworks.php?answers=<?= (int)$h->id ?>&class_id=<?= (int)$class_id ?>#answers">
                                                        پاسخ‌ها
                                                        <?php if ((int)$h->answers_count > 0): ?>
                                                            <span class="badge bg-primary ms-1"><?= (int)$h->answers_count ?></span>
                                                        <?php endif; ?>
                                                    </a>

                                                    <!-- ONE EDIT BUTTON: deadline + video -->
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-warning ms-2"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editHwModal"
                                                            data-hw-id="<?= (int)$h->id ?>"
                                                            data-deadline="<?= $deadline_attr ?>"
                                                            data-video-link="<?= $video_attr ?>">
                                                        ویرایش
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php $i++; endforeach; else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">تکلیفی ثبت نشده است.</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php
                                // ---------- Answers panel (same page) ----------
                                if (isset($_GET['answers']) && ctype_digit((string)$_GET['answers'])):
                                    $hwId = (int)$_GET['answers'];

                                    $hwStmt = $conn->prepare("SELECT id, title, grade_type, min_grade, max_grade, homework_type
        FROM {$HOMEWORKS_TABLE}
        WHERE id = ? AND class_id = ? LIMIT 1");
                                    $hwStmt->execute([$hwId, $class_id]);
                                    $hwRow = $hwStmt->fetch(PDO::FETCH_OBJ);

                                    if ($hwRow):
                                        // 2. Build conditional queries based on Homework Type (Public vs Private)
                                        if ((int)$hwRow->homework_type === 1) {
                                            // Private homework: Get targeted students from the pivot table, left joining submissions
                                            $subQuery = "
                SELECT 
                    pws.username,
                    p.fname, p.lname,
                    s.id AS submission_id, s.submission_text, s.file_path,
                    s.professors_answer, s.submitted_at, s.updated_at
                FROM private_homework_students pws
                INNER JOIN dbpersonal p ON pws.username = p.codemeli
                LEFT JOIN {$SUBMISSIONS_TABLE} s ON (s.student_username = pws.username AND s.homework_id = ?)
                WHERE pws.homework_id = ?
                ORDER BY (s.submitted_at IS NULL) ASC, s.submitted_at DESC
            ";
                                            $subStmt = $conn->prepare($subQuery);
                                            $subStmt->execute([$hwId, $hwId]);
                                        } else {
                                            // Public homework: Get all class students from dbcourse, left joining submissions
                                            $subQuery = "
                SELECT 
                    d.username AS student_username,
                    p.fname, p.lname,
                    s.id AS submission_id, s.submission_text, s.file_path,
                    s.professors_answer, s.submitted_at, s.updated_at
                FROM dbcourse d
                INNER JOIN dbpersonal p ON d.username = p.codemeli
                LEFT JOIN {$SUBMISSIONS_TABLE} s ON (s.student_username = d.username AND s.homework_id = ?)
                WHERE d.class_id = ?
                ORDER BY (s.submitted_at IS NULL) ASC, s.submitted_at DESC
            ";
                                            $subStmt = $conn->prepare($subQuery);
                                            $subStmt->execute([$hwId, $class_id]);
                                        }

                                        $subs = $subStmt->fetchAll(PDO::FETCH_OBJ);
                                        ?>
                                        <div id="answers" class="mt-4">
                                            <h6 class="main-content-label mb-2">
                                                وضعیت پاسخ‌های تکلیف:
                                                <span class="text-dark"><?= htmlspecialchars($hwRow->title, ENT_QUOTES, 'UTF-8') ?></span>
                                            </h6>

                                            <div class="table-responsive">
                                                <table id="example5" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                    <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>دانش آموز</th>
                                                        <th>وضعیت ارسال</th>
                                                        <th>متن پاسخ</th>
                                                        <th>فایل</th>
                                                        <th>زمان ارسال</th>
                                                        <th>نمره تمرین</th>
                                                        <th>عملیات</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if ($subs): $k = 1; foreach ($subs as $s):
                                                        $hasSubmitted = !empty($s->submission_id);
                                                        ?>
                                                        <tr class="<?= !$hasSubmitted ? 'bg-light text-muted' : '' ?>">
                                                            <td><?= $k ?></td>
                                                            <td>
                                                                <?= htmlspecialchars(($s->fname ?? '') . ' ' . ($s->lname ?? '') , ENT_QUOTES, 'UTF-8') ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($hasSubmitted): ?>
                                                                    <span class="badge bg-success-light text-success">ارسال شده</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger-light text-danger">عدم ارسال</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?= $hasSubmitted ? htmlspecialchars((string)$s->submission_text, ENT_QUOTES, 'UTF-8') : '<em class="text-muted">پاسخی ثبت نشده</em>' ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($hasSubmitted && !empty($s->file_path)): ?>
                                                                    <a href="<?= url($s->file_path) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-primary-light">دانلود فایل</a>
                                                                <?php else: ?>
                                                                    —
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $raw = trim((string)($s->submitted_at ?? ''));
                                                                if ($hasSubmitted && $raw !== '' && $raw !== '0000-00-00 00:00:00') {
                                                                    $gY = (int)substr($raw, 0, 4);
                                                                    $gM = (int)substr($raw, 5, 2);
                                                                    $gD = (int)substr($raw, 8, 2);
                                                                    if ($gY > 1900 && $gM >= 1 && $gM <= 12 && $gD >= 1 && $gD <= 31 && function_exists('gregorian_to_jalali')) {
                                                                        [$jY, $jM, $jD] = gregorian_to_jalali($gY, $gM, $gD);
                                                                        $out = sprintf('%04d/%02d/%02d', $jY, $jM, $jD);
                                                                    } else {
                                                                        $out = substr($raw, 0, 10);
                                                                    }
                                                                    echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                                                } else {
                                                                    echo '—';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $pa = $s->professors_answer ?? '';
                                                                echo ($pa !== '' && $pa !== null) ? htmlspecialchars($pa, ENT_QUOTES, 'UTF-8') : '<span class="text-warning">نامشخص</span>';
                                                                ?>
                                                            </td>

                                                            <td>
                                                                <?php if ($hasSubmitted): ?>
                                                                    <button type="button"
                                                                            class="btn btn-sm btn-outline-success"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#profAnswerModal"
                                                                            data-sub-id="<?= (int)$s->submission_id ?>"
                                                                            data-prof-answer="<?= htmlspecialchars((string)($s->professors_answer ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                        ثبت / ویرایش نمره
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-sm btn-light disabled" disabled>تکلیفی ثبت نشده</button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                        <?php $k++; endforeach; else: ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center">هیچ دانش‌آموزی به این تکلیف تخصیص نیافته است.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php
                                    endif;
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>

                </div><!-- row -->
            </div><!-- inner-body -->
        </div><!-- container-fluid -->
    </div><!-- main-content -->

    <!-- Professor Answer Modal -->
    <div class="modal fade" id="profAnswerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ثبت جواب استاد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="sub_id" id="prof_sub_id">
                    <input type="hidden" name="token_prof" value="<?= htmlspecialchars($token_prof, ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden" id="hw_grade_type" value="<?= htmlspecialchars($hwRow->grade_type ?? 'numeric', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" id="hw_min_grade" value="<?= (int)($hwRow->min_grade ?? 0) ?>">
                    <input type="hidden" id="hw_max_grade" value="<?= (int)($hwRow->max_grade ?? 100) ?>">

                    <div id="prof_numeric_box">
                        <label class="mb-2">نمره (عددی)</label>
                        <input type="number" class="form-control" name="professors_answer" id="prof_answer_input"
                               placeholder="مثلاً: 20">
                        <small class="text-muted" id="prof_range_hint"></small>
                    </div>

                    <div id="prof_scale_box" style="display:none;">
                        <label class="mb-2">نمره (کیفی)</label>
                        <select class="form-control" name="professors_answer" id="prof_answer_select">
                            <option value="">— انتخاب کنید —</option>
                            <option value="خیلی خوب">خیلی خوب</option>
                            <option value="خوب">خوب</option>
                            <option value="قابل قبول">قابل قبول</option>
                            <option value="نیاز به تلاش بیشتر">نیاز به تلاش بیشتر</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                    <button type="submit" name="save_prof_answer" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT HOMEWORK Modal (deadline + video_link) -->
    <div class="modal fade" id="editHwModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش تکلیف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="hw_id" id="edit_hw_id">
                    <input type="hidden" name="token_edit_hw" value="<?= htmlspecialchars($token_edit_hw, ENT_QUOTES, 'UTF-8') ?>">

                    <label class="mb-2">مهلت تحویل (شمسی)</label>
                    <input class="form-control fc-datepicker"
                           name="deadline"
                           id="edit_deadline_input"
                           placeholder="YYYY/MM/DD"
                           required>

                    <div class="mt-3"></div>

                    <label class="mb-2">لینک ویدیو (اختیاری)</label>
                    <input class="form-control" name="video_link" id="edit_video_input" placeholder="https://..." dir="ltr">
                    <small class="text-muted">برای حذف لینک، این فیلد را خالی ذخیره کنید.</small>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                    <button type="submit" name="update_hw" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /**
         * ======================================================================
         * 1. GLOBAL ACCESS CONTROL HANDLERS (Private/Public Assignment Toggles)
         * ======================================================================
         */

// Toggle the visibility of the student assignment pool based on selected type
        function toggleHomeworkType() {
            const typeSelect = document.getElementById('homework_type_select');
            const privateBox = document.getElementById('private_students_box');
            const container  = document.getElementById('selected_students_container');

            if (!typeSelect || !privateBox) return;

            if (typeSelect.value === "1") {
                privateBox.style.display = "block";
            } else {
                privateBox.style.display = "none";
                if (container) {
                    container.innerHTML = '<small class="text-muted dynamic-placeholder">هیچ دانش آموزی انتخاب نشده است.</small>';
                }
            }
        }

        // Add student from dropdown selection into the active homework access pool
        function addStudentToList() {
            const selector  = document.getElementById('student_selector');
            const container = document.getElementById('selected_students_container');

            if (!selector || !container) return;

            const username = selector.value;
            const fullName = selector.options[selector.selectedIndex].text;

            if (!username) return;

            // Guard constraint: Stop duplicate additions
            if (document.getElementById(`badge-${username}`)) {
                selector.value = "";
                return;
            }

            // Drop clean layout informational notice if present
            const placeholder = container.querySelector('.dynamic-placeholder');
            if (placeholder) placeholder.remove();

            // Render Tag Component Node
            const badge = document.createElement('div');
            badge.id = `badge-${username}`;
            badge.className = 'badge bg-primary d-flex align-items-center p-2 m-1 text-white rounded fs-13';
            badge.style.fontWeight = 'normal';
            badge.style.gap = '8px';
            badge.innerHTML = `
        <span>${fullName}</span>
        <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.65rem; background-color: transparent; border: none; color: white;" onclick="removeStudentFromList('${username}')"></button>
        <input type="hidden" name="selected_students[]" value="${username}">
    `;

            container.appendChild(badge);
            selector.value = ""; // Flush pick element selection target focus
        }

        // Remove student identifier out of the assigned targeted list
        function removeStudentFromList(username) {
            const badge = document.getElementById(`badge-${username}`);
            if (badge) {
                badge.remove();
            }

            const container = document.getElementById('selected_students_container');
            if (container && container.querySelectorAll('.badge').length === 0) {
                container.innerHTML = '<small class="text-muted dynamic-placeholder">هیچ دانش آموزی انتخاب نشده است.</small>';
            }
        }


        /**
         * ======================================================================
         * 2. DOM CONTENT DRIVEN INITIALIZERS (Modal Bindings & Grade Interceptors)
         * ======================================================================
         */
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Bootstrap popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl, {
                    trigger: 'click focus'
                })
            });

            const table = $("#example5").DataTable({
                stateSave: true,
                language: {
                    searchPlaceholder: "جستجو کردن...",
                    sSearch: "",
                    lengthMenu: "_MENU_ موارد/صفحه"
                },
                drawCallback: function(settings) {
                    let titleColumnIndex = -1;

                    // 1. Find the index of the "عنوان" column dynamically
                    $(this.api().table().header()).find('th').each(function(index) {
                        if ($(this).text().trim() === 'عنوان') {
                            titleColumnIndex = index;
                            return false; // Exit loop
                        }
                    });

                    if (titleColumnIndex !== -1) {
                        // 2. Apply the wrapping styles to the <a> tags in the specific column index
                        const columnIndexSelector = `:nth-child(${titleColumnIndex + 1}) a`;

                        $(this.api().table().body()).find('tr td' + columnIndexSelector).css({
                            'display': 'block',
                            'word-break': 'break-word',
                            'overflow-wrap': 'break-word',
                            'white-space': 'normal'
                        });
                    }
                }
            });

            /* --- [A] Edit Homework Modal Setup --- */
            const editModal = document.getElementById('editHwModal');
            if (editModal) {
                const hwIdInput     = document.getElementById('edit_hw_id');
                const deadlineInput = document.getElementById('edit_deadline_input');
                const videoInput    = document.getElementById('edit_video_input');

                if (deadlineInput) deadlineInput.setAttribute('autocomplete', 'off');
                if (videoInput)    videoInput.setAttribute('autocomplete', 'off');

                editModal.addEventListener('show.bs.modal', function (event) {
                    const btn = event.relatedTarget;
                    if (!btn) return;
                    if (hwIdInput)     hwIdInput.value     = btn.getAttribute('data-hw-id')      || '';
                    if (deadlineInput) deadlineInput.value = btn.getAttribute('data-deadline')   || '';
                    if (videoInput)    videoInput.value    = btn.getAttribute('data-video-link') || '';
                });

                editModal.addEventListener('hidden.bs.modal', function () {
                    if (hwIdInput)     hwIdInput.value     = '';
                    if (deadlineInput) deadlineInput.value = '';
                    if (videoInput)    videoInput.value    = '';
                });
            }

            /* --- [B] Professor Review & Grading Modal Setup --- */
            const profModal = document.getElementById('profAnswerModal');
            if (profModal) {
                const gt          = document.getElementById('hw_grade_type');
                const mn          = document.getElementById('hw_min_grade');
                const mx          = document.getElementById('hw_max_grade');
                const numericBox  = document.getElementById('prof_numeric_box');
                const scaleBox    = document.getElementById('prof_scale_box');
                const inputNum    = document.getElementById('prof_answer_input');
                const selectSc    = document.getElementById('prof_answer_select');
                const rangeHint   = document.getElementById('prof_range_hint');
                const subIdInput  = document.getElementById('prof_sub_id');

                function applyMode(existingValue) {
                    const isScale = (gt && gt.value === 'scale');

                    if (isScale) {
                        if (numericBox) numericBox.style.display = 'none';
                        if (scaleBox)   scaleBox.style.display   = 'block';
                        if (inputNum)   inputNum.disabled        = true;
                        if (selectSc) {
                            selectSc.disabled = false;
                            selectSc.value    = (existingValue || '').trim();
                        }
                        if (inputNum) inputNum.value = '';
                    } else {
                        if (numericBox) numericBox.style.display = 'block';
                        if (scaleBox)   scaleBox.style.display   = 'none';
                        if (inputNum)   inputNum.disabled        = false;
                        if (selectSc)   selectSc.disabled        = true;

                        const minV = mn ? mn.value : 0;
                        const maxV = mx ? mx.value : 100;

                        if (inputNum) {
                            inputNum.min   = minV;
                            inputNum.max   = maxV;
                            inputNum.value = (existingValue || '').trim();
                        }
                        if (rangeHint) {
                            rangeHint.textContent = 'بازه مجاز: ' + minV + ' تا ' + maxV;
                        }
                        if (selectSc) selectSc.value = '';
                    }
                }

                profModal.addEventListener('show.bs.modal', function (event) {
                    const btn = event.relatedTarget;
                    if (!btn) return;
                    if (subIdInput) subIdInput.value = btn.getAttribute('data-sub-id') || '';
                    const existing = btn.getAttribute('data-prof-answer') || '';
                    applyMode(existing);
                });

                profModal.addEventListener('hidden.bs.modal', function () {
                    if (subIdInput) subIdInput.value = '';
                    if (inputNum)   inputNum.value   = '';
                    if (selectSc)   selectSc.value   = '';
                });
            }

            /* --- [C] Grading Field Toggles inside CREATE form --- */
            const createGradeType = document.getElementById('grade_type');
            const numericBox      = document.getElementById('numeric_box');
            const scaleBox        = document.getElementById('scale_box');

            if (createGradeType) {
                function toggleCreateGradingLayout() {
                    if (createGradeType.value === 'scale') {
                        if (numericBox) numericBox.style.display = 'none';
                        if (scaleBox)   scaleBox.style.display   = 'block';
                    } else {
                        if (numericBox) numericBox.style.display = 'block';
                        if (scaleBox)   scaleBox.style.display   = 'none';
                    }
                }

                createGradeType.addEventListener('change', toggleCreateGradingLayout);
                toggleCreateGradingLayout(); // Execute initialization baseline check
            }
        });

    </script>
    <?php include '../../footers.php'; ?>
</div><!-- page -->
</body>
</html>
