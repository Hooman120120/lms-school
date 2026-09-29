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
    exit;
}
if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    exit;
}
$session = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($session['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}

$courseCode = filter_input(INPUT_GET, 'code', FILTER_VALIDATE_INT);
$darsId = filter_input(INPUT_GET, 'dars', FILTER_VALIDATE_INT);
if (!$courseCode || !$darsId) {
    http_response_code(400);
    exit('اطلاعات درس معتبر نیست.');
}
if (StaffCourseScopeRequired() && !StaffHasCourseAccess((int)$courseCode)) {
    http_response_code(403);
    exit('شما به این پایه دسترسی ندارید.');
}

$darsStmt = $conn->prepare('
    SELECT d.id, d.dars, c.coursename
    FROM dbdars d
    INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
    WHERE d.id = ? AND d.codecourse = ?
    LIMIT 1
');
$darsStmt->execute([(int)$darsId, (int)$courseCode]);
$dars = $darsStmt->fetch(PDO::FETCH_OBJ);
if (!$dars) {
    http_response_code(404);
    exit('درس مورد نظر یافت نشد.');
}

$rowsStmt = $conn->prepare('
    SELECT c.id AS class_id, c.name AS class_name,
           a.username, a.fname, a.lname, a.adminactive
    FROM classes c
    INNER JOIN class_dars cd ON cd.class_id = c.id AND cd.dars_id = ?
    LEFT JOIN course_teacher_dars ctd ON ctd.class_id = c.id AND ctd.dars_id = cd.dars_id
    LEFT JOIN dbadmin a ON a.username = ctd.teacher_username
    WHERE c.codecourse = ?
    ORDER BY c.id ASC, a.lname ASC, a.fname ASC
');
$rowsStmt->execute([(int)$darsId, (int)$courseCode]);
$classes = [];
foreach ($rowsStmt->fetchAll(PDO::FETCH_OBJ) as $row) {
    if (!isset($classes[$row->class_id])) {
        $classes[$row->class_id] = [
            'name' => $row->class_name,
            'teachers' => [],
        ];
    }
    if ($row->username !== null) {
        $classes[$row->class_id]['teachers'][] = [
            'username' => $row->username,
            'name' => trim($row->fname . ' ' . $row->lname) ?: $row->username,
            'active' => (int)$row->adminactive === 1,
        ];
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo m('page.lesson_teachers_prefix') ?><?php echo htmlspecialchars($dars->dars); ?> | <?php echo __TITLE; ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header my-3">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5"><?php echo m('page.lesson_teachers_prefix') ?><?php echo htmlspecialchars($dars->dars); ?></h2>
                        <p class="text-muted mb-0">پایه: <?php echo htmlspecialchars($dars->coursename); ?></p>
                    </div>
                    <a class="btn btn-outline-secondary" href="dars.php?code=<?php echo (int)$courseCode; ?>">بازگشت به درس‌ها</a>
                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped text-center" id="example1">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>کلاس</th>
                                    <th><?php echo m('col.teachers_of_lesson') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php $index = 1; foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo $index++; ?></td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($class['name']); ?></td>
                                        <td>
                                            <?php if ($class['teachers']): ?>
                                                <?php foreach ($class['teachers'] as $teacher): ?>
                                                    <span class="badge badge-light border p-2 m-1">
                                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($teacher['username']); ?>)</small>
                                                        <?php if (!$teacher['active']): ?>
                                                            <small class="text-danger">- غیرفعال</small>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo m('msg.no_teacher_for_class') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$classes): ?>
                                    <tr><td colspan="3" class="text-muted">کلاسی برای این پایه تعریف نشده است.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../footers.php'; ?>
</body>
</html>
