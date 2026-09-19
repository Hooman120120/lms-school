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

if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

if (!Permision('permision_manage_suporter')) {
    echo 'دسترسی ندارید';
    die();
}

$rolesStmt = $conn->query('
    SELECT r.id, r.role_key, r.title, COUNT(a.idadmin) AS staff_count
    FROM school_admin_roles r
    LEFT JOIN dbadmin a ON a.school_role_id = r.id
    WHERE r.is_active = 1
    GROUP BY r.id, r.role_key, r.title, r.sort_order
    ORDER BY r.sort_order ASC, r.id ASC
');
$schoolRoles = $rolesStmt->fetchAll(PDO::FETCH_OBJ);
$validRoleIds = array_map('intval', array_column(array_map(function ($role) {
    return (array)$role;
}, $schoolRoles), 'id'));
$deputyRoleIds = [];
$mainSchoolRoles = [];
foreach ($schoolRoles as $schoolRole) {
    if (substr((string)$schoolRole->role_key, -7) === '_deputy') {
        $deputyRoleIds[] = (int)$schoolRole->id;
    } else {
        $mainSchoolRoles[] = $schoolRole;
    }
}
$showAllDeputies = isset($_GET['group']) && $_GET['group'] === 'deputies';
$staffType = (string)($_GET['type'] ?? 'all');
if (!in_array($staffType, ['all', 'teachers', 'staff'], true)) {
    $staffType = 'all';
}

$selectedRole = filter_input(INPUT_GET, 'role', FILTER_VALIDATE_INT);
$selectedRole = !$showAllDeputies && $selectedRole && in_array((int)$selectedRole, $validRoleIds, true)
    ? (int)$selectedRole
    : 0;
$todayDate = date('Y-m-d');

$staffSql = '
    SELECT a.idadmin, a.username, a.fname, a.lname, a.mobile, a.dakheli, a.adminrole,
           a.adminactive, a.school_role_id, r.role_key AS school_role_key, r.title AS school_role_title,
           course_scope.assigned_course_count, course_scope.assigned_course_names,
           teacher_scope.teacher_course_count, teacher_scope.teacher_course_names,
           COALESCE(today_log.login_count, 0) AS today_login_count,
           today_log.first_login_time,
           today_log.last_login_time,
           today_log.last_login_ip,
           today_log.last_login_os
    FROM dbadmin a
    LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
    LEFT JOIN (
        SELECT ssc.admin_username,
               COUNT(*) AS assigned_course_count,
               GROUP_CONCAT(c.coursename ORDER BY c.idcourse DESC SEPARATOR \'، \') AS assigned_course_names
        FROM school_staff_courses ssc
        INNER JOIN codtbl_course c ON c.codecourse = ssc.codecourse
        GROUP BY ssc.admin_username
    ) course_scope ON course_scope.admin_username = a.username
    LEFT JOIN (
        SELECT ct.teacher_username,
               COUNT(DISTINCT c.codecourse) AS teacher_course_count,
               GROUP_CONCAT(DISTINCT cr.coursename ORDER BY cr.idcourse DESC SEPARATOR \'، \') AS teacher_course_names
        FROM course_teacher ct
        INNER JOIN classes c ON c.id = ct.class_id
        INNER JOIN codtbl_course cr ON cr.codecourse = c.codecourse
        GROUP BY ct.teacher_username
    ) teacher_scope ON teacher_scope.teacher_username = a.username
    LEFT JOIN (
        SELECT h.username,
               COUNT(*) AS login_count,
               MIN(h.timelogin) AS first_login_time,
               MAX(h.timelogin) AS last_login_time,
               SUBSTRING_INDEX(
                   GROUP_CONCAT(NULLIF(h.ip, \'\') ORDER BY h.timelogin DESC, h.id DESC SEPARATOR \'|||\'),
                   \'|||\', 1
               ) AS last_login_ip,
               SUBSTRING_INDEX(
                   GROUP_CONCAT(NULLIF(h.os, \'\') ORDER BY h.timelogin DESC, h.id DESC SEPARATOR \'|||\'),
                   \'|||\', 1
               ) AS last_login_os
        FROM dbh_admin h
        WHERE h.datelogin = ?
        GROUP BY h.username
    ) today_log ON today_log.username = a.username
    WHERE 1 = 1
';
$staffParams = [$todayDate];
if ($showAllDeputies && !empty($deputyRoleIds)) {
    $deputyPlaceholders = implode(',', array_fill(0, count($deputyRoleIds), '?'));
    $staffSql .= " AND a.school_role_id IN ($deputyPlaceholders) ";
    $staffParams = array_merge($staffParams, $deputyRoleIds);
} elseif ($selectedRole > 0) {
    $staffSql .= ' AND a.school_role_id = ? ';
    $staffParams[] = $selectedRole;
} elseif ($staffType === 'teachers') {
    $staffSql .= " AND (COALESCE(a.adminrole, -1) = 3 OR r.role_key = 'teacher') ";
} elseif ($staffType === 'staff') {
    $staffSql .= " AND NOT (COALESCE(a.adminrole, -1) = 3 OR COALESCE(r.role_key, '') = 'teacher') ";
}
$staffSql .= ' ORDER BY today_login_count DESC, a.adminactive DESC, a.lname ASC, a.fname ASC ';

$staffStmt = $conn->prepare($staffSql);
$staffStmt->execute($staffParams);
$staffMembers = $staffStmt->fetchAll(PDO::FETCH_OBJ);

$totalStaff = (int)$conn->query('SELECT COUNT(*) FROM dbadmin')->fetchColumn();
$totalTeachers = (int)$conn->query("
    SELECT COUNT(*)
    FROM dbadmin a
    LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
    WHERE COALESCE(a.adminrole, -1) = 3 OR r.role_key = 'teacher'
")->fetchColumn();
$totalOtherStaff = $totalStaff - $totalTeachers;
$unassignedStaff = (int)$conn->query('SELECT COUNT(*) FROM dbadmin WHERE school_role_id IS NULL')->fetchColumn();
$todaySummaryStmt = $conn->prepare('
    SELECT 
           COUNT(*) AS total_logins,
           COUNT(DISTINCT h.username) AS visited_staff,
           MIN(h.timelogin) AS first_login,
           MAX(h.timelogin) AS last_login
    FROM dbh_admin h
    INNER JOIN dbadmin a ON a.username = h.username
    WHERE h.datelogin = ?
');
$todaySummaryStmt->execute([$todayDate]);
$todaySummary = $todaySummaryStmt->fetch(PDO::FETCH_OBJ);
$todayTotalLogins = (int)($todaySummary->total_logins ?? 0);
$todayVisitedStaff = (int)($todaySummary->visited_staff ?? 0);
$todayGroupsStmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN COALESCE(a.adminrole, -1) = 3 OR r.role_key = 'teacher' THEN 1 ELSE 0 END) AS teacher_logins,
        COUNT(DISTINCT CASE WHEN COALESCE(a.adminrole, -1) = 3 OR r.role_key = 'teacher' THEN h.username END) AS visited_teachers,
        SUM(CASE WHEN NOT (COALESCE(a.adminrole, -1) = 3 OR COALESCE(r.role_key, '') = 'teacher') THEN 1 ELSE 0 END) AS staff_logins,
        COUNT(DISTINCT CASE WHEN NOT (COALESCE(a.adminrole, -1) = 3 OR COALESCE(r.role_key, '') = 'teacher') THEN h.username END) AS visited_other_staff
    FROM dbh_admin h
    INNER JOIN dbadmin a ON a.username = h.username
    LEFT JOIN school_admin_roles r ON r.id = a.school_role_id
    WHERE h.datelogin = ?
");
$todayGroupsStmt->execute([$todayDate]);
$todayGroups = $todayGroupsStmt->fetch(PDO::FETCH_OBJ);
$allDeputiesCount = 0;
foreach ($schoolRoles as $schoolRole) {
    if (in_array((int)$schoolRole->id, $deputyRoleIds, true)) {
        $allDeputiesCount += (int)$schoolRole->staff_count;
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
    <meta name="description" content="مدیریت همکاران مدرسه">
    <title>همکاران مدرسه | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .role-filter.active {
            color: #fff !important;
            background: #1cc88a !important;
            border-color: #1cc88a !important;
        }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">همکاران و معلمان مدرسه</h6>
                                    <small class="text-muted">فیلتر بر اساس سمت سازمانی در مدرسه</small>
                                </div>
                                <span class="badge badge-primary"><?= number_format($totalStaff) ?> نفر</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-center mb-3" style="gap: 8px;">
                                    <span class="badge badge-primary p-2">
                                        تاریخ امروز: <?= htmlspecialchars(jdate('Y/m/d')) ?>
                                    </span>
                                    <a href="staff_management.php?type=teachers" class="badge badge-info p-2">
                                        بازدید معلمان امروز:
                                        <?= number_format((int)($todayGroups->teacher_logins ?? 0)) ?> ورود از
                                        <?= number_format((int)($todayGroups->visited_teachers ?? 0)) ?> معلم
                                    </a>
                                    <a href="staff_management.php?type=staff" class="badge badge-success p-2">
                                        بازدید همکاران امروز:
                                        <?= number_format((int)($todayGroups->staff_logins ?? 0)) ?> ورود از
                                        <?= number_format((int)($todayGroups->visited_other_staff ?? 0)) ?> همکار
                                    </a>
                                    <?php if ($todayTotalLogins > 0) { ?>
                                        <span class="badge badge-light p-2">
                                            بازه ورود: <?= htmlspecialchars(substr($todaySummary->first_login, 0, 5)) ?>
                                            تا <?= htmlspecialchars(substr($todaySummary->last_login, 0, 5)) ?>
                                        </span>
                                    <?php } ?>
                                </div>
                                <div class="d-flex flex-wrap justify-content-center" style="gap: 8px;">
                                    <a href="staff_management.php?type=all"
                                       class="btn btn-outline-secondary role-filter <?= $selectedRole === 0 && !$showAllDeputies && $staffType === 'all' ? 'active' : '' ?>">
                                        همه <span class="badge badge-light mr-1"><?= number_format($totalStaff) ?></span>
                                    </a>
                                    <a href="staff_management.php?type=teachers"
                                       class="btn btn-outline-secondary role-filter <?= $selectedRole === 0 && !$showAllDeputies && $staffType === 'teachers' ? 'active' : '' ?>">
                                        معلمان <span class="badge badge-light mr-1"><?= number_format($totalTeachers) ?></span>
                                    </a>
                                    <a href="staff_management.php?type=staff"
                                       class="btn btn-outline-secondary role-filter <?= $selectedRole === 0 && !$showAllDeputies && $staffType === 'staff' ? 'active' : '' ?>">
                                        سایر همکاران <span class="badge badge-light mr-1"><?= number_format($totalOtherStaff) ?></span>
                                    </a>
                                    <?php foreach ($mainSchoolRoles as $role) { ?>
                                        <a href="staff_management.php?role=<?= (int)$role->id ?>"
                                           class="btn btn-outline-secondary role-filter <?= $selectedRole === (int)$role->id ? 'active' : '' ?>">
                                            <?= htmlspecialchars($role->title) ?>
                                            <span class="badge badge-light mr-1"><?= number_format((int)$role->staff_count) ?></span>
                                        </a>
                                    <?php } ?>
                                    <a href="staff_management.php?group=deputies"
                                       class="btn btn-outline-secondary role-filter <?= $showAllDeputies ? 'active' : '' ?>">
                                        همه معاونین
                                        <span class="badge badge-light mr-1"><?= number_format($allDeputiesCount) ?></span>
                                    </a>
                                    <select class="form-control" id="deputyRoleFilter" style="width: 230px; height: 38px;">
                                        <option value="">انتخاب نوع معاونت</option>
                                        <?php foreach ($schoolRoles as $role) {
                                            if (!in_array((int)$role->id, $deputyRoleIds, true)) {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?= (int)$role->id ?>" <?= $selectedRole === (int)$role->id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role->title) ?> (<?= number_format((int)$role->staff_count) ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <?php if ($unassignedStaff > 0) { ?>
                            <div class="alert alert-warning">
                                سمت سازمانی <?= number_format($unassignedStaff) ?> همکار قدیمی هنوز تعیین نشده است.
                                برای تعیین سمت از دکمه ویرایش همان همکار استفاده کنید.
                            </div>
                        <?php } ?>

                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>نام کاربری</th>
                                            <th>سمت سازمانی</th>
                                            <th>پایه‌های تخصیص‌یافته</th>
                                            <th>شماره همراه</th>
                                            <th>داخلی</th>
                                            <th>وضعیت</th>
                                            <th>خلاصه ورود امروز</th>
                                            <th>ویرایش</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($staffMembers)) { ?>
                                            <tr><td colspan="10">همکار یا معلمی با این سمت یافت نشد.</td></tr>
                                        <?php } else { ?>
                                            <?php foreach ($staffMembers as $index => $staff) { ?>
                                                <tr>
                                                    <td><?= number_format($index + 1) ?></td>
                                                    <td><?= htmlspecialchars(trim($staff->fname . ' ' . $staff->lname) ?: '-') ?></td>
                                                    <td><?= htmlspecialchars($staff->username) ?></td>
                                                    <td>
                                                        <?php if (!empty($staff->school_role_title)) { ?>
                                                            <span class="badge badge-info"><?= htmlspecialchars($staff->school_role_title) ?></span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-warning">تعیین‌نشده</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td style="min-width: 180px;">
                                                        <?php if ((string)$staff->school_role_key === 'teacher' || (int)$staff->adminrole === 3) { ?>
                                                            <?php if ((int)($staff->teacher_course_count ?? 0) > 0) { ?>
                                                                <span class="badge badge-primary mb-1"><?= number_format((int)$staff->teacher_course_count) ?> پایه</span>
                                                                <div class="small text-muted text-wrap"><?= htmlspecialchars($staff->teacher_course_names) ?></div>
                                                            <?php } else { ?>
                                                                <span class="badge badge-warning">بدون پایه</span>
                                                            <?php } ?>
                                                        <?php } elseif (in_array((string)$staff->school_role_key, ['grade_deputy', 'counselor'], true)) { ?>
                                                            <?php if ((int)($staff->assigned_course_count ?? 0) > 0) { ?>
                                                                <span class="badge badge-primary mb-1"><?= number_format((int)$staff->assigned_course_count) ?> پایه</span>
                                                                <div class="small text-muted text-wrap"><?= htmlspecialchars($staff->assigned_course_names) ?></div>
                                                            <?php } else { ?>
                                                                <span class="badge badge-warning">بدون پایه</span>
                                                            <?php } ?>
                                                        <?php } else { ?>
                                                            <span class="text-muted">—</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($staff->mobile ?: '-') ?></td>
                                                    <td><?= htmlspecialchars($staff->dakheli ?: '-') ?></td>
                                                    <td>
                                                        <?php if ((int)$staff->adminactive === 1) { ?>
                                                            <span class="badge badge-success">فعال</span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-danger">غیرفعال</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td style="min-width: 245px;">
                                                        <?php if ((int)$staff->today_login_count > 0) { ?>
                                                            <div class="mb-1">
                                                                <span class="badge badge-success">
                                                                    <?= number_format((int)$staff->today_login_count) ?> ورود
                                                                </span>
                                                                <small class="text-muted mr-1">
                                                                    اولین: <?= htmlspecialchars(substr($staff->first_login_time, 0, 5)) ?>
                                                                    | آخرین: <?= htmlspecialchars(substr($staff->last_login_time, 0, 5)) ?>
                                                                </small>
                                                            </div>
                                                        <?php } else { ?>
                                                            <span class="badge badge-light">امروز وارد نشده</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <a class="btn btn-sm btn-primary btn-icon"
                                                           href="../employ/<?= ((string)$staff->school_role_key === 'teacher' || (int)$staff->adminrole === 3) ? 'addteacher.php' : 'addemploy.php' ?>?edit=<?= rawurlencode($staff->username) ?>"
                                                           target="_blank" rel="noopener">
                                                            <i class="fe fe-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>
<script>
    (function () {
        const deputyFilter = document.getElementById('deputyRoleFilter');
        deputyFilter.addEventListener('change', function () {
            if (this.value !== '') {
                window.location.href = 'staff_management.php?role=' + encodeURIComponent(this.value);
            }
        });
    })();
</script>
</body>
</html>
