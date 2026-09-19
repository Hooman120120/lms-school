<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
// Demo Data - In the future, this will come from your API
$studentName = "علیرضا محمدی";
$courses = [
    [
        'title' => 'ریاضیات پیشرفته',
        'teacher' => 'دکتر حسینی',
        'attendance' => 92,
        'last_exam' => 18.5,
        'total_exams' => 4,
        'status' => 'active',
        'color' => '#4e73df'
    ],
    [
        'title' => 'فیزیک کوانتوم',
        'teacher' => 'مهندس راد',
        'attendance' => 75,
        'last_exam' => 14.25,
        'total_exams' => 3,
        'status' => 'warning',
        'color' => '#1cc88a'
    ],
    [
        'title' => 'برنامه‌نویسی پایتون',
        'teacher' => 'استاد احمدی',
        'attendance' => 100,
        'last_exam' => 20,
        'total_exams' => 5,
        'status' => 'active',
        'color' => '#36b9cc'
    ]
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <title> میزکار من | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        body {
            background-color: #f0f2f5;
            /*font-family: 'Segoe UI', Tahoma, sans-serif;*/
        }

        .course-row {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid #e0e6ed;
            transition: all 0.2s;
        }

        .course-header {
            padding: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .course-header:hover {
            background-color: #f8f9fa;
        }

        .stat-box {
            text-align: center;
            padding: 5px 15px;
            border-left: 1px solid #eee;
        }

        .stat-box:last-child {
            border-left: none;
        }

        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            display: block;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }

        .detail-pane {
            background: #fafbfc;
            border-top: 1px solid #eee;
            padding: 25px;
            display: none;
        }

        /* Progress mini-bar */
        .progress-tiny {
            height: 6px;
            width: 100px;
            border-radius: 10px;
            background: #eee;
            margin-top: 5px;
        }

        /* Status Dot */
        .dot {
            height: 10px;
            width: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }

        .table-custom {
            font-size: 13px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .table-custom th {
            background: #f1f4f8;
            color: #555;
            font-weight: 600;
        }
    </style>
</head>
<body class="main-body leftmenu">
<?php
include_once '../menu.php';
include_once '../header.php';

?>
<?php $user = retPerson($_GET['user']); ?>
<!-- Main Content-->
<div class="main-content side-content pt-0">
    <div class="container-fluid">
        <div class="inner-body">
            <h4 class="mb-4 fw-bold text-dark"><i class="fa fa-graduation-cap me-2"></i>سوابق تحصیلی دانش آموز
                : <?= $user->fname . ' ' . $user->lname ?></h4>
            <?php
            $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse WHERE dbcourse.username = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_GET['user']]);
            $userCourses = $stmt->fetchAll(PDO::FETCH_OBJ);
            foreach ($userCourses

                     as $userCourse) {
                ?>
                <div class="course-row shadow-sm">
                    <div class="course-header" onclick="toggleDetails(<?= $userCourse->codecourse ?>)">
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold"><?= $userCourse->coursename ?> <span
                                        class="badge bg-primary-subtle text-primary fw-normal ms-2"
                                        style="font-size: 10px;">کد: <?= $userCourse->codecourse ?></span></h5>
                            <!--                            <div class="d-flex align-items-center">-->
                            <!--                                <span class="stat-label me-2">پیشرفت پایه:</span>-->
                            <!--                                <div class="progress-tiny">-->
                            <!--                                    <div class="progress-bar bg-primary" style="width: 75%"></div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="stat-box">
                                <span class="stat-label">میانگین نمرات</span>
                                <span class="stat-value text-success">18.25</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-label">درصد حضور</span>
                                <span class="stat-value text-info">90%</span>
                            </div>
                            <div class="ms-3">
                                <i class="fa fa-chevron-down text-muted" id="icon-<?= $userCourse->codecourse ?>"></i>
                            </div>
                        </div>
                    </div>

                    <div class="detail-pane" id="pane-<?= $userCourse->codecourse ?>">
                        <div class="row">
                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3 text-secondary"><i class="fa fa-calendar-check me-2"></i> ریز
                                    حضور و
                                    غیاب
                                </h6>
                                <?php
                                $sqlMeets = 'SELECT * FROM `tbl_attendanced` WHERE codecourse=? ORDER BY datestart DESC';
                                $stmtMeets = $conn->prepare($sqlMeets);
                                $stmtMeets->execute([$userCourse->codecourse]);
                                $meets = $stmtMeets->fetchAll(PDO::FETCH_OBJ);
                                ?>
                                <table class="table table-custom border">
                                    <thead>
                                    <tr>
                                        <th>جلسه</th>
                                        <th>تاریخ</th>
                                        <th>وضعیت</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if ($stmtMeets->rowCount() > 0):
                                        foreach ($meets as $meet):
                                            // Date Conversion
                                            $dateParts = explode('-', $meet->datestart);
                                            $jalaliDate = gregorian_to_jalali($dateParts[0], $dateParts[1], $dateParts[2]);
                                            $formattedDate = implode('/', $jalaliDate);

                                            // Fetch Attendance Status
                                            $stmtAtt = $conn->prepare('SELECT status FROM dbattendance WHERE coding=? AND username=?');
                                            $stmtAtt->execute([$meet->id, $_GET['user']]);
                                            $att = $stmtAtt->fetch(PDO::FETCH_OBJ);

                                            // Logic for Presence
                                            $isPresent = ($att && $att->status !== 'غایب');
                                            $statusText = $isPresent ? 'حاضر' : 'غایب';
                                            $statusClass = $isPresent ? 'text-success' : 'text-danger';
                                            $statusIcon = $isPresent ? 'fa-check-circle' : 'fa-times-circle';
                                            ?>
                                            <tr>
                                                <td class="fw-medium"><?= htmlspecialchars($meet->classname) ?></td>
                                                <td class="text-muted"><?= $formattedDate ?></td>
                                                <td>
                                                    <span class="<?= $statusClass ?> fw-bold">
                                                        <i class="fa <?= $statusIcon ?> me-1"></i>
                                                        <?= $statusText ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php
                                        endforeach;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted">
                                                <i class="fa fa-exclamation-triangle me-2"></i>
                                                گزارش حضور و غیابی بارگزاری نشده است
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3 text-secondary"><i class="fa fa-file-signature me-2"></i> نمرات
                                    آزمون‌ها
                                </h6>
                                <?php
                                $sqlQuizzes = 'SELECT * FROM `dbquiz` WHERE codecourse=? ORDER BY idquiz DESC';
                                $stmtQuizzes = $conn->prepare($sqlQuizzes);
                                $stmtQuizzes->execute([$userCourse->codecourse]);
                                $quizzes = $stmtQuizzes->fetchAll(PDO::FETCH_OBJ);
                                ?>
                                <!--                                <table class="table table-custom border text-center">-->
                                <!--                                    <thead>-->
                                <!--                                    <tr>-->
                                <!--                                        <th>نام آزمون</th>-->
                                <!--                                        <th>نمره</th>-->
                                <!--                                        <th>رتبه در کلاس</th>-->
                                <!--                                    </tr>-->
                                <!--                                    </thead>-->
                                <!--                                    <tbody>-->
                                <!--                                    --><?php
                                //                                    $hasData = false; // Track if we actually show any rows
                                //                                    foreach ($quizzes as $quiz):
                                //                                        // Check first table
                                //                                        $stmt = $conn->prepare('SELECT nomre, rotbe FROM result_quiz WHERE quizid=? AND username=?');
                                //                                        $stmt->execute([$quiz->idquiz, $_GET['user']]);
                                //                                        $result = $stmt->fetch(PDO::FETCH_OBJ);
                                //
                                //                                        // If not found, check second table
                                //                                        if (!$result) {
                                //                                            $stmtt = $conn->prepare('SELECT nomre, rotbe FROM result_quizzes_jame WHERE quiz_id = ? AND username = ?');
                                //                                            $stmtt->execute([$quiz->idquiz, $_GET['user']]);
                                //                                            $result = $stmtt->fetch(PDO::FETCH_OBJ);
                                //                                        }
                                //
                                //                                        if ($stmt->rowCount() > 0 or $stmtt->rowCount() > 0):
                                //                                            $hasData = true;
                                //                                            ?>
                                <!--                                            <tr>-->
                                <!--                                                <td class="fw-bold">-->
                                <? //= $quiz->quizname ?><!--</td>-->
                                <!--                                                <td>-->
                                <!--                                                    <span class="badge bg-light text-dark fs-14">-->
                                <!--                                                        --><? //= $result->nomre ?>
                                <!--                                                    </span>-->
                                <!--                                                </td>-->
                                <!--                                                <td>-->
                                <!--                                                    <span class="text-primary">-->
                                <? //= $result->rotbe ?><!--</span>-->
                                <!--                                                </td>-->
                                <!--                                            </tr>-->
                                <!--                                        --><?php //endif; endforeach; ?>
                                <!---->
                                <!--                                    --><?php //if (!$hasData): ?>
                                <!--                                        <tr>-->
                                <!--                                            <td colspan="3" class="py-4 text-muted">-->
                                <!--                                                <i class="fa fa-info-circle me-1"></i>-->
                                <!--                                                آزمونی برای این پایه ثبت نشده است.-->
                                <!--                                            </td>-->
                                <!--                                        </tr>-->
                                <!--                                    --><?php //endif; ?>
                                <!--                                    </tbody>-->
                                <!--                                </table>-->
                                <table class="table table-custom border text-center">
                                    <thead>
                                    <tr>
                                        <th>نام آزمون</th>
                                        <th>نمره</th>
                                        <th>رتبه در کلاس</th>
                                        <th>وضعیت</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $hasData = count($quizzes) > 0;
                                    foreach ($quizzes as $quiz):
                                        // 1. Try first table
                                        $stmt = $conn->prepare('SELECT nomre, rotbe FROM result_quiz WHERE quizid=? AND username=?');
                                        $stmt->execute([$quiz->idquiz, $_GET['user']]);
                                        $result = $stmt->fetch(PDO::FETCH_OBJ);

                                        // 2. If not found, try second table
                                        if (!$result) {
                                            $stmtt = $conn->prepare('SELECT nomre, rotbe FROM result_quizzes_jame WHERE quiz_id = ? AND username = ?');
                                            $stmtt->execute([$quiz->idquiz, $_GET['user']]);
                                            $result = $stmtt->fetch(PDO::FETCH_OBJ);
                                        }

                                        // We always show the row now
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?= $quiz->quizname ?></td>

                                            <?php if ($result): ?>
                                                <td>
                                                    <span class="badge bg-light text-dark fs-14">
                                                        <?= $result->nomre ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-primary"><?= $result->rotbe ?></span>
                                                </td>
                                                <td><span class="text-success small">حاضر</span></td>
                                            <?php else: ?>
                                                <td colspan="2" class="text-danger small italic">
                                                    <i class="fa fa-user-times me-1"></i> غایب در آزمون
                                                </td>
                                                <td><span class="text-danger small">غایب</span></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (!$hasData): ?>
                                        <tr>
                                            <td colspan="4" class="py-4 text-muted">
                                                <i class="fa fa-info-circle me-1"></i>
                                                آزمونی برای این پایه ثبت نشده است.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php include '../footers.php'; ?>
<script>
    function toggleDetails(id) {
        const pane = document.getElementById('pane-' + id);
        const icon = document.getElementById('icon-' + id);
        const allPanes = document.querySelectorAll('.detail-pane');

        // Toggle visibility
        if (pane.style.display === 'block') {
            pane.style.display = 'none';
            if (icon) icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
        } else {
            pane.style.display = 'block';
            if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
        }
    }
</script>
</body>
</html>