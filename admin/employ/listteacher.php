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
if (!Permision('permision_viewemploy')) {
    header('location:' . url("admin/index.php"));
    die();
}

$academicYear = getSchoolAcademicYearRange();
$academicStart = $academicYear['start_gregorian'];
$academicEndExclusive = $academicYear['end_exclusive_gregorian'];

$coursesStmt = $conn->prepare(''
    . 'SELECT c.codecourse, c.coursename, c.paye, COUNT(DISTINCT ct.teacher_username) AS teacher_count '
    . 'FROM codtbl_course c '
    . 'LEFT JOIN classes cl ON cl.codecourse = c.codecourse '
    . 'LEFT JOIN course_teacher ct ON ct.class_id = cl.id '
    . 'WHERE c.activecourse = 1 AND c.course_type = 0 '
    . 'AND (c.datestart IS NULL OR c.datestart < ?) '
    . 'AND (c.dateend IS NULL OR c.dateend >= ?) '
    . 'GROUP BY c.codecourse, c.coursename, c.paye '
    . 'ORDER BY c.paye ASC, c.coursename ASC'
);
$coursesStmt->execute([$academicEndExclusive, $academicStart]);
$activeCourses = $coursesStmt->fetchAll(PDO::FETCH_OBJ);
$validCourseCodes = array_map(static function ($course) {
    return (int)$course->codecourse;
}, $activeCourses);

$selectedCourse = filter_input(INPUT_GET, 'course', FILTER_VALIDATE_INT);
$selectedCourse = $selectedCourse && in_array((int)$selectedCourse, $validCourseCodes, true)
    ? (int)$selectedCourse
    : 0;
$activeFilterQuery = $selectedCourse > 0 ? '?course=' . $selectedCourse : '';
$activeFilterSuffix = $selectedCourse > 0 ? '&course=' . $selectedCourse : '';

if (isset($_GET['disable'])) {
    if($_GET['disable']!=$_SESSION['admin']['user']) {
        $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
        $query = $conn->prepare($sql);
        $query->execute([0, $_GET['disable']]);
        header('location: listteacher.php' . $activeFilterQuery);
        die();
    }
}
if (isset($_GET['enable'])) {
    $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([1, $_GET['enable']]);
    header('location: listteacher.php' . $activeFilterQuery);
    die();
}

$teacherSql = '
    SELECT a.*, assignment_summary.active_class_count, assignment_summary.active_course_names
    FROM dbadmin a
    LEFT JOIN school_admin_roles teacher_role ON teacher_role.id = a.school_role_id
    LEFT JOIN (
        SELECT ct.teacher_username,
               COUNT(DISTINCT cl.id) AS active_class_count,
               GROUP_CONCAT(DISTINCT c.coursename ORDER BY c.coursename SEPARATOR \'، \') AS active_course_names
        FROM course_teacher ct
        INNER JOIN classes cl ON cl.id = ct.class_id
        INNER JOIN codtbl_course c ON c.codecourse = cl.codecourse
        WHERE c.activecourse = 1 AND c.course_type = 0
          AND (c.datestart IS NULL OR c.datestart < ?)
          AND (c.dateend IS NULL OR c.dateend >= ?)
        GROUP BY ct.teacher_username
    ) assignment_summary ON assignment_summary.teacher_username = a.username
    WHERE (a.adminrole = 3 OR teacher_role.role_key = \'teacher\')
';
$teacherParams = [$academicEndExclusive, $academicStart];
if ($selectedCourse > 0) {
    $teacherSql .= '
        AND EXISTS (
            SELECT 1
            FROM course_teacher selected_ct
            INNER JOIN classes selected_cl ON selected_cl.id = selected_ct.class_id
            INNER JOIN codtbl_course selected_c ON selected_c.codecourse = selected_cl.codecourse
            WHERE selected_ct.teacher_username = a.username
              AND selected_c.codecourse = ?
              AND selected_c.activecourse = 1 AND selected_c.course_type = 0
              AND (selected_c.datestart IS NULL OR selected_c.datestart < ?)
              AND (selected_c.dateend IS NULL OR selected_c.dateend >= ?)
        )
    ';
    $teacherParams[] = $selectedCourse;
    $teacherParams[] = $academicEndExclusive;
    $teacherParams[] = $academicStart;
}
$teacherSql .= ' ORDER BY a.dakheli ASC, a.lname ASC, a.fname ASC';
$teacherStmt = $conn->prepare($teacherSql);
$teacherStmt->execute($teacherParams);
$teachers = $teacherStmt->fetchAll(PDO::FETCH_OBJ);

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
    <title> <?php echo m('page.teacher_list') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
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
                                                <h6 class="main-content-label mb-1"><?php echo t('teacher_pl') ?></h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="<?php echo m('tooltip.teacher_list') ?>"
                                                      aria-label="<?php echo m('aria.teacher_list_help') ?>"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <p class="text-muted card-sub-title">
                                                <?php echo m('label.teacher_list_courses_prefix') ?><?= htmlspecialchars($academicYear['label']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-left">
                                        <a href="<?php echo url('admin/employ/addteacher.php') ?> "
                                           target="_blank"
                                           class="btn btn-sm btn-primary"><i class="fe fe-download"></i> <?php echo m('action.add_new_teacher') ?> </a>
                                    </div>
                                </div>
                                <div class="row row-sm align-items-end my-3">
                                    <div class="col-md-8 col-lg-6">
                                        <label for="courseFilter">پایه فعال سال تحصیلی <?= htmlspecialchars($academicYear['label']) ?></label>
                                        <select class="form-control" id="courseFilter">
                                            <option value="0"><?php echo m('filter.all_teachers') ?></option>
                                            <?php foreach ($activeCourses as $course) { ?>
                                                <option value="<?= (int)$course->codecourse ?>" <?= $selectedCourse === (int)$course->codecourse ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($course->coursename) ?>
                                                    <?= !empty($course->paye) ? ' - ' . htmlspecialchars($course->paye) : '' ?>
                                                    (<?= number_format((int)$course->teacher_count) ?> <?php echo t('teacher') ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 col-lg-3 mt-2 mt-md-0">
                                        <a href="listteacher.php" class="btn btn-outline-secondary btn-block"><?php echo m('filter.all_teachers') ?></a>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>اقدامات</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>نام کاربری</th>
                                            <th>پایه‌های فعال</th>
                                            <th>تعداد کلاس</th>
                                            <th>وضعیت</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $id = 1;
                                        if (!empty($teachers)) {
                                            foreach ($teachers as $res) {

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
                                                                           href="<?php echo url('admin/employ/addteacher.php') ?>?edit=<?php echo $res->username ?>"
                                                                        ><i class="fe fe-edit"></i> ویرایش کردن

                                                                        </a>
                                                                        <a class="dropdown-item text-primary"
                                                                           href="<?php echo url('admin/employ/addteacher.php') ?>?edit=<?php echo rawurlencode($res->username) ?>#personnel-documents">
                                                                            <i class="fe fe-file-text"></i> مدارک و سوابق
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->fname . ' ' . $res->lname ?></td>
                                                    <td class="wd-15"><?php echo $res->username ?></td>
                                                    <td>
                                                        <?php if (!empty($res->active_course_names)) { ?>
                                                            <span class="text-wrap"><?= htmlspecialchars($res->active_course_names) ?></span>
                                                        <?php } else { ?>
                                                            <span class="text-muted">بدون پایه فعال</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-15"><?= number_format((int)$res->active_class_count) ?></td>
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
                                                <td colspan="7" class="text-center text-muted py-4"><?php echo m('msg.no_teacher_for_filter') ?></td>
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
            const courseFilter = document.getElementById('courseFilter');
            courseFilter.addEventListener('change', function () {
                window.location.href = this.value === '0'
                    ? 'listteacher.php'
                    : 'listteacher.php?course=' + encodeURIComponent(this.value);
            });
        })();
    </script>


</body>
</html>

