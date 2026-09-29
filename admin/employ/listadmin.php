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
//Protecting against SESSION Hijaking
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
//* End Check Session JUST ONE Session

$rolesStmt = $conn->query('
    SELECT r.id, r.role_key, r.title, COUNT(a.idadmin) AS staff_count
    FROM school_admin_roles r
    LEFT JOIN dbadmin a
        ON a.school_role_id = r.id
       AND (a.adminrole IS NULL OR a.adminrole <> 3)
    WHERE r.is_active = 1 AND r.role_key <> \'teacher\'
    GROUP BY r.id, r.role_key, r.title, r.sort_order
    ORDER BY r.sort_order ASC, r.id ASC
');
$schoolRoles = $rolesStmt->fetchAll(PDO::FETCH_OBJ);
$validRoleIds = [];
$deputyRoleIds = [];
$mainSchoolRoles = [];
foreach ($schoolRoles as $schoolRole) {
    $validRoleIds[] = (int)$schoolRole->id;
    if (substr((string)$schoolRole->role_key, -7) === '_deputy') {
        $deputyRoleIds[] = (int)$schoolRole->id;
    } else {
        $mainSchoolRoles[] = $schoolRole;
    }
}

$showAllDeputies = isset($_GET['group']) && $_GET['group'] === 'deputies';
$selectedRole = filter_input(INPUT_GET, 'role', FILTER_VALIDATE_INT);
$selectedRole = !$showAllDeputies && $selectedRole && in_array((int)$selectedRole, $validRoleIds, true)
    ? (int)$selectedRole
    : 0;
$activeFilterQuery = $showAllDeputies
    ? '?group=deputies'
    : ($selectedRole > 0 ? '?role=' . $selectedRole : '');
$activeFilterSuffix = $showAllDeputies
    ? '&group=deputies'
    : ($selectedRole > 0 ? '&role=' . $selectedRole : '');

if (isset($_GET['disable'])) {
    AdminRequire('staff.manage.toggle');
    $disableTarget = retAdmin((string)$_GET['disable']);
    if ($disableTarget && AdminHoldsEverything((string)$disableTarget->username) && !AdminHoldsEverything()) {
        http_response_code(403);
        exit('فقط مدیر می‌تواند حساب مدیر را غیرفعال کند.');
    }
    if($_GET['disable']!=$_SESSION['admin']['user']) {
        $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
        $query = $conn->prepare($sql);
        $query->execute([0, $_GET['disable']]);
        header('location: listadmin.php' . $activeFilterQuery);
        die();
    }
}
if (isset($_GET['enable'])) {
    AdminRequire('staff.manage.toggle');
    $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([1, $_GET['enable']]);
    header('location: listadmin.php' . $activeFilterQuery);
    die();
}

$employeeWhere = ['(a.adminrole IS NULL OR a.adminrole <> 3)', '(sr.role_key IS NULL OR sr.role_key <> \'teacher\')'];
$employeeParams = [];
if ($showAllDeputies && !empty($deputyRoleIds)) {
    $employeeWhere[] = 'a.school_role_id IN (' . implode(',', array_fill(0, count($deputyRoleIds), '?')) . ')';
    $employeeParams = $deputyRoleIds;
} elseif ($selectedRole > 0) {
    $employeeWhere[] = 'a.school_role_id = ?';
    $employeeParams[] = $selectedRole;
}

$employeeSql = '
    SELECT a.*, sr.title AS school_role_title
    FROM dbadmin a
    LEFT JOIN school_admin_roles sr ON sr.id = a.school_role_id
    WHERE ' . implode(' AND ', $employeeWhere) . '
    ORDER BY a.dakheli ASC, a.lname ASC, a.fname ASC
';
$employeeStmt = $conn->prepare($employeeSql);
$employeeStmt->execute($employeeParams);
$employees = $employeeStmt->fetchAll(PDO::FETCH_OBJ);

$totalEmployees = (int)$conn->query("SELECT COUNT(*) FROM dbadmin a LEFT JOIN school_admin_roles sr ON sr.id = a.school_role_id WHERE (a.adminrole IS NULL OR a.adminrole <> 3) AND (sr.role_key IS NULL OR sr.role_key <> 'teacher')")->fetchColumn();
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست کارمندان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .role-filter.active {
            color: #fff !important;
            background-color: #1cc88a !important;
            border-color: #1cc88a !important;
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row row-sm">
                                    <div class="col-8">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">کارمندان</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش لیست تمامی کارمندان سامانه نمایش داده می‌شود. از طریق این صفحه می‌توانید اطلاعات هر کارمند را مشاهده، ویرایش یا در صورت نیاز حساب کاربری او را غیرفعال کنید. با غیرفعال‌سازی حساب کارمندان، دسترسی آنها به تمامی بخش‌های سامانه فوراً مسدود خواهد شد."
                                                      aria-label="راهنمای کارمندان"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                لیست همه کارمندان موجود
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-left">
                                        <a href="<?php echo url('admin/employ/addemploy.php') ?> "
                                           target="_blank"
                                           class="btn btn-sm btn-primary"><i class="fe fe-download"></i> افزودن کارمند جدید </a>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap justify-content-center my-3" style="gap: 8px;">
                                    <a href="listadmin.php"
                                       class="btn btn-outline-secondary role-filter <?= $selectedRole === 0 && !$showAllDeputies ? 'active' : '' ?>">
                                        همه <span class="badge badge-light mr-1"><?= number_format($totalEmployees) ?></span>
                                    </a>
                                    <?php foreach ($mainSchoolRoles as $role) { ?>
                                        <a href="listadmin.php?role=<?= (int)$role->id ?>"
                                           class="btn btn-outline-secondary role-filter <?= $selectedRole === (int)$role->id ? 'active' : '' ?>">
                                            <?= htmlspecialchars($role->title) ?>
                                            <span class="badge badge-light mr-1"><?= number_format((int)$role->staff_count) ?></span>
                                        </a>
                                    <?php } ?>
                                    <a href="listadmin.php?group=deputies"
                                       class="btn btn-outline-secondary role-filter <?= $showAllDeputies ? 'active' : '' ?>">
                                        همه معاونان
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
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>اقدامات</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>نام کاربری</th>
                                            <th>سمت مدرسه</th>
                                            <th>پشتیبان</th>
                                            <th>داخلی</th>
                                            <th>وضعیت</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $id = 1;
                                        if (!empty($employees)) {
                                            foreach ($employees as $res) {

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $id ?></td>
                                                    <td class="wd-15">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-sm btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i
                                                                                class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <?php if ($res->adminactive == 1) { ?>
                                                                            <a class="dropdown-item text-warning "
                                                                               href="?disable=<?= urlencode($res->username) ?><?= htmlspecialchars($activeFilterSuffix) ?>"
                                                                            ><i class="fe fe-eye-off"></i> غیرفعال کردن
                                                                            </a>
                                                                        <?php } else { ?>
                                                                            <a class="dropdown-item text-success "
                                                                               href="?enable=<?= urlencode($res->username) ?><?= htmlspecialchars($activeFilterSuffix) ?>"
                                                                            ><i class="fe fe-eye"></i> فعال کردن
                                                                            </a>

                                                                        <?php } ?>
                                                                        <a class="dropdown-item text-success "
                                                                           href="<?php echo url('admin/employ/addemploy.php') ?>?edit=<?php echo $res->username ?>"
                                                                        ><i class="fe fe-edit"></i> ویرایش کردن

                                                                        </a>
                                                                        <a class="dropdown-item text-primary"
                                                                           href="<?php echo url('admin/employ/addemploy.php') ?>?edit=<?php echo rawurlencode($res->username) ?>#personnel-documents">
                                                                            <i class="fe fe-file-text"></i> مدارک و سوابق
                                                                        </a>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->fname . ' ' . $res->lname ?></td>
                                                    <td class="wd-15"><?php echo $res->username ?></td>
                                                    <td class="wd-15">
                                                        <?php if (!empty($res->school_role_title)) { ?>
                                                            <span class="badge badge-info-light"><?= htmlspecialchars($res->school_role_title) ?></span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-warning-light">تعیین‌نشده</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-15"><?php if(AdminCan('support.desk', (string)$res->username) ){echo '   <span class="badge badge-success-light">می باشد</span> ';} else{echo '  <span class="badge badge-warning-light">نیست</span> ';} ?></td>
                                                    <td class="wd-15"><?php echo $res->dakheli ?></td>
                                                    <td class="wd-20">
                                                        <?php if ($res->adminactive == 1) { ?>
                                                            <span class="badge badge-success-light">فعال</span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-warning-light">غیرفعال</span>
                                                        <?php } ?>
                                                    </td>

                                                </tr>
                                                <?php
                                                $id++;
                                            }
                                        } else { ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">همکاری با این فیلتر یافت نشد.</td>
                                            </tr>
                                        <?php }
                                        ?>

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
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        (function () {
            const deputyFilter = document.getElementById('deputyRoleFilter');
            deputyFilter.addEventListener('change', function () {
                if (this.value !== '') {
                    window.location.href = 'listadmin.php?role=' + encodeURIComponent(this.value);
                }
            });
        })();
    </script>


</body>
</html>
