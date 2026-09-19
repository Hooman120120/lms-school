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
// Protecting against SESSION Hijacking
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
function jalaliToGregorianDate($jalaliDate) {
    if (empty($jalaliDate)) return null;
    $parts = explode('/', $jalaliDate);
    if (count($parts) != 3) return null;
    list($jy, $jm, $jd) = $parts;
    $greg = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    return sprintf("%04d-%02d-%02d", $greg[0], $greg[1], $greg[2]);
}

$adminrole = $_SESSION['admin']['adminrole'];
$admin_id  = $_SESSION['admin']['idadmin'] ?? $_SESSION['admin']['id'] ?? 0;
$class_id  = isset($_GET['class']) ? intval($_GET['class']) : 0;
$is_teacher = ((int)$adminrole === 3);
$school_role_key = SchoolStaffRoleKey((string)($_SESSION['admin']['user'] ?? ''));

if ($class_id <= 0) {
    die('فاقد کلاس');
}
RequireTeacherPermission('teacher_perm_attendance', url('admin/course/Active-course.php'));
if ($is_teacher && !TeacherHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دفتر کلاسی این کلاس دسترسی ندارید.');
}
if (!$is_teacher && StaffCourseScopeRequired() && !StaffHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دفتر کلاسی این پایه و کلاس دسترسی ندارید.');
}
if (!$is_teacher && !Permision('permision_viewcourse') && !Permision('register_class_permission')) {
    http_response_code(403);
    exit('شما به دفتر کلاسی دسترسی ندارید.');
}

// مدیر، معاون کل و معاون پایه در دامنه مجاز خود مدیریت کامل دارند.
// معلم نیز فقط رکوردهای متعلق به خودش را مدیریت می‌کند؛ سایر سمت‌ها به مجوز صریح نیاز دارند.
$has_full_permission = $is_teacher
    || in_array($school_role_key, ['manager', 'general_deputy', 'grade_deputy'], true)
    || Permision('register_class_permission');

// استاد (نقش ۳) فقط کار خودش را می بیند؛ نقش های بالاتر همه چیز را می بینند
$sees_all_columns = !$is_teacher;

// شناسه استادی که رکوردها باید متعلق به او باشند (برای نقش های بالاتر محدودیتی نیست)
$scope_teacher_id = $is_teacher ? (int)$admin_id : null;

/**
 * بررسی اینکه ستون متعلق به همین کلاس (و در صورت استاد بودن، به خود کاربر) است.
 * چون حذف و ویرایش برای همه باز شده، شناسه ورودی باید سمت سرور اعتبارسنجی شود.
 */
function registerClassEventInScope(?array $event, int $classId, ?int $teacherId): bool
{
    if (!$event || (int)$event['class_id'] !== $classId) {
        return false;
    }
    return ($teacherId === null) || ((int)$event['teacher_id'] === $teacherId);
}

$msg = '';
$msg_type = '';
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    $msg_type = $_SESSION['flash_msg_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_msg_type']);
}

$actor = $_SESSION['admin']['idadmin'];

// -------------------------------------------------------------
// 1. CREATE EVENT HANDLER
// -------------------------------------------------------------

// حذف یکجای یک دسته ستون
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event_group' && $has_full_permission) {
    $group_uid = trim($_POST['group_uid'] ?? '');
    // استاد فقط دسته ای را می تواند حذف کند که همه ستون هایش متعلق به خودش باشد
    if ($scope_teacher_id !== null && $group_uid !== '') {
        $owned = true;
        foreach (getRegisterClassEvents($class_id, 0, 0) as $ev) {
            if (($ev['group_uid'] ?? '') === $group_uid && (int)$ev['teacher_id'] !== $scope_teacher_id) {
                $owned = false;
                break;
            }
        }
        if (!$owned) {
            $_SESSION['flash_msg'] = 'این ستون ها در دسترس شما نیست.';
            $_SESSION['flash_msg_type'] = 'danger';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    if ($group_uid !== '' && deleteRegisterClassEventGroup($class_id, $group_uid)) {
        registerClassLog($class_id, '', '', null, null, 'حذف دسته ستون ها', $actor, 'delete_event');
        $_SESSION['flash_msg'] = 'ستون های این تاریخ حذف شد.';
        $_SESSION['flash_msg_type'] = 'success';
    } else {
        $_SESSION['flash_msg'] = 'خطا در حذف ستون ها.';
        $_SESSION['flash_msg_type'] = 'danger';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_event') {
    $jalali_date = trim($_POST['event_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type        = isset($_POST['type']) ? (int)$_POST['type'] : 0;

    // عنوان ستون از روی نوع انتخاب شده تعیین می شود
    $title = '';
    foreach (registerClassColumnKinds() as $kindRow) {
        if ((int)$kindRow['type'] === $type) {
            $title = $kindRow['title'];
            break;
        }
    }

    // تاریخ اختیاری است؛ اگر وارد نشود ستون بدون تاریخ ساخته می شود
    $gregorian_date = ($jalali_date !== '') ? jalaliToGregorianDate($jalali_date) : null;

    if ((int)$adminrole === 3) {
        $assigned_teacher_id = (int)$admin_id;
    } else {
        $assigned_teacher_id = (int)($_POST['teacher_id'] ?? 0);
    }

    if (!empty($title) && $assigned_teacher_id > 0 && $admin_id > 0) {
        $newEventId = createRegisterClassEvent($class_id, (int)$admin_id, $assigned_teacher_id, $title, $gregorian_date, $type, $description);

        if ($newEventId) {
            registerClassLog($class_id, $title, '', null, null, $description, $actor, 'create_event');
            $_SESSION['flash_msg'] = 'ستون جدید با موفقیت ثبت شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ثبت ستون.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'لطفاً نوع ستون' . ((int)$adminrole === 3 ? '' : m('msg.choose_column_and_teacher')) . ' را انتخاب کنید.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 2. SAVE GRADE HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_grade') {
    $event_id     = (int)($_POST['event_id'] ?? 0);
    $student_user = trim($_POST['student_id'] ?? '');
    $score_input  = ($_POST['grade_val'] !== '' && $_POST['grade_val'] !== null) ? (float)$_POST['grade_val'] : null;

    // ارزیابی کیفی فقط برای ستون نمره کلاسی ارسال می شود؛ صفر یعنی پاک کردن
    $quality_input = null;
    if (isset($_POST['quality_val'])) {
        $q = (int)$_POST['quality_val'];
        $quality_input = array_key_exists($q, registerClassQualityOptions()) ? $q : 0;
    }

    // ستون باید متعلق به همین کلاس باشد و برای استاد، متعلق به خودش
    if (!registerClassEventInScope($event_id > 0 ? getSingleRegisterClassEvent($event_id) : null, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($class_id > 0 && $event_id > 0 && !empty($student_user) && ($score_input !== null || $quality_input !== null)) {
        if (saveStudentRegisterClassEntry($class_id, $event_id, $student_user, $score_input, null, null, $actor, null, $quality_input)) {
            $_SESSION['flash_msg'] = 'نمره با موفقیت ثبت شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ثبت نمره.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'اطلاعات نمره ارسالی نامعتبر است.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 2.b SAVE NOTE HANDLER (ستون توضیحات)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note') {
    $event_id     = (int)($_POST['event_id'] ?? 0);
    $student_user = trim($_POST['student_id'] ?? '');
    $note_input   = trim($_POST['note_val'] ?? '');

    // ستون باید متعلق به همین کلاس باشد و برای استاد، متعلق به خودش
    if (!registerClassEventInScope($event_id > 0 ? getSingleRegisterClassEvent($event_id) : null, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($class_id > 0 && $event_id > 0 && !empty($student_user)) {
        if (saveStudentRegisterClassEntry($class_id, $event_id, $student_user, null, null, null, $actor, $note_input)) {
            $_SESSION['flash_msg'] = ($note_input === '') ? 'توضیحات حذف شد.' : 'توضیحات با موفقیت ثبت شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ثبت توضیحات.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'اطلاعات توضیحات ارسالی نامعتبر است.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 3. SAVE ATTENDANCE HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
    $event_id     = (int)($_POST['event_id'] ?? 0);
    $student_user = trim($_POST['student_id'] ?? '');
    $status_code  = ($_POST['status_code'] !== '' && $_POST['status_code'] !== null) ? (int)$_POST['status_code'] : null;

    // ستون باید متعلق به همین کلاس باشد و برای استاد، متعلق به خودش
    if (!registerClassEventInScope($event_id > 0 ? getSingleRegisterClassEvent($event_id) : null, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($class_id > 0 && $event_id > 0 && !empty($student_user) && $status_code !== null) {
        if (saveStudentRegisterClassEntry($class_id, $event_id, $student_user, null, $status_code, null, $actor)) {
            $_SESSION['flash_msg'] = 'وضعیت حضور و غیاب ثبت شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ثبت حضور و غیاب.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'وضعیت حضور و غیاب نامعتبر است.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 4. DELETE EVENT HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event' && $has_full_permission) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $eventInfo = $event_id > 0 ? getSingleRegisterClassEvent($event_id) : null;
    if (!registerClassEventInScope($eventInfo, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if ($event_id > 0) {
        $event_title = $eventInfo['title'] ?? '';
        if (deleteRegisterClassEvent($event_id)) {
            registerClassLog($class_id, $event_title, '', null, null, '', $actor, 'delete_event');
            $_SESSION['flash_msg'] = 'رویداد با موفقیت حذف شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در حذف رویداد.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 5. EDIT EVENT HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_event' && $has_full_permission) {
    $event_id     = (int)($_POST['event_id'] ?? 0);
    $jalali_date  = trim($_POST['event_date'] ?? '');
    $type         = (int)($_POST['type'] ?? 0);
    // تاریخ در ویرایش هم اختیاری است
    $gregorian_date = ($jalali_date !== '') ? jalaliToGregorianDate($jalali_date) : null;

    $eventInfo = $event_id > 0 ? getSingleRegisterClassEvent($event_id) : null;
    if (!registerClassEventInScope($eventInfo, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // عنوان ستون قابل تغییر نیست و همیشه از روی نوع ستون تعیین می شود
    $title = '';
    foreach (registerClassColumnKinds() as $kindRow) {
        if ((int)$kindRow['type'] === $type) {
            $title = $kindRow['title'];
            break;
        }
    }

    if ($event_id > 0 && !empty($title)) {
        if (updateRegisterClassEvent($event_id, $title, $gregorian_date, $type)) {
            registerClassLog($class_id, $title, '', null, null, 'ویرایش ستون', $actor, 'edit_event');
            $_SESSION['flash_msg'] = 'ستون با موفقیت ویرایش شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ویرایش ستون.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'لطفاً نوع ستون را انتخاب کنید.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 6. DELETE GRADE/ATTENDANCE ENTRY
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_entry' && $has_full_permission) {
    $event_id     = (int)($_POST['event_id'] ?? 0);
    $student_user = trim($_POST['student_id'] ?? '');
    $eventInfo = $event_id > 0 ? getSingleRegisterClassEvent($event_id) : null;
    if (!registerClassEventInScope($eventInfo, $class_id, $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این ستون در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if ($event_id > 0 && !empty($student_user)) {
        $event_title = $eventInfo['title'] ?? '';
        if (deleteStudentRegisterClassEntry($class_id, $event_id, $student_user)) {
            registerClassLog($class_id, $event_title, $student_user, null, null, 'حذف رکورد', $actor, 'delete_entry');
            $_SESSION['flash_msg'] = 'رکورد با موفقیت حذف شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در حذف رکورد.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 7. SAVE DISCIPLINARY HANDLER
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_discipline') {
    $student_user = trim($_POST['student_id'] ?? '');
    $reason       = trim($_POST['discipline_reason'] ?? '');

    if ($class_id > 0 && !empty($student_user) && !empty($reason)) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO register_class_discipline (username, reason, class_id, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt->execute([$student_user, $reason, $class_id, $actor])) {
            registerClassLog($class_id, '', $student_user, null, null, $reason, $actor, 'discipline');
            $_SESSION['flash_msg'] = 'مورد انضباطی با موفقیت ثبت شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ثبت مورد انضباطی.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'لطفاً علت را وارد کنید.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 8. DELETE DISCIPLINARY HANDLER
// -------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_discipline' && $has_full_permission) {
    $discipline_id = (int)($_POST['discipline_id'] ?? 0);
    // مورد انضباطی باید متعلق به همین کلاس باشد و برای استاد، ثبت شده توسط خودش
    $discInfo = $discipline_id > 0 ? getSingleDisciplineEntry($discipline_id) : null;
    if (!$discInfo
        || (int)$discInfo['class_id'] !== $class_id
        || ($scope_teacher_id !== null && (int)$discInfo['created_by'] !== $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این مورد انضباطی در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($discipline_id > 0) {
        if (deleteDisciplineEntry($discipline_id)) {
            registerClassLog($class_id, '', $discInfo['username'], null, null, 'حذف مورد انضباطی: ' . ($discInfo['reason'] ?? ''), $actor, 'delete_discipline');
            $_SESSION['flash_msg'] = 'مورد انضباطی با موفقیت حذف شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در حذف مورد انضباطی.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 9. EDIT DISCIPLINARY HANDLER
// -------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_discipline' && $has_full_permission) {
    $discipline_id = (int)($_POST['discipline_id'] ?? 0);
    $reason        = trim($_POST['discipline_reason'] ?? '');

    // مورد انضباطی باید متعلق به همین کلاس باشد و برای استاد، ثبت شده توسط خودش
    $discInfo = $discipline_id > 0 ? getSingleDisciplineEntry($discipline_id) : null;
    if (!$discInfo
        || (int)$discInfo['class_id'] !== $class_id
        || ($scope_teacher_id !== null && (int)$discInfo['created_by'] !== $scope_teacher_id)) {
        $_SESSION['flash_msg'] = 'این مورد انضباطی در دسترس شما نیست.';
        $_SESSION['flash_msg_type'] = 'danger';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($discipline_id > 0 && !empty($reason)) {
        if (updateDisciplineEntry($discipline_id, $reason)) {
            registerClassLog($class_id, '', $discInfo['username'], null, null, 'ویرایش مورد انضباطی: ' . $reason, $actor, 'edit_discipline');
            $_SESSION['flash_msg'] = 'مورد انضباطی با موفقیت ویرایش شد.';
            $_SESSION['flash_msg_type'] = 'success';
        } else {
            $_SESSION['flash_msg'] = 'خطا در ویرایش مورد انضباطی.';
            $_SESSION['flash_msg_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_msg'] = 'لطفاً علت را وارد کنید.';
        $_SESSION['flash_msg_type'] = 'warning';
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------
// 10. هفته های سال تحصیلی (از شهریور تا خرداد)
// توابع ساخت هفته ها در inc/function.php هستند تا خروجی اکسل هم از آن ها استفاده کند.
// -------------------------------------------------------------
$academicYear   = registerClassAcademicYear(time());
$weeks          = registerClassWeeks($academicYear);
$currentWeekKey = registerClassWeekKeyFor($weeks, time());

// هفته ی انتخاب شده از آدرس خوانده می شود؛ در غیر این صورت هفته ی جاری تقویم شمسی
$selected_week_key = $_GET['week'] ?? '';
$all_weeks_selected = ($selected_week_key === 'all');
$selected_week = null;
foreach ($weeks as $week) {
    if (!$all_weeks_selected && $week['key'] === $selected_week_key) {
        $selected_week = $week;
        break;
    }
}
if ($selected_week === null) {
    foreach ($weeks as $week) {
        if ($week['key'] === $currentWeekKey) {
            $selected_week = $week;
            break;
        }
    }
}
if ($selected_week === null) {
    $selected_week = $weeks[0];
}
$selected_week_key = $selected_week['key'];
$last_week = $weeks[count($weeks) - 1];
$week_scope_label = $all_weeks_selected ? 'همه‌ی هفته‌ها' : $selected_week['label'];
$week_scope_range = $all_weeks_selected
    ? jdate('Y/m/d', strtotime($weeks[0]['start']), '', 'Asia/Tehran', 'fa') . ' تا ' . jdate('Y/m/d', strtotime($last_week['end']), '', 'Asia/Tehran', 'fa')
    : $selected_week['range'];

// تاریخ پیش فرض ستون جدید: امروز اگر داخل همین هفته باشد، وگرنه شنبه ی همان هفته
$today_iso = date('Y-m-d');
$default_column_date = ($today_iso >= $selected_week['start'] && $today_iso <= $selected_week['end'])
    ? jdate('Y/m/d', time(), '', 'Asia/Tehran', 'en')
    : jdate('Y/m/d', strtotime($selected_week['start']), '', 'Asia/Tehran', 'en');

$students = getRegisterClassStudents($class_id);

// دامنه دید: استاد فقط ستون ها و رکوردهای خودش را می بیند، نقش های بالاتر همه را
if ($sees_all_columns) {
    $events = getRegisterClassEvents($class_id, 0, 0);
    $evaluations = getRegisterClassEvaluations($class_id, 0, 0);
    $attendances = getRegisterClassAttendance($class_id, 0, 0);
    $disciplinaries = getClassDisciplinaries($class_id, 0, 0);
} else {
    $events = getRegisterClassEvents($class_id, $admin_id, (int)$adminrole);
    $evaluations = getRegisterClassEvaluations($class_id, $admin_id, (int)$adminrole);
    $attendances = getRegisterClassAttendance($class_id, $admin_id, (int)$adminrole);
    $disciplinaries = getClassDisciplinaries($class_id, $admin_id, (int)$adminrole);
}

// فقط ستون های همین هفته نمایش داده می شوند؛ قاعده ی تعیین هفته ی هر ستون
// در registerClassEventWeekKey است تا خروجی اکسل هم دقیقاً همین را ببیند.
if (!$all_weeks_selected) {
    $events = array_values(array_filter($events, function ($evt) use ($selected_week, $weeks) {
        return registerClassEventWeekKey($evt, $weeks) === $selected_week['key'];
    }));
}

$eventTeacherIds = array_unique(array_column($events, 'teacher_id'));
// در سربرگ ستون ها نام و نام خانوادگی استاد نمایش داده می شود، نه نام کاربری
$teacherUsernames = getTeacherFullNamesByIds($eventTeacherIds);

$discipline_lookup = [];
foreach ($disciplinaries as $disc) {
    $timestamp = strtotime($disc['created_at']);
    $jalali_date = $timestamp ? jdate('Y/m/d H:i', $timestamp) : $disc['created_at'];

    $discipline_lookup[$disc['username']][] = [
        'id'         => $disc['id'],
        'reason'     => $disc['reason'],
        'created_at' => $jalali_date,
        'created_by' => $disc['created_by_username'] ?? 'نامشخص'
    ];
}
$teachers_list = ((int)$adminrole !== 3) ? getTeachersList() : [];
$log_lookup = [];

foreach ($evaluations as $row) {
    $log_lookup[$row['username']][$row['event_id']]['score']   = $row['score'];
    $log_lookup[$row['username']][$row['event_id']]['note']    = $row['note'];
    $log_lookup[$row['username']][$row['event_id']]['quality'] = $row['quality'];
}
$qualityOptions = registerClassQualityOptions();

// تعداد ستون های واقعی؛ تناوب رنگ از سمت دکمه + شمرده می شود
// تا ستون چسبیده به + همیشه سفید بماند و دو ستون طوسی کنار هم نیفتد
$event_count = count($events);
foreach ($attendances as $row) {
    $statusCode = ($row['status'] === '1') ? 1 : 2;
    $log_lookup[$row['username']][$row['event_id']]['status_code'] = $statusCode;
}

// اطلاعات هر نوع ستون، کلید = مقدار ستون type
$kindMeta = [];
foreach (registerClassColumnKinds() as $kind) {
    $kindMeta[(string)$kind['type']] = $kind;
}
// ستون های قدیمی که هنوز نوع مشخصی ندارند
$defaultKind = ['title' => '', 'date_bound' => true, 'input' => 'attendance'];

// شماره گذاری دسته ها برای رنگ بندی یک در میان
$groupIndexes = [];
$groupSizes   = [];
foreach ($events as $evt) {
    $g = $evt['group_uid'] ?? null;
    $key = $g !== null && $g !== '' ? $g : 'single-' . $evt['id'];
    if (!isset($groupIndexes[$key])) {
        $groupIndexes[$key] = count($groupIndexes);
        $groupSizes[$key]   = 0;
    }
    $groupSizes[$key]++;
}


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <title>دفتر کلاسی | <?php echo __TITLE; ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        /* ============================================================
           دفتر کلاسی - طرح بنفش
           پالت رنگی مطابق فایل طراحی
           ============================================================ */
        .rg-wrap {
            --rg-primary:   #9a62f4;
            --rg-primary-d: #8449ea;
            --rg-head-a:    #c2a0f9;
            --rg-head-b:    #bb8ff9;
            --rg-row-a:     #efe3ff;
            --rg-row-a-alt: #e0ccff;
            --rg-row-b:     #e2d0fc;
            --rg-row-b-alt: #d0b7f9;
            --rg-name-bg:   #f5eeff;
            --rg-num-bg:    #d9c2fb;
            --rg-ink:       #43277e;
            --rg-ink-soft:  #7c68a8;
            --rg-line:      rgba(255, 255, 255, .75);
            --rg-radius:    26px;
        }

        /* ---------- نوار ابزار بالای جدول ---------- */
        .rg-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 18px 0 14px;
        }

        .rg-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--rg-ink);
            margin: 0;
        }

        .rg-title small {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--rg-ink-soft);
            margin-top: 4px;
        }

        .rg-tools {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        /* کادرهای نواری: برچسب بنفش سمت راست، مقدار سمت چپ */
        .rg-field {
            display: flex;
            align-items: stretch;
            border-radius: 12px;
            overflow: hidden;
            background: #eceaf4;
            box-shadow: 0 1px 2px rgba(67, 39, 126, .08);
        }

        .rg-field-label {
            display: flex;
            align-items: center;
            background: #e0d2fb;
            color: var(--rg-ink);
            font-size: 13px;
            font-weight: 700;
            padding: 9px 14px;
            white-space: nowrap;
        }

        .rg-field-value {
            display: flex;
            align-items: center;
            min-width: 90px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--rg-ink);
        }

        .rg-field input.rg-field-control,
        .rg-field select.rg-field-control {
            border: 0;
            outline: 0;
            background: transparent;
            padding: 9px 14px;
            font-size: 13px;
            color: var(--rg-ink);
            min-width: 180px;
        }

        .rg-field select.rg-field-control {
            font-weight: 700;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, var(--rg-ink) 50%),
                              linear-gradient(135deg, var(--rg-ink) 50%, transparent 50%);
            background-position: 14px calc(50% - 2px), 19px calc(50% - 2px);
            background-size: 5px 5px, 5px 5px;
            background-repeat: no-repeat;
            padding-left: 34px;
        }

        .rg-field input.rg-field-control::placeholder {
            color: #a89bc4;
            font-weight: 400;
        }

        .rg-week-nav {
            display: flex;
            align-items: center;
            background: #e0d2fb;
            border: 0;
            color: var(--rg-ink);
            font-size: 15px;
            font-weight: 700;
            padding: 0 11px;
            cursor: pointer;
            transition: background .15s;
        }

        .rg-week-nav:hover:not(:disabled) { background: var(--rg-head-b); }
        .rg-week-nav:disabled { opacity: .35; cursor: default; }

        .rg-export-btn {
            border: 0;
            border-radius: 12px;
            background: var(--rg-primary);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 9px 16px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(67, 39, 126, .12);
            transition: background .15s;
        }

        .rg-export-btn:hover { background: var(--rg-primary-d); }

        /* ---------- بدنه ی جدول ---------- */
        .rg-card {
            position: relative;
            border-radius: var(--rg-radius);
            overflow: hidden;
            background: var(--rg-row-a);
            box-shadow: 0 8px 24px rgba(122, 79, 200, .16);
            margin-bottom: 28px;
        }

        .rg-scroll {
            max-height: 74vh;
            overflow: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--rg-head-b) transparent;
        }

        .rg-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
        .rg-scroll::-webkit-scrollbar-thumb {
            background: var(--rg-head-b);
            border-radius: 9px;
        }
        .rg-scroll::-webkit-scrollbar-track { background: transparent; }

        .rg-table {
            width: 100%;
            min-width: max-content;
            border-collapse: collapse;
            margin: 0;
            font-size: 13px;
            color: var(--rg-ink);
        }

        .rg-table th,
        .rg-table td {
            border: 1px solid var(--rg-line);
            padding: 10px 14px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* --- سربرگ --- */
        .rg-table thead th {
            position: sticky;
            top: 0;
            z-index: 6;
            background: var(--rg-head-a);
            font-weight: 700;
            font-size: 13px;
            padding: 12px 16px;
            line-height: 1.9;
        }

        .rg-table thead th.rg-stripe { background: var(--rg-head-b); }

        .rg-th-title {
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .rg-th-date {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #5c3fa4;
        }

        .rg-th-teacher {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #6f5aa0;
            opacity: .85;
        }

        .rg-icon-btn {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            font-size: 13px;
            cursor: pointer;
            opacity: .9;
            transition: transform .12s, opacity .12s;
        }

        .rg-icon-btn:hover { transform: scale(1.18); opacity: 1; }
        .rg-icon-edit   { color: #3f6bd8; }
        .rg-icon-delete { color: #e8536d; font-weight: 700; }

        /* --- بدنه: چهار ترکیب رنگی سطر و ستون --- */
        .rg-table tbody td { background: var(--rg-row-a); }
        .rg-table tbody td.rg-stripe { background: var(--rg-row-a-alt); }
        .rg-table tbody tr:nth-child(even) td { background: var(--rg-row-b); }
        .rg-table tbody tr:nth-child(even) td.rg-stripe { background: var(--rg-row-b-alt); }

        .rg-table tbody tr:hover td:not(.rg-add-bar) { filter: brightness(1.04); }

        /* --- ستون شماره و نام، چسبیده به راست --- */
        .rg-col-num {
            position: sticky;
            right: 0;
            z-index: 4;
            width: 1px;
            padding: 10px 10px !important;
            font-size: 12px;
            font-weight: 700;
        }

        .rg-table tbody td.rg-col-num,
        .rg-table tbody tr:nth-child(even) td.rg-col-num { background: var(--rg-num-bg); }

        .rg-table thead th.rg-col-num {
            right: 0;
            z-index: 9;
            background: var(--rg-head-b);
        }

        .rg-col-name {
            position: sticky;
            right: 44px;
            z-index: 4;
            width: 1px;
            min-width: 190px;
            font-weight: 700;
        }

        .rg-table tbody td.rg-col-name,
        .rg-table tbody tr:nth-child(even) td.rg-col-name { background: var(--rg-name-bg); }

        .rg-table thead th.rg-col-name {
            right: 44px;
            z-index: 9;
            background: var(--rg-head-a);
        }

        .rg-name-btn {
            border: 0;
            background: transparent;
            color: var(--rg-ink);
            font-weight: 700;
            font-size: 13px;
            padding: 2px 4px;
            cursor: pointer;
        }

        .rg-name-btn::after {
            content: "";
            display: inline-block;
            margin-right: 7px;
            vertical-align: middle;
            border-top: 4px solid currentColor;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            opacity: .65;
        }

        .rg-col-disc { width: 1px; }

        .rg-disc-badge {
            border: 0;
            background: transparent;
            color: #e8536d;
            font-size: 18px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 2px;
            cursor: pointer;
        }

        .rg-empty { color: rgba(67, 39, 126, .28); }

        /* --- سلول های داده --- */
        .rg-cell-btn {
            border: 0;
            background: transparent;
            color: var(--rg-ink);
            font-size: 13px;
            font-weight: 600;
            width: 100%;
            padding: 4px 6px;
            border-radius: 9px;
            cursor: pointer;
            transition: background .12s;
        }

        .rg-cell-btn:hover { background: rgba(255, 255, 255, .55); }
        .rg-cell-btn:disabled { color: rgba(67, 39, 126, .3); cursor: default; }

        /* سلول خالی: علامت مثبت فقط هنگام عبور موشواره دیده می شود */
        .rg-cell-btn.is-empty { color: transparent; }
        .rg-table tbody tr:hover .rg-cell-btn.is-empty { color: rgba(67, 39, 126, .45); }

        .rg-pill {
            display: inline-block;
            min-width: 62px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
        }

        .rg-pill-present { background: #3fae7a; }
        .rg-pill-absent  { background: #e8536d; }

        .rg-score-num {
            display: block;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.4;
        }

        .rg-score-quality {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: #6f5aa0;
            line-height: 1.4;
        }

        /* ستون توضیحات پهن تر است و متن در آن می شکند */
        .rg-table th.rg-col-note,
        .rg-table td.rg-col-note {
            width: 250px;
            min-width: 250px;
            max-width: 250px;
            white-space: normal;
        }

        .rg-table td.rg-col-note .rg-cell-btn {
            white-space: normal;
            word-break: break-word;
            line-height: 1.8;
            font-weight: 500;
        }

        /* مرز بین دسته ستون های یک تاریخ */
        .rg-table th.rg-group-start,
        .rg-table td.rg-group-start { border-right: 2px solid #fff; }

        /* --- نوار عمودی افزودن ستون جدید در لبه ی چپ --- */
        .rg-add-head,
        .rg-add-bar {
            position: sticky;
            left: 0;
            width: 52px;
            min-width: 52px;
            padding: 0 !important;
            border: 0 !important;
        }

        .rg-table thead th.rg-add-head {
            z-index: 130;
            background: var(--rg-primary) !important;
        }

        .rg-table tbody td.rg-add-bar,
        .rg-table tbody tr:nth-child(even) td.rg-add-bar {
            z-index: 3;
            background: var(--rg-primary) !important;
            filter: none !important;
        }

        .rg-add-btn {
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 22px;
            font-weight: 400;
            line-height: 1;
            width: 100%;
            padding: 10px 0;
            cursor: pointer;
            transition: transform .12s;
        }

        .rg-add-btn:hover { transform: scale(1.25); }

        /* برچسب عمودی از سربرگ آویزان می شود و روی نوار بنفش بدنه می نشیند */
        .rg-add-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            height: 160px;
            writing-mode: vertical-rl;
            text-align: center;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            white-space: nowrap;
            padding: 10px 0;
            cursor: pointer;
            pointer-events: auto;
        }

        .rg-table thead th.rg-add-head { position: sticky; overflow: visible; }

        .rg-empty-head {
            font-weight: 600 !important;
            color: #5c3fa4;
            min-width: 320px;
        }

        .rg-no-data {
            padding: 40px 16px !important;
            color: var(--rg-ink-soft);
            font-size: 14px;
            background: var(--rg-row-a) !important;
        }

        /* ---------- منوی کشویی نام دانش آموز ---------- */
        .rg-table tbody tr { position: relative; }
        .rg-table tbody tr:hover { z-index: 100; }

        .rg-wrap .dropdown-hover:hover > .dropdown-menu { display: block; }
        .rg-wrap .dropdown-hover > .dropdown-menu { margin-top: 0; min-width: 160px; }

        .rg-wrap .dropdown-menu {
            padding: 5px !important;
            border: 0;
            border-radius: 12px;
            background: #f6f0ff;
            box-shadow: 0 6px 18px rgba(67, 39, 126, .18);
        }

        .rg-wrap .dropdown-menu .dropdown-item {
            font-size: 12.5px !important;
            border-radius: 8px;
            padding: 7px 10px !important;
        }

        .rg-wrap .dropdown-menu .dropdown-item:hover { background: var(--rg-row-a-alt); }

        @media (max-width: 767px) {
            .rg-field input.rg-field-control,
            .rg-field select.rg-field-control { min-width: 140px; }
            .rg-col-name { position: static; min-width: 150px; }
            .rg-table thead th.rg-col-name { position: sticky; right: auto; }
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

            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show my-3" role="alert">
                    <?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="rg-wrap">

                <div class="rg-toolbar">
                    <h4 class="rg-title">
                        دفتر کلاسی
                        <small><?php echo htmlspecialchars($week_scope_label . ' ( ' . $week_scope_range . ' )'); ?></small>
                    </h4>

                    <div class="rg-tools">
                        <?php /* انتخاب هفته: داده های هر هفته جداگانه نمایش داده می شود */ ?>
                        <div class="rg-field">
                            <span class="rg-field-label">هفته</span>
                            <button type="button" class="rg-week-nav" id="weekPrev" title="هفته ی قبل">&#8250;</button>
                            <select class="rg-field-control" id="weekSelect" aria-label="انتخاب هفته">
                                <option value="all" <?php echo $all_weeks_selected ? 'selected' : ''; ?>>
                                    همه‌ی هفته‌ها
                                </option>
                                <?php foreach ($weeks as $week): ?>
                                    <option value="<?php echo htmlspecialchars($week['key']); ?>"
                                        <?php echo (!$all_weeks_selected && $week['key'] === $selected_week_key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($week['label']); ?>
                                        <?php echo ($week['key'] === $currentWeekKey) ? '(هفته ی جاری)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="rg-week-nav" id="weekNext" title="هفته ی بعد">&#8249;</button>
                        </div>

                        <div class="rg-field">
                            <span class="rg-field-label">تعداد دانش آموزان</span>
                            <span class="rg-field-value"><?php echo count($students); ?></span>
                        </div>

                        <div class="rg-field">
                            <span class="rg-field-label">جستجو</span>
                            <input type="text" id="studentSearch" class="rg-field-control"
                                   placeholder="نام دانش آموز ..." autocomplete="off">
                        </div>

                        <?php /* خروجی اکسل: یا فقط هفته ی انتخاب شده، یا کل سال تحصیلی */ ?>
                        <div class="dropdown">
                            <button type="button" class="rg-export-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                خروجی اکسل
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item"
                                       href="class-register-excel.php?class=<?php echo $class_id; ?>&scope=<?php echo $all_weeks_selected ? 'all' : 'week'; ?>&week=<?php echo urlencode($selected_week_key); ?>">
                                        <?php echo htmlspecialchars($week_scope_label); ?>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item"
                                       href="class-register-excel.php?class=<?php echo $class_id; ?>&scope=all">
                                        کل سال تحصیلی
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="rg-card">
                    <div class="rg-scroll">
                        <table class="rg-table">
                            <thead>
                            <tr>
                                <th class="rg-col-num">#</th>
                                <th class="rg-col-name">نام و نام خانوادگی</th>
                                <th class="rg-col-disc">موارد انضباطی</th>

                                <?php
                                $prev_group = null;
                                $col_index  = 0;
                                foreach ($events as $evt):
                                    // تاریخ اختیاری است؛ ستون بدون تاریخ چیزی نمایش نمی دهد
                                    $evt_timestamp  = !empty($evt['event_date']) ? strtotime($evt['event_date']) : false;
                                    $jalali_display = $evt_timestamp ? jdate('Y/m/d', $evt_timestamp) : '';
                                    $jalali_input   = $evt_timestamp ? jdate('Y/m/d', $evt_timestamp, '', 'Asia/Tehran', 'en') : '';

                                    $g          = $evt['group_uid'] ?? null;
                                    $is_grouped = ($g !== null && $g !== '');
                                    $groupKey   = $is_grouped ? $g : 'single-' . $evt['id'];
                                    // فقط دسته های واقعی (ساخته شده به صورت گروهی) جداکننده می گیرند
                                    $is_group_start = $is_grouped && ($groupKey !== $prev_group);
                                    $prev_group = $groupKey;

                                    $kind = $kindMeta[(string)$evt['type']] ?? $defaultKind;
                                    // تناوب رنگ از سمت نوار افزودن ستون شمرده می شود
                                    $is_stripe = ((($event_count - 1 - $col_index) % 2) === 1);
                                    $cell_classes = 'rg-th-cell'
                                        . ($is_group_start ? ' rg-group-start' : '')
                                        . (($kind['input'] === 'note') ? ' rg-col-note' : '')
                                        . ($is_stripe ? ' rg-stripe' : '');
                                    $col_index++;
                                    ?>
                                    <th class="<?php echo $cell_classes; ?>">
                                        <span class="rg-th-title">
                                            <?php if ($has_full_permission): ?>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('این ستون حذف شود؟')">
                                                    <input type="hidden" name="action" value="delete_event">
                                                    <input type="hidden" name="event_id" value="<?php echo $evt['id']; ?>">
                                                    <button type="submit" class="rg-icon-btn rg-icon-delete" title="حذف ستون">&#10005;</button>
                                                </form>
                                                <button type="button"
                                                        class="rg-icon-btn rg-icon-edit"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#createEventModal"
                                                        data-event-id="<?php echo $evt['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($evt['title']); ?>"
                                                        data-type="<?php echo $evt['type']; ?>"
                                                        data-date="<?php echo htmlspecialchars($jalali_input); ?>"
                                                        data-teacher="<?php echo $evt['teacher_id'] ?? ''; ?>"
                                                        title="ویرایش ستون"><i class="fe fe-edit"></i></button>
                                                <?php if ($is_group_start && $g): ?>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('کل ستون های این تاریخ حذف شود؟')">
                                                        <input type="hidden" name="action" value="delete_event_group">
                                                        <input type="hidden" name="group_uid" value="<?php echo htmlspecialchars($g); ?>">
                                                        <button type="submit" class="rg-icon-btn rg-icon-delete" title="حذف کل ستون های این تاریخ">&#8864;</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($evt['title']); ?>
                                        </span>
                                        <?php /* تاریخ فقط وقتی ثبت شده باشد نمایش داده می شود */ ?>
                                        <?php if ($jalali_display !== ''): ?>
                                            <span class="rg-th-date"><?php echo htmlspecialchars($jalali_display); ?></span>
                                        <?php else: ?>
                                            <span class="rg-th-date">
                                                بدون تاریخ
                                                <?php if (!empty($evt['created_at'])): ?>
                                                    (ثبت: <?php echo jdate('Y/m/d', strtotime($evt['created_at'])); ?>)
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ((int)$adminrole !== 3): ?>
                                            <span class="rg-th-teacher"><?php echo htmlspecialchars($teacherUsernames[$evt['teacher_id']] ?? 'نامشخص'); ?></span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>

                                <?php if (empty($events)): ?>
                                    <th class="rg-empty-head">
                                        <?php echo $all_weeks_selected ? 'هنوز ستونی ثبت نشده است' : 'برای این هفته هنوز ستونی ثبت نشده است'; ?>
                                    </th>
                                <?php endif; ?>

                                <th class="rg-add-head">
                                    <button type="button" class="rg-add-btn"
                                            data-bs-toggle="modal" data-bs-target="#createEventModal"
                                            title="افزودن ستون جدید">+</button>
                                    <span class="rg-add-label" data-bs-toggle="modal" data-bs-target="#createEventModal">
                                        افزودن ستون جدید
                                    </span>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($students)): ?>
                                <?php foreach ($students as $index => $student):
                                    $fullName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
                                    if (empty($fullName)) {
                                        $fullName = $student['username'];
                                    }
                                    $disc_count = count($discipline_lookup[$student['username']] ?? []);
                                    ?>
                                    <tr>
                                        <td class="rg-col-num"><?php echo $index + 1; ?></td>

                                        <td class="rg-col-name">
                                            <div class="dropdown dropdown-hover" style="position: relative;">
                                                <button class="rg-name-btn"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-boundary="viewport"
                                                        data-bs-reference="parent"
                                                        aria-expanded="false"
                                                        data-username="<?php echo htmlspecialchars($student['username']); ?>">
                                                    <?php echo htmlspecialchars($fullName); ?>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item text-danger"
                                                           href="#"
                                                           data-bs-toggle="modal"
                                                           data-bs-target="#disciplineModal"
                                                           data-student="<?php echo htmlspecialchars($student['username']); ?>"
                                                           data-student-name="<?php echo htmlspecialchars($fullName); ?>">
                                                            ثبت مورد انضباطی
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>

                                        <?php /* ستون موارد انضباطی؛ کلیک روی علامت ها لیست را باز می کند */ ?>
                                        <td class="rg-col-disc">
                                            <?php if ($disc_count > 0): ?>
                                                <button type="button"
                                                        class="rg-disc-badge"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#viewDisciplineModal"
                                                        data-student="<?php echo htmlspecialchars($student['username']); ?>"
                                                        data-student-name="<?php echo htmlspecialchars($fullName); ?>"
                                                        title="<?php echo $disc_count; ?> مورد انضباطی">
                                                    <?php echo str_repeat('-', $disc_count); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="rg-empty">&mdash;</span>
                                            <?php endif; ?>
                                        </td>

                                        <?php
                                        $prev_group = null;
                                        $col_index  = 0;
                                        foreach ($events as $evt):
                                            $cell_log   = $log_lookup[$student['username']][$evt['id']] ?? [];
                                            $has_entry  = !empty($cell_log);
                                            $score_val  = $cell_log['score'] ?? '';
                                            $status_val = $cell_log['status_code'] ?? '';
                                            $note_val    = $cell_log['note'] ?? '';
                                            $quality_val = $cell_log['quality'] ?? '';

                                            $g          = $evt['group_uid'] ?? null;
                                            $is_grouped = ($g !== null && $g !== '');
                                            $groupKey   = $is_grouped ? $g : 'single-' . $evt['id'];
                                            $is_group_start = $is_grouped && ($groupKey !== $prev_group);
                                            $prev_group = $groupKey;

                                            $kind = $kindMeta[(string)$evt['type']] ?? $defaultKind;
                                            $is_stripe = ((($event_count - 1 - $col_index) % 2) === 1);
                                            $td_classes = 'rg-cell'
                                                . ($is_group_start ? ' rg-group-start' : '')
                                                . (($kind['input'] === 'note') ? ' rg-col-note' : '')
                                                . ($is_stripe ? ' rg-stripe' : '');
                                            $col_index++;

                                            $event_locked = ($evt['status'] == 1);
                                            if ($has_full_permission) {
                                                $event_locked = false;
                                            }

                                            $btn_class = 'rg-cell-btn is-empty';
                                            $btn_label = '+';

                                            if ($has_entry) {
                                                if ($kind['input'] === 'score' && ($score_val !== '' || $quality_val !== '')) {
                                                    $parts = [];
                                                    if ($score_val !== '' && $score_val !== null) {
                                                        $parts[] = '<span class="rg-score-num">' . htmlspecialchars($score_val) . '</span>';
                                                    }
                                                    // ستون نمره کلاسی علاوه بر عدد، ارزیابی کیفی هم دارد
                                                    if (!empty($kind['quality']) && $quality_val !== '' && isset($qualityOptions[(int)$quality_val])) {
                                                        $parts[] = '<span class="rg-score-quality">' . htmlspecialchars($qualityOptions[(int)$quality_val]) . '</span>';
                                                    }
                                                    if ($parts) {
                                                        $btn_class = 'rg-cell-btn';
                                                        $btn_label = implode('', $parts);
                                                    }
                                                } elseif ($kind['input'] === 'note' && trim((string)$note_val) !== '') {
                                                    $btn_class = 'rg-cell-btn';
                                                    // متن در سلول می شکند؛ متن خیلی بلند کوتاه شده و کامل آن در تولتیپ و مودال دیده می شود
                                                    $btn_label = htmlspecialchars(mb_strimwidth(trim((string)$note_val), 0, 90, '…'));
                                                } elseif ($kind['input'] === 'attendance') {
                                                    $btn_class = 'rg-cell-btn';
                                                    $btn_label = (intval($status_val) === 1)
                                                        ? '<span class="rg-pill rg-pill-present">حاضر</span>'
                                                        : '<span class="rg-pill rg-pill-absent">غایب</span>';
                                                }
                                            }
                                            ?>
                                            <td class="<?php echo $td_classes; ?>">
                                                <button type="button"
                                                        class="<?php echo $btn_class; ?>"
                                                    <?php if ($event_locked): ?>
                                                        disabled
                                                    <?php else: ?>
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#saveGradeModal"
                                                    <?php endif; ?>
                                                        title="<?php echo ($kind['input'] === 'note' && trim((string)$note_val) !== '') ? htmlspecialchars(trim((string)$note_val)) : 'ثبت / ویرایش'; ?>"
                                                        data-student="<?php echo htmlspecialchars($student['username']); ?>"
                                                        data-student-name="<?php echo htmlspecialchars($fullName); ?>"
                                                        data-event="<?php echo $evt['id']; ?>"
                                                        data-event-title="<?php echo htmlspecialchars($evt['title']); ?>"
                                                        data-event-type="<?php echo $evt['type']; ?>"
                                                        data-existing-score="<?php echo htmlspecialchars($score_val); ?>"
                                                        data-existing-status="<?php echo htmlspecialchars($status_val); ?>"
                                                        data-existing-note="<?php echo htmlspecialchars((string)$note_val); ?>"
                                                        data-existing-quality="<?php echo htmlspecialchars((string)$quality_val); ?>"
                                                        data-has-quality="<?php echo !empty($kind['quality']) ? '1' : '0'; ?>">
                                                    <?php echo $btn_label; ?>
                                                </button>
                                            </td>
                                        <?php endforeach; ?>

                                        <?php if (empty($events)): ?>
                                            <td class="rg-cell"></td>
                                        <?php endif; ?>

                                        <?php /* نوار عمودی افزودن ستون؛ سلول های بدون کادر یک نوار پیوسته می سازند */ ?>
                                        <td class="rg-add-bar"></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($events) + 4 + (empty($events) ? 1 : 0); ?>" class="rg-no-data">
                                        هیچ دانش آموزی برای این کلاس یافت نشد.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Modal 1: Create New Event -->
    <div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createEventModalLabel">افزودن ستون جدید به دفتر کلاسی</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" value="create_event">
                    <div class="modal-body">

                        <?php /* عنوان ستون قابل تغییر نیست و همیشه از روی نوع ستون تعیین می شود */ ?>
                        <div class="mb-3" id="teacher_select_container">
                            <?php if ((int)$adminrole === 3): ?>
                                <input type="hidden" name="teacher_id" value="<?php echo (int)$admin_id; ?>">
                            <?php else: ?>
                                <label class="form-label fw-bold"><?php echo m('label.related_teacher') ?></label>
                                <select class="form-select" name="teacher_id" required>
                                    <option value=""><?php echo m('label.choose_teacher_dash') ?></option>
                                    <?php foreach ($teachers_list as $teacher):
                                        $teacherName = trim(($teacher['fname'] ?? '') . ' ' . ($teacher['lname'] ?? ''));
                                        if (empty($teacherName)) {
                                            $teacherName = $teacher['username'];
                                        }
                                        $t_id = $teacher['idadmin'] ?? $teacher['id'] ?? 0;
                                        ?>
                                        <option value="<?php echo $t_id; ?>">
                                            <?php echo htmlspecialchars($teacherName); ?> (<?php echo htmlspecialchars($teacher['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">نوع ستون:</label>
                            <select class="form-select" name="type" required>
                                <option value="" disabled selected>-- انتخاب نوع ستون --</option>
                                <?php foreach (registerClassColumnKinds() as $kindRow): ?>
                                    <option value="<?php echo $kindRow['type']; ?>"><?php echo htmlspecialchars($kindRow['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                تاریخ ستون:
                                <span class="fw-normal text-muted small">(اختیاری)</span>
                            </label>
                            <input type="text" class="form-control fc-datepicker" name="event_date" placeholder="1403/09/10" autocomplete="off">
                            <div class="form-text small">اگر خالی بماند، ستون بدون تاریخ ساخته می شود.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary" id="create_event_submit">ثبت ستون</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="saveGradeModal" tabindex="-1" aria-labelledby="saveGradeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="saveGradeModalLabel">ثبت نمره / وضعیت</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" id="modal_action_type" value="save_grade">
                    <input type="hidden" name="event_id" id="modal_event_id" value="0">
                    <input type="hidden" name="student_id" id="modal_student_id" value="">

                    <div class="modal-body">
                        <div class="bg-light p-2 rounded mb-3 text-center border">
                            <div class="fw-bold text-primary" id="modal_student_name">-</div>
                            <div class="small text-muted" id="modal_event_title">-</div>
                        </div>

                        <div class="mb-3" id="grade_input_container">
                            <label class="form-label small fw-bold" id="grade_input_label">نمره دانش‌آموز:</label>
                            <input type="number" step="0.25" min="0" max="20" class="form-control text-center fs-5" id="modal_grade_val" name="grade_val" placeholder="00.00">
                        </div>

                        <div class="mb-3" id="quality_input_container" style="display:none;">
                            <label class="form-label small fw-bold">ارزیابی کیفی:</label>
                            <select class="form-select" id="modal_quality_val" name="quality_val">
                                <option value="0">-- بدون ارزیابی --</option>
                                <?php foreach (registerClassQualityOptions() as $qv => $qlabel): ?>
                                    <option value="<?php echo $qv; ?>"><?php echo htmlspecialchars($qlabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="note_input_container" style="display:none;">
                            <label class="form-label small fw-bold">توضیحات:</label>
                            <textarea class="form-control" id="modal_note_val" name="note_val" rows="3"
                                      maxlength="500" placeholder="توضیحات خود را بنویسید ..."></textarea>
                            <div class="form-text small">برای پاک کردن، کادر را خالی بگذارید و ثبت کنید.</div>
                        </div>

                        <div class="mb-3" id="status_input_container">
                            <label class="form-label small fw-bold">وضعیت حضور و غیاب:</label>
                            <select class="form-select" id="modal_status_code" name="status_code">
                                <option value="1">حاضر </option>
                                <option value="2">غایب </option>

                            </select>
                        </div>
                    </div>

                    <div class="modal-footer py-2 d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">انصراف</button>
                        <div>
                            <?php if ($has_full_permission): ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger me-1" id="delete_entry_btn" style="display:none;" onclick="document.getElementById('modal_action_type').value='delete_entry';">حذف</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-primary px-3">ثبت</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal 3: Disciplinary Entry -->
    <div class="modal fade" id="disciplineModal" tabindex="-1" aria-labelledby="disciplineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="disciplineModalLabel">ثبت مورد انضباطی</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" value="save_discipline">
                    <input type="hidden" name="student_id" id="discipline_student_id" value="">
                    <div class="modal-body">
                        <div class="bg-light p-2 rounded mb-3 text-center border">
                            <div class="fw-bold text-primary" id="discipline_student_name">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">علت:</label>
                            <textarea class="form-control" name="discipline_reason" rows="3" placeholder="توضیح مورد انضباطی..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2 d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-sm btn-danger px-3">ثبت</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal 4: View/Edit Disciplinary Records -->
    <div class="modal fade" id="viewDisciplineModal" tabindex="-1" aria-labelledby="viewDisciplineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="viewDisciplineModalLabel">موارد انضباطی</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="bg-light p-2 rounded mb-3 text-center border">
                        <div class="fw-bold text-primary" id="view_discipline_student_name">-</div>
                    </div>
                    <div id="discipline_records_container">
                        <!-- Records loaded here by JS -->
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal 5: Edit Disciplinary Entry -->
    <div class="modal fade" id="editDisciplineModal" tabindex="-1" aria-labelledby="editDisciplineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="editDisciplineModalLabel">ویرایش مورد انضباطی</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" value="edit_discipline">
                    <input type="hidden" name="discipline_id" id="edit_discipline_id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">علت:</label>
                            <textarea class="form-control" name="discipline_reason" id="edit_discipline_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2 d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-sm btn-primary px-3">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ---------- انتخاب هفته ----------
        const weekSelect = document.getElementById('weekSelect');
        if (weekSelect) {
            const gotoWeek = function (value) {
                const url = new URL(window.location.href);
                url.searchParams.set('week', value);
                window.location.href = url.toString();
            };

            weekSelect.addEventListener('change', function () {
                gotoWeek(this.value);
            });

            // دکمه های هفته ی قبل و بعد
            const prevBtn = document.getElementById('weekPrev');
            const nextBtn = document.getElementById('weekNext');
            const idx = weekSelect.selectedIndex;
            const last = weekSelect.options.length - 1;
            const first = weekSelect.options[0] && weekSelect.options[0].value === 'all' ? 1 : 0;
            const onAll = (idx < first);

            if (prevBtn) {
                prevBtn.disabled = onAll || (idx <= first);
                prevBtn.addEventListener('click', function () {
                    if (!onAll && idx > first) gotoWeek(weekSelect.options[idx - 1].value);
                });
            }
            if (nextBtn) {
                nextBtn.disabled = onAll || (idx >= last);
                nextBtn.addEventListener('click', function () {
                    if (!onAll && idx < last) gotoWeek(weekSelect.options[idx + 1].value);
                });
            }
        }

        // ---------- Grade / Attendance Modal ----------
        const gradeModal = document.getElementById('saveGradeModal');
        const searchInput = document.getElementById('studentSearch');
        if (gradeModal) {
            gradeModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                const studentId   = button.getAttribute('data-student');
                const studentName = button.getAttribute('data-student-name');
                const eventId     = button.getAttribute('data-event');
                const eventTitle  = button.getAttribute('data-event-title');
                const eventType   = button.getAttribute('data-event-type');

                const existingScore  = button.getAttribute('data-existing-score') || '';
                const existingStatus = button.getAttribute('data-existing-status') || '1';
                const existingNote    = button.getAttribute('data-existing-note') || '';
                const existingQuality = button.getAttribute('data-existing-quality') || '0';
                const hasQuality      = button.getAttribute('data-has-quality') === '1';

                document.getElementById('modal_student_id').value = studentId;
                document.getElementById('modal_event_id').value   = eventId;
                document.getElementById('modal_student_name').textContent = studentName;
                document.getElementById('modal_event_title').textContent   = eventTitle;

                document.getElementById('modal_grade_val').value   = existingScore;
                document.getElementById('modal_status_code').value = existingStatus;

                const gradeContainer  = document.getElementById('grade_input_container');
                const statusContainer = document.getElementById('status_input_container');
                const actionInput     = document.getElementById('modal_action_type');

                // 1 = نمره کلاسی، 4 = تراز آزمون آنلاین  → ورودی عددی
                // 3 = توضیحات                            → ورودی متنی
                // 0 = حضور دستی،  2 = حضور آنلاین        → ورودی وضعیت
                const isScoreColumn = (eventType === '1' || eventType === '4');
                const isNoteColumn     = (eventType === '3');
                const noteContainer    = document.getElementById('note_input_container');
                const qualityContainer = document.getElementById('quality_input_container');

                document.getElementById('modal_note_val').value    = existingNote;
                document.getElementById('modal_quality_val').value = existingQuality || '0';

                if (isNoteColumn) {
                    actionInput.value = 'save_note';
                    gradeContainer.style.display    = 'none';
                    statusContainer.style.display   = 'none';
                    qualityContainer.style.display  = 'none';
                    noteContainer.style.display     = 'block';
                } else if (isScoreColumn) {
                    actionInput.value = 'save_grade';
                    gradeContainer.style.display    = 'block';
                    statusContainer.style.display   = 'none';
                    // ارزیابی کیفی فقط در ستون نمره کلاسی دیده می شود
                    qualityContainer.style.display  = hasQuality ? 'block' : 'none';
                    noteContainer.style.display     = 'none';

                    // نمره کلاسی از ۰ تا ۲۰ است اما تراز آزمون عدد بزرگ تری دارد
                    const gradeField = document.getElementById('modal_grade_val');
                    const gradeLabel = document.getElementById('grade_input_label');
                    if (eventType === '4') {
                        gradeField.max  = '999999';
                        gradeField.step = '1';
                        gradeField.placeholder = '7200';
                        gradeLabel.textContent = 'تراز آزمون آنلاین:';
                    } else {
                        gradeField.max  = '20';
                        gradeField.step = '0.25';
                        gradeField.placeholder = '00.00';
                        gradeLabel.textContent = 'نمره دانش‌آموز:';
                    }
                } else {
                    actionInput.value = 'save_attendance';
                    gradeContainer.style.display    = 'none';
                    statusContainer.style.display   = 'block';
                    qualityContainer.style.display  = 'none';
                    noteContainer.style.display     = 'none';
                }

                const deleteBtn = document.getElementById('delete_entry_btn');
                if (deleteBtn) {
                    if ((existingScore !== '' && existingScore !== null) || existingStatus !== '') {
                        deleteBtn.style.display = 'inline-block';
                    } else {
                        deleteBtn.style.display = 'none';
                    }
                    // actionInput بالاتر بر اساس نوع ستون تعیین شده است و نباید دوباره بازنویسی شود
                }
            });
        }

        // ---------- Edit Event Modal (reuses createEventModal) ----------
        const createModal = document.getElementById('createEventModal');
        if (createModal) {
            createModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const eventId = button.getAttribute('data-event-id');

                if (eventId) {
                    document.querySelector('#createEventModal form input[name="action"]').value = 'edit_event';
                    document.querySelector('#createEventModal form select[name="type"]').value = button.getAttribute('data-type') || '0';
                    document.querySelector('#createEventModal form input[name="event_date"]').value = button.getAttribute('data-date') || '';

                    let hidden = document.querySelector('#createEventModal form input[name="event_id"]');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'event_id';
                        document.querySelector('#createEventModal form').appendChild(hidden);
                    }
                    hidden.value = eventId;

                    const teacherContainer = document.getElementById('teacher_select_container');
                    if (teacherContainer) {
                        teacherContainer.style.display = 'none';
                        const teacherSelect = teacherContainer.querySelector('select[name="teacher_id"]');
                        if (teacherSelect) teacherSelect.removeAttribute('required');
                    }

                    // در ویرایش، عنوان قابل تغییر است
                    document.getElementById('create_event_submit').textContent = 'ثبت تغییرات';
                    createModal.querySelector('.modal-title').textContent = 'ویرایش ستون';
                } else {
                    document.querySelector('#createEventModal form input[name="action"]').value = 'create_event';
                    document.querySelector('#createEventModal form select[name="type"]').value = '';
                    // تاریخ پیش فرض داخل هفته ی انتخاب شده است تا ستون در همان هفته ساخته شود
                    document.querySelector('#createEventModal form input[name="event_date"]').value =
                        <?php echo json_encode($default_column_date, JSON_UNESCAPED_UNICODE); ?>;
                    const hidden = document.querySelector('#createEventModal form input[name="event_id"]');
                    if (hidden) hidden.remove();

                    const teacherContainer = document.getElementById('teacher_select_container');
                    if (teacherContainer) {
                        teacherContainer.style.display = '';
                        const teacherSelect = teacherContainer.querySelector('select[name="teacher_id"]');
                        if (teacherSelect) teacherSelect.setAttribute('required', 'required');
                    }

                    // هنگام افزودن، عنوان از روی نوع ستون تعیین می شود و پرسیده نمی شود
                    document.getElementById('create_event_submit').textContent = 'ثبت ستون';
                    createModal.querySelector('.modal-title').textContent = 'افزودن ستون جدید به دفتر کلاسی';
                }
            });
        }

        // ---------- Student Search ----------
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const filter = this.value.trim().toLowerCase();
                const rows = document.querySelectorAll('.rg-table tbody tr');

                rows.forEach(row => {
                    const nameButton = row.querySelector('.rg-name-btn[data-username]');
                    if (!nameButton) return;

                    const fullName = nameButton.textContent.trim().toLowerCase();
                    const username = (nameButton.getAttribute('data-username') || '').toLowerCase();

                    if (fullName.includes(filter) || username.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        const currentAdminRole = <?php echo (int)$adminrole; ?>;
        // ---------- Add Discipline Modal ----------
        const disciplineModal = document.getElementById('disciplineModal');
        if (disciplineModal) {
            disciplineModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const studentId   = button.getAttribute('data-student');
                const studentName = button.getAttribute('data-student-name');

                document.getElementById('discipline_student_id').value = studentId;
                document.getElementById('discipline_student_name').textContent = studentName;
                disciplineModal.querySelector('textarea[name="discipline_reason"]').value = '';
            });
        }

        // ---------- View Discipline Modal ----------
        const viewDisciplineModal = document.getElementById('viewDisciplineModal');
        if (viewDisciplineModal) {
            viewDisciplineModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const studentUsername = button.getAttribute('data-student');
                const studentName = button.getAttribute('data-student-name');

                document.getElementById('view_discipline_student_name').textContent = studentName;

                const container = document.getElementById('discipline_records_container');
                const disciplineData = <?php echo json_encode($discipline_lookup, JSON_UNESCAPED_UNICODE); ?>;
                const records = disciplineData[studentUsername] || [];

                if (records.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center">موردی یافت نشد.</p>';
                    return;
                }

                const canEdit = <?php echo $has_full_permission ? 'true' : 'false'; ?>;

                let html = '';
                records.forEach(function(record, index) {
                    html += `
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="text-danger small" style="flex: 1;">${record.reason}</div>
                                    ${canEdit ? `
                                    <div class="d-flex gap-1 ms-2">
                                        <button class="btn btn-sm text-primary p-0 edit-disc-btn"
                                                title="ویرایش"
                                                data-id="${record.id}"
                                                data-reason="${record.reason.replace(/"/g, '&quot;')}">
                                            <i class="fe fe-edit" style="font-size: 12px;"></i>
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('این مورد انضباطی حذف شود؟')">
                                            <input type="hidden" name="action" value="delete_discipline">
                                            <input type="hidden" name="discipline_id" value="${record.id}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="حذف این مورد">
                                                <i class="fe fe-trash-2" style="font-size: 11px;"></i> حذف
                                            </button>
                                        </form>
                                    </div>
                                    ` : ''}
                                </div>
                              <div class="text-muted mt-1" style="font-size: 10px;">
                                ${record.created_at}
                                ${currentAdminRole !== 3 ? ' | توسط: ' + record.created_by : ''}
                            </div>
                            </div>
                        `;
                });
                container.innerHTML = html;

                if (canEdit) {
                    container.querySelectorAll('.edit-disc-btn').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const reason = this.getAttribute('data-reason');
                            document.getElementById('edit_discipline_id').value = id;
                            document.getElementById('edit_discipline_reason').value = reason;

                            const viewModal = bootstrap.Modal.getInstance(viewDisciplineModal);
                            viewModal.hide();

                            const editModal = new bootstrap.Modal(document.getElementById('editDisciplineModal'));
                            editModal.show();
                        });
                    });
                }
            });
        }

        // ---------- Prevent double form submissions ----------
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.dataset.submitted === 'true') {
                    e.preventDefault();
                    return false;
                }
                if (submitBtn) {
                    submitBtn.dataset.submitted = 'true';
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> لطفاً صبر کنید...';
                }
            });
        });


    });
</script>
</body>
</html>
