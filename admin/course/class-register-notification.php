<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    exit;
}

$sessionUser = (string)($_SESSION['admin']['user'] ?? '');
$adminRole = (int)($_SESSION['admin']['adminrole'] ?? 0);
$eventId = (int)($_GET['event'] ?? 0);

if ($eventId <= 0) {
    http_response_code(400);
    exit('شناسه جلسه نامعتبر است.');
}

$eventStmt = $conn->prepare('SELECT ev.*, c.name AS class_name, c.codecourse,
        cr.coursename, CONCAT(COALESCE(a.fname, \'\'), \' \', COALESCE(a.lname, \'\')) AS teacher_name
    FROM register_class_events ev
    INNER JOIN classes c ON c.id = ev.class_id
    LEFT JOIN codtbl_course cr ON cr.codecourse = c.codecourse
    LEFT JOIN dbadmin a ON a.idadmin = ev.teacher_id
    WHERE ev.id = ? LIMIT 1');
$eventStmt->execute([$eventId]);
$event = $eventStmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit('جلسه دفتر کلاسی یافت نشد.');
}

$eventTypeCode = (int)$event['type'];
$notificationType = in_array($eventTypeCode, [0, 2], true) ? 'attendance'
    : (in_array($eventTypeCode, [1, 4, 5], true) ? 'exam' : '');

if ($notificationType === '' || !in_array($notificationType, SchoolNotificationAllowedTypes(), true)) {
    http_response_code(403);
    exit('برای مشاهده جزئیات این جلسه دسترسی ندارید.');
}

if ($adminRole === 3) {
    RequireTeacherPermission('teacher_perm_attendance', url('admin/course/Active-course.php'));
    if (!TeacherHasClassAccess((int)$event['class_id'], $sessionUser)) {
        http_response_code(403);
        exit('این کلاس به شما تخصیص داده نشده است.');
    }
} elseif (StaffCourseScopeRequired($sessionUser) && !StaffHasClassAccess((int)$event['class_id'], $sessionUser)) {
    http_response_code(403);
    exit('این کلاس در پایه‌های تخصیص‌یافته شما نیست.');
}

$studentsSql = 'SELECT s.username, p.fname, p.lname';
if ($notificationType === 'attendance') {
    $studentsSql .= ', a.status, a.created_at AS registered_at
        FROM (SELECT DISTINCT username FROM dbcourse WHERE class_id = ?) s
        INNER JOIN dbpersonal p ON p.codemeli = s.username
        LEFT JOIN register_class_attendance a
          ON a.username = s.username AND a.register_class_events_id = ?';
} else {
    $studentsSql .= ', e.score, e.quality, e.note, e.created_at AS registered_at
        FROM (SELECT DISTINCT username FROM dbcourse WHERE class_id = ?) s
        INNER JOIN dbpersonal p ON p.codemeli = s.username
        LEFT JOIN register_class_evaluation e
          ON e.username = s.username AND e.register_class_events_id = ?';
}
$studentsSql .= ' ORDER BY p.lname, p.fname, s.username';
$studentsStmt = $conn->prepare($studentsSql);
$studentsStmt->execute([(int)$event['class_id'], $eventId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$qualityOptions = registerClassQualityOptions();
$presentCount = 0;
$absentCount = 0;
$unregisteredCount = 0;
$gradedCount = 0;

foreach ($students as $student) {
    if ($notificationType === 'attendance') {
        if ($student['status'] === '1') {
            $presentCount++;
        } elseif ($student['status'] === '0') {
            $absentCount++;
        } else {
            $unregisteredCount++;
        }
    } elseif ($student['score'] !== null || $student['quality'] !== null) {
        $gradedCount++;
    }
}

$eventDateLabel = !empty($event['event_date'])
    ? toJalali($event['event_date'], 'Y/m/d', 'fa')
    : 'بدون تاریخ';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>جزئیات <?= $notificationType === 'attendance' ? 'حضور و غیاب' : 'آزمون و نمره' ?> | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">
                            <?= $notificationType === 'attendance' ? 'جزئیات حضور و غیاب جلسه' : 'نمرات جلسه/آزمون' ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <?= htmlspecialchars((string)$event['coursename']) ?> -
                            کلاس <?= htmlspecialchars((string)$event['class_name']) ?> -
                            <?= htmlspecialchars($eventDateLabel) ?>
                        </p>
                    </div>
                    <a class="btn btn-outline-primary" href="class-register.php?class=<?= (int)$event['class_id'] ?>">
                        مشاهده دفتر کلاسی
                    </a>
                </div>

                <div class="card custom-card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2"><strong>عنوان:</strong> <?= htmlspecialchars((string)$event['title']) ?></div>
                            <div class="col-md-3 mb-2"><strong>تاریخ:</strong> <?= htmlspecialchars($eventDateLabel) ?></div>
                            <div class="col-md-3 mb-2"><strong>کلاس:</strong> <?= htmlspecialchars((string)$event['class_name']) ?></div>
                            <div class="col-md-3 mb-2"><strong>معلم:</strong> <?= htmlspecialchars(trim((string)$event['teacher_name']) ?: '-') ?></div>
                        </div>
                        <?php if (!empty($event['description'])): ?>
                            <div class="mt-2"><strong>توضیحات:</strong> <?= nl2br(htmlspecialchars((string)$event['description'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($notificationType === 'attendance'): ?>
                    <div class="row row-sm mb-3">
                        <div class="col-md-4"><div class="card custom-card"><div class="card-body text-center"><h3 class="text-success"><?= $presentCount ?></h3><span>حاضر</span></div></div></div>
                        <div class="col-md-4"><div class="card custom-card"><div class="card-body text-center"><h3 class="text-danger"><?= $absentCount ?></h3><span>غایب</span></div></div></div>
                        <div class="col-md-4"><div class="card custom-card"><div class="card-body text-center"><h3 class="text-muted"><?= $unregisteredCount ?></h3><span>ثبت‌نشده</span></div></div></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">برای <?= $gradedCount ?> نفر از <?= count($students) ?> دانش‌آموز این کلاس نمره یا ارزیابی ثبت شده است.</div>
                <?php endif; ?>

                <div class="card custom-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>دانش‌آموز</th>
                                    <th>کد ملی</th>
                                    <?php if ($notificationType === 'attendance'): ?>
                                        <th>وضعیت</th>
                                    <?php else: ?>
                                        <th>نمره</th>
                                        <th>ارزیابی کیفی</th>
                                        <th>توضیحات</th>
                                    <?php endif; ?>
                                    <th>زمان آخرین ثبت</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($students === []): ?>
                                    <tr><td colspan="<?= $notificationType === 'attendance' ? 5 : 7 ?>">دانش‌آموزی در این کلاس یافت نشد.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($students as $index => $student): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars(trim((string)$student['fname'] . ' ' . (string)$student['lname'])) ?></td>
                                        <td><?= htmlspecialchars((string)$student['username']) ?></td>
                                        <?php if ($notificationType === 'attendance'): ?>
                                            <td>
                                                <?php if ($student['status'] === '1'): ?>
                                                    <span class="badge badge-success">حاضر</span>
                                                <?php elseif ($student['status'] === '0'): ?>
                                                    <span class="badge badge-danger">غایب</span>
                                                <?php else: ?>
                                                    <span class="badge badge-light">ثبت‌نشده</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php else: ?>
                                            <td><?= $student['score'] !== null ? htmlspecialchars((string)$student['score']) : '-' ?></td>
                                            <td><?= isset($qualityOptions[(int)$student['quality']]) ? htmlspecialchars($qualityOptions[(int)$student['quality']]) : '-' ?></td>
                                            <td><?= !empty($student['note']) ? nl2br(htmlspecialchars((string)$student['note'])) : '-' ?></td>
                                        <?php endif; ?>
                                        <td><?= !empty($student['registered_at']) ? htmlspecialchars(toJalali($student['registered_at'], 'Y/m/d H:i', 'fa')) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
