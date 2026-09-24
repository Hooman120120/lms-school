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

$apiUrl = 'http://' . SIMOTEL_SERVER . '//api/v4/reports/quick/search';
$apiHeaders = [
    'X-APIKEY: ' . SIMOTEL_API_KEY,
    'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
    'Content-Type: application/json'
];

if (!function_exists('secToHMS')) {
    function secToHMS(int $sec): string
    {
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('fetchTalkSecondsForRange')) {
    function fetchTalkSecondsForRange(array $numbers, string $from, string $to, string $apiUrl, array $apiHeaders): int
    {
        if (empty($numbers)) return 0;

        $mh = curl_multi_init();
        $chs = [];

        foreach ($numbers as $num) {
            // OUTGOING
            $postOut = [
                "conditions" => ["dst" => (string)$num, "type" => "outgoing"],
                "date_range" => ["from" => $from, "to" => $to],
                "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                "alike" => "true"
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
                "alike" => "true"
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
            $ch = $entry['h'];
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
                        $sec = (int)($r['duration'] ?? 0);
                    } else {
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

// -------------------- POST HANDLER: course-status --------------------
if (Permision('history_permission')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course-status'])) {

        if (!isset($_POST['token']) || $_SESSION['token'] !== $_POST['token']) {
            http_response_code(403);
            die('خطای امنیتی');
        }

        $student_id = trim($_POST['student_id'] ?? '');
        $exam_type = trim($_POST['exam_type'] ?? '');
        $registered = trim($_POST['registered'] ?? '');
        $attended = trim($_POST['attended'] ?? '');
        $not_registered_reason = trim($_POST['not_registered_reason'] ?? '');
        $result = trim($_POST['result'] ?? '');
        $history_year = isset($_POST['history_year']) ? (int)$_POST['history_year'] : 0;

        $errors = [];
        if (!$student_id) $errors[] = m('err.student_id_required');
        if (!$exam_type) $errors[] = "نوع آزمون الزامی است";
        if (!$registered) $errors[] = "وضعیت ثبت نام الزامی است";

        if ($registered === 'ثبت نام کرده') {
            if (!$attended) {
                $errors[] = "وضعیت آزمون الزامی است";
            }

            if ($attended === 'آزمون داده') {
                if (!$result) {
                    $errors[] = "نتیجه آزمون الزامی است";
                }
            } else {
                $result = null;
            }

            $not_registered_reason = null;
        } else {
            if (!$not_registered_reason) $errors[] = "دلیل ثبت نام نکردن الزامی است";
            $attended = null;
            $result = null;
        }

        if (empty($errors)) {
            $now = new DateTime('now');
            $tsNow = $now->getTimestamp();

            $currentJYear = (int)jdate('Y', $tsNow, '', '', 'en');
            $currentJMonth = (int)jdate('m', $tsNow, '', '', 'en');
            $currentJDay = (int)jdate('d', $tsNow, '', '', 'en');

            $targetJYear = $history_year ?: $currentJYear;
            $targetJMonth = $currentJMonth;
            $targetJDay = $currentJDay;

            $createdAt = $now->format('Y-m-d H:i:s');
            try {
                $g = jalali_to_gregorian($targetJYear, $targetJMonth, $targetJDay);
                if (is_array($g) && count($g) >= 3) {
                    $gYear = (int)$g[0];
                    $gMonth = (int)$g[1];
                    $gDay = (int)$g[2];
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
                $_SESSION['course_status_success'] = 'وضعیت آزمون با موفقیت اضافه شد.';
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
    }
}

if (Permision('history_permission')) {
    if (isset($_GET['delm'], $_GET['Id'])) {
        $msgId = (int)$_GET['delm'];
        $stdIdDel = $_GET['Id'];

        $select = "SELECT * FROM suport_message 
               WHERE id = ? AND (sender_username = ? OR receiver_username = ?)";
        $query = $conn->prepare($select);
        $query->execute([$msgId, $stdIdDel, $stdIdDel]);
        $message = $query->fetch(PDO::FETCH_OBJ);

        if ($message) {
            if (!empty($message->file_path)) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }

            $sql = "DELETE FROM suport_message WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$msgId]);
        }

        $self = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $self . '?Id=' . urlencode($stdIdDel));
        exit;
    }
}

// -------------------- POST HANDLER: student-status --------------------
if (Permision('history_permission')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student-status'])) {

        $student_id = trim($_POST['student_id'] ?? '');
        $avg_first = trim($_POST['avg_first_semester'] ?? '');
        $avg_second = trim($_POST['avg_second_semester'] ?? '');
        $student_comment = trim($_POST['student_comment'] ?? '');
        $supporter_comment = trim($_POST['supporter_comment'] ?? '');
        $history_year = isset($_POST['history_year']) ? (int)$_POST['history_year'] : 0;

        $errors_status = [];
        if (!$student_id) {
            $errors_status[] = m('err.student_id_required');
        }

        if (
            $avg_first === '' &&
            $avg_second === '' &&
            $student_comment === '' &&
            $supporter_comment === ''
        ) {
            $errors_status[] = "حداقل یکی از فیلدهای معدل یا نظر را وارد کنید.";
        }

        if (empty($errors_status)) {
            $now = new DateTime('now');
            $tsNow = $now->getTimestamp();

            $currentJYear = (int)jdate('Y', $tsNow, '', '', 'en');
            $currentJMonth = (int)jdate('m', $tsNow, '', '', 'en');
            $currentJDay = (int)jdate('d', $tsNow, '', '', 'en');

            $targetJYear = $history_year ?: $currentJYear;
            $targetJMonth = $currentJMonth;
            $targetJDay = $currentJDay;

            $createdAt = $now->format('Y-m-d H:i:s');
            try {
                $g = jalali_to_gregorian($targetJYear, $targetJMonth, $targetJDay);
                if (is_array($g) && count($g) >= 3) {
                    $gYear = (int)$g[0];
                    $gMonth = (int)$g[1];
                    $gDay = (int)$g[2];
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
            }
        }
    }
}

// -------------------- DATA PREP START --------------------
include_once '../../temp.html';

$codemeli = isset($_GET['Id']) ? trim($_GET['Id']) : null;
$studentInfo = null;
$historyByYear = [];
$coursesByYear = [];
$messagesByYear = [];
$supportByYear = [];
$numbers = [];
$hasNumbers = false;
$callStatsByYear = [];
$answeredEvents = [];

if ($codemeli !== null && $codemeli !== '') {
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

    // 1) SUPPORT REPORTS
    $sqlSupport = "SELECT * FROM suport_report WHERE username = ? ORDER BY datenow DESC, timenow DESC";
    $stmt = $conn->prepare($sqlSupport);
    $stmt->execute([$codemeli]);
    $supportRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($supportRows as $row) {
        if (empty($row['datenow'])) continue;

        $dateStr = $row['datenow'];
        $timeStr = $row['timenow'] ?? '00:00:00';
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($supportByYear[$academicYear])) {
            $supportByYear[$academicYear] = [];
        }
        $supportByYear[$academicYear][] = $row;
    }

    // 2) CHAT MESSAGES
    $sqlMsg = "SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ? ORDER BY message_date DESC, message_time DESC";
    $stmt = $conn->prepare($sqlMsg);
    $stmt->execute([$codemeli, $codemeli]);
    $msgRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($msgRows as $row) {
        if (empty($row['message_date'])) continue;

        $dateStr = $row['message_date'];
        $timeStr = $row['message_time'] ?? '00:00:00';
        $ts = strtotime($dateStr . ' ' . $timeStr);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($messagesByYear[$academicYear])) {
            $messagesByYear[$academicYear] = [];
        }
        $messagesByYear[$academicYear][] = $row;
    }

    // 3) MAIN STUDENT INFO
    $sqlInfo = "SELECT fname, lname, codemeli, city, state, datecreated FROM dbpersonal WHERE codemeli = ? LIMIT 1";
    $stmt = $conn->prepare($sqlInfo);
    $stmt->execute([$codemeli]);
    $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $callsByYear = [];
    if (!empty($codemeli)) {
        $queryCalls = "SELECT * FROM calls WHERE codemeli = ? ORDER BY created_at DESC";
        $stmtCalls = $conn->prepare($queryCalls);
        $stmtCalls->execute([$codemeli]);
        $calls = $stmtCalls->fetchAll(PDO::FETCH_ASSOC);

        foreach ($calls as $call) {
            $ts = strtotime($call['created_at']);
            $jYear = (int)jdate('Y', $ts, '', '', 'en');
            $jMonth = (int)jdate('m', $ts, '', '', 'en');

            $academicYear = ($jMonth >= 4) ? $jYear : ($jYear - 1);
            $callsByYear[$academicYear][] = $call;
        }
    }

    // 4) COURSE & STUDENT STATUS
    $studentId = $codemeli;

    $sqlCourse = "SELECT * FROM course_status WHERE student_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sqlCourse);
    $stmt->execute([$studentId]);
    $courseRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $sqlStatus = "SELECT * FROM student_status WHERE student_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sqlStatus);
    $stmt->execute([$studentId]);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($courseRows as $row) {
        if (empty($row['created_at'])) continue;

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($historyByYear[$academicYear])) {
            $historyByYear[$academicYear] = ['course' => [], 'status' => []];
        }
        $historyByYear[$academicYear]['course'][] = $row;
    }

    foreach ($statusRows as $row) {
        if (empty($row['created_at'])) continue;

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($historyByYear[$academicYear])) {
            $historyByYear[$academicYear] = ['course' => [], 'status' => []];
        }
        $historyByYear[$academicYear]['status'][] = $row;
    }

    // 6) COURSES
    $swr = 'SELECT dbcourse.*, codtbl_course.*, codtbl_course.datacreated AS course_date
            FROM dbcourse
            INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
            WHERE dbcourse.username = ?
            ORDER BY codtbl_course.datacreated DESC';

    $wer = $conn->prepare($swr);
    $wer->execute([$codemeli]);
    $recive = $wer->fetchAll(PDO::FETCH_OBJ);

    if ($wer->rowCount() > 0) {
        foreach ($recive as $res) {
            $regDate = $res->course_date ?? null;
            if (empty($regDate)) continue;

            $ts = strtotime($regDate);
            if ($ts <= 0) continue;

            $jYear = (int)jdate('Y', $ts, '', '', 'en');
            $jMonth = (int)jdate('m', $ts, '', '', 'en');

            $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

            if (!isset($coursesByYear[$academicYear])) {
                $coursesByYear[$academicYear] = [];
            }
            $coursesByYear[$academicYear][] = $res;
        }
    }
}

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
            $historyByYear[$y] = ['course' => [], 'status' => []];
        }
    }
}

if (!empty($years) && $hasNumbers) {
    foreach ($years as $academicYear) {
        $academicYear = (int)$academicYear;

        $gStart = jalali_to_gregorian($academicYear, 4, 1);
        $gEnd = jalali_to_gregorian($academicYear + 1, 3, 31);

        $startG = sprintf('%04d-%02d-%02d 00:00:00', $gStart[0], $gStart[1], $gStart[2]);
        $endG = sprintf('%04d-%02d-%02d 23:59:59', $gEnd[0], $gEnd[1], $gEnd[2]);

        $seconds = fetchTalkSecondsForRange($numbers, $startG, $endG, $apiUrl, $apiHeaders);
        $hms = secToHMS($seconds);

        $startJ = sprintf('%04d/%02d/%02d', $academicYear, 4, 1);
        $endJ = sprintf('%04d/%02d/%02d', $academicYear + 1, 3, 31);

        $callStatsByYear[$academicYear] = [
            'seconds' => $seconds,
            'hms' => $hms,
            'startJ' => $startJ,
            'endJ' => $endJ,
        ];
    }
}

// DELETE HANDLERS
if (Permision('history_permission')) {
    if (isset($_GET['del_course'], $_GET['Id'])) {
        $courseId = (int)$_GET['del_course'];
        $stdIdDel = trim($_GET['Id']);

        $sql = "DELETE FROM course_status WHERE id = ? AND student_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$courseId, $stdIdDel]);

        $self = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $self . '?Id=' . urlencode($stdIdDel));
        exit;
    }
}

if (Permision('history_permission')) {
    if (isset($_GET['del_status'], $_GET['Id'])) {
        $statusId = (int)$_GET['del_status'];
        $stdIdDel = trim($_GET['Id']);

        $sql = "DELETE FROM student_status WHERE id = ? AND student_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$statusId, $stdIdDel]);

        $self = basename($_SERVER['PHP_SELF']);
        header('Location: ' . $self . '?Id=' . urlencode($stdIdDel));
        exit;
    }
}

// 7) ANSWERED ACTIVE EVENTS
if (!empty($codemeli)) {
    $sqlAnswered = "
        SELECT 
            a.event_id AS answer_id,
            a.event_id,
            a.option_id,
            a.reason,
            a.created_by,
            a.created_at,
            e.title AS event_title,
            e.start_date,
            e.end_date,
            o.events_option AS option_text,
            o.needs_reason
        FROM active_event_answers a
        INNER JOIN calendar_events e ON a.event_id = e.id
        INNER JOIN active_event_options o ON a.option_id = o.id
        WHERE a.username = ?
        ORDER BY a.created_at DESC
    ";
    $stmtAnswered = $conn->prepare($sqlAnswered);
    $stmtAnswered->execute([$codemeli]);
    $rowsAnswered = $stmtAnswered->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rowsAnswered as $row) {
        $startDate = $row['start_date'] ?? null;
        if (empty($startDate)) continue;

        $ts = strtotime($startDate);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');
        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($answeredEvents[$academicYear])) {
            $answeredEvents[$academicYear] = [];
        }
        $answeredEvents[$academicYear][] = $row;
    }
}

if (!empty($answeredEvents)) {
    $years = array_unique(array_merge($years, array_keys($answeredEvents)));
    rsort($years);
}

// POST HANDLER: Edit Answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_answer') {
    $debugLog = __DIR__ . '/edit_answer_debug.log';
    $logPrefix = date('Y-m-d H:i:s') . ' [IP: ' . GetIp() . '] [User: ' . ($_SESSION['admin']['user'] ?? 'unknown') . ']';

    $postCopy = $_POST;
    unset($postCopy['token']);
    error_log($logPrefix . " POST data: " . print_r($postCopy, true) . "\n", 3, $debugLog);

    if (!Permision('history_permission')) {
        http_response_code(403);
        die('دسترسی غیرمجاز');
    }

    if (!isset($_POST['token']) || !isset($_SESSION['token']) || $_SESSION['token'] !== $_POST['token']) {
        http_response_code(403);
        die('خطای امنیتی');
    }

    $answerId = isset($_POST['edit_answer_id']) ? (int)$_POST['edit_answer_id'] : 0;
    $newOptionId = isset($_POST['edit_option_id']) ? (int)$_POST['edit_option_id'] : 0;
    $newReason = trim($_POST['edit_reason'] ?? '');
    $studentId = isset($_GET['Id']) ? trim($_GET['Id']) : '';

    if (!$answerId || !$newOptionId || empty($studentId)) {
        $_SESSION['edit_answer_error'] = 'اطلاعات ناقص.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $checkStmt = $conn->prepare("SELECT username FROM active_event_answers WHERE event_id = ?");
        $checkStmt->execute([$answerId]);
        $answerRow = $checkStmt->fetch(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        $_SESSION['edit_answer_error'] = 'خطای پایگاه داده.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!$answerRow || $answerRow->username !== $studentId) {
        $_SESSION['edit_answer_error'] = m('err.record_not_owned');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $eventCheck = $conn->prepare("
            SELECT o.id, o.needs_reason, o.events_option
            FROM active_event_options o
            INNER JOIN active_event_answers a ON a.event_id = o.event_id
            WHERE a.event_id = ? AND o.id = ?
        ");
        $eventCheck->execute([$answerId, $newOptionId]);
        $newOption = $eventCheck->fetch(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        $_SESSION['edit_answer_error'] = 'خطای پایگاه داده.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!$newOption) {
        $_SESSION['edit_answer_error'] = 'گزینه انتخاب‌شده برای این رویداد معتبر نیست.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($newOption->needs_reason && empty($newReason)) {
        $_SESSION['edit_answer_error'] = 'این گزینه نیاز به توضیح دارد.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    try {
        $operator = $_SESSION['admin']['user'];
        $updateStmt = $conn->prepare("
            UPDATE active_event_answers 
            SET option_id = ?, reason = ?, created_by = ?, created_at = NOW() 
            WHERE event_id = ?
        ");
        $updateResult = $updateStmt->execute([$newOptionId, $newReason, $operator, $answerId]);

        if ($updateResult) {
            $_SESSION['edit_answer_success'] = 'پاسخ با موفقیت ویرایش شد.';
        } else {
            $_SESSION['edit_answer_error'] = 'خطا در اجرای به‌روزرسانی.';
        }
    } catch (Exception $e) {
        $_SESSION['edit_answer_error'] = 'خطا در ویرایش پاسخ: ' . $e->getMessage();
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$supporterComments = [];
if (!empty($codemeli)) {
    $stmtComments = $conn->prepare("
        SELECT comment, created_by, created_at 
        FROM student_supporter_comments 
        WHERE codemeli = :codemeli 
        ORDER BY created_at DESC
    ");
    $stmtComments->execute([':codemeli' => $codemeli]);
    $supporterComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

    foreach ($supporterComments as $commentRow) {
        if (empty($commentRow['created_at'])) continue;

        $ts = strtotime($commentRow['created_at']);
        if ($ts <= 0) continue;

        $jYear = (int)jdate('Y', $ts, '', '', 'en');
        $jMonth = (int)jdate('m', $ts, '', '', 'en');

        $academicYear = ($jMonth >= 4) ? $jYear : $jYear - 1;

        if (!isset($historyByYear[$academicYear]['supporter_comments'])) {
            $historyByYear[$academicYear]['supporter_comments'] = [];
        }
        $historyByYear[$academicYear]['supporter_comments'][] = $commentRow;
    }
}

$allOptions = [];
$optStmt = $conn->prepare("SELECT id, event_id, events_option, needs_reason FROM active_event_options ORDER BY event_id, id");
$optStmt->execute();
while ($optRow = $optStmt->fetch(PDO::FETCH_ASSOC)) {
    $allOptions[$optRow['event_id']][] = $optRow;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <title><?php echo m('page.student_history') ?> | <?php echo __TITLE; ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <div class="container-fluid">

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
        <?php if (!empty($_SESSION['edit_answer_success'])): ?>
            <div class="alert alert-success text-center mt-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['edit_answer_success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['edit_answer_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['edit_answer_error'])): ?>
            <div class="alert alert-danger text-center mt-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['edit_answer_error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php unset($_SESSION['edit_answer_error']); ?>
        <?php endif; ?>

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
                        <div class="d-flex flex-row card-body d-flex flex-wrap justify-content-between"
                             style="direction: rtl; text-align: right;">
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

        <div class="row mt-2 mb-3">
            <div class="col-12">

                <h4 class="mb-1"><?php echo m('page.student_history') ?></h4>
                <p class="text-muted mb-3">
                    <?php echo m('label.history_subtitle') ?>
                </p>

                <?php if (!empty($years)): ?>
                    <div class="accordion" id="historyAccordion">
                        <?php
                        $isFirst = true;

                        foreach ($years as $year):
                            $courseRowsYear = $historyByYear[$year]['course'] ?? [];
                            $statusRowsYear = $historyByYear[$year]['status'] ?? [];
                            $yearCourses = $coursesByYear[$year] ?? [];

                            $collapseId = 'collapseYear_' . (int)$year;
                            $headingId = 'headingYear_' . (int)$year;

                            $showClass = $isFirst ? 'show' : '';
                            $ariaExpanded = $isFirst ? 'true' : 'false';
                            $btnCollapsed = $isFirst ? '' : 'collapsed';

                            $examCount = count($courseRowsYear);
                            $statusCount = count($statusRowsYear);
                            $courseCount = count($yearCourses);
                            $yearMessages = $messagesByYear[$year] ?? [];
                            $yearSupport = $supportByYear[$year] ?? [];
                            $yearCalls = $callsByYear[$year] ?? [];
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
                                    $endYear = $startYear + 1;
                                    echo htmlspecialchars($startYear, ENT_QUOTES, 'UTF-8') . '-' . htmlspecialchars($endYear, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </span>
                                        <span class="ms-auto text-muted" style="font-size: 12px;">
                                    آزمون‌ها: <?php echo $examCount; ?> |
                                    وضعیت/نظرات: <?php echo $statusCount; ?> |
                                    <?php echo m('label.courses_colon') ?> <?php echo $courseCount; ?>
                                </span>
                                    </button>
                                </h2>

                                <div id="<?php echo $collapseId; ?>"
                                     class="accordion-collapse collapse <?php echo $showClass; ?>"
                                     aria-labelledby="<?php echo $headingId; ?>">
                                    <div class="accordion-body" style="background-color: #FDFCF7;">
                                        <div class="row">
                                            <!-- Exams list -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3">وضعیت آزمون‌ها</h6>

                                                <?php if (!empty($courseRowsYear)): ?>
                                                    <?php
                                                    $num = 1;
                                                    foreach ($courseRowsYear as $row):
                                                        $exam_type = htmlspecialchars($row['exam_type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $registered = htmlspecialchars($row['registered'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $attended = htmlspecialchars($row['attended'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $not_registered_reason = htmlspecialchars($row['not_registered_reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $result = htmlspecialchars($row['result'] ?? '', ENT_QUOTES, 'UTF-8');

                                                        $createdLabel = '';
                                                        if (!empty($row['created_at'])) {
                                                            $tsc = strtotime($row['created_at']);
                                                            if ($tsc > 0) {
                                                                $createdLabel = jdate('ثبت در تاریخ : ' . 'Y/m/d', $tsc);
                                                            }
                                                        }
                                                        ?>
                                                        <div class="border rounded p-2 mb-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <div>
                                                                    <span class="badge bg-primary ms-1"><?php echo $num; ?></span>
                                                                    <strong><?php echo $exam_type; ?></strong>
                                                                </div>

                                                                <div class="d-flex align-items-center" style="gap:10px;">
                                                                    <?php if ($createdLabel): ?>
                                                                        <span class="text-muted" style="font-size: 12px;">
                                                                    <?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                                    <?php endif; ?>

                                                                    <?php if (Permision('history_permission')): ?>
                                                                        <a href="?Id=<?= htmlspecialchars($_GET['Id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>&del_course=<?= (int)($row['id'] ?? 0); ?>"
                                                                           onclick="return confirm('آیا از حذف این وضعیت آزمون مطمئن هستید؟')"
                                                                           title="حذف">
                                                                            <i class="fe fe-trash-2 text-danger"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
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
                                                <h6 class="border-bottom pb-2 mb-3">نمرات و نظرات</h6>

                                                <?php if (!empty($statusRowsYear)): ?>
                                                    <?php foreach ($statusRowsYear as $row):
                                                        $avg1_display = (!isset($row['avg_first_semester']) || $row['avg_first_semester'] === '')
                                                            ? '-'
                                                            : htmlspecialchars($row['avg_first_semester'], ENT_QUOTES, 'UTF-8');

                                                        $avg2_display = (!isset($row['avg_second_semester']) || $row['avg_second_semester'] === '')
                                                            ? '-'
                                                            : htmlspecialchars($row['avg_second_semester'], ENT_QUOTES, 'UTF-8');

                                                        $student_comment = nl2br(htmlspecialchars($row['student_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
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
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <div class="text-muted">
                                                                    <?php if ($createdStatusLabel): ?>
                                                                        ثبت در تاریخ: <?php echo htmlspecialchars($createdStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                    <?php else: ?>
                                                                        &nbsp;
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (Permision('history_permission')): ?>
                                                                    <a href="?Id=<?= htmlspecialchars($_GET['Id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>&del_status=<?= (int)($row['id'] ?? 0); ?>"
                                                                       onclick="return confirm('آیا از حذف این رکورد نمرات/نظرات مطمئن هستید؟')"
                                                                       title="حذف">
                                                                        <i class="fe fe-trash-2 text-danger"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div class="mb-1">
                                                                <strong>معدل ترم اول:</strong>
                                                                <span><?php echo $avg1_display; ?></span>
                                                            </div>

                                                            <div class="mb-1">
                                                                <strong>معدل ترم دوم:</strong>
                                                                <span><?php echo $avg2_display; ?></span>
                                                            </div>

                                                            <div class="mb-1 d-flex">
                                                                <strong><?php echo m('label.student_comment_colon') ?>&nbsp;</strong>
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

                                            <div class="col-md-12 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3 fw-bold text-secondary">
                                                    <?php echo m('label.student_narrative') ?>
                                                </h6>

                                                <?php $yearSupporterComments = $historyByYear[$year]['supporter_comments'] ?? []; ?>

                                                <?php if (!empty($yearSupporterComments)): ?>
                                                    <?php foreach ($yearSupporterComments as $commentItem):
                                                        $createdLabel = '';
                                                        if (!empty($commentItem['created_at'])) {
                                                            $ts = strtotime($commentItem['created_at']);
                                                            if ($ts > 0) {
                                                                $createdLabel = jdate('Y/m/d H:i', $ts);
                                                            }
                                                        }
                                                        $commentText = nl2br(htmlspecialchars($commentItem['comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                        $createdBy = htmlspecialchars($commentItem['created_by'] ?? 'پشتیبان', ENT_QUOTES, 'UTF-8');
                                                        ?>
                                                        <div class="card mb-3 border-0 bg-light shadow-sm" style="border-right: 4px solid #0d6efd !important;">
                                                            <div class="card-body p-3" style="font-size: 13px;">
                                                                <div class="d-flex justify-content-between align-items-center mb-2 text-muted border-bottom pb-1" style="font-size: 12px;">
                                                                    <span><i class="fe fe-user-check ms-1 text-primary"></i>گزارش: <?= $createdBy; ?></span>
                                                                    <span><i class="fe fe-clock ms-1"></i><?= $createdLabel; ?></span>
                                                                </div>
                                                                <div class="text-dark lh-base">
                                                                    <?= $commentText; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="alert alert-light border text-muted" style="font-size: 13px;">
                                                        <i class="fe fe-info ms-1"></i>شرح حالی از طرف پشتیبان برای این سال تحصیلی ثبت نشده است.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- تاریخچه تماس‌های این سال -->
                                        <div class="row mt-4 p-2">
                                            <div class="col-12">
                                                <h6 class="border-bottom pb-2 mb-3">تاریخچه تماس‌ها</h6>

                                                <?php if (!empty($yearCalls)): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-striped text-center align-middle">
                                                            <thead class="table-light">
                                                            <tr>
                                                                <th>تاریخ و زمان</th>
                                                                <th>جهت تماس</th>
                                                                <th>شماره (مبدا/مقصد)</th>
                                                                <th>داخلی</th>
                                                                <th>وضعیت</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php foreach ($yearCalls as $call): ?>
                                                                <?php
                                                                $callTime = $call['created_at'];
                                                                $answered = $call['answered'] ?? 0;

                                                                $direction = '';
                                                                if (isset($call['direction'])) {
                                                                    if ($call['direction'] === 'in') {
                                                                        $direction = '<span class="text-primary">ورودی <i class="bi bi-arrow-down-left"></i></span>';
                                                                    } elseif ($call['direction'] === 'out') {
                                                                        $direction = '<span class="text-success">خروجی <i class="bi bi-arrow-up-right"></i></span>';
                                                                    }
                                                                }

                                                                if ($answered == 1) {
                                                                    $badge = '<span class="badge bg-success">پاسخ داده شده</span>';
                                                                } else {
                                                                    $badge = '<span class="badge bg-danger">عدم پاسخ/خطا</span>';
                                                                }
                                                                ?>
                                                                <tr>
                                                                    <td class="dir-ltr text-end">
                                                                        <?php
                                                                        if ($callTime && $callTime !== '0000-00-00 00:00:00') {
                                                                            echo jdate('Y/m/d H:i', strtotime($callTime));
                                                                        } else {
                                                                            echo '<span class="badge bg-warning text-dark">درحال ثبت...</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td><?= $direction ?></td>
                                                                    <td class="dir-ltr"><?= htmlspecialchars($call['remote_number'] ?? '') ?></td>
                                                                    <td>
                                                                        <?= (isset($call['exten']) && $call['exten'] == '0') ? 'شماره مجموعه' : htmlspecialchars($call['exten'] ?? '') ?>
                                                                    </td>
                                                                    <td><?= $badge ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-secondary text-center" role="alert">
                                                        تماسی برای این سال تحصیلی ثبت نشده است.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if (Permision('history_permission')) { ?>
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
                                        <?php } ?>

                                        <!-- Talk time -->
                                        <?php $callStatsThisYear = $callStatsByYear[$year] ?? null; ?>
                                        <div class="mt-3">
                                            <?php if (!$hasNumbers): ?>
                                                <div class="alert alert-warning d-flex align-items-center mb-2" style="direction: rtl; font-size: 1rem;">
                                                    <i class="fe fe-alert-triangle ms-2" style="font-size: 1.4rem;"></i>
                                                    <div><?php echo m('msg.no_valid_phone') ?></div>
                                                </div>
                                            <?php elseif ($callStatsThisYear): ?>
                                                <div class="card shadow-sm border-0 mb-3" style="direction: rtl;">
                                                    <div class="card-body py-3 px-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="d-flex align-items-center">
                                                                <div class="ms-3">
                                                                    <div class="fw-bold" style="font-size: 1.05rem;">
                                                                        زمان مکالمه این سال تحصیلی
                                                                    </div>
                                                                    <div class="text-muted" style="font-size: 0.9rem;">
                                                                        (<?= htmlspecialchars($callStatsThisYear['startJ'], ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($callStatsThisYear['endJ'], ENT_QUOTES, 'UTF-8') ?>)
                                                                    </div>
                                                                </div>
                                                                <div class="ms-2">
                                                                    <i class="fe fe-phone-call" style="font-size: 1.8rem;"></i>
                                                                </div>
                                                            </div>

                                                            <div class="text-end">
                                                                <div class="mb-1">
                                                            <span class="badge bg-primary" style="font-size: 1rem; padding: 0.4rem 0.8rem;">
                                                                <?= htmlspecialchars($callStatsThisYear['hms'], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                                </div>
                                                                <div class="text-muted" style="font-size: 0.9rem;">
                                                                    (<?= number_format($callStatsThisYear['seconds']) ?> ثانیه)
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-secondary d-flex align-items-center mb-2" style="direction: rtl; font-size: 1rem;">
                                                    <i class="fe fe-info ms-2" style="font-size: 1.4rem;"></i>
                                                    <div>برای این سال تحصیلی گزارشی از تماس‌ها یافت نشد.</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Courses for this year -->
                                        <?php if (!empty($yearCourses)): ?>
                                            <hr class="my-3">
                                            <h6 class="border-bottom pb-2 mb-2"><?php echo m('label.courses_this_year') ?></h6>

                                            <div class="history-course-list" style="max-height:20vh; overflow-y:auto; font-size:15px; line-height:1.5;">
                                                <?php
                                                $cnum = 1;
                                                foreach ($yearCourses as $res):
                                                    ?>
                                                    <div class="border rounded px-2 py-2 mb-1 d-flex justify-content-between align-items-start">
                                                        <div class="d-flex align-items-start" style="gap:6px; flex:1 1 auto;">
                                                    <span class="badge bg-light text-dark ms-1" style="font-size:12px; min-width:26px; text-align:center;">
                                                        <?php echo $cnum; ?>
                                                    </span>
                                                            <span class="fw-bold" style="white-space:normal; word-break:break-word;">
                                                        <?php echo htmlspecialchars($res->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                        </div>

                                                        <div class="d-flex flex-wrap align-items-center justify-content-start" style="gap:4px; font-size:13px;">
                                                            <?php
                                                            $classDotClass = '';
                                                            $classDotStyle = "display:inline-block;width:10px;height:10px;border-radius:50%;";
                                                            $classTitle = '';

                                                            if ($res->color == 0) {
                                                                $classDotClass = 'bg-danger';
                                                                $classTitle = 'حضور زیر 25% در کلاس آنلاین';
                                                            } elseif ($res->color == 1) {
                                                                $classDotClass = 'bg-warning';
                                                                $classTitle = 'حضور بین 25% تا 50% در کلاس آنلاین';
                                                            } elseif ($res->color == 2) {
                                                                $classDotClass = '';
                                                                $classDotStyle .= 'background-color:#fffb02;';
                                                                $classTitle = 'حضور بین 50% تا 75% در کلاس آنلاین';
                                                            } elseif ($res->color == 3) {
                                                                $classDotClass = 'bg-success';
                                                                $classTitle = 'حضور بالای 75% در کلاس آنلاین';
                                                            }
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                                                        <span class="rounded-circle <?php echo $classDotClass; ?>" style="<?php echo $classDotStyle; ?>; margin-left:4px;" data-bs-toggle="tooltip" title="<?php echo $classTitle; ?>"></span>
                                                        کلاس
                                                    </span>

                                                            <?php
                                                            $colorquiz = getTblQuizColor($res->codecourse, $res->username);
                                                            $totalquiz = $colorquiz[0] + $colorquiz[1];
                                                            $darsadquiz = $totalquiz > 0 ? ($colorquiz[0] / $totalquiz) * 100 : 0;

                                                            $quizDotClass = '';
                                                            $quizDotStyle = "display:inline-block;width:10px;height:10px;border-radius:50%;";
                                                            $quizTitle = '';

                                                            if ($darsadquiz < 25) {
                                                                $quizDotClass = 'bg-danger';
                                                                $quizTitle = 'حضور زیر 25% در آزمون';
                                                            } elseif ($darsadquiz < 50) {
                                                                $quizDotClass = 'bg-warning';
                                                                $quizTitle = 'حضور بین 25% تا 50% در آزمون';
                                                            } elseif ($darsadquiz < 75) {
                                                                $quizDotClass = '';
                                                                $quizDotStyle .= 'background-color:#fffb02;';
                                                                $quizTitle = 'حضور بین 50% تا 75% در آزمون';
                                                            } else {
                                                                $quizDotClass = 'bg-success';
                                                                $quizTitle = 'حضور بالای 75% در آزمون';
                                                            }
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                                                        <span class="rounded-circle <?php echo $quizDotClass; ?>" style="<?php echo $quizDotStyle; ?>; margin-left:4px;" data-bs-toggle="tooltip" title="<?php echo $quizTitle; ?>"></span>
                                                        آزمون
                                                    </span>

                                                            <?php
                                                            $logfilm = retLogViewFilm($res->codecourse, $res->username);
                                                            $filmClass = $logfilm ? 'bg-teal' : 'bg-dark';
                                                            $filmTitle = $logfilm ? 'دارای گزارش تماشای فیلم' : 'فاقد گزارش تماشای فیلم';
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                                                        <span class="rounded-circle <?php echo $filmClass; ?>" style="display:inline-block;width:10px;height:10px;border-radius:50%; margin-left:4px;" data-bs-toggle="tooltip" title="<?php echo $filmTitle; ?>"></span>
                                                        فیلم
                                                    </span>

                                                            <?php
                                                            $logdoc = retLogViewDoc($res->codecourse, $res->username);
                                                            $docClass = $logdoc ? 'bg-teal' : 'bg-dark';
                                                            $docTitle = $logdoc ? 'دارای گزارش مطالعه جزوه' : 'فاقد گزارش مطالعه جزوه';
                                                            ?>
                                                            <span class="badge bg-light text-dark d-inline-flex align-items-center px-2 py-1">
                                                        <span class="rounded-circle <?php echo $docClass; ?>" style="display:inline-block;width:10px;height:10px;border-radius:50%; margin-left:4px;" data-bs-toggle="tooltip" title="<?php echo $docTitle; ?>"></span>
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

                                        <hr class="my-3">

                                        <div class="row">
                                            <!-- Messages -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3">گفتگوهای این سال</h6>

                                                <?php if (!empty($yearMessages)): ?>
                                                    <div class="card custom-card">
                                                        <div class="card-body" style="max-height:300px; overflow-y:auto; direction: rtl; text-align: right;">
                                                            <?php foreach ($yearMessages as $msg): ?>
                                                                <?php
                                                                $isAdmin = ($msg['sender_type'] === 'admin');
                                                                $alignClass = $isAdmin ? 'media' : 'media flex-row-reverse';
                                                                $text = htmlspecialchars($msg['message_text'] ?? '', ENT_QUOTES, 'UTF-8');

                                                                $baseDate = !empty($msg['edit_message_date']) ? $msg['edit_message_date'] : $msg['message_date'];
                                                                $baseTime = !empty($msg['edit_message_time']) ? $msg['edit_message_time'] : $msg['message_time'];
                                                                $jLabel = '';

                                                                if (!empty($baseDate)) {
                                                                    $tsm = strtotime($baseDate . ' ' . $baseTime);
                                                                    if ($tsm > 0) {
                                                                        $jLabel = jdate('H:i | Y/m/d', $tsm);
                                                                    }
                                                                }

                                                                $senderLabel = '';
                                                                if ($isAdmin) {
                                                                    $adminObj = retAdmin($msg['sender_username']);
                                                                    if ($adminObj && !empty($adminObj->dakheli)) {
                                                                        switch ($adminObj->dakheli) {
                                                                            case '201': $senderLabel = 'اپراتور ارشد 1'; break;
                                                                            case '500': $senderLabel = 'مدیریت موسسه'; break;
                                                                            case '220': $senderLabel = 'اپراتور ارشد 2'; break;
                                                                            case '501': $senderLabel = 'مهندس بلالی'; break;
                                                                            default: $senderLabel = 'اپراتور ' . $adminObj->dakheli; break;
                                                                        }
                                                                    }
                                                                } else {
                                                                    $senderLabel = t('student');
                                                                }
                                                                ?>

                                                                <div class="<?php echo $alignClass; ?> mb-2">
                                                                    <div class="main-img-user online">
                                                                        <?php if ($msg['sender_type'] === 'student'): ?>
                                                                            <img alt="پروفایل" src="<?= assest('/assets/img/users/1.jpg') ?>" class="my-2">
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <div class="main-msg-wrapper ml-3">
                                                                        <?php if ($senderLabel): ?>
                                                                            <div class="text-muted small mb-1"><?php echo $senderLabel; ?></div>
                                                                        <?php endif; ?>

                                                                        <div class="media-body mr-3">
                                                                            <span><?php echo $text; ?></span>
                                                                        </div>

                                                                        <div class="mt-1 d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <?php if (!empty($msg['file_path'])): ?>
                                                                                    <a href="<?php echo url('Support/attachments.php') . '?fileChat=' . urlencode($msg['id']); ?>" title="دانلود فایل بارگذاری شده">
                                                                                        <i class="fe fe-paperclip"></i>
                                                                                    </a>
                                                                                <?php endif; ?>

                                                                                <?php if ($jLabel): ?>
                                                                                    <span class="text-muted" style="font-size: 11px;">
                                                                                <?php echo htmlspecialchars($jLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                                        <?php if (!empty($msg['edit_message_date'])): ?>(ویرایش‌شده)<?php endif; ?>
                                                                            </span>
                                                                                <?php endif; ?>
                                                                            </div>

                                                                            <div>
                                                                                <?php
                                                                                $datetime = trim(($msg['message_date'] ?? '') . ' ' . ($msg['message_time'] ?? ''));

                                                                                if (Permision('history_permission')) {
                                                                                    ?>
                                                                                    <a href="?Id=<?= htmlspecialchars($_GET['Id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>&delm=<?= (int)$msg['id']; ?>" onclick="return confirm('آیا با حذف این پیام موافقید؟')" title="حذف">
                                                                                        <i class="fe fe-trash-2 text-danger"></i>
                                                                                    </a>
                                                                                    <?php
                                                                                } elseif (($msg['sender_type'] ?? '') === 'admin' && !Permision('permision_manage_chat')) {
                                                                                    if (!empty($datetime) && (time() - strtotime($datetime) <= 3600)) {
                                                                                        ?>
                                                                                        <a href="?Id=<?= htmlspecialchars($_GET['Id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>&delm=<?= (int)$msg['id']; ?>" onclick="return confirm('آیا با حذف این پیام موافقید؟')" title="حذف">
                                                                                            <i class="fe fe-trash-2 text-danger"></i>
                                                                                        </a>
                                                                                        <?php
                                                                                    }
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-muted mb-0" style="font-size: 13px;">در این سال گفتگویی ثبت نشده است.</p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Support reports -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="border-bottom pb-2 mb-3">سوابق گزارشات ثبت شده</h6>

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
                                                                        $type = htmlspecialchars($report['type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                        $status = htmlspecialchars($report['status'] ?? '', ENT_QUOTES, 'UTF-8');
                                                                        $detail = htmlspecialchars($report['detail'] ?? '', ENT_QUOTES, 'UTF-8');
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
                                                    <p class="text-muted mb-0" style="font-size: 13px;">در این سال گزارشی ثبت نشده است.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Answered events -->
                                        <?php
                                        $yearAnswered = $answeredEvents[(int)$year] ?? [];
                                        if (!empty($yearAnswered)):
                                            ?>
                                            <hr class="my-3">
                                            <h6 class="border-bottom pb-2 mb-2">رویدادهای پاسخ‌داده‌شده (عملی)</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead>
                                                    <tr>
                                                        <th>عنوان رویداد</th>
                                                        <th>گزینه انتخاب‌شده</th>
                                                        <th>توضیح (در صورت نیاز)</th>
                                                        <th>پاسخ‌دهنده</th>
                                                        <th>تاریخ پاسخ</th>
                                                        <?php if (Permision('history_permission')): ?>
                                                            <th>عملیات</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($yearAnswered as $ans): ?>
                                                        <?php
                                                        $optionText = htmlspecialchars($ans['option_text'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $reason = htmlspecialchars($ans['reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $operator = htmlspecialchars($ans['created_by'] ?? '', ENT_QUOTES, 'UTF-8');
                                                        $answerDate = !empty($ans['created_at']) ? jdate('Y/m/d H:i', strtotime($ans['created_at'])) : '';
                                                        ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($ans['event_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td><?= $optionText ?></td>
                                                            <td><?= $reason ?: '-' ?></td>
                                                            <td><?= $operator ?: '-' ?></td>
                                                            <td><?= $answerDate ?></td>
                                                            <?php if (Permision('history_permission')): ?>
                                                                <td>
                                                                    <button class="btn btn-sm btn-outline-warning edit-answer-btn"
                                                                            data-answer-id="<?= (int)$ans['answer_id'] ?>"
                                                                            data-event-id="<?= (int)$ans['event_id'] ?>"
                                                                            data-option-id="<?= (int)$ans['option_id'] ?>"
                                                                            data-reason="<?= htmlspecialchars($ans['reason'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#editAnswerModal">
                                                                        <i class="fe fe-edit-2"></i> ویرایش
                                                                    </button>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>

                                    </div> <!-- /accordion-body -->
                                </div> <!-- /accordion-collapse -->
                            </div> <!-- /accordion-item -->
                            <?php
                            $isFirst = false;
                        endforeach;
                        ?>
                    </div> <!-- /historyAccordion -->

                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            <?php echo m('msg.no_history') ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div><!-- /.container-fluid -->
</div><!-- /.page -->

<!-- Modal: Edit Answer for Active Event -->
<div class="modal fade" id="editAnswerModal" tabindex="-1" aria-labelledby="editAnswerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAnswerModalLabel">ویرایش پاسخ رویداد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="editAnswerForm">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <input type="hidden" name="edit_answer_id" id="edit_answer_id" value="">
                    <input type="hidden" name="edit_event_id" id="edit_event_id" value="">
                    <input type="hidden" name="action" value="edit_answer">

                    <div class="mb-3">
                        <label for="edit_option_id" class="form-label">گزینه جدید</label>
                        <select class="form-select" id="edit_option_id" name="edit_option_id" required>
                            <option value="">— انتخاب گزینه —</option>
                        </select>
                    </div>

                    <div class="mb-3" id="edit_reason_container">
                        <label for="edit_reason" class="form-label">توضیح (در صورت نیاز)</label>
                        <input type="text" class="form-control" id="edit_reason" name="edit_reason" placeholder="علت را وارد کنید">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">ذخیره تغییرات</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <input type="hidden" name="history_year" id="history_year" value="">

                    <div class="mb-3">
                        <label for="registered" class="form-label" style="color: #222;">ثبت نام کرده / نکرده</label>
                        <select class="form-select" id="registered" name="registered" required style="color: #000;">
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

                    <div class="mb-3" id="reasonDiv" style="display:none;">
                        <label for="reason" class="form-label" style="color: #222;">چرا ثبت نام نکرده؟</label>
                        <input type="text" class="form-control" id="reason" name="not_registered_reason" placeholder="علت را وارد کنید">
                    </div>

                    <div class="mb-3" id="takenExamDiv" style="display:none;">
                        <label for="takenExam" class="form-label" style="color: #222;">آزمون داده / نداده</label>
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

<!-- Modal: Add student_status -->
<div class="modal fade" id="studentStatusModal" tabindex="-1" aria-labelledby="studentStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentStatusModalLabel">افزودن نمرات و نظرات</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                    <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">
                    <input type="hidden" name="history_year" id="history_year_status" value="">

                    <div class="mb-3">
                        <label for="avg_first_semester" class="form-label" style="color:#222;">معدل ترم اول</label>
                        <input type="text" class="form-control" id="avg_first_semester" name="avg_first_semester" placeholder="مثال: 18.25">
                    </div>

                    <div class="mb-3">
                        <label for="avg_second_semester" class="form-label" style="color:#222;">معدل ترم دوم</label>
                        <input type="text" class="form-control" id="avg_second_semester" name="avg_second_semester" placeholder="مثال: 17.80">
                    </div>

                    <div class="mb-3">
                        <label for="student_comment" class="form-label" style="color:#222;"><?php echo m('label.student_comment') ?></label>
                        <textarea class="form-control" id="student_comment" name="student_comment" rows="3" placeholder="<?php echo m('label.student_comment_ph') ?>"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="supporter_comment" class="form-label" style="color:#222;">نظر پشتیبان</label>
                        <textarea class="form-control" id="supporter_comment" name="supporter_comment" rows="3" placeholder="نظر پشتیبان را وارد کنید"></textarea>
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
        var allOptions = <?php echo json_encode($allOptions ?? []); ?>;

        var courseStatusModal = document.getElementById('courseStatusModal');
        if (courseStatusModal) {
            courseStatusModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;

                var year = button.getAttribute('data-history-year');
                var yearInput = document.getElementById('history_year');
                if (yearInput && year) yearInput.value = year;

                var modalTitle = courseStatusModal.querySelector('.modal-title');
                if (modalTitle && year) modalTitle.textContent = 'افزودن وضعیت آزمون - سال ' + year;
            });
        }

        var studentStatusModal = document.getElementById('studentStatusModal');
        if (studentStatusModal) {
            studentStatusModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;

                var year = button.getAttribute('data-history-year');
                var yearInput = document.getElementById('history_year_status');
                if (yearInput && year) yearInput.value = year;

                var modalTitle = studentStatusModal.querySelector('.modal-title');
                if (modalTitle && year) modalTitle.textContent = 'افزودن نمرات و نظرات - سال ' + year;
            });
        }

        var registered = document.getElementById('registered');
        var reasonDiv = document.getElementById('reasonDiv');
        var examTypeDiv = document.getElementById('examTypeDiv');
        var takenExamDiv = document.getElementById('takenExamDiv');
        var passedDiv = document.getElementById('passedDiv');

        var examType = document.getElementById('examType');
        var takenExam = document.getElementById('takenExam');
        var passed = document.getElementById('passed');
        var reason = document.getElementById('reason');

        if (registered && reasonDiv && examTypeDiv && takenExamDiv && passedDiv &&
            examType && takenExam && passed && reason) {

            reasonDiv.style.display = 'none';
            examTypeDiv.style.display = 'none';
            takenExamDiv.style.display = 'none';
            passedDiv.style.display = 'none';

            examType.required = false;
            takenExam.required = false;
            passed.required = false;
            reason.required = false;

            function resetValues() {
                examType.value = '';
                takenExam.value = '';
                passed.value = '';
                reason.value = '';
            }

            registered.addEventListener('change', function () {
                var val = this.value;

                if (val === 'ثبت نام نکرده') {
                    reasonDiv.style.display = 'block';
                    examTypeDiv.style.display = 'block';
                    takenExamDiv.style.display = 'none';
                    passedDiv.style.display = 'none';

                    reason.required = true;
                    examType.required = true;
                    takenExam.required = false;
                    passed.required = false;
                    passed.value = '';
                } else if (val === 'ثبت نام کرده') {
                    reasonDiv.style.display = 'none';
                    examTypeDiv.style.display = 'block';
                    takenExamDiv.style.display = 'block';
                    passedDiv.style.display = (takenExam.value === 'آزمون داده') ? 'block' : 'none';

                    reason.required = false;
                    reason.value = '';
                    examType.required = true;
                    takenExam.required = true;
                    passed.required = (takenExam.value === 'آزمون داده');
                    if (takenExam.value !== 'آزمون داده') {
                        passed.value = '';
                    }
                } else if (val === 'بررسی گردد') {
                    reasonDiv.style.display = 'block';
                    examTypeDiv.style.display = 'block';
                    takenExamDiv.style.display = 'none';
                    passedDiv.style.display = 'none';

                    reason.required = true;
                    examType.required = true;
                    takenExam.required = false;
                    passed.required = false;
                    takenExam.value = '';
                    passed.value = '';
                } else {
                    reasonDiv.style.display = 'none';
                    examTypeDiv.style.display = 'none';
                    takenExamDiv.style.display = 'none';
                    passedDiv.style.display = 'none';

                    reason.required = false;
                    examType.required = false;
                    takenExam.required = false;
                    passed.required = false;
                    resetValues();
                }
            });

            takenExam.addEventListener('change', function () {
                if (this.value === 'آزمون داده') {
                    passedDiv.style.display = 'block';
                    passed.required = true;
                } else {
                    passedDiv.style.display = 'none';
                    passed.required = false;
                    passed.value = '';
                }
            });
        }

        var editModal = document.getElementById('editAnswerModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;

                var answerId = button.getAttribute('data-answer-id');
                var eventId = button.getAttribute('data-event-id');
                var currentOptionId = parseInt(button.getAttribute('data-option-id')) || 0;
                var currentReason = button.getAttribute('data-reason') || '';

                document.getElementById('edit_answer_id').value = answerId;
                document.getElementById('edit_event_id').value = eventId;

                var select = document.getElementById('edit_option_id');
                var reasonContainer = document.getElementById('edit_reason_container');
                var reasonInput = document.getElementById('edit_reason');

                select.innerHTML = '<option value="">— انتخاب گزینه —</option>';

                if (allOptions && allOptions[eventId]) {
                    allOptions[eventId].forEach(function (opt) {
                        var option = document.createElement('option');
                        option.value = opt.id;
                        option.textContent = opt.events_option;
                        option.dataset.needsReason = opt.needs_reason;
                        if (opt.needs_reason == 1) {
                            option.textContent += ' (نیاز به توضیح)';
                        }
                        if (opt.id == currentOptionId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                }

                reasonInput.value = currentReason;

                function toggleReason() {
                    var selected = select.options[select.selectedIndex];
                    var needsReason = selected ? selected.dataset.needsReason == '1' : false;
                    reasonContainer.style.display = needsReason ? 'block' : 'none';
                    if (!needsReason) {
                        reasonInput.value = '';
                    }
                }

                select.onchange = toggleReason;
                toggleReason();
            });
        }
    });
</script>
</body>
</html>