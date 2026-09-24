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

$selectedCourseCode = filter_input(INPUT_GET, 'code', FILTER_VALIDATE_INT);
$selectedCourseCode = $selectedCourseCode && $selectedCourseCode > 0 ? (int)$selectedCourseCode : 0;
$selectedCourse = null;
$students = [];

if ($selectedCourseCode > 0) {
    $courseStmt = $conn->prepare('
        SELECT *
        FROM codtbl_course
        WHERE codecourse = ? AND activecourse = 1 AND course_type = 0
        LIMIT 1
    ');
    $courseStmt->execute([$selectedCourseCode]);
    $selectedCourse = $courseStmt->fetch(PDO::FETCH_OBJ);

    if (!$selectedCourse) {
        header('location: course_detail.php');
        die();
    }

    $studentsStmt = $conn->prepare('
        SELECT enrolled.username,
               enrolled.class_id,
               enrolled.enrollment_created_at,
               p.fname,
               p.lname,
               p.codemeli,
               p.datecreated,
               cl.name AS class_name
        FROM (
            SELECT dc.username,
                   MAX(dc.class_id) AS class_id,
                   MAX(dc.created_at) AS enrollment_created_at
            FROM dbcourse dc
            WHERE dc.codecourse = ? AND dc.activityuser = 1
            GROUP BY dc.username
        ) enrolled
        LEFT JOIN dbpersonal p ON p.codemeli = enrolled.username
        LEFT JOIN classes cl ON cl.id = enrolled.class_id
        ORDER BY p.lname ASC, p.fname ASC, enrolled.username ASC
    ');
    $studentsStmt->execute([$selectedCourseCode]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_OBJ);
} else {
    $coursesStmt = $conn->query('
        SELECT c.idcourse,
               c.codecourse,
               c.coursename,
               c.paye,
               c.datestart,
               c.dateend,
               COUNT(DISTINCT CASE WHEN dc.activityuser = 1 THEN dc.username END) AS student_count
        FROM codtbl_course c
        LEFT JOIN dbcourse dc ON dc.codecourse = c.codecourse
        WHERE c.activecourse = 1 AND c.course_type = 0
        GROUP BY c.idcourse, c.codecourse, c.coursename, c.paye, c.datestart, c.dateend
        ORDER BY c.idcourse DESC
    ');
    $courses = $coursesStmt->fetchAll(PDO::FETCH_OBJ);
}

function courseDetailJalaliDate(?string $date): string
{
    if (!$date || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $matches)) {
        return '-';
    }

    return implode('/', gregorian_to_jalali((int)$matches[1], (int)$matches[2], (int)$matches[3]));
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="پایه‌های در حال اجرا">
    <title>پایه‌های در حال اجرا | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-12">
                        <?php if ($selectedCourse) { ?>
                            <div class="card custom-card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            دانش‌آموزان پایه
                                            <span class="text-primary"><?= htmlspecialchars($selectedCourse->coursename) ?></span>
                                        </h6>
                                        <small class="text-muted">
                                            کد پایه: <?= htmlspecialchars($selectedCourse->codecourse) ?>
                                            <?php if (!empty($selectedCourse->paye)) { ?>
                                                — <?= htmlspecialchars($selectedCourse->paye) ?>
                                            <?php } ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 8px;">
                                        <span class="badge badge-primary"><?= number_format(count($students)) ?> دانش‌آموز</span>
                                        <a href="course_detail.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="fe fe-arrow-right ml-1"></i> بازگشت به پایه‌ها
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>دسترسی</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>کد ملی</th>
                                                <th>کلاس</th>
                                                <th>تاریخ ثبت‌نام</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($students)) { ?>
                                                <tr><td colspan="6">دانش‌آموز فعالی برای این پایه یافت نشد.</td></tr>
                                            <?php } else { ?>
                                                <?php foreach ($students as $index => $student) { ?>
                                                    <tr>
                                                        <td><?= number_format($index + 1) ?></td>
                                                        <td>
                                                            <a class="btn btn-sm ripple btn-info btn-icon"
                                                               href="suporting.php?Id=<?= rawurlencode($student->username) ?>"
                                                               title="ورود به پرونده پشتیبانی دانش‌آموز">
                                                                <i class="fa fa-key"></i>
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars(trim(($student->fname ?? '') . ' ' . ($student->lname ?? '')) ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($student->codemeli ?: $student->username) ?></td>
                                                        <td><?= htmlspecialchars($student->class_name ?: 'تعیین‌نشده') ?></td>
                                                        <td><?= htmlspecialchars(courseDetailJalaliDate($student->datecreated)) ?></td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="card custom-card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">پایه‌های در حال اجرا</h6>
                                        <small class="text-muted">پایه‌های فعال مدرسه، مستقل از باز یا بسته بودن ثبت‌نام</small>
                                    </div>
                                    <span class="badge badge-primary"><?= number_format(count($courses)) ?> پایه</span>
                                </div>
                            </div>

                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>کد پایه</th>
                                                <th>نام پایه</th>
                                                <th>پایه تحصیلی</th>
                                                <th>شروع</th>
                                                <th>پایان</th>
                                                <th>تعداد دانش‌آموزان</th>
                                                <th>عملیات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($courses)) { ?>
                                                <tr><td colspan="8">پایه‌ای در حال اجرا یافت نشد.</td></tr>
                                            <?php } else { ?>
                                                <?php foreach ($courses as $index => $course) { ?>
                                                    <tr>
                                                        <td><?= number_format($index + 1) ?></td>
                                                        <td><?= htmlspecialchars($course->codecourse) ?></td>
                                                        <td><?= htmlspecialchars($course->coursename) ?></td>
                                                        <td><?= htmlspecialchars($course->paye ?: '-') ?></td>
                                                        <td><?= htmlspecialchars(courseDetailJalaliDate($course->datestart)) ?></td>
                                                        <td><?= htmlspecialchars(courseDetailJalaliDate($course->dateend)) ?></td>
                                                        <td>
                                                            <span class="badge badge-success"><?= number_format((int)$course->student_count) ?> نفر</span>
                                                        </td>
                                                        <td>
                                                            <a href="course_detail.php?code=<?= (int)$course->codecourse ?>"
                                                               class="btn btn-sm btn-primary">
                                                                <i class="fe fe-users ml-1"></i> مشاهده دانش‌آموزان
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
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>
</body>
</html>
