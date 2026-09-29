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
    header('location: ' . url('admin/login.php'));
    die();
}
// Protecting against SESSION HIJACKING
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

AdminRequire(!empty($_GET['edit']) ? 'staff.teachers.edit' : 'staff.teacher-create');

$teacherSchoolRoleStmt = $conn->prepare("SELECT id FROM school_admin_roles WHERE role_key = 'teacher' LIMIT 1");
$teacherSchoolRoleStmt->execute();
$teacherSchoolRoleId = (int)($teacherSchoolRoleStmt->fetchColumn() ?: 8);

$teacherPermissionItems = [
    ['teacher_switch_courses', 'مشاهده پایه‌ها و کلاس‌های تخصیص‌یافته', m('perm.teacher_courses_desc')],
    ['teacher_switch_students', 'مشاهده دانش‌آموزان کلاس', m('perm.teacher_students_desc')],
    ['teacher_switch_attendance', 'ثبت جلسات، حضوروغیاب و نمرات', m('perm.teacher_attendance_desc')],
    ['teacher_switch_homework', 'مدیریت تکالیف کلاس', 'اجازه ایجاد، ویرایش، حذف و بررسی پاسخ‌های تکلیف در کلاس‌های تخصیص‌یافته را می‌دهد.'],
    ['teacher_switch_content', 'سرفصل، فیلم و جزوه کلاس', m('perm.teacher_content_desc')],
    ['teacher_switch_messages', 'پیام‌ها و پیگیری‌های آموزشی', 'امکان مشاهده و ارسال پیام، فایل و گزارش آموزشی به مدیریت یا مسئول پیگیری را می‌دهد.'],
];
$documentUploadErrors = $_SESSION['admin_document_flash_errors'] ?? [];
unset($_SESSION['admin_document_flash_errors']);

/*
 * Each switch of the teacher form stands for these nodes of the permission
 * tree. Every teacher also gets TEACHER_BASE_PERMISSIONS (dashboard boards and
 * the academic calendar).
 */
const TEACHER_SWITCH_PERMISSIONS = [
    'teacher_switch_courses' => ['courses.main.active.classes.detail', 'courses.main.active.classes.lesson-plans'],
    'teacher_switch_students' => ['courses.main.active.classes.students.assign', 'courses.main.active.classes.students.import',
        'courses.main.active.classes.students.remove', 'students.record.class-profile'],
    'teacher_switch_attendance' => ['courses.main.active.classes.attendance.manage', 'courses.main.active.classes.register'],
    'teacher_switch_homework' => ['courses.main.active.classes.homeworks'],
    'teacher_switch_content' => ['courses.main.active.content.dars.edit', 'courses.main.active.content.lessons',
        'courses.main.active.content.sub-chapters', 'courses.main.active.content.teacher-upload'],
    'teacher_switch_messages' => ['courses.main.active.classes.students.tickets', 'notifications.operator.teachers'],
    'teacher_switch_online_class' => ['courses.main.active.online-class'],
];
const TEACHER_BASE_PERMISSIONS = ['dashboard.notice-board', 'dashboard.news-board', 'dashboard.calendar',
    'executive.academic-calendar', 'executive.day-info'];

/** The tree grants of the ticked switches. */
function teacherPermissionCodes(array $switches): array
{
    $codes = TEACHER_BASE_PERMISSIONS;
    foreach ($switches as $switch => $on) {
        if ($on && isset(TEACHER_SWITCH_PERMISSIONS[$switch])) $codes = array_merge($codes, TEACHER_SWITCH_PERMISSIONS[$switch]);
    }
    return array_values(array_unique($codes));
}

/** A switch shows ticked when the teacher holds every node it stands for. */
function teacherHasSwitch(string $username, string $switch): bool
{
    foreach (TEACHER_SWITCH_PERMISSIONS[$switch] ?? [] as $code) {
        if (!AdminCan($code, $username)) return false;
    }
    return true;
}

function collectTeacherPermissions(array $items): array
{
    $values = [];
    foreach ($items as $item) {
        $values[$item[0]] = isset($_POST[$item[0]]) ? 1 : 0;
    }
    $values['teacher_switch_online_class'] = 0;
    return $values;
}

function renderTeacherPermissions(array $items, $teacher = null): void
{
    $isEdit = is_object($teacher);
    foreach ($items as $item) {
        [$name, $label, $help] = $item;
        $checked = $isEdit ? teacherHasSwitch((string)$teacher->username, $name) : true;
        ?>
        <div class="teacher-permission-option">
            <label class="mb-0">
                <input type="checkbox" name="<?= htmlspecialchars($name) ?>" <?= $checked ? 'checked' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </label>
            <span class="menu-info-trigger teacher-permission-info"
                  data-tooltip="<?= htmlspecialchars($help) ?>"
                  aria-label="راهنمای <?= htmlspecialchars($label) ?>"
                  onclick="event.preventDefault(); event.stopPropagation();">
                <i class="fe fe-info"></i>
            </span>
        </div>
    <?php }
}

function renderTeacherGroupSelector(array $selectedIds = [], string $id = 'teacherGroups'): void
{
    $selectedIds = array_map('intval', $selectedIds);
    ?>
    <div class="mt-3 pt-3 border-top">
        <label class="form-label" for="<?= htmlspecialchars($id) ?>"><?php echo m('label.teacher_groups') ?></label>
        <div class="d-flex align-items-start" style="gap:8px;">
            <select id="<?= htmlspecialchars($id) ?>" class="form-control js-teacher-groups"
                    name="teacher_groups[]" multiple data-placeholder="انتخاب گروه‌ها ..." style="flex:1 1 auto;">
                <?php foreach (getTeacherGroups() as $group): ?>
                    <option value="<?= (int)$group['id'] ?>" <?= in_array((int)$group['id'], $selectedIds, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                    data-bs-target="#teacherGroupsModal" title="مدیریت گروه‌های آموزشی">
                <i class="fe fe-settings"></i>
            </button>
        </div>
        <small class="text-muted">رویدادهای محدودشده به این گروه‌ها فقط برای اعضای همان گروه نمایش داده می‌شوند.</small>
    </div>
    <?php
}

// مدیریت گروه‌های آموزشی؛ همان مجوز مدیریت حساب معلمان و توکن فرم الزامی است.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_action'])) {
    AdminRequire('staff.teachers.group-action');
    $postedToken = (string)($_POST['token'] ?? '');
    $sessionToken = (string)($_SESSION['token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(403);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی و دوباره تلاش کنید.');
    }

    $groupTitle = trim((string)($_POST['group_title'] ?? ''));
    $groupDescription = trim((string)($_POST['group_description'] ?? ''));
    $groupId = (int)($_POST['group_id'] ?? 0);
    $actorId = (int)($_SESSION['admin']['idadmin'] ?? 0);

    if ($_POST['group_action'] === 'add') {
        $_SESSION['group_msg'] = createTeacherGroup($groupTitle, $groupDescription, $actorId)
            ? 'گروه جدید ثبت شد.' : 'ثبت گروه ناموفق بود؛ عنوان خالی یا تکراری است.';
    } elseif ($_POST['group_action'] === 'edit') {
        $_SESSION['group_msg'] = updateTeacherGroup($groupId, $groupTitle, $groupDescription)
            ? 'گروه ویرایش شد.' : 'ویرایش گروه ناموفق بود.';
    } elseif ($_POST['group_action'] === 'delete') {
        $_SESSION['group_msg'] = deleteTeacherGroup($groupId)
            ? 'گروه حذف شد.' : 'حذف گروه ناموفق بود.';
    }
    header('location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

// UPDATE TEACHER
if (isset($_POST['esave'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name     = trim($_POST['ename']);
        $famili   = trim($_POST['efamili']);
        $gender   = $_POST['egender'];
        $password = $_POST['epassword'];
        $mob      = !empty($_POST['emob']) ? $_POST['emob'] : NULL;
        $pic      = !empty($_POST['epic']) ? $_POST['epic'] : NULL;
        $activate = isset($_POST['eactivate']) ? 1 : 0;
        $skyroom  = isset($_POST['skyroom']) ? intval($_POST['skyroom']) : 1;
        $teacherPermissions = collectTeacherPermissions($teacherPermissionItems);

        // Operational role + school organizational role for teachers.
        $adminrole = 3;
        $schoolRoleId = $teacherSchoolRoleId;

        $uptv = 'UPDATE dbadmin SET password=?, fname=?, lname=?, adminactive=?, mobile=?, adminpic=?, gender=?, per_skyroom=?, adminrole=?, school_role_id=? WHERE username=? AND adminrole=3';
        $exup = $conn->prepare($uptv);
        $exup->execute([$password, $name, $famili, $activate, $mob, $pic, $gender, $skyroom, $adminrole, $schoolRoleId, $_GET['edit']]);
        SyncAdminPermissionGrants((string)$_GET['edit'], AdminGrantableCodes(teacherPermissionCodes($teacherPermissions), (string)$_GET['edit']));
        $editedTeacher = retAdmin((string)$_GET['edit']);
        if ($editedTeacher && !empty($editedTeacher->idadmin)) {
            setTeacherGroupsForAdmin((int)$editedTeacher->idadmin, $_POST['teacher_groups'] ?? []);
        }
        $documentUploadErrors = SaveAdminDocumentUploads((string)$_GET['edit']);
        $accept = 'بروز رسانی با موفقیت انجام شد';
    }
}

// INSERT TEACHER
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (GetAdminUsername($_POST['username'])) {
            $eror = 'نام کاربری از قبل وجود دارد';
        } else {
            if (checkDakheli($_POST['dakheli'])) {
                $name     = trim($_POST['name']);
                $famili   = trim($_POST['famili']);
                $dakheli  = $_POST['dakheli'];
                $gender   = $_POST['gender'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                $mob      = !empty($_POST['mob']) ? $_POST['mob'] : NULL;
                $pic      = !empty($_POST['pic']) ? $_POST['pic'] : NULL;
                $activate = isset($_POST['activate']) ? 1 : 0;
                $skyroom  = isset($_POST['skyroom']) ? intval($_POST['skyroom']) : 1;
                $teacherPermissions = collectTeacherPermissions($teacherPermissionItems);

                // Operational role + school organizational role for teachers.
                $adminrole = 3;
                $schoolRoleId = $teacherSchoolRoleId;

                hf_control_require_seat($conn, hf_control_admin_id($username), 'teacher');
                $addmin = 'INSERT INTO dbadmin (username, password, fname, lname, adminactive, mobile, dakheli, adminpic, gender, per_skyroom, adminrole, school_role_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $ins = $conn->prepare($addmin);
                $ins->execute([$username, $password, $name, $famili, $activate, $mob, $dakheli, $pic, $gender, $skyroom, $adminrole, $schoolRoleId]);
                setTeacherGroupsForAdmin((int)$conn->lastInsertId(), $_POST['teacher_groups'] ?? []);

                SyncAdminPermissionGrants($username, AdminGrantableCodes(teacherPermissionCodes($teacherPermissions), $username));
                $documentUploadErrors = SaveAdminDocumentUploads($username);
                if ($documentUploadErrors) {
                    $_SESSION['admin_document_flash_errors'] = $documentUploadErrors;
                    header('location: addteacher.php?edit=' . rawurlencode($username));
                    exit();
                }

                header('location: listteacher.php');
                exit();

            } else {
                $eror = 'داخلی وارد شده از قبل وجود دارد';
            }
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title><?php echo m('page.add_teacher') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .teacher-permission-option {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding: 10px;
            margin-bottom: 7px;
            border: 1px solid #edf0f5;
            border-radius: 7px;
            background: #fafbfc;
        }
        .teacher-permission-option label {
            flex: 1;
            line-height: 1.7;
            cursor: pointer;
        }
        .teacher-permission-option input { margin-left: 6px; }
        .teacher-permission-info {
            color: #5965db;
            cursor: help;
            font-size: 17px;
        }
    </style>
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

    <?php if (!isset($_GET['edit'])) { ?>
        <!-- ADD TEACHER FORM -->
        <div class="main-content side-content pt-0" dir="rtl">
            <div class="container-fluid">
                <div class="inner-body">

                    <!-- Error Alert Block -->
                    <?php if (isset($eror)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $eror ?>
                        </div>
                    <?php } ?>

                    <!-- CSRF Token Generation -->
                    <?php
                    $hash = bin2hex(random_bytes(32));
                    $_SESSION['token'] = $hash;
                    ?>

                    <form method="post" enctype="multipart/form-data" data-parsley-validate="">
                        <!-- Hidden CSRF Token Input -->
                        <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">

                        <div class="row row-sm">
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-items-center justify-content-between" style="background-color: #5c6bc0;">
                                        اطلاعات عمومی
                                        <span class="menu-info-trigger" style="color: white; cursor: pointer;"
                                              data-tooltip="<?php echo m('tooltip.add_teacher') ?>"
                                              aria-label="<?php echo m('aria.add_teacher_help') ?>"
                                              onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                        </span>
                                    </div>

                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="name" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="famili" type="text" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="username" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="password" type="password" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="dakheli" type="number" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="mob" type="text">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">جنسیت <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="gender" required>
                                                    <option value="خانم">خانم</option>
                                                    <option value="آقای">آقا</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نوع دسترسی اتاق مجازی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="skyroom" required>
                                                    <option value="1">شرکت کننده</option>
                                                    <option value="2">ارائه دهنده</option>
                                                    <option value="3">اپراتور</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6 d-flex align-items-center pt-3">
                                                <label class="ckbox">
                                                    <input name="activate" type="checkbox" checked>
                                                    <span>فعالسازی حساب</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">آدرس عکس</label>
                                            <input class="form-control" name="pic" type="text" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-items-center justify-content-between" style="background-color: #3949ab;">
                                        <?php echo m('label.teacher_permissions') ?>
<span class="menu-info-trigger" style="color: white; cursor: help;"
                                              data-tooltip="<?php echo m('tooltip.teacher_permissions') ?>"
                                              aria-label="<?php echo m('aria.teacher_permissions_help') ?>"
                                              onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info py-2"><?php echo m('help.teacher_permissions_scope') ?></div>
                                        <?php renderTeacherPermissions($teacherPermissionItems); ?>
                                        <?php renderTeacherGroupSelector([], 'teacherGroupsAdd'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;"><?php echo m('label.teacher_documents') ?></div>
                                    <div class="card-body">
                                        <div class="alert alert-light border">فرمت‌های مجاز: PDF، تصویر، Word — حداکثر حجم هر فایل ۱۰ مگابایت</div>
                                        <?php RenderAdminDocumentUploadFields(); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-start gap-2">
                                <button type="submit" class="btn btn-success px-4" name="save">
                                    <i class="fa fa-save"></i> <?php echo m('action.save_and_create_teacher') ?>
                                </button>
                                <a href="listteacher.php" class="btn text-white px-4" style="background-color: #e91e63;">
                                    انصراف و بازگشت
                                </a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>

    <?php } else {
        $user = $_GET['edit'];
        $retadmin = retAdmin($user);
        if ($retadmin == NULL || (int)$retadmin->adminrole !== 3) {
            echo '<script>window.location.href="listteacher.php"</script>';
            exit();
        }
        $teacherGroupIds = getTeacherGroupIdsForAdmin((int)$retadmin->idadmin);
        ?>

        <!-- EDIT TEACHER FORM -->
        <div class="main-content side-content pt-0" dir="rtl">
            <div class="container-fluid">
                <div class="inner-body">

                    <!-- Success Alert Block -->
                    <?php if (isset($accept)) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $accept ?>
                        </div>
                    <?php } ?>

                    <!-- CSRF Token Generation -->
                    <?php
                    $hash = bin2hex(random_bytes(32));
                    $_SESSION['token'] = $hash;
                    ?>

                    <form method="post" enctype="multipart/form-data" data-parsley-validate="">
                        <!-- Hidden CSRF Token Input -->
                        <input class="form-control" name="token" type="hidden" value="<?php echo $hash ?>">

                        <div class="row row-sm">
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex justify-content-between align-items-center" style="background-color: #5c6bc0;">
                                        <span class="d-flex align-items-center gap-2">اطلاعات عمومی
                                           <span class="menu-info-trigger" style="color: white; cursor: pointer;"
                                                 data-tooltip="<?php echo m('tooltip.edit_teacher') ?>"
                                                 aria-label="<?php echo m('aria.edit_teacher_help') ?>"
                                                 onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                           </span>
                                        </span>
                                        <span class="badge badge-warning p-2" style="font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($retadmin->fname . ' ' . $retadmin->lname); ?>
                                        </span>
                                    </div>

                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="ename" type="text" required value="<?php echo htmlspecialchars($retadmin->fname); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="efamili" type="text" required value="<?php echo htmlspecialchars($retadmin->lname); ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="eusername" type="text" required disabled value="<?php echo htmlspecialchars($retadmin->username); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="epassword" type="password" required value="<?php echo htmlspecialchars($retadmin->password); ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="edakheli" type="number" required disabled value="<?php echo htmlspecialchars($retadmin->dakheli); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="emob" type="text" value="<?php echo htmlspecialchars($retadmin->mobile); ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">جنسیت <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="egender" required>
                                                    <option <?php if ($retadmin->gender == 'خانم') echo 'selected'; ?> value="خانم">خانم</option>
                                                    <option <?php if ($retadmin->gender == 'آقای') echo 'selected'; ?> value="آقای">آقا</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نوع دسترسی اتاق مجازی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="skyroom" required>
                                                    <option value="1" <?php if ($retadmin->per_skyroom == '1') echo 'selected'; ?>>شرکت کننده</option>
                                                    <option value="2" <?php if ($retadmin->per_skyroom == '2') echo 'selected'; ?>>ارائه دهنده</option>
                                                    <option value="3" <?php if ($retadmin->per_skyroom == '3') echo 'selected'; ?>>اپراتور</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6 d-flex align-items-center pt-3">
                                                <label class="ckbox">
                                                    <input name="eactivate" type="checkbox" <?php if ($retadmin->adminactive == 1) echo 'checked'; ?>>
                                                    <span>فعالسازی حساب</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">آدرس عکس</label>
                                            <input class="form-control" name="epic" type="text" value="<?php echo htmlspecialchars($retadmin->adminpic); ?>" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-items-center justify-content-between" style="background-color: #3949ab;">
                                        <?php echo m('label.teacher_permissions') ?>
<span class="menu-info-trigger" style="color: white; cursor: help;"
                                              data-tooltip="<?php echo m('tooltip.edit_teacher_permissions') ?>"
                                              aria-label="<?php echo m('aria.edit_teacher_permissions_help') ?>"
                                              onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info py-2"><?php echo m('help.teacher_changes_scope') ?></div>
                                        <?php renderTeacherPermissions($teacherPermissionItems, $retadmin); ?>
                                        <?php renderTeacherGroupSelector($teacherGroupIds, 'teacherGroupsEdit'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;"><?php echo m('label.teacher_documents') ?></div>
                                    <div class="card-body">
                                        <?php if ($documentUploadErrors): ?>
                                            <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $documentUploadErrors)) ?></div>
                                        <?php endif; ?>
                                        <div class="alert alert-light border">فایل جدید فقط همان مدرک قبلی را جایگزین می‌کند. فرمت‌های مجاز: PDF، تصویر، Word</div>
                                        <?php RenderAdminDocumentUploadFields($retadmin); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12 d-flex justify-content-start gap-2">
                                <button type="submit" class="btn btn-warning px-4" name="esave">
                                    <i class="fa fa-save"></i> <?php echo m('action.save_and_edit_teacher') ?>
                                </button>
                                <a href="listteacher.php" class="btn text-white px-4" style="background-color: #e91e63;">
                                    انصراف و بازگشت
                                </a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="modal fade" id="teacherGroupsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">مدیریت گروه‌های آموزشی</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($_SESSION['group_msg'])): ?>
                        <div class="alert alert-info py-2"><?= htmlspecialchars($_SESSION['group_msg']) ?></div>
                        <?php unset($_SESSION['group_msg']); ?>
                    <?php endif; ?>
                    <form method="post" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="token" value="<?= htmlspecialchars((string)($_SESSION['token'] ?? '')) ?>">
                        <input type="hidden" name="group_action" value="add">
                        <div class="col-md-4">
                            <label class="form-label">عنوان گروه</label>
                            <input class="form-control" name="group_title" required placeholder="مثلاً ریاضی پایه ششم">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">توضیح اختیاری</label>
                            <input class="form-control" name="group_description">
                        </div>
                        <div class="col-md-3"><button class="btn btn-success w-100" type="submit">افزودن گروه</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light"><tr><th>عنوان</th><th>توضیح</th><th style="width:150px;">اقدام</th></tr></thead>
                            <tbody>
                            <?php $allTeacherGroups = getTeacherGroups(); ?>
                            <?php if (!$allTeacherGroups): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">هنوز گروهی ساخته نشده است.</td></tr>
                            <?php else: foreach ($allTeacherGroups as $group): ?>
                                <tr>
                                    <td colspan="3" class="p-0">
                                        <form method="post" class="row g-1 m-0 p-1 align-items-center">
                                            <input type="hidden" name="token" value="<?= htmlspecialchars((string)($_SESSION['token'] ?? '')) ?>">
                                            <input type="hidden" name="group_action" value="edit">
                                            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                                            <div class="col-md-4"><input class="form-control form-control-sm" name="group_title" value="<?= htmlspecialchars($group['title']) ?>" required></div>
                                            <div class="col-md-5"><input class="form-control form-control-sm" name="group_description" value="<?= htmlspecialchars($group['description'] ?? '') ?>"></div>
                                            <div class="col-md-3 text-nowrap">
                                                <button class="btn btn-sm btn-warning" type="submit">ذخیره</button>
                                                <button class="btn btn-sm btn-danger" type="submit" name="group_action" value="delete" onclick="return confirm('این گروه و عضویت‌هایش حذف شود؟')">حذف</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        if (!window.jQuery || !jQuery.fn.select2) return;
        jQuery('.js-teacher-groups').each(function () {
            if (jQuery(this).data('select2')) jQuery(this).select2('destroy');
            jQuery(this).select2({width: '100%', dir: 'rtl', placeholder: this.getAttribute('data-placeholder') || ''});
        });
    }, 0);
});
</script>
</body>
</html>
