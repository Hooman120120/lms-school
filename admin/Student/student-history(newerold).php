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

// -------------------- Simotel config & helpers (talk time per academic year) --------------------
$apiUrl = 'http://5.202.253.246:9880//api/v4/reports/quick/search';
$apiHeaders = [
    'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
    'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
    'Content-Type: application/json'
];

if (!function_exists('secToHMS')) {
    function secToHMS(int $sec): string {
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('fetchTalkSecondsForRange')) {
    function fetchTalkSecondsForRange(array $numbers, string $from, string $to, string $apiUrl, array $apiHeaders): int {
        if (empty($numbers)) return 0;

        $mh  = curl_multi_init();
        $chs = [];

        foreach ($numbers as $num) {
            // OUTGOING
            $postOut = [
                "conditions" => ["dst" => (string)$num, "type" => "outgoing"],
                "date_range" => ["from" => $from, "to" => $to],
                "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                "alike"      => "true"
            ];
            $ch1 = curl_init();
            curl_setopt_array($ch1, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postOut),
                CURLOPT_HTTPHEADER => $apiHeaders,
                CURLOPT_TIMEOUT => 20,
            ]);
            curl_multi_add_handle($mh, $ch1);
            $chs[] = ['h' => $ch1, 'dir' => 'out'];

            // INCOMING
            $postIn = [
                "conditions" => ["src" => (string)$num, "type" => "incoming"],
                "date_range" => ["from" => $from, "to" => $to],
                "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                "alike"      => "true"
            ];
            $ch2 = curl_init();
            curl_setopt_array($ch2, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($postIn),
                CURLOPT_HTTPHEADER => $apiHeaders,
                CURLOPT_TIMEOUT => 20,
            ]);
            curl_multi_add_handle($mh, $ch2);
            $chs[] = ['h' => $ch2, 'dir' => 'in'];
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0 && $status == CURLM_OK);

        $total = 0;
        foreach ($chs as $entry) {
            $ch  = $entry['h'];
            $dir = $entry['dir'];
            $resp = curl_multi_getcontent($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($http >= 200 && $http < 300 && $resp) {
                $data = json_decode($resp, true);
                $rows = $data['data']['data'] ?? ($data['data'] ?? []);
                foreach ($rows as $r) {
                    $disp = strtoupper(trim((string)($r['disposition'] ?? '')));
                    if ($disp !== 'ANSWERED') continue;

                    if ($dir === 'out') {
                        // OUTGOING answered -> use duration
                        $sec = (int)($r['duration'] ?? 0);
                    } else {
                        // INCOMING answered -> use billsec
                        $sec = (int)($r['billsec'] ?? 0);
                    }
                    $total += max(0, $sec);
                }
            }
        }

        curl_multi_close($mh);
        return $total;
    }
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

// -------------------- POST HANDLER: student-status (نمرات و نظرات) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student-status'])) {

    $student_id         = trim($_POST['student_id'] ?? '');
    $avg_first          = trim($_POST['avg_first_semester'] ?? '');
    $avg_second         = trim($_POST['avg_second_semester'] ?? '');
    $student_comment    = trim($_POST['student_comment'] ?? '');
    $supporter_comment  = trim($_POST['supporter_comment'] ?? '');
    $history_year       = isset($_POST['history_year']) ? (int)$_POST['history_year'] : 0;

    // validation
    $errors_status = [];
    if (!$student_id) {
        $errors_status[] = "شناسه دانش آموز الزامی است";
    }

    // حداقل یکی از فیلدها باید پر باشد
    if (
        $avg_first === '' &&
        $avg_second === '' &&
        $student_comment === '' &&
        $supporter_comment === ''
    ) {
        $errors_status[] = "حداقل یکی از فیلدهای معدل یا نظر را وارد کنید.";
    }

    if (empty($errors_status)) {

        // --- build created_at with same Jalali month/day/time but accordion year ---
        $now   = new DateTime('now');
        $tsNow = $now->getTimestamp();

        $currentJYear  = (int) jdate('Y', $tsNow, '', '', 'en');
        $currentJMonth = (int) jdate('m', $tsNow, '', '', 'en');
        $currentJDay   = (int) jdate('d', $tsNow, '', '', 'en');

        $targetJYear  = $history_year ?: $currentJYear;
        $targetJMonth = $currentJMonth;
        $targetJDay   = $currentJDay;

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

        $sql = "INSERT INTO student_status
                (student_id, avg_first_semester, avg_second_semester, student_comment, supporter_comment, created_at)
                VALUES
                (:student_id, :avg_first_semester, :avg_second_semester, :student_comment, :supporter_comment, :created_at)";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':student_id', $student_id);
        $stmt->bindValue(':avg_first_semester', $avg_first !== '' ? $avg_first : null);
        $stmt->bindValue(':avg_second_semester', $avg_second !== '' ? $avg_second : null);
        $stmt->bindValue(':student_comment', $student_comment !== '' ? $student_comment : null);
        $stmt->bindValue(':supporter_comment', $supporter_comment !== '' ? $supporter_comment : null);
        $stmt->bindValue(':created_at', $createdAt);

        if ($stmt->execute()) {
            $_SESSION['student_status_success'] = 'نمرات و نظرات با موفقیت ثبت شد.';
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            // optional: log error
        }
    } else {
        // optional: store $errors_status in session to show in UI
    }
}


// -------------------- DATA PREP START --------------------

include_once '../../temp.html';

// codemeli comes from URL: student-history.php?Id=XXXXXXXXXX
$codemeli      = isset($_GET['Id']) ? trim($_GET['Id']) : null;
$studentInfo   = null;
$historyByYear = [];
$coursesByYear = [];   // سال‌ها و دوره‌ها
$messagesByYear = [];
$supportByYear  = [];
$numbers         = [];
$hasNumbers      = false;
$callStatsByYear = [];
// ======================================================================
//  IF: Only load data when we actually have a student (codemeli) in URL
// ======================================================================
if ($codemeli !== null && $codemeli !== '') {


    // Collect student phone numbers (for Simotel talk-time)
    $personal = retPerson($codemeli);
    if ($personal) {
        $numbers = array_values(array_unique(array_filter([
            $personal->tel1 ?? '',
            $personal->tel2 ?? '',
            $personal->tel3 ?? '',
            $personal->tel_home ?? '',
        ], function ($v) {
            return trim($v) !== '';
        })));
        $hasNumbers = !empty($numbers);
    }

    // -------------------------------------------------------------
    // 1) SUPPORT REPORTS (suport_report) → grouped into $supportByYear
    // -------------------------------------------------------------
    $sqlSupport = "SELECT * FROM suport_report
                   WHERE username = ?
                   ORDER BY datenow DESC, timenow DESC";
    $stmt = $conn->prepare($sqlSupport);
    $stmt->execute([$codemeli]);
    $supportRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($supportRows as $row) {
        if (empty($row['datenow'])) {
            continue;
        }

        // datenow: 'Y-m-d', timenow: 'H:i:s'
        $dateStr = $row['datenow'];
        $timeStr = $row['timenow'] ?? '00:00:00';
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts <= 0) {
            continue;
        }

        $jYear  = (int) jdate('Y', $ts, '', '', 'en');
        $jMonth = (int) jdate('m', $ts, '', '', 'en');

        // سال تحصیلی: ۱ تیر (ماه ۴) تا آخر خرداد سال بعد
        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($supportByYear[$academicYear])) {
            $supportByYear[$academicYear] = [];
        }
        $supportByYear[$academicYear][] = $row;
    }
    // ---------------------- END 1) suport_report ----------------------


    // -------------------------------------------------------------
    // 2) CHAT MESSAGES (suport_message) → grouped into $messagesByYear
    // -------------------------------------------------------------
    $sqlMsg = "SELECT * FROM suport_message
               WHERE sender_username = ? OR receiver_username = ?
               ORDER BY message_date DESC, message_time DESC";
    $stmt = $conn->prepare($sqlMsg);
    $stmt->execute([$codemeli, $codemeli]);
    $msgRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($msgRows as $row) {
        if (empty($row['message_date'])) {
            continue;
        }

        // DB: message_date = 'Y-m-d', message_time = 'H:i:s'
        $dateStr = $row['message_date'];
        $timeStr = $row['message_time'] ?? '00:00:00';
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts <= 0) {
            continue;
        }

        // تبدیل به جلالی
        $jYear  = (int) jdate('Y', $ts, '', '', 'en');
        $jMonth = (int) jdate('m', $ts, '', '', 'en');

        // سال تحصیلی: از 1 تیر (ماه 4) تا آخر خرداد (ماه 3 سال بعد)
        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($messagesByYear[$academicYear])) {
            $messagesByYear[$academicYear] = [];
        }
        $messagesByYear[$academicYear][] = $row;
    }
    // ---------------------- END 2) suport_message ----------------------


    // -------------------------------------------------------------
    // 3) MAIN STUDENT INFO (dbpersonal)
    // -------------------------------------------------------------
    $sqlInfo = "SELECT fname, lname, codemeli, city, state, datecreated 
                FROM dbpersonal 
                WHERE codemeli = ? 
                LIMIT 1";
    $stmt    = $conn->prepare($sqlInfo);
    $stmt->execute([$codemeli]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    // ---------------------- END 3) dbpersonal ----------------------


    // -------------------------------------------------------------
    // 4) COURSE STATUS (course_status) → grouped into $historyByYear['course']
    // 5) STUDENT STATUS (student_status) → grouped into $historyByYear['status']
    // -------------------------------------------------------------
    // course_status.student_id & student_status.student_id store codemeli
    $studentId = $codemeli;

    // 4) course_status rows for this student
    $sqlCourse = "SELECT * FROM course_status WHERE student_id = ? ORDER BY created_at DESC";
    $stmt      = $conn->prepare($sqlCourse);
    $stmt->execute([$studentId]);
    $courseRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 5) student_status rows for this student
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
    // ---------------------- END 4 & 5) course_status / student_status ----------------------


    // -------------------------------------------------------------
    // 6) COURSES (dbcourse INNER JOIN codtbl_course) → $coursesByYear
    // -------------------------------------------------------------
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
    // ---------------------- END 6) coursesByYear ----------------------

} // ======================== END IF $codemeli NOT EMPTY ========================

// ======================================================================
// BUILD $years ARRAY (union of all years from all 4 buckets)
// ======================================================================
$years = [];
if (!empty($historyByYear) || !empty($coursesByYear) || !empty($messagesByYear) || !empty($supportByYear)) {
    $years = array_unique(array_merge(
        array_keys($historyByYear),
        array_keys($coursesByYear),
        array_keys($messagesByYear),
        array_keys($supportByYear)
    ));
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


if (!empty($years) && $hasNumbers) {
    foreach ($years as $academicYear) {
        $academicYear = (int)$academicYear;

        // Jalali: 1 Tir this year
        $gStart = jalali_to_gregorian($academicYear, 4, 1);
        // Jalali: 31 Khordad next year
        $gEnd   = jalali_to_gregorian($academicYear + 1, 3, 31);

        $startG = sprintf('%04d-%02d-%02d 00:00:00', $gStart[0], $gStart[1], $gStart[2]);
        $endG   = sprintf('%04d-%02d-%02d 23:59:59', $gEnd[0],   $gEnd[1],   $gEnd[2]);

        $seconds = fetchTalkSecondsForRange($numbers, $startG, $endG, $apiUrl, $apiHeaders);
        $hms     = secToHMS($seconds);

        // Jalali labels to display
        $startJ = sprintf('%04d/%02d/%02d', $academicYear, 4, 1);
        $endJ   = sprintf('%04d/%02d/%02d', $academicYear + 1, 3, 31);

        $callStatsByYear[$academicYear] = [
            'seconds' => $seconds,
            'hms'     => $hms,
            'startJ'  => $startJ,
            'endJ'    => $endJ,
        ];
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
        <?php if (!empty($_SESSION['student_status_success'])): ?>
            <div class="alert alert-success text-center mt-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['student_status_success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['student_status_success']); ?>
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
                            $yearMessages = $messagesByYear[$year] ?? [];
                            $yearSupport  = $supportByYear[$year]  ?? [];
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
                                    <div class="accordion-body" style="background-color: #FDFCF7;">
                                        <!-- Exams + comments row -->
                                        <div class="row">

                                            <!-- Exams list -->
                                            <div class="col-md-6 mb-3" >
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

                                        <div class="mb-2 d-flex justify-content-between">
                                            <button type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#courseStatusModal"
                                                    data-history-year="<?php echo (int)$year; ?>">
                                                افزودن وضعیت آزمون
                                            </button>

                                            <button type="button"
                                                    class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#studentStatusModal"
                                                    data-history-year="<?php echo (int)$year; ?>">
                                                افزودن نمرات و نظرات
                                            </button>
                                        </div>
                                        <!-- Talk time for this academic year -->
                                        <?php
                                        $callStatsThisYear = $callStatsByYear[$year] ?? null;
                                        ?>
                                        <div class="mt-3">
                                            <?php if (!$hasNumbers): ?>
                                                <div class="alert alert-warning mb-2">
                                                    برای این دانش آموز هیچ شمارهٔ معتبری ثبت نشده است.
                                                </div>
                                            <?php elseif ($callStatsThisYear): ?>
                                                <div class="alert alert-secondary mb-2" style="direction:rtl">
                                                    <strong>زمان مکالمه این سال تحصیلی</strong>
                                                    <small class="text-muted">
                                                        (<?= htmlspecialchars($callStatsThisYear['startJ'], ENT_QUOTES, 'UTF-8') ?>
                                                        —
                                                        <?= htmlspecialchars($callStatsThisYear['endJ'],   ENT_QUOTES, 'UTF-8') ?>)
                                                    </small>
                                                    :
                                                    <span class="badge bg-primary">
                                                        <?= htmlspecialchars($callStatsThisYear['hms'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        (<?= number_format($callStatsThisYear['seconds']) ?> ثانیه)
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-secondary mb-2">
                                                    برای این سال تحصیلی گزارشی از تماس‌ها یافت نشد.
                                                </div>
                                            <?php endif; ?>
                                        </div>
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
                                            <?php
                                            // پیام‌های این سال تحصیلی + گزارشات این سال تحصیلی
                                            $yearMessages = $messagesByYear[$year] ?? [];
                                            $yearSupport  = $supportByYear[$year]  ?? [];
                                            ?>

                                            <hr class="my-3">

                                            <div class="row">
                                                <!-- ستون گفتگوهای این سال -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        گفتگوهای این سال
                                                    </h6>

                                                    <?php if (!empty($yearMessages)): ?>
                                                        <div class="card custom-card">
                                                            <div class="card-body" style="max-height:300px; overflow-y:auto; direction: rtl; text-align: right;">

                                                                <?php foreach ($yearMessages as $msg): ?>
                                                                    <?php
                                                                    $isAdmin    = ($msg['sender_type'] === 'admin');
                                                                    $alignClass = $isAdmin ? 'media' : 'media flex-row-reverse';
                                                                    $text       = htmlspecialchars($msg['message_text'] ?? '', ENT_QUOTES, 'UTF-8');

                                                                    // تاریخ / ساعت (اولویت با ویرایش‌شده)
                                                                    $baseDate = !empty($msg['edit_message_date']) ? $msg['edit_message_date'] : $msg['message_date'];
                                                                    $baseTime = !empty($msg['edit_message_time']) ? $msg['edit_message_time'] : $msg['message_time'];
                                                                    $jLabel   = '';

                                                                    if (!empty($baseDate)) {
                                                                        $tsm = strtotime($baseDate . ' ' . $baseTime);
                                                                        if ($tsm > 0) {
                                                                            $jLabel = jdate('H:i | Y/m/d', $tsm);
                                                                        }
                                                                    }

                                                                    // نمایش نام فرستنده (مثل صفحه پشتیبانی)
                                                                    $senderLabel = '';
                                                                    if ($isAdmin) {
                                                                        $adminObj = retAdmin($msg['sender_username']);
                                                                        if ($adminObj && !empty($adminObj->dakheli)) {
                                                                            switch ($adminObj->dakheli) {
                                                                                case '201': $senderLabel = 'اپراتور ارشد 1'; break;
                                                                                case '500': $senderLabel = 'مدیریت موسسه'; break;
                                                                                case '220': $senderLabel = 'اپراتور ارشد 2'; break;
                                                                                case '501': $senderLabel = 'مهندس بلالی'; break;
                                                                                default:    $senderLabel = 'اپراتور ' . $adminObj->dakheli; break;
                                                                            }
                                                                        }
                                                                    } else {
                                                                        $senderLabel = 'دانش‌آموز';
                                                                    }
                                                                    ?>

                                                                    <div class="<?php echo $alignClass; ?> mb-2">
                                                                        <div class="main-img-user online">
                                                                            <?php if ($msg['sender_type'] === 'student'): ?>
                                                                                <img alt="پروفایل"
                                                                                     src="<?= assest('/assets/img/users/1.jpg') ?>"
                                                                                     class="my-2">
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <div class="main-msg-wrapper ml-3">
                                                                            <?php if ($senderLabel): ?>
                                                                                <div class="text-muted small mb-1">
                                                                                    <?php echo $senderLabel; ?>
                                                                                </div>
                                                                            <?php endif; ?>

                                                                            <div class="media-body mr-3">
                                                                                <span><?php echo $text; ?></span>
                                                                            </div>

                                                                            <div class="mt-1">
                                                                                <?php if (!empty($msg['file_path'])): ?>
                                                                                    <a href="<?php echo url('Support/attachments.php') . '?fileChat=' . urlencode($msg['id']); ?>"
                                                                                       title="دانلود فایل بارگذاری شده">
                                                                                        <i class="fe fe-paperclip"></i>
                                                                                    </a>
                                                                                <?php endif; ?>

                                                                                <?php if ($jLabel): ?>
                                                                                    <span class="text-muted" style="font-size: 11px;">
                                                                                        <?php echo htmlspecialchars($jLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                                        <?php if (!empty($msg['edit_message_date'])): ?>
                                                                                            (ویرایش‌شده)
                                                                                        <?php endif; ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                <?php endforeach; ?>

                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted mb-0" style="font-size: 13px;">
                                                            در این سال گفتگویی ثبت نشده است.
                                                        </p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- ستون سوابق گزارشات ثبت شده -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="border-bottom pb-2 mb-3">
                                                        سوابق گزارشات ثبت شده
                                                    </h6>

                                                    <?php if (!empty($yearSupport)): ?>
                                                        <div class="card custom-card">
                                                            <div class="card-body" style="max-height:300px; overflow-y:auto; direction: rtl; text-align: right;">
                                                                <div class="table-responsive table-hover">
                                                                    <table class="table table-sm mb-0">
                                                                        <thead>
                                                                        <tr>
                                                                            <th class="bg-light text-center wd-5"><small>#</small></th>
                                                                            <th class="bg-light text-center">تاریخ پشتیبانی</th>
                                                                            <th class="bg-light text-center"><small class="ml-1">پشتیبان</small></th>
                                                                            <th class="bg-light text-center">نوع</th>
                                                                            <th class="bg-light text-center">وضعیت</th>
                                                                            <th class="bg-light text-center">شرح</th>
                                                                        </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                        <?php
                                                                        $is = 1;
                                                                        foreach ($yearSupport as $report):
                                                                            // تبدیل datesupport از میلادی به جلالی
                                                                            $after = '';
                                                                            if (!empty($report['datesupport'])) {
                                                                                $parts = explode('-', $report['datesupport']);
                                                                                if (count($parts) === 3) {
                                                                                    [$gy, $gm, $gd] = $parts;
                                                                                    [$jy, $jm, $jd] = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
                                                                                    $after = $jy . '/' . $jm . '/' . $jd;
                                                                                }
                                                                            }

                                                                            $poshtiban = htmlspecialchars($report['poshtiban'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                            $type      = htmlspecialchars($report['type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                            $status    = htmlspecialchars($report['status'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                            $detail    = htmlspecialchars($report['detail'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                            ?>
                                                                            <tr>
                                                                                <td class="text-center"><?php echo $is; ?></td>
                                                                                <td class="text-center"><?php echo $after ?: '-'; ?></td>
                                                                                <td class="text-center"><?php echo $poshtiban ?: '-'; ?></td>
                                                                                <td class="text-center"><?php echo $type ?: '-'; ?></td>
                                                                                <td class="text-center"><?php echo $status ?: '-'; ?></td>
                                                                                <td><?php echo nl2br($detail); ?></td>
                                                                            </tr>
                                                                            <?php
                                                                            $is++;
                                                                        endforeach;
                                                                        ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted mb-0" style="font-size: 13px;">
                                                            در این سال گزارشی ثبت نشده است.
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Modal: Add student_status (نمرات و نظرات) -->
<div class="modal fade" id="studentStatusModal" tabindex="-1" aria-labelledby="studentStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="studentStatusModalLabel">افزودن نمرات و نظرات</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>

            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="student_id"
                           value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

                    <!-- year of accordion (Jalali) -->
                    <input type="hidden" name="history_year" id="history_year_status" value="">

                    <div class="mb-3">
                        <label for="avg_first_semester" class="form-label" style="color:#222;">معدل ترم اول</label>
                        <input type="text"
                               class="form-control"
                               id="avg_first_semester"
                               name="avg_first_semester"
                               placeholder="مثال: 18.25">
                    </div>

                    <div class="mb-3">
                        <label for="avg_second_semester" class="form-label" style="color:#222;">معدل ترم دوم</label>
                        <input type="text"
                               class="form-control"
                               id="avg_second_semester"
                               name="avg_second_semester"
                               placeholder="مثال: 17.80">
                    </div>

                    <div class="mb-3">
                        <label for="student_comment" class="form-label" style="color:#222;">نظر دانش‌آموز</label>
                        <textarea class="form-control"
                                  id="student_comment"
                                  name="student_comment"
                                  rows="3"
                                  placeholder="نظر دانش‌آموز را وارد کنید"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="supporter_comment" class="form-label" style="color:#222;">نظر پشتیبان</label>
                        <textarea class="form-control"
                                  id="supporter_comment"
                                  name="supporter_comment"
                                  rows="3"
                                  placeholder="نظر پشتیبان را وارد کنید"></textarea>
                    </div>

                    <button type="submit" name="student-status" class="btn btn-primary w-100">ثبت</button>
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

    document.addEventListener('DOMContentLoaded', function () {
        var studentStatusModal = document.getElementById('studentStatusModal');
        if (!studentStatusModal) return;

        studentStatusModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var year = button.getAttribute('data-history-year');
            var yearInput = document.getElementById('history_year_status');

            if (yearInput && year) {
                yearInput.value = year;
            }

            var modalTitle = studentStatusModal.querySelector('.modal-title');
            if (modalTitle && year) {
                modalTitle.textContent = 'افزودن نمرات و نظرات - سال ' + year;
            }
        });
    });
</script>

</body>
</html>
