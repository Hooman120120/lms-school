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
// (LEFT AS-IS PER YOUR REQUEST)
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

// -------------------- POST HANDLER (before output) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course-status'])) {

    $student_id = trim($_POST['student_id'] ?? '');
    $exam_type  = trim($_POST['exam_type'] ?? '');
    $registered = trim($_POST['registered'] ?? '');
    $attended   = trim($_POST['attended'] ?? '');
    $not_registered_reason = trim($_POST['not_registered_reason'] ?? '');
    $result     = trim($_POST['result'] ?? '');
    $history_year = isset($_POST['history_year']) ? (int)$_POST['history_year'] : 0;

    // validation
    $errors = [];
    if (!$student_id)  $errors[] = "شناسه دانش آموز الزامی است";
    if (!$exam_type)   $errors[] = "نوع آزمون الزامی است";
    if (!$registered)  $errors[] = "وضعیت ثبت نام الزامی است";

    if ($registered === 'ثبت نام کرده') {
        if (!$attended) {
            $errors[] = "وضعیت آزمون الزامی است";
        }

        if ($attended === 'آزمون داده') {
            // Only then result is required
            if (!$result) {
                $errors[] = "نتیجه آزمون الزامی است";
            }
        } else {
            // آزمون نداده → no result
            $result = null;
        }

        $not_registered_reason = null;
    } else {
        if (!$not_registered_reason) $errors[] = "دلیل ثبت نام نکردن الزامی است";
        $attended = null;
        $result   = null;
    }


    if (empty($errors)) {

        // --- build created_at with same Jalali month/day/time but accordion year ---
        // Years in $years and in data-history-year come from jdate('Y', ...) => JALALI.
        // DB stores Gregorian, but Jalali year = clicked accordion year.

        $now  = new DateTime('now');
        $tsNow = $now->getTimestamp();

        // current Jalali date (with English digits)
        $currentJYear  = (int) jdate('Y', $tsNow, '', '', 'en');
        $currentJMonth = (int) jdate('m', $tsNow, '', '', 'en');
        $currentJDay   = (int) jdate('d', $tsNow, '', '', 'en');

        // target Jalali year = clicked accordion year (or current if missing)
        $targetJYear  = $history_year ?: $currentJYear;
        $targetJMonth = $currentJMonth;
        $targetJDay   = $currentJDay;

        // convert Jalali -> Gregorian for DB
        $createdAt = $now->format('Y-m-d H:i:s'); // fallback
        try {
            $g = jalali_to_gregorian($targetJYear, $targetJMonth, $targetJDay);
            if (is_array($g) && count($g) >= 3) {
                $gYear  = (int) $g[0];
                $gMonth = (int) $g[1];
                $gDay   = (int) $g[2];
                $createdAt = sprintf('%04d-%02d-%02d %s', $gYear, $gMonth, $gDay, $now->format('H:i:s'));
            }
        } catch (Exception $e) {
            // keep fallback
        }

        $sql = "INSERT INTO course_status
                (student_id, exam_type, registered, attended, not_registered_reason, result, created_at)
                VALUES
                (:student_id, :exam_type, :registered, :attended, :not_registered_reason, :result, :created_at)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':student_id', $student_id);
        $stmt->bindValue(':exam_type', $exam_type);
        $stmt->bindValue(':registered', $registered);
        $stmt->bindValue(':attended', $attended);
        $stmt->bindValue(':not_registered_reason', $not_registered_reason);
        $stmt->bindValue(':result', $result);
        $stmt->bindValue(':created_at', $createdAt);

        if ($stmt->execute()) {
            // set flash message for next request
            $_SESSION['course_status_success'] = 'وضعیت آزمون با موفقیت اضافه شد.';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            // optional: log error
        }
    } else {
        // optional: store $errors in session to show in UI
    }
}

// -------------------- DATA PREP START --------------------

include_once '../../temp.html';

// codemeli comes from URL: student-history.php?Id=XXXXXXXXXX
$codemeli      = isset($_GET['Id']) ? trim($_GET['Id']) : null;
$studentInfo   = null;
$historyByYear = [];
$coursesByYear = [];   // سال‌ها و دوره‌ها

if ($codemeli !== null && $codemeli !== '') {

    // 1) Get main info from dbpersonal (including datecreated)
    $sqlInfo = "SELECT fname, lname, codemeli, city, state, datecreated 
                FROM dbpersonal 
                WHERE codemeli = ? 
                LIMIT 1";
    $stmt    = $conn->prepare($sqlInfo);
    $stmt->execute([$codemeli]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // We assume course_status.student_id & student_status.student_id store codemeli
    $studentId = $codemeli;

    // 2) course_status rows for this student
    $sqlCourse = "SELECT * FROM course_status WHERE student_id = ? ORDER BY created_at DESC";
    $stmt      = $conn->prepare($sqlCourse);
    $stmt->execute([$studentId]);
    $courseRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3) student_status rows for this student
    $sqlStatus = "SELECT * FROM student_status WHERE student_id = ? ORDER BY created_at DESC";
    $stmt      = $conn->prepare($sqlStatus);
    $stmt->execute([$studentId]);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // --- GROUP course_status BY ACADEMIC YEAR (1 Tir to 1 Tir) ---
    foreach ($courseRows as $row) {
        if (empty($row['created_at'])) {
            continue;
        }

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) {
            continue;
        }

        $jYear  = (int) jdate('Y', $ts, '', '', 'en');
        $jMonth = (int) jdate('m', $ts, '', '', 'en');

        // academic year: 1 Tir (month 4) to 31 Khordad (month 3 next year)
        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($historyByYear[$academicYear])) {
            $historyByYear[$academicYear] = [
                'course' => [],
                'status' => [],
            ];
        }

        $historyByYear[$academicYear]['course'][] = $row;
    }

    // --- GROUP student_status BY SAME ACADEMIC YEAR ---
    foreach ($statusRows as $row) {
        if (empty($row['created_at'])) {
            continue;
        }

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) {
            continue;
        }

        $jYear  = (int) jdate('Y', $ts, '', '', 'en');
        $jMonth = (int) jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($historyByYear[$academicYear])) {
            $historyByYear[$academicYear] = [
                'course' => [],
                'status' => [],
            ];
        }

        $historyByYear[$academicYear]['status'][] = $row;
    }

    $swr = 'SELECT dbcourse.*, codtbl_course.*, codtbl_course.datacreated AS course_date
            FROM dbcourse
            INNER JOIN codtbl_course 
                ON dbcourse.codecourse = codtbl_course.codecourse
            WHERE dbcourse.username = ?
            ORDER BY codtbl_course.datacreated DESC';

    $wer = $conn->prepare($swr);
    $wer->execute([$codemeli]);
    $recive = $wer->fetchAll(PDO::FETCH_OBJ);

    if ($wer->rowCount() > 0) {
        foreach ($recive as $res) {
            $regDate = $res->course_date ?? null;
            if (empty($regDate)) {
                continue;
            }

            $ts = strtotime($regDate);
            if ($ts <= 0) {
                continue;
            }

            $jYear  = (int) jdate('Y', $ts, '', '', 'en');
            $jMonth = (int) jdate('m', $ts, '', '', 'en');

            $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

            if (!isset($coursesByYear[$academicYear])) {
                $coursesByYear[$academicYear] = [];
            }
            $coursesByYear[$academicYear][] = $res;
        }
    }
}

$years = [];
if (!empty($historyByYear) || !empty($coursesByYear)) {
    $years = array_unique(array_merge(array_keys($historyByYear), array_keys($coursesByYear)));
    rsort($years);

    foreach ($years as $y) {
        if (!isset($historyByYear[$y])) {
            $historyByYear[$y] = [
                'course' => [],
                'status' => [],
            ];
        }
    }
}
// -------------------- DATA PREP END --------------------
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>سوابق دانش‌آموز | <?php echo __TITLE; ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <div class="container-fluid">

        <!-- Flash success message -->
        <?php if (!empty($_SESSION['course_status_success'])): ?>
            <div class="alert alert-success text-center mt-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['course_status_success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['course_status_success']); ?>
        <?php endif; ?>

        <!-- Student info card (from dbpersonal) -->
        <?php if (!empty($studentInfo)): ?>
            <?php
            $signupLabel = '';
            if (!empty($studentInfo['datecreated'])) {
                $tsd = strtotime($studentInfo['datecreated']);
                if ($tsd > 0) {
                    $signupLabel = jdate('Y/m/d', $tsd);
                }
            }
            ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="d-flex flex-row card-body d-flex flex-wrap justify-content-between" style="direction: rtl; text-align: right;">
                            <div class="fw-bold">
                                <?php
                                $fname = htmlspecialchars($studentInfo['fname'] ?? '', ENT_QUOTES, 'UTF-8');
                                $lname = htmlspecialchars($studentInfo['lname'] ?? '', ENT_QUOTES, 'UTF-8');
                                echo trim($fname . ' ' . $lname);
                                ?>
                            </div>
                            <div class="text-muted mt-1">
                                کدملی:
                                <span class="fw-bold">
                                        <?php echo htmlspecialchars($studentInfo['codemeli'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                            </div>
                            <?php if ($signupLabel): ?>
                                <div class="text-muted mt-1">
                                    تاریخ عضویت:
                                    <span class="fw-bold">
                                            <?php echo htmlspecialchars($signupLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                </div>
                            <?php endif; ?>

                            <div class="mb-1">
                                <span class="text-muted">استان:</span>
                                <span class="fw-bold">
                                        <?php echo htmlspecialchars($studentInfo['state'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                            </div>
                            <div>
                                <span class="text-muted">شهر:</span>
                                <span class="fw-bold">
                                        <?php echo htmlspecialchars($studentInfo['city'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- History accordion (exam status + comments + courses by year) -->
        <div class="row mt-2 mb-3">
            <div class="col-12">

                <h4 class="mb-1">سوابق دانش‌آموز</h4>
                <p class="text-muted mb-3">
                    وضعیت آزمون‌ها، نمرات، توضیحات و دوره‌های ثبت‌نام‌شده در سال‌های مختلف
                </p>

                <?php if (!empty($years)): ?>
                    <div class="accordion" id="historyAccordion">
                        <?php
                        $isFirst = true; // newest year accordion open by default

                        foreach ($years as $year):
                            $courseRowsYear = $historyByYear[$year]['course'] ?? [];
                            $statusRowsYear = $historyByYear[$year]['status'] ?? [];
                            $yearCourses    = $coursesByYear[$year] ?? [];

                            $collapseId = 'collapseYear_' . (int)$year;
                            $headingId  = 'headingYear_' . (int)$year;

                            $showClass      = $isFirst ? 'show' : '';
                            $ariaExpanded   = $isFirst ? 'true' : 'false';
                            $btnCollapsed   = $isFirst ? '' : 'collapsed';

                            $examCount   = count($courseRowsYear);
                            $statusCount = count($statusRowsYear);
                            $courseCount = count($yearCourses);
                            ?>
                            <div class="accordion-item mb-2">
                                <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                                    <button
                                            class="accordion-button <?php echo $btnCollapsed; ?>"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?php echo $collapseId; ?>"
                                            aria-expanded="<?php echo $ariaExpanded; ?>"
                                            aria-controls="<?php echo $collapseId; ?>"
                                    >
                                    <span class="me-2">
                                        سوابق سال
                                        <?php
                                        $startYear = (int)$year;
                                        $endYear   = $startYear + 1;
                                        echo htmlspecialchars($startYear, ENT_QUOTES, 'UTF-8')
                                            . '-' .
                                            htmlspecialchars($endYear, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </span>
                                        <span class="ms-auto text-muted" style="font-size: 12px;">
                                            آزمون‌ها: <?php echo $examCount; ?> |
                                            وضعیت/نظرات: <?php echo $statusCount; ?> |
                                            دوره‌ها: <?php echo $courseCount; ?>
                                        </span>
                                    </button>
                                </h2>

                                <div
                                        id="<?php echo $collapseId; ?>"
                                        class="accordion-collapse collapse <?php echo $showClass; ?>"
                                        aria-labelledby="<?php echo $headingId; ?>"
                                >
                                    <div class="accordion-body">
                                        <!-- Exams + comments row -->
                                        <div class="row">

                                            <!-- Exams list -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3">
                                                    وضعیت آزمون‌ها
                                                </h6>

                                                <?php if (!empty($courseRowsYear)): ?>
                                                    <?php
                                                    $num = 1;
                                                    foreach ($courseRowsYear as $row):
                                                        $exam_type             = htmlspecialchars($row['exam_type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $registered            = htmlspecialchars($row['registered'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $attended              = htmlspecialchars($row['attended'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $not_registered_reason = htmlspecialchars($row['not_registered_reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $result                = htmlspecialchars($row['result'] ?? '', ENT_QUOTES, 'UTF-8');

                                                        $createdLabel = '';
                                                        if (!empty($row['created_at'])) {
                                                            $tsc = strtotime($row['created_at']);
                                                            if ($tsc > 0) {
                                                                $createdLabel = jdate('ثبت در تاریخ : '.'Y/m/d', $tsc);
                                                            }
                                                        }
                                                        ?>
                                                        <div class="border rounded p-2 mb-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <div>
                                                                    <span class="badge bg-primary ms-1"><?php echo $num; ?></span>
                                                                    <strong><?php echo $exam_type; ?></strong>
                                                                </div>
                                                                <?php if ($createdLabel): ?>
                                                                    <span class="text-muted" style="font-size: 12px;">
                                                                        <?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div style="font-size: 13px;">
                                                                <div class="mb-1">
                                                                    <span class="text-muted">ثبت‌نام:</span>
                                                                    <span><?php echo $registered ?: '-'; ?></span>
                                                                </div>
                                                                <div class="mb-1">
                                                                    <span class="text-muted">حضور در آزمون:</span>
                                                                    <span><?php echo $attended ?: '-'; ?></span>
                                                                </div>
                                                                <div class="mb-1">
                                                                    <span class="text-muted">نتیجه:</span>
                                                                    <span><?php echo $result ?: '-'; ?></span>
                                                                </div>
                                                                <div class="mb-0">
                                                                    <span class="text-muted">توضیحات:</span>
                                                                    <span><?php echo $not_registered_reason ?: '-'; ?></span>
                                                                </div>

                                                            </div>

                                                        </div>
                                                        <?php
                                                        $num++;
                                                    endforeach;
                                                    ?>
                                                <?php else: ?>
                                                    <p class="text-muted mb-0" style="font-size: 13px;">
                                                        هیچ آزمونی برای این سال ثبت نشده است.
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Averages & comments -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3">
                                                    نمرات و نظرات
                                                </h6>

                                                <?php if (!empty($statusRowsYear)): ?>
                                                    <?php foreach ($statusRowsYear as $row):
                                                        $avg1_display = (!isset($row['avg_first_semester']) || $row['avg_first_semester'] === '')
                                                            ? '-'
                                                            : htmlspecialchars($row['avg_first_semester'], ENT_QUOTES, 'UTF-8');

                                                        $avg2_display = (!isset($row['avg_second_semester']) || $row['avg_second_semester'] === '')
                                                            ? '-'
                                                            : htmlspecialchars($row['avg_second_semester'], ENT_QUOTES, 'UTF-8');

                                                        $student_comment   = nl2br(htmlspecialchars($row['student_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                        $supporter_comment = nl2br(htmlspecialchars($row['supporter_comment'] ?? '', ENT_QUOTES, 'UTF-8'));

                                                        $createdStatusLabel = '';
                                                        if (!empty($row['created_at'])) {
                                                            $tss = strtotime($row['created_at']);
                                                            if ($tss > 0) {
                                                                $createdStatusLabel = jdate('Y/m/d', $tss);
                                                            }
                                                        }
                                                        ?>
                                                        <div class="border rounded p-2 mb-2" style="font-size: 13px;">
                                                            <?php if ($createdStatusLabel): ?>
                                                                <div class="text-muted mb-1">
                                                                    ثبت در تاریخ:
                                                                    <?php echo htmlspecialchars($createdStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="mb-1">
                                                                <strong>معدل ترم اول:</strong>
                                                                <span><?php echo $avg1_display; ?></span>
                                                            </div>
                                                            <div class="mb-1">
                                                                <strong>معدل ترم دوم:</strong>
                                                                <span><?php echo $avg2_display; ?></span>
                                                            </div>
                                                            <div class="mb-1 d-flex">
                                                                <strong>نظر دانش‌آموز:&nbsp;</strong>
                                                                <div><?php echo $student_comment ?: '-'; ?></div>
                                                            </div>
                                                            <div class="mb-0 d-flex">
                                                                <strong>نظر پشتیبان:&nbsp;</strong>
                                                                <div><?php echo $supporter_comment ?: '-'; ?></div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted mb-0" style="font-size: 13px;">
                                                        هیچ توضیح یا نمره‌ای برای این سال ثبت نشده است.
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <button type="button"
                                                class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#courseStatusModal"
                                                data-history-year="<?php echo (int)$year; ?>">
                                            افزودن وضعیت آزمون
                                        </button>
                                        <!-- /row -->

                                        <!-- Courses for this year (compact table) -->
                                        <?php if (!empty($yearCourses)): ?>
                                            <hr class="my-3">
                                            <h6 class="border-bottom pb-2 mb-2">
                                                دوره‌های ثبت‌نام‌شده در این سال
                                            </h6>

                                            <div class="history-course-list" style="max-height:20vh; overflow-y:auto; font-size:15px; line-height:1.5;">
                                                <?php
                                                $cnum = 1;
                                                foreach ($yearCourses as $res):
                                                    ?>
                                                    <div class="border rounded px-2 py-2 mb-1 d-flex justify-content-between align-items-start">
                                                        <!-- course title / index -->
                                                        <div class="d-flex align-items-start" style="gap:6px; flex:1 1 auto;">
                <span class="badge bg-light text-dark ms-1"
                      style="font-size:12px; min-width:26px; text-align:center;">
                    <?php echo $cnum; ?>
                </span>
                                                            <span class="fw-bold"
                                                                  style="white-space:normal; word-break:break-word;">
                    <?php echo htmlspecialchars($res->coursename, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                                                        </div>

                                                        <!-- status chips -->
                                                        <div class="d-flex flex-wrap align-items-center justify-content-start" style="gap:4px; font-size:13px;">

                                                            <?php
                                                            // ---- کلاس (attendance color) ----
                                                            $classDotClass = '';
                                                            $classDotStyle = "display:inline-block;width:10px;height:10px;border-radius:50%;";
                                                            $classTitle    = '';

                                                            if ($res->color == 0) {
                                                                $classDotClass = 'bg-danger';
                                                                $classTitle    = 'حضور زیر 25% در کلاس آنلاین';
                                                            } elseif ($res->color == 1) {
                                                                $classDotClass = 'bg-warning';
                                                                $classTitle    = 'حضور بین 25% تا 50% در کلاس آنلاین';
                                                            } elseif ($res->color == 2) {
                                                                $classDotClass = '';
                                                                $classDotStyle .= 'background-color:#fffb02;';
                                                                $classTitle    = 'حضور بین 50% تا 75% در کلاس آنلاین';
                                                            } elseif ($res->color == 3) {
                                                                $classDotClass = 'bg-success';
                                                                $classTitle    = 'حضور بالای 75% در کلاس آنلاین';
                                                            }
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                    <span class="rounded-circle <?php echo $classDotClass; ?>"
                          style="<?php echo $classDotStyle; ?>; margin-left:4px;"
                          data-bs-toggle="tooltip"
                          title="<?php echo $classTitle; ?>"></span>
                    کلاس
                </span>

                                                            <?php
                                                            // ---- آزمون (quiz color) ----
                                                            $colorquiz = getTblQuizColor($res->codecourse, $res->username);
                                                            $totalquiz = $colorquiz[0] + $colorquiz[1];
                                                            $darsadquiz = $totalquiz > 0 ? ($colorquiz[0] / $totalquiz) * 100 : 0;

                                                            $quizDotClass = '';
                                                            $quizDotStyle = "display:inline-block;width:10px;height:10px;border-radius:50%;";
                                                            $quizTitle    = '';

                                                            if ($darsadquiz < 25) {
                                                                $quizDotClass = 'bg-danger';
                                                                $quizTitle    = 'حضور زیر 25% در آزمون';
                                                            } elseif ($darsadquiz < 50) {
                                                                $quizDotClass = 'bg-warning';
                                                                $quizTitle    = 'حضور بین 25% تا 50% در آزمون';
                                                            } elseif ($darsadquiz < 75) {
                                                                $quizDotClass = '';
                                                                $quizDotStyle .= 'background-color:#fffb02;';
                                                                $quizTitle    = 'حضور بین 50% تا 75% در آزمون';
                                                            } else {
                                                                $quizDotClass = 'bg-success';
                                                                $quizTitle    = 'حضور بالای 75% در آزمون';
                                                            }
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                    <span class="rounded-circle <?php echo $quizDotClass; ?>"
                          style="<?php echo $quizDotStyle; ?>; margin-left:4px;"
                          data-bs-toggle="tooltip"
                          title="<?php echo $quizTitle; ?>"></span>
                    آزمون
                </span>

                                                            <?php
                                                            // ---- فیلم ----
                                                            $logfilm   = retLogViewFilm($res->codecourse, $res->username);
                                                            $filmClass = $logfilm ? 'bg-teal' : 'bg-dark';
                                                            $filmTitle = $logfilm ? 'دارای گزارش تماشای فیلم' : 'فاقد گزارش تماشای فیلم';
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                    <span class="rounded-circle <?php echo $filmClass; ?>"
                          style="display:inline-block;width:10px;height:10px;border-radius:50%; margin-left:4px;"
                          data-bs-toggle="tooltip"
                          title="<?php echo $filmTitle; ?>"></span>
                    فیلم
                </span>

                                                            <?php
                                                            // ---- جزوات ----
                                                            $logdoc   = retLogViewDoc($res->codecourse, $res->username);
                                                            $docClass = $logdoc ? 'bg-teal' : 'bg-dark';
                                                            $docTitle = $logdoc ? 'دارای گزارش مطالعه جزوه' : 'فاقد گزارش مطالعه جزوه';
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                    <span class="rounded-circle <?php echo $docClass; ?>"
                          style="display:inline-block;width:10px;height:10px;border-radius:50%; margin-left:4px;"
                          data-bs-toggle="tooltip"
                          title="<?php echo $docTitle; ?>"></span>
                    جزوات
                </span>

                                                        </div>
                                                    </div>
                                                    <?php
                                                    $cnum++;
                                                endforeach;
                                                ?>
                                            </div>
                                        <?php endif; ?>


                                    </div> <!-- /accordion-body -->
                                </div> <!-- /accordion-collapse -->
                            </div> <!-- /accordion-item -->
                            <?php
                            $isFirst = false; // only newest stays open initially
                        endforeach;
                        ?>
                    </div> <!-- /historyAccordion -->

                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            هیچ سابقه‌ای برای این دانش‌آموز ثبت نشده است.
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div><!-- /.container-fluid -->
</div><!-- /.page -->

<!-- Modal: Add course_status -->
<div class="modal fade" id="courseStatusModal" tabindex="-1" aria-labelledby="courseStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="courseStatusModalLabel">افزودن وضعیت آزمون</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>

            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="student_id"
                           value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

                    <!-- year of accordion (Jalali) -->
                    <input type="hidden" name="history_year" id="history_year" value="">

                    <div class="mb-3">
                        <label for="registered" class="form-label" style="color: #222;">ثبت نام کرده /
                            نکرده</label>
                        <select class="form-select" id="registered" name="registered" required
                                style="color: #000;">
                            <option value="" selected disabled>انتخاب کنید</option>
                            <option value="ثبت نام کرده">ثبت نام کرده</option>
                            <option value="بررسی گردد">بررسی گردد</option>
                            <option value="ثبت نام نکرده">ثبت نام نکرده</option>
                        </select>
                    </div>

                    <div class="mb-3" id="examTypeDiv" style="display:none;">
                        <label for="examType" class="form-label" style="color: #222;">نوع آزمون</label>
                        <select class="form-select" id="examType" name="exam_type" style="color: #000;">
                            <option value="" selected disabled>انتخاب کنید</option>
                            <option value="تیز هوشان">تیز هوشان</option>
                            <option value="نمونه دولتی">نمونه دولتی</option>
                            <option value="کنکور سراسری">کنکور سراسری</option>
                            <option value="موارد دیگر">موارد دیگر</option>
                        </select>
                    </div>

                    <!-- Reason text box if not registered -->
                    <div class="mb-3" id="reasonDiv" style="display:none;">
                        <label for="reason" class="form-label" style="color: #222;">چرا ثبت نام
                            نکرده؟</label>
                        <input type="text" class="form-control" id="reason" name="not_registered_reason"
                               placeholder="علت را وارد کنید">
                    </div>

                    <div class="mb-3" id="takenExamDiv" style="display:none;">
                        <label for="takenExam" class="form-label" style="color: #222;">آزمون داده /
                            نداده</label>
                        <select class="form-select" id="takenExam" name="attended" style="color: #000;">
                            <option value="" selected disabled>انتخاب کنید</option>
                            <option value="آزمون داده">آزمون داده</option>
                            <option value="آزمون نداده">آزمون نداده</option>
                        </select>
                    </div>

                    <div class="mb-3" id="passedDiv" style="display:none;">
                        <label for="passed" class="form-label" style="color: #222;">قبول شده / نشده</label>
                        <select class="form-select" id="passed" name="result" style="color: #000;">
                            <option value="" selected disabled>انتخاب کنید</option>
                            <option value="قبول شده">قبول شده</option>
                            <option value="قبول نشده">قبول نشده</option>
                        </select>
                    </div>

                    <button type="submit" name="course-status" class="btn btn-primary w-100">ثبت</button>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var courseStatusModal = document.getElementById('courseStatusModal');
        if (!courseStatusModal) return;

        courseStatusModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var year = button.getAttribute('data-history-year');
            var yearInput = document.getElementById('history_year');

            if (yearInput && year) {
                yearInput.value = year;
            }

            var modalTitle = courseStatusModal.querySelector('.modal-title');
            if (modalTitle && year) {
                modalTitle.textContent = 'افزودن وضعیت آزمون - سال ' + year;
            }
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const registered   = document.getElementById('registered');
        const reasonDiv    = document.getElementById('reasonDiv');
        const examTypeDiv  = document.getElementById('examTypeDiv');
        const takenExamDiv = document.getElementById('takenExamDiv');
        const passedDiv    = document.getElementById('passedDiv');

        const examType     = document.getElementById('examType');
        const takenExam    = document.getElementById('takenExam');
        const passed       = document.getElementById('passed');
        const reason       = document.getElementById('reason');

        if (!registered || !reasonDiv || !examTypeDiv || !takenExamDiv || !passedDiv ||
            !examType || !takenExam || !passed || !reason) {
            return;
        }

        // Initial state
        reasonDiv.style.display   = 'none';
        examTypeDiv.style.display = 'none';
        takenExamDiv.style.display = 'none';
        passedDiv.style.display   = 'none';

        // Clear required flags initially
        examType.required  = false;
        takenExam.required = false;
        passed.required    = false;
        reason.required    = false;

        function resetValues() {
            examType.value  = '';
            takenExam.value = '';
            passed.value    = '';
            reason.value    = '';
        }

        // On change of registration status
        registered.addEventListener('change', function () {
            const val = this.value;

            if (val === 'ثبت نام نکرده') {
                // Not registered: need reason + exam type, no exam taken / result
                reasonDiv.style.display   = 'block';
                examTypeDiv.style.display = 'block';
                takenExamDiv.style.display = 'none';
                passedDiv.style.display   = 'none';

                reason.required    = true;
                examType.required  = true;
                takenExam.required = false;
                passed.required    = false;
                passed.value       = '';
            }
            else if (val === 'ثبت نام کرده') {
                // Registered: need exam type + attended, result only if attended = آزمون داده
                reasonDiv.style.display   = 'none';
                examTypeDiv.style.display = 'block';
                takenExamDiv.style.display = 'block';
                passedDiv.style.display   = (takenExam.value === 'آزمون داده') ? 'block' : 'none';

                reason.required    = false;
                reason.value       = '';
                examType.required  = true;
                takenExam.required = true;
                passed.required    = (takenExam.value === 'آزمون داده');
                if (takenExam.value !== 'آزمون داده') {
                    passed.value = '';
                }
            }
            else if (val === 'بررسی گردد') {
                // Under review: need reason, exam type optional (or required if you want)
                reasonDiv.style.display   = 'block';
                examTypeDiv.style.display = 'block'; // <-- show it so server-side "نوع آزمون الزامی است" is possible
                takenExamDiv.style.display = 'none';
                passedDiv.style.display   = 'none';

                reason.required    = true;
                examType.required  = true; // match your PHP: exam_type is always required
                takenExam.required = false;
                passed.required    = false;
                takenExam.value    = '';
                passed.value       = '';
            }
            else {
                // Fallback: hide everything
                reasonDiv.style.display   = 'none';
                examTypeDiv.style.display = 'none';
                takenExamDiv.style.display = 'none';
                passedDiv.style.display   = 'none';

                reason.required    = false;
                examType.required  = false;
                takenExam.required = false;
                passed.required    = false;
                resetValues();
            }
        });

        // On change of exam taken
        takenExam.addEventListener('change', function () {
            if (this.value === 'آزمون داده') {
                passedDiv.style.display = 'block';
                passed.required         = true;
            } else {
                passedDiv.style.display = 'none';
                passed.required         = false;
                passed.value            = '';
            }
        });
    });
</script>

</body>
</html>
