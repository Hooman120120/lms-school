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
//Protecting against SESSION HIJACKING
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
/*
 * Editing another account needs «ویرایش همکار», adding one needs «ثبت همکار».
 * Only an account that holds the whole tree may change one that does, and an
 * editor can hand out only permissions they hold themselves.
 */
$staffEditorIsTop = AdminHoldsEverything();
if (!empty($_GET['edit'])) {
    AdminRequire('staff.manage.edit');
    if (AdminHoldsEverything((string)$_GET['edit']) && !$staffEditorIsTop) {
        http_response_code(403);
        exit('فقط مدیر می‌تواند حساب مدیر را ویرایش کند.');
    }
} else {
    AdminRequire('staff.create');
}

/*
 * The tree grants to store for an account: the school manager post holds the
 * whole tree ('*'); otherwise the ticked boxes, limited to what the editor may
 * grant.
 */
function staffGrantablePermissionCodes(array $requested, string $target, ?int $adminrole = null): array
{
    if ($adminrole === 0 && AdminHoldsEverything()) return [ADMIN_PERMISSION_ALL];
    return AdminGrantableCodes(array_values(array_diff($requested, [ADMIN_PERMISSION_ALL])), $target);
}

$schoolRoles = $conn->query(
    "SELECT id, role_key, title FROM school_admin_roles WHERE is_active = 1 AND role_key <> 'teacher' ORDER BY sort_order ASC, id ASC"
)->fetchAll(PDO::FETCH_OBJ);
$validSchoolRoleIds = array_map('intval', array_column(array_map(function ($role) {
    return (array)$role;
}, $schoolRoles), 'id'));
$courseScopedRoleKeys = ['grade_deputy', 'counselor'];
$assignableCourses = $conn->query('
    SELECT codecourse, coursename
    FROM codtbl_course
    WHERE activecourse = 1
    ORDER BY idcourse DESC
')->fetchAll(PDO::FETCH_OBJ);
$editAssignedCourseCodes = [];
if (!empty($_GET['edit'])) {
    $assignedCourseStmt = $conn->prepare('SELECT codecourse FROM school_staff_courses WHERE admin_username = ?');
    $assignedCourseStmt->execute([(string)$_GET['edit']]);
    $editAssignedCourseCodes = array_map('intval', $assignedCourseStmt->fetchAll(PDO::FETCH_COLUMN));
}
$documentUploadErrors = $_SESSION['admin_document_flash_errors'] ?? [];
unset($_SESSION['admin_document_flash_errors']);

function normalizeStaffCourseCodes(PDO $conn, $rawCourseCodes): array
{
    if (!is_array($rawCourseCodes)) {
        return [];
    }
    $courseCodes = array_values(array_unique(array_filter(array_map('intval', $rawCourseCodes), function ($code) {
        return $code > 0;
    })));
    if (!$courseCodes) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($courseCodes), '?'));
    $stmt = $conn->prepare("SELECT codecourse FROM codtbl_course WHERE activecourse = 1 AND codecourse IN ($placeholders)");
    $stmt->execute($courseCodes);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function syncStaffCourseAssignments(PDO $conn, string $username, ?int $schoolRoleId, array $courseCodes, array $schoolRoles, array $scopedRoleKeys): void
{
    $roleKey = null;
    foreach ($schoolRoles as $role) {
        if ((int)$role->id === (int)$schoolRoleId) {
            $roleKey = (string)$role->role_key;
            break;
        }
    }

    $delete = $conn->prepare('DELETE FROM school_staff_courses WHERE admin_username = ?');
    $delete->execute([$username]);
    if (!in_array($roleKey, $scopedRoleKeys, true)) {
        return;
    }

    $insert = $conn->prepare('INSERT INTO school_staff_courses (admin_username, codecourse) VALUES (?, ?)');
    foreach ($courseCodes as $codecourse) {
        $insert->execute([$username, $codecourse]);
    }
}

function schoolRoleKeyById(?int $schoolRoleId, array $schoolRoles): ?string
{
    foreach ($schoolRoles as $role) {
        if ((int)$role->id === (int)$schoolRoleId) {
            return (string)$role->role_key;
        }
    }
    return null;
}

/** School students list registration groups as one list; the other groups stay as they are. */
function employeeMenuPermissionChildren(array $node, array $selected): array
{
    $children = array_values(array_filter($node['children'] ?? [], 'AdminPermissionNodeAvailable'));
    if (!inst_is('school') || $node['code'] !== 'students') return $children;
    $flat = [];
    foreach ($children as $group) {
        if (!in_array($group['code'], ['students.definitive', 'students.temporary'], true)) {
            $flat[] = $group;
            continue;
        }
        foreach ($group['children'] ?? [] as $child) {
            if (!AdminPermissionNodeAvailable($child)) continue;
            $child['_grantedByGroup'] = in_array($group['code'], $selected, true);
            $flat[] = $child;
        }
    }
    // Place the extra-curricular actions beside their ordinary counterparts.
    foreach (['students.temporary.enrolment' => 'students.definitive.enrolment',
              'students.temporary.report' => 'students.definitive.course-report'] as $code => $after) {
        $position = array_search($code, array_column($flat, 'code'), true);
        if ($position === false) continue;
        $moved = array_splice($flat, $position, 1);
        $position = array_search($after, array_column($flat, 'code'), true);
        array_splice($flat, $position === false ? count($flat) : $position + 1, 0, $moved);
    }
    return $flat;
}

function renderMenuPermissionNode(array $node, array $selected, int $depth = 0, bool $grantedByParent = false): void
{
    static $instance = 0;
    if (!AdminPermissionNodeAvailable($node)) return;
    /* A checked parent grants its complete branch. Rendering descendants as
       checked keeps that broad grant visible when the form is edited. */
    $checked = $grantedByParent || in_array($node['code'], $selected, true);
    $children = employeeMenuPermissionChildren($node, $selected);
    $childrenId = 'permission-children-' . (++$instance) . '-' . substr(sha1($node['code']), 0, 8);
    $startsCollapsed = $children !== [];
    ?>
    <div class="menu-permission-node depth-<?= $depth ?><?= $startsCollapsed ? ' is-collapsed' : '' ?>" data-menu-node data-depth="<?= $depth ?>">
        <div class="menu-permission-row">
            <input class="menu-permission-check" type="checkbox" name="access_permissions[]"
                   value="<?= htmlspecialchars($node['code'], ENT_QUOTES, 'UTF-8') ?>"
                   aria-label="اعطای دسترسی <?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?>"
                   <?= $checked ? 'checked' : '' ?>>
            <?php if ($children): ?>
                <button type="button" class="menu-permission-name"
                        aria-expanded="<?= $startsCollapsed ? 'false' : 'true' ?>"
                        aria-controls="<?= $childrenId ?>"><?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></button>
                <span class="menu-permission-count"><?= count($children) ?> زیرمجموعه</span>
                <button type="button" class="menu-permission-toggle"
                        aria-expanded="<?= $startsCollapsed ? 'false' : 'true' ?>"
                        aria-controls="<?= $childrenId ?>"
                        title="جمع یا باز کردن زیرمجموعه‌ها">
                    <i class="fe fe-chevron-down" aria-hidden="true"></i>
                    <span class="sr-only">جمع یا باز کردن زیرمجموعه‌های <?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            <?php else: ?>
                <span class="menu-permission-name-static"><?= htmlspecialchars(AdminPermissionDisplayLabel($node), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <?php if ($children): ?>
            <div class="menu-permission-children" id="<?= $childrenId ?>" <?= $startsCollapsed ? 'hidden' : '' ?>>
                <?php foreach ($children as $child) renderMenuPermissionNode($child, $selected, $depth + 1, $checked || !empty($child['_grantedByGroup'])); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function renderMenuPermissionTree(?object $admin = null): void
{
    global $conn;
    $selected = [];
    if ($admin && !empty($admin->username)) {
        try {
            $stmt = $conn->prepare('SELECT permission_code FROM admin_user_permissions WHERE admin_username = ?');
            $stmt->execute([$admin->username]);
            $selected = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) { $selected = []; }
    }
    $holdsEverything = in_array(ADMIN_PERMISSION_ALL, $selected, true);
    echo '<div class="col-12 menu-permission-tree">';
    foreach (AdminPermissionRegistry() as $node) renderMenuPermissionNode($node, $selected, 0, $holdsEverything);
    echo '</div>';
}

/*
 * «سمت سازمانی مدرسه/آموزشگاه»: one dropdown instead of «سطح دسترسی سامانه»
 * (dbadmin.adminrole) + «سمت سازمانی مدرسه» (dbadmin.school_role_id). Both
 * columns are still stored and read everywhere as before; each option sets both.
 * School panels list the school posts, institutes the access levels.
 */
const STAFF_SCHOOL_ROLE_ADMINROLE = [
    'manager' => 0, 'general_deputy' => 51, 'executive_deputy' => 51, 'grade_deputy' => 52,
    'technical_deputy' => 54, 'financial_deputy' => 55, 'counselor' => 1,
    'cultural_disciplinary_deputy' => 2, 'disciplinary_deputy' => 2, 'cultural_deputy' => 2,
];
const STAFF_ADMINROLE_TITLES = [
    0 => 'مدیر', 51 => 'معاونت اجرایی', 52 => 'معاونت آموزشی', 53 => 'معاونت فروش', 54 => 'معاونت فنی',
    55 => 'معاونت مالی', 2 => 'معاونت میانی', 6 => 'کارشناس فروش', 1 => 'پشتیبان',
];
// institute access level -> school post, only where the two clearly mean the same job
const STAFF_ADMINROLE_SCHOOL_ROLE = [0 => 'manager', 51 => 'executive_deputy', 54 => 'technical_deputy', 55 => 'financial_deputy'];

/*
 * Default permissions ticked when a post is picked on the «add» form (the admin
 * can still change them before saving). '*' = everything. Keys are the school
 * post keys; institute access levels point at the closest one below.
 */
const STAFF_ROLE_PERMISSION_PRESETS = [
    'manager' => ['*'],
    'general_deputy' => ['dashboard', 'public-relations', 'students', 'executive', 'courses', 'school-notifications', 'tickets',
        'documents', 'staff.directory', 'staff.teachers', 'assigned-tasks', 'referrers', 'reports', 'homework-approval',
        'finance.pending', 'finance.unpaid', 'finance.returned', 'finance.wallet', 'finance.statement', 'finance.installments'],
    'executive_deputy' => ['dashboard', 'public-relations', 'students', 'executive', 'school-notifications', 'tickets',
        'documents', 'staff.directory', 'staff.teachers', 'assigned-tasks', 'reports.students'],
    'grade_deputy' => ['dashboard', 'students', 'executive.academic-calendar', 'executive.daily-notices', 'executive.news',
        'courses.main', 'school-notifications', 'tickets', 'documents', 'staff.teachers', 'homework-approval', 'reports.students'],
    'cultural_disciplinary_deputy' => ['dashboard', 'students', 'executive.academic-calendar', 'executive.daily-notices',
        'executive.news', 'school-notifications', 'tickets', 'documents'],
    'counselor' => ['dashboard', 'public-relations.safe-call', 'public-relations.safe-sms', 'students',
        'executive.academic-calendar', 'school-notifications', 'tickets.inbox', 'tickets.open', 'documents'],
    'technical_deputy' => ['dashboard', 'executive', 'documents', 'assigned-tasks', 'school-notifications', 'tickets', 'staff.directory'],
    'financial_deputy' => ['dashboard', 'finance', 'school-notifications.financial', 'documents', 'staff.directory'],
    'sales_deputy' => ['dashboard', 'public-relations', 'students', 'referrers', 'finance.pending', 'finance.unpaid',
        'reports.students', 'tickets', 'documents'],
    'sales_expert' => ['dashboard', 'public-relations', 'students', 'referrers', 'tickets.inbox', 'documents'],
];
const STAFF_ADMINROLE_PRESET = [
    0 => 'manager', 51 => 'general_deputy', 52 => 'grade_deputy', 53 => 'sales_deputy', 54 => 'technical_deputy',
    55 => 'financial_deputy', 2 => 'cultural_disciplinary_deputy', 6 => 'sales_expert', 1 => 'counselor',
];

function staffRoleOptions(array $schoolRoles, array $courseScopedRoleKeys, $current = null): array
{
    $top = AdminHoldsEverything();
    $idByKey = [];
    foreach ($schoolRoles as $role) $idByKey[(string)$role->role_key] = (int)$role->id;
    $options = [];
    if (inst_is('school')) {
        foreach ($schoolRoles as $role) {
            $key = (string)$role->role_key;
            $adminrole = STAFF_SCHOOL_ROLE_ADMINROLE[$key] ?? 2;
            $value = 's' . (int)$role->id;
            $isCurrent = $current && (int)($current->school_role_id ?? 0) === (int)$role->id;
            if (in_array($adminrole, [0, 51], true) && !$top && !$isCurrent) continue;
            $options[$value] = ['label' => (string)$role->title, 'adminrole' => $adminrole, 'school_role_id' => (int)$role->id,
                'role_key' => $key, 'course_scope' => in_array($key, $courseScopedRoleKeys, true), 'preset' => $key];
        }
    } else {
        foreach (STAFF_ADMINROLE_TITLES as $adminrole => $title) {
            $value = 'a' . $adminrole;
            $isCurrent = $current && $current->adminrole !== null && $current->adminrole !== '' && (int)$current->adminrole === $adminrole;
            if (in_array($adminrole, [0, 51], true) && !$top && !$isCurrent) continue;
            $key = STAFF_ADMINROLE_SCHOOL_ROLE[$adminrole] ?? null;
            $options[$value] = ['label' => $title, 'adminrole' => $adminrole, 'school_role_id' => $key !== null ? ($idByKey[$key] ?? null) : null,
                'role_key' => (string)$key, 'course_scope' => false, 'preset' => STAFF_ADMINROLE_PRESET[$adminrole] ?? ''];
        }
    }
    return $options;
}

/** The option this admin has now (for the edit form). */
function staffRoleCurrentValue($current): string
{
    if (!$current) return '';
    if (inst_is('school')) return !empty($current->school_role_id) ? 's' . (int)$current->school_role_id : '';
    return ($current->adminrole !== null && $current->adminrole !== '') ? 'a' . (int)$current->adminrole : '';
}

/**
 * Turn the one dropdown back into the two fields the save code expects. When
 * editing and the choice is unchanged, the other stored value is kept as is,
 * so opening and saving an admin never changes their access by itself.
 */
function applyStaffRoleChoice(string $field, string $prefix, array $options, $current = null): void
{
    $choice = (string)($_POST[$field] ?? '');
    unset($_POST[$prefix . 'adminrole'], $_POST[$prefix . 'school_role_id']);
    if (!isset($options[$choice])) return;
    $option = $options[$choice];
    $adminrole = $option['adminrole'];
    $schoolRoleId = $option['school_role_id'];
    if ($current && $choice === staffRoleCurrentValue($current)) {
        if (inst_is('school')) {
            $adminrole = ($current->adminrole === null || $current->adminrole === '') ? null : (int)$current->adminrole;
        } else {
            $schoolRoleId = empty($current->school_role_id) ? null : (int)$current->school_role_id;
        }
    }
    if ($adminrole !== null) $_POST[$prefix . 'adminrole'] = (string)$adminrole;
    if ($schoolRoleId !== null) $_POST[$prefix . 'school_role_id'] = (string)$schoolRoleId;
}

if (isset($_POST['esave']) && isset($_GET['edit'])) {
    $staffRoleCurrent = retAdmin((string)$_GET['edit']);
    applyStaffRoleChoice('estaff_role', 'e', staffRoleOptions($schoolRoles, $courseScopedRoleKeys, $staffRoleCurrent), $staffRoleCurrent);
}
if (isset($_POST['save'])) {
    applyStaffRoleChoice('staff_role', '', staffRoleOptions($schoolRoles, $courseScopedRoleKeys));
}

if (isset($_POST['esave'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim($_POST['ename']);
        $famili = trim($_POST['efamili']);
        $gender = $_POST['egender'];
        $password = $_POST['epassword'];
        if (isset($_POST['emob'])) {
            $mob = $_POST['emob'];
        } else {
            $mob = NULL;
        }
        if (isset($_POST['epic'])) {
            $pic = $_POST['epic'];
        } else {
            $pic = NULL;
        }
        if (isset($_POST['eactivate'])) {
            $activate = 1;
        } else {
            $activate = 0;
        }
        $skyroom = intval($_POST['skyroom']);

        $adminrole = (isset($_POST['eadminrole']) && in_array((int)$_POST['eadminrole'], [0,51,52,53,54,55,2,6,1,3], true))
            ? (int)$_POST['eadminrole'] : NULL;
        $schoolRoleId = isset($_POST['eschool_role_id']) && in_array((int)$_POST['eschool_role_id'], $validSchoolRoleIds, true)
            ? (int)$_POST['eschool_role_id'] : NULL;
        $selectedSchoolRoleKey = schoolRoleKeyById($schoolRoleId, $schoolRoles);
        if (inst_is('school') && $selectedSchoolRoleKey === 'counselor') {
            // مشاور از نظر قالب پنل و رفتار قدیمی سامانه همان adminrole پشتیبان است.
            $adminrole = 1;
        } elseif ($selectedSchoolRoleKey === 'financial_deputy') {
            $adminrole = 55;
        } elseif ($selectedSchoolRoleKey === 'technical_deputy') {
            $adminrole = 54;
        }
        $staffCourseCodes = normalizeStaffCourseCodes($conn, $_POST['ecourse_codes'] ?? []);

        $uptv = 'UPDATE dbadmin SET password=?,fname=?,lname=?,adminactive=?,mobile=?,adminpic=?,gender=?,per_skyroom=?,adminrole=? WHERE username=?';
        $exup = $conn->prepare($uptv);
        $exup->execute([$password, $name, $famili, $activate, $mob, $pic, $gender, $skyroom, $adminrole, $_GET['edit']]);
        $schoolRoleUpdate = $conn->prepare('UPDATE dbadmin SET school_role_id = ? WHERE username = ?');
        $schoolRoleUpdate->execute([$schoolRoleId, $_GET['edit']]);
        SyncAdminPermissionGrants((string)$_GET['edit'], staffGrantablePermissionCodes((array)($_POST['access_permissions'] ?? []), (string)$_GET['edit'], $adminrole));
        syncStaffCourseAssignments($conn, (string)$_GET['edit'], $schoolRoleId, $staffCourseCodes, $schoolRoles, $courseScopedRoleKeys);
        // داخلی (Simotel extension): editable here; digits only, unique among admins.
        if (isset($_POST['edakheli'])) {
            $newDakheli = trim(strtr((string) $_POST['edakheli'], ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']));
            if ($newDakheli !== '' && (!ctype_digit($newDakheli) || strlen($newDakheli) > 6)) {
                $eror = 'داخلی باید عددی حداکثر ۶ رقمی باشد';
            } else {
                $dakheliTaken = $conn->prepare('SELECT 1 FROM dbadmin WHERE dakheli = ? AND username <> ? LIMIT 1');
                $dakheliTaken->execute([$newDakheli, (string) $_GET['edit']]);
                if ($newDakheli !== '' && $dakheliTaken->fetchColumn()) {
                    $eror = 'داخلی ' . $newDakheli . ' برای همکار دیگری ثبت شده است';
                } else {
                    // empty = no extension (NULL: the column is unique, so '' could only be used once)
                    $conn->prepare('UPDATE dbadmin SET dakheli = ? WHERE username = ?')->execute([$newDakheli !== '' ? $newDakheli : null, (string) $_GET['edit']]);
                }
            }
        }
        $documentUploadErrors = SaveAdminDocumentUploads((string)$_GET['edit']);
        $accept = 'بروز رسانی با موفقیت انجام شد';
    }
}
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (GetAdminUsername($_POST['username'])) {
            $eror = 'نام کاربری از قبل وجود دارد';
        } else {
            if (checkDakheli($_POST['dakheli'])) {
                $name = trim($_POST['name']);
                $famili = trim($_POST['famili']);
                $dakheli = $_POST['dakheli'];
                $gender = $_POST['gender'];
                $username = $_POST['username'];
                $password = $_POST['password'];
                if (isset($_POST['mob'])) {
                    $mob = $_POST['mob'];
                } else {
                    $mob = NULL;
                }
                if (isset($_POST['pic'])) {
                    $pic = $_POST['pic'];
                } else {
                    $pic = NULL;
                }
                if (isset($_POST['activate'])) {
                    $activate = 1;
                } else {
                    $activate = 0;
                }
                $skyroom = intval($_POST['skyroom']);
                // each number represents a role , for more info about the roles check menu.php in admin directory or the comment of adminrole column in dbadmin.
                $adminrole = (isset($_POST['adminrole']) && in_array((int)$_POST['adminrole'], [0,51,52,53,54,55,2,6,1,3], true))
                    ? (int)$_POST['adminrole'] : NULL;
                $schoolRoleId = isset($_POST['school_role_id']) && in_array((int)$_POST['school_role_id'], $validSchoolRoleIds, true)
                    ? (int)$_POST['school_role_id'] : NULL;
                $selectedSchoolRoleKey = schoolRoleKeyById($schoolRoleId, $schoolRoles);
                if (inst_is('school') && $selectedSchoolRoleKey === 'counselor') {
                    // مشاور با داشبورد و منطق پشتیبان قدیمی ایجاد می‌شود.
                    $adminrole = 1;
                } elseif ($selectedSchoolRoleKey === 'financial_deputy') {
                    $adminrole = 55;
                } elseif ($selectedSchoolRoleKey === 'technical_deputy') {
                    $adminrole = 54;
                }
                $staffCourseCodes = normalizeStaffCourseCodes($conn, $_POST['course_codes'] ?? []);

                $hfStaffType = $adminrole === 3 ? 'teacher' : ($adminrole === 0 ? 'admin' : 'staff');
                hf_control_require_seat($conn, hf_control_admin_id($username), $hfStaffType);
                $addmin = 'INSERT INTO dbadmin (username,password,fname,lname,adminactive,mobile,dakheli,adminpic,gender,per_skyroom,adminrole)
VALUES (?,?,?,?,?,?,?,?,?,?,?)';
                $ins = $conn->prepare($addmin);
                $ins->execute([$username, $password, $name, $famili, $activate, $mob, $dakheli, $pic, $gender, $skyroom, $adminrole]);
                $schoolRoleUpdate = $conn->prepare('UPDATE dbadmin SET school_role_id = ? WHERE username = ?');
                $schoolRoleUpdate->execute([$schoolRoleId, $username]);
                SyncAdminPermissionGrants($username, staffGrantablePermissionCodes((array)($_POST['access_permissions'] ?? []), $username, $adminrole));
                syncStaffCourseAssignments($conn, $username, $schoolRoleId, $staffCourseCodes, $schoolRoles, $courseScopedRoleKeys);
                $can = static fn(string $code, bool $see = false): int => (int)($see ? AdminCanSee($code, $username) : AdminCan($code, $username));
                $oesPermissions = [
                    'dashboard.view' => 1,
                    'calendar.view' => $can('executive.calendar', true),
                    'calendar.manage' => $can('executive.calendar.edit'),
                    'students.section' => $can('students', true),
                    'students.register' => $can('students.definitive.register'),
                    'groups.registration' => $can('students.definitive.enrolment'),
                    'groups.manage' => $can('courses.main', true),
                    'groups.assign' => $can('courses.main', true),
                    'students.report' => $can('students.definitive.course-report'),
                    'packages.create' => $can('dashboard.exam-system'),
                    'packages.schedule_manage' => $can('dashboard.exam-system'),
                    'exams.section' => $can('dashboard.exam-system'),
                    'exams.manage' => $can('dashboard.exam-system'),
                    'exams.registration' => $can('dashboard.exam-system'),
                    'exams.list' => $can('dashboard.exam-system'),
                    'packages.list' => $can('dashboard.exam-system'),
                    'results.list' => $can('dashboard.exam-system'),
                    'results.detailed' => $can('dashboard.exam-system'),
                    'exams.descriptive_grade' => $can('dashboard.exam-system'),
                    'colleagues.section' => $can('staff', true),
                    'colleagues.create' => $can('staff.manage'),
                    'colleagues.list' => $can('staff', true),
                    'colleagues.edit' => $can('staff.manage'),
                    'colleagues.reset_password' => $can('staff.manage'),
                    'colleagues.delete' => $can('staff.manage'),
                    'content.section' => $can('executive.news', true),
                    'exams.approve' => $can('dashboard.exam-system'),
                    'notifications.manage' => $can('executive.news', true),
                    'news.manage' => $can('executive.news', true),
                    'events.manage' => $can('executive.news', true),
                    'requests.section' => $can('tickets', true),
                    'signup_requests.manage' => $can('tickets', true),
                    'teacher_tickets.manage' => $can('tickets', true),
                ];
                // Same account in this school's OES and chahard (when it has them): same
                // username and password, close permissions. Each panel gets its own
                // password verifier (OES md5, chahard bcrypt), never the plain password.
                $crossWarnings = [];
                $oesSync = cross_panel_sync_oes_admin([
                    'username' => $username,
                    'password_md5' => md5($password),
                    'first_name' => $name,
                    'last_name' => $famili,
                    'email' => '',
                    'mobile' => $mob,
                    'gender' => $gender === 'آقای' ? 1 : 0,
                    'active' => (int) $activate,
                    'role' => cross_panel_oes_role($adminrole, $selectedSchoolRoleKey),
                    'permissions' => $oesPermissions,
                ]);
                if (empty($oesSync['ok'])) {
                    $crossWarnings[] = (string) ($oesSync['message'] ?? 'ساخت حساب در پنل آزمون انجام نشد');
                }
                $chahardSync = cross_panel_sync_chahard_admin([
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name' => $name,
                    'last_name' => $famili,
                    'mobile' => $mob,
                    'gender' => $gender === 'آقای' ? 'male' : 'female',
                    'active' => (int) $activate,
                    'manager' => $adminrole === 0,
                    'permissions' => ['excel' => $can('students.export'), 'history' => $can('students.record.history')],
                ]);
                if (empty($chahardSync['ok'])) {
                    $crossWarnings[] = (string) ($chahardSync['message'] ?? 'ساخت حساب در پنل کلاس آنلاین انجام نشد');
                } elseif (!empty($chahardSync['warning'])) {
                    $crossWarnings[] = 'پنل کلاس آنلاین: ' . $chahardSync['warning'];
                }
                if ($crossWarnings) {
                    $_SESSION['cross_panel_warning'] = implode(' — ', $crossWarnings);
                }
                $documentUploadErrors = SaveAdminDocumentUploads($username);
                if ($documentUploadErrors) {
                    $_SESSION['admin_document_flash_errors'] = $documentUploadErrors;
                    header('location: addemploy.php?edit=' . rawurlencode($username));
                    exit;
                }
                header('location: listadmin.php');

            } else {
                $eror = 'داخلی وارد شده از قبل وجود دارد';
            }
        }


    }
}
include_once '../../temp.html';
displayError(1);
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
    <title> افزودن کارمند جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .permission-option {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 9px;
            margin-bottom: 5px;
            border: 1px solid #edf0f5;
            border-radius: 7px;
            background: #fafbfc;
        }

        .permission-option label {
            flex: 1;
            line-height: 1.7;
            cursor: pointer;
        }

        .permission-option input {
            margin-left: 6px;
        }

        .permission-info {
            flex: 0 0 auto;
            color: #5965db;
            cursor: help;
            font-size: 17px;
            margin-top: 1px;
        }
        .permission-tree { border: 1px solid #dce4ef; border-radius: 10px; overflow: hidden; background: #fff; }
        .permission-tree-heading { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:11px 12px; background:#f0f5fb; border-bottom:1px solid #dce4ef; }
        .permission-tree-heading h6 { color:#214f87; font-weight:800; }
        .permission-tree-heading small { color:#667085; line-height:1.6; display:block; }
        .permission-master { white-space:nowrap; font-weight:700; color:#214f87; cursor:pointer; }
        .permission-tree-children { padding:8px; }
        .permission-tree-children .permission-option { margin-bottom:6px; }
        .menu-permission-tree { width:100%; }
        .menu-permission-node { border:1px solid #e1e8f0; border-radius:8px; margin:7px 0; background:#fff; overflow:hidden; }
        .menu-permission-row { display:flex; align-items:center; min-height:44px; padding:0 8px 0 6px; }
        .menu-permission-check { flex:0 0 auto; margin:0 4px 0 9px; cursor:pointer; }
        .menu-permission-name, .menu-permission-name-static { flex:1; text-align:right; padding:10px 4px; font-weight:700; }
        .menu-permission-name { border:0; background:transparent; color:inherit; cursor:pointer; }
        .menu-permission-name:hover, .menu-permission-name:focus { color:#1769aa; text-decoration:underline; outline:none; }
        .menu-permission-count { color:#768396; font-size:12px; white-space:nowrap; margin:0 8px; }
        .menu-permission-toggle { width:34px; height:34px; flex:0 0 34px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:0; border-radius:7px; color:#315d8b; background:#e5eef8; cursor:pointer; }
        .menu-permission-toggle:hover, .menu-permission-toggle:focus { color:#fff; background:#315d8b; outline:none; }
        .menu-permission-toggle i { transition:transform .18s ease; }
        .menu-permission-node.is-collapsed > .menu-permission-row .menu-permission-toggle i { transform:rotate(90deg); }
        .menu-permission-children { margin:0 18px 10px 0; padding-right:12px; border-right:2px solid #d8e4f2; }
        .menu-permission-children[hidden] { display:none !important; }
        .menu-permission-node.depth-0 > .menu-permission-row { background:#eef5fc; color:#174a7c; font-size:16px; }
        .permission-tree-actions { display:flex; align-items:center; gap:5px; }
        .permission-tree-action { border:1px solid rgba(255,255,255,.7); border-radius:6px; padding:3px 7px; color:#fff; background:transparent; font-size:12px; cursor:pointer; }
        .permission-tree-action:hover, .permission-tree-action:focus { color:#e91e63; background:#fff; outline:none; }
        .permission-card > .card-header { flex-wrap:wrap; gap:8px; }
        @media (max-width: 576px) {
            .permission-tree-heading { align-items:flex-start; flex-direction:column; }
            .permission-card > .card-header > div { width:100%; justify-content:space-between; gap:6px !important; }
            .permission-tree-action { padding:3px 5px; }
            .menu-permission-count { display:none; }
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

                            <!-- RIGHT COLUMN: General Information (اطلاعات عمومی) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #5c6bc0;">
                                        اطلاعات عمومی
                                        <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید با ثبت اطلاعات عمومی، تعیین نقش، سطوح دسترسی و سایر تنظیمات، حساب کاربری کارمند جدید را در سامانه ایجاد کنید تا امکان ورود و استفاده از بخش‌های مجاز برای او فراهم شود. همچنین در همین قسمت، اطلاعات ورود شامل نام کاربری و رمز عبور مشخص می‌گردد؛ لطفاً توجه داشته باشید که این اطلاعات به‌درستی ثبت شده و به صورت امن تنها در اختیار شخص مجاز قرار گیرد."
                                              aria-label="راهنمای کامل ایجاد کارمند و اطلاعات ورود"
                                              onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                    </span>

                                    </div>
                                    <div class="card-body">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="name" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="famili" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="username" aria-describedby="basic-addon2" type="text" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="password" aria-describedby="basic-addon2" type="password" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="dakheli" aria-describedby="basic-addon2" type="number" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="mob" aria-describedby="basic-addon2" type="text">
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
                                            <div class="col-md-6">
                                                <label class="form-label">سمت سازمانی <?= inst_is('school') ? 'مدرسه' : 'آموزشگاه' ?> <span class="tx-danger">*</span></label>
                                                <select class="form-control" name="staff_role" required>
                                                    <option value="">— انتخاب سمت —</option>
                                                    <?php foreach (staffRoleOptions($schoolRoles, $courseScopedRoleKeys) as $staffRoleValue => $staffRoleOption) { ?>
                                                        <option value="<?= htmlspecialchars($staffRoleValue) ?>"
                                                                data-role-key="<?= htmlspecialchars($staffRoleOption['role_key']) ?>"
                                                                data-course-scope="<?= $staffRoleOption['course_scope'] ? '1' : '0' ?>"
                                                                data-preset="<?= htmlspecialchars($staffRoleOption['preset'] ?? '') ?>"
                                                            <?php if ('' === $staffRoleValue) { echo 'selected'; } ?>>
                                                            <?= htmlspecialchars($staffRoleOption['label']) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3 staff-course-scope d-none" data-mode="create">
                                            <div class="col-12">
                                                <label class="form-label">پایه‌های قابل دسترسی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2 staff-course-select" name="course_codes[]" multiple>
                                                    <?php foreach ($assignableCourses as $course) { ?>
                                                        <option value="<?= (int)$course->codecourse ?>"><?= htmlspecialchars($course->coursename) ?></option>
                                                    <?php } ?>
                                                </select>
                                                <small class="text-muted">برای معاون پایه یا مشاور، فقط پایه‌های انتخاب‌شده در بخش پایه‌ها نمایش داده می‌شوند.</small>
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
                                            <input class="form-control" name="pic" aria-describedby="basic-addon2" type="text" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- LEFT COLUMN: Permissions (دسترسى‌ها و نقش‌ها) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm permission-card">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #e91e63;">
                                        دسترسی‌ها و نقش‌ها
                                        <div class="d-flex align-items-center" style="gap:12px;">
                                            <div class="permission-tree-actions">
                                                <button type="button" class="permission-tree-action permission-tree-expand-all">باز کردن همه</button>
                                                <button type="button" class="permission-tree-action permission-tree-collapse-all">بستن همه</button>
                                            </div>
                                            <label class="mb-0 d-flex align-items-center" style="gap:6px;cursor:pointer;">
                                                <input type="checkbox" class="permission-select-all">
                                                انتخاب همه
                                            </label>
                                            <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید مجوزها و سطح دسترسی کارمند را تعیین کنید.با فعال کردن و یا غیرفعال کردن هر بخش،مجوز دسترسی و یا عدم دسترسی کارمندان به آن بخش داده می شود."
                                              aria-label="راهنمای مجوزات"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-right">
                                            <?php renderMenuPermissionTree(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;">مدارک و سوابق همکار</div>
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
                                    <i class="fa fa-save"></i> ذخیره تنظیمات و ایجاد کارمند
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    <?php } else {
        $user = $_GET['edit'];
        $retadmin = retAdmin($user);
        if ($retadmin == NULL) {
            echo '<script>window.location.href="listadmin.php" </script>';
        }
        ?>

        <div class="main-content side-content pt-0" dir="rtl">
            <div class="container-fluid">
                <div class="inner-body">

                    <!-- Success Alert Block -->
                    <?php if (isset($eror)) { ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars((string) $eror) ?></div>
                    <?php } ?>
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

                            <!-- RIGHT COLUMN: General Information (اطلاعات عمومی) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex justify-content-between align-items-center" style="background-color: #5c6bc0;">
                                        <span class="d-flex justify-content-between align-items-center">اطلاعات عمومی
                                           <span class="menu-info-trigger " style="color: white;"
                                                 data-tooltip="در این بخش می‌توانید با ویرایش اطلاعات عمومی، تعیین نقش، سطوح دسترسی و سایر تنظیمات، حساب کاربری کارمند جدید را در سامانه ایجاد کنید تا امکان ورود و استفاده از بخش‌های مجاز برای او فراهم شود. همچنین در همین قسمت، اطلاعات ورود شامل نام کاربری و رمز عبور مشخص می‌گردد؛ لطفاً توجه داشته باشید که این اطلاعات به‌درستی ثبت شده و به صورت امن تنها در اختیار شخص مجاز قرار گیرد."
                                                 aria-label="راهنمای کامل ایجاد کارمند و اطلاعات ورود"
                                                 onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fe fe-info fs-4"></i>
                                    </span>

                                        </span>
                                        <span class="badge badge-warning p-2" style="font-size: 0.9rem;">
                                    <?php echo $retadmin->fname . ' ' . $retadmin->lname ?>
                                </span>
                                    </div>
                                    <div class="card-body">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="ename" aria-describedby="basic-addon2" type="text" required value="<?php echo $retadmin->fname ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نام خانوادگی <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="efamili" aria-describedby="basic-addon2" type="text" required value="<?php echo $retadmin->lname ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">نام کاربری <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="eusername" aria-describedby="basic-addon2" type="text" required disabled value="<?php echo $retadmin->username ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رمزعبور <span class="tx-danger">*</span></label>
                                                <input class="form-control" name="epassword" aria-describedby="basic-addon2" type="password" required value="<?php echo $retadmin->password ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">داخلی</label>
                                                <input class="form-control" name="edakheli" aria-describedby="basic-addon2" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" value="<?php echo htmlspecialchars((string) $retadmin->dakheli) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">شماره موبایل</label>
                                                <input class="form-control" name="emob" aria-describedby="basic-addon2" type="text" value="<?php echo $retadmin->mobile ?>">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">جنسیت <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="egender" required>
                                                    <option <?php if ($retadmin->gender == 'خانم') { echo 'selected'; } ?> value="خانم">خانم</option>
                                                    <option <?php if ($retadmin->gender == 'آقای') { echo 'selected'; } ?> value="آقای">آقا</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">نوع دسترسی اتاق مجازی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2" name="skyroom" required>
                                                    <option value="1" <?php if ($retadmin->per_skyroom == '1') { echo 'selected'; } ?>>شرکت کننده</option>
                                                    <option value="2" <?php if ($retadmin->per_skyroom == '2') { echo 'selected'; } ?>>ارائه دهنده</option>
                                                    <option value="3" <?php if ($retadmin->per_skyroom == '3') { echo 'selected'; } ?>>اپراتور</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">سمت سازمانی <?= inst_is('school') ? 'مدرسه' : 'آموزشگاه' ?> <span class="tx-danger">*</span></label>
                                                <select class="form-control" name="estaff_role" required>
                                                    <option value="">— انتخاب سمت —</option>
                                                    <?php foreach (staffRoleOptions($schoolRoles, $courseScopedRoleKeys, $retadmin) as $staffRoleValue => $staffRoleOption) { ?>
                                                        <option value="<?= htmlspecialchars($staffRoleValue) ?>"
                                                                data-role-key="<?= htmlspecialchars($staffRoleOption['role_key']) ?>"
                                                                data-course-scope="<?= $staffRoleOption['course_scope'] ? '1' : '0' ?>"
                                                                data-preset="<?= htmlspecialchars($staffRoleOption['preset'] ?? '') ?>"
                                                            <?php if (staffRoleCurrentValue($retadmin) === $staffRoleValue) { echo 'selected'; } ?>>
                                                            <?= htmlspecialchars($staffRoleOption['label']) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row mb-3 staff-course-scope d-none" data-mode="edit">
                                            <div class="col-12">
                                                <label class="form-label">پایه‌های قابل دسترسی <span class="tx-danger">*</span></label>
                                                <select class="form-control select2 staff-course-select" name="ecourse_codes[]" multiple>
                                                    <?php foreach ($assignableCourses as $course) { ?>
                                                        <option value="<?= (int)$course->codecourse ?>" <?= in_array((int)$course->codecourse, $editAssignedCourseCodes, true) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($course->coursename) ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <small class="text-muted">دسترسی معاون پایه و مشاور به بخش پایه‌ها، فقط به موارد انتخاب‌شده محدود می‌شود.</small>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 d-flex align-items-center pt-3">
                                                <label class="ckbox">
                                                    <input name="eactivate" type="checkbox" <?php if ($retadmin->adminactive == 1) { echo 'checked'; } ?>>
                                                    <span>فعالسازی حساب</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">آدرس عکس</label>
                                            <input class="form-control" name="epic" aria-describedby="basic-addon2" type="text" value="<?php echo $retadmin->adminpic ?>" placeholder="http://yourdomain.ir/wp-content/uploads/3.jpg">
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- LEFT COLUMN: Permissions (دسترسى‌ها و نقش‌ها) -->
                            <div class="col-lg-6 mb-4">
                                <div class="card custom-card h-100 border-0 shadow-sm permission-card">
                                    <div class="card-header text-white font-weight-bold rounded-top p-3 d-flex align-content-center justify-content-between" style="background-color: #e91e63;">
                                        دسترسی‌ها و نقش‌ها
                                        <div class="d-flex align-items-center" style="gap:12px;">
                                            <div class="permission-tree-actions">
                                                <button type="button" class="permission-tree-action permission-tree-expand-all">باز کردن همه</button>
                                                <button type="button" class="permission-tree-action permission-tree-collapse-all">بستن همه</button>
                                            </div>
                                            <label class="mb-0 d-flex align-items-center" style="gap:6px;cursor:pointer;">
                                                <input type="checkbox" class="permission-select-all">
                                                انتخاب همه
                                            </label>
                                            <span class="menu-info-trigger" style="color: white;"
                                              data-tooltip="در این بخش می‌توانید مجوزها و سطح دسترسی کارمند را تعیین کنید.با فعال کردن و یا غیرفعال کردن هر بخش،مجوز دسترسی و یا عدم دسترسی کارمندان به آن بخش داده می شود."
                                              aria-label="راهنمای مجوزات"
                                              onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-right">
                                            <?php renderMenuPermissionTree($retadmin); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card custom-card border-0 shadow-sm" id="personnel-documents">
                                    <div class="card-header text-white font-weight-bold" style="background-color:#00897b;">مدارک و سوابق همکار</div>
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
                                    <i class="fa fa-save"></i> ذخیره تنظیمات و ویرایش کارمند
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    <?php } ?>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.permission-card').forEach(function (card) {
                const selectAll = card.querySelector('.permission-select-all');
                const permissions = Array.from(card.querySelectorAll('.menu-permission-check'));
                if (!selectAll || permissions.length === 0) return;
                const nodes = Array.from(card.querySelectorAll('[data-menu-node]'));

                function refreshSelectAll() {
                    const checkedCount = permissions.filter(function (permission) {
                        return permission.checked;
                    }).length;
                    selectAll.checked = checkedCount === permissions.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < permissions.length;
                }

                function refreshHierarchy() {
                    nodes.slice().reverse().forEach(function (node) {
                        const own = node.querySelector(':scope > .menu-permission-row > .menu-permission-check');
                        const descendants = Array.from(node.querySelectorAll(':scope > .menu-permission-children input.menu-permission-check'));
                        if (!own || descendants.length === 0) return;
                        const checked = descendants.filter(function (item) { return item.checked; }).length;
                        own.checked = checked === descendants.length;
                        own.indeterminate = checked > 0 && checked < descendants.length;
                    });
                    refreshSelectAll();
                }

                function setExpanded(node, expanded) {
                    const children = node.querySelector(':scope > .menu-permission-children');
                    const toggle = node.querySelector(':scope > .menu-permission-row > .menu-permission-toggle');
                    const name = node.querySelector(':scope > .menu-permission-row > .menu-permission-name');
                    if (!children || !toggle) return;
                    children.hidden = !expanded;
                    node.classList.toggle('is-collapsed', !expanded);
                    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                    if (name) name.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }

                nodes.forEach(function (node) {
                    const own = node.querySelector(':scope > .menu-permission-row > .menu-permission-check');
                    const descendants = Array.from(node.querySelectorAll(':scope > .menu-permission-children input.menu-permission-check'));
                    const toggle = node.querySelector(':scope > .menu-permission-row > .menu-permission-toggle');
                    const name = node.querySelector(':scope > .menu-permission-row > .menu-permission-name');

                    if (own && descendants.length > 0) {
                        own.addEventListener('change', function () {
                            descendants.forEach(function (item) {
                                item.checked = own.checked;
                                item.indeterminate = false;
                            });
                        });
                    }
                    if (toggle) {
                        toggle.addEventListener('click', function () {
                            setExpanded(node, toggle.getAttribute('aria-expanded') !== 'true');
                        });
                    }
                    if (name) {
                        name.addEventListener('click', function () {
                            setExpanded(node, name.getAttribute('aria-expanded') !== 'true');
                        });
                    }
                });

                selectAll.addEventListener('change', function () {
                    const shouldCheck = selectAll.checked;
                    permissions.forEach(function (permission) {
                        permission.checked = shouldCheck;
                        permission.indeterminate = false;
                    });
                    refreshHierarchy();
                });

                card.addEventListener('change', function (event) {
                    if (event.target.classList.contains('menu-permission-check')) refreshHierarchy();
                });

                const expandAll = card.querySelector('.permission-tree-expand-all');
                const collapseAll = card.querySelector('.permission-tree-collapse-all');
                if (expandAll) expandAll.addEventListener('click', function () {
                    nodes.forEach(function (node) { setExpanded(node, true); });
                });
                if (collapseAll) collapseAll.addEventListener('click', function () {
                    nodes.forEach(function (node) { setExpanded(node, false); });
                });

                refreshHierarchy();
            });

            function bindCourseScope(roleSelectName, mode) {
                const roleSelect = document.querySelector('select[name="' + roleSelectName + '"]');
                const wrapper = document.querySelector('.staff-course-scope[data-mode="' + mode + '"]');
                if (!roleSelect || !wrapper) return;
                const courseSelect = wrapper.querySelector('.staff-course-select');

                function updateVisibility() {
                    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
                    const requiresCourses = selectedOption && selectedOption.dataset.courseScope === '1';
                    wrapper.classList.toggle('d-none', !requiresCourses);
                    if (courseSelect) {
                        courseSelect.required = requiresCourses;
                    }
                }

                roleSelect.addEventListener('change', updateVisibility);
                updateVisibility();
            }

            bindCourseScope('staff_role', 'create');
            bindCourseScope('estaff_role', 'edit');

            document.querySelectorAll('[data-permission-tree]').forEach(function (tree) {
                const parent = tree.querySelector('.permission-parent');
                const children = Array.from(tree.querySelectorAll('.permission-tree-children input[type="checkbox"]'));
                if (!parent || children.length === 0) return;
                const refresh = function () {
                    const selected = children.filter(function (item) { return item.checked; }).length;
                    parent.checked = selected === children.length;
                    parent.indeterminate = selected > 0 && selected < children.length;
                };
                parent.addEventListener('change', function () {
                    children.forEach(function (item) { item.checked = parent.checked; });
                    parent.indeterminate = false;
                });
                children.forEach(function (item) { item.addEventListener('change', refresh); });
                refresh();
            });

            function bindSchoolRoleDefaults(roleSelectName, adminRoleName, isEdit) {
                const roleSelect = document.querySelector('select[name="' + roleSelectName + '"]');
                const adminRole = document.querySelector('select[name="' + adminRoleName + '"]');
                if (!roleSelect) return;

                roleSelect.addEventListener('change', function () {
                    const option = roleSelect.options[roleSelect.selectedIndex];
                    const roleKey = option ? option.dataset.roleKey : '';

                    if (roleKey === 'counselor') {
                        if (adminRole) adminRole.value = '1';
                    } else if (roleKey === 'technical_deputy') {
                        if (adminRole) adminRole.value = '54';
                    } else if (roleKey === 'financial_deputy') {
                        if (adminRole) adminRole.value = '55';
                    }
                    if (adminRole && window.jQuery) {
                        jQuery(adminRole).trigger('change.select2');
                    }
                });
            }

            // «add» form: picking a post ticks its default permissions in the tree
            const STAFF_PERMISSION_PRESETS = <?= json_encode(STAFF_ROLE_PERMISSION_PRESETS, JSON_UNESCAPED_UNICODE) ?>;
            (function () {
                const roleSelect = document.querySelector('select[name="staff_role"]');
                if (!roleSelect || !roleSelect.form) return;
                roleSelect.addEventListener('change', function () {
                    const option = roleSelect.options[roleSelect.selectedIndex];
                    const codes = STAFF_PERMISSION_PRESETS[option ? option.dataset.preset : ''];
                    if (!codes) return;
                    const boxes = Array.from(roleSelect.form.querySelectorAll('input.menu-permission-check'));
                    if (!boxes.length) return;
                    const all = codes.indexOf('*') !== -1;
                    boxes.forEach(function (box) { box.checked = all; box.indeterminate = false; });
                    if (!all) {
                        boxes.forEach(function (box) {
                            if (codes.indexOf(box.value) === -1) return;
                            const node = box.closest('[data-menu-node]');
                            box.checked = true;
                            node.querySelectorAll('.menu-permission-children input.menu-permission-check').forEach(function (child) {
                                child.checked = true;
                            });
                        });
                    }
                    // let the tree recompute parent (partly ticked) states and «select all»
                    boxes[0].dispatchEvent(new Event('change', {bubbles: true}));
                });
            })();

            // the access level now comes with the chosen post (no separate dropdown)
            bindSchoolRoleDefaults('staff_role', 'adminrole', false);
            bindSchoolRoleDefaults('estaff_role', 'eadminrole', true);
        });
    </script>


</body>
</html>

