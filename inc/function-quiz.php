<?php
// =========================== توابع کمکی لاگ ===========================
function logApiCall($event_type, $target_type, $target_id, $request_method, $request_url, $request_body = null, $request_headers = null, $requested_by = null)
{
    global $conn;
    date_default_timezone_set('Asia/Tehran');

    // اگر target_id معتبر عددی نیست، 0 بگذار
    if (!is_numeric($target_id) || $target_id <= 0) {
        $target_id = 0;
    }

    // پردازش request_headers (آرایه یا null)
    $headers_for_db = null;
    if ($request_headers !== null) {
        if (is_array($request_headers)) {
            $headers_for_db = json_encode($request_headers, JSON_UNESCAPED_UNICODE);
        } else {
            // اگر رشته است، سعی کنیم decode کنیم تا یونی‌کد آن تبدیل شود
            $temp = json_decode($request_headers, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $headers_for_db = json_encode($temp, JSON_UNESCAPED_UNICODE);
            } else {
                $headers_for_db = $request_headers;
            }
        }
    }

    // پردازش request_body (آرایه، رشته JSON یا رشته معمولی)
    $body_for_db = null;
    if ($request_body !== null) {
        if (is_array($request_body)) {
            $body_for_db = json_encode($request_body, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($request_body)) {
            // بررسی کنیم آیا این رشته یک JSON معتبر است
            $temp = json_decode($request_body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // بازنویسی بدون یونی‌کد escape
                $body_for_db = json_encode($temp, JSON_UNESCAPED_UNICODE);
            } else {
                $body_for_db = $request_body;
            }
        } else {
            // سایر نوع‌ها (مثل عدد) به رشته تبدیل شود
            $body_for_db = (string)$request_body;
        }
    }

    $stmt = $conn->prepare("INSERT INTO api_request_log 
        (requested_by, target_type, target_id, event_type, request_method, request_url, request_headers, request_body, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $requested_by ?? $_SESSION['admin']['user'] ?? null,
        $target_type,
        $target_id,
        $event_type,
        $request_method,
        $request_url,
        $headers_for_db,
        $body_for_db,
        date('Y-m-d H:i:s')
    ]);
    return $conn->lastInsertId();
}

function updateLogResult($log_id, $http_code, $response_body, $is_success, $error_message = null)
{
    global $conn;

    // پردازش response_body (مشابه request_body)
    $response_for_db = null;
    if ($response_body !== null) {
        if (is_array($response_body)) {
            $response_for_db = json_encode($response_body, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($response_body)) {
            $temp = json_decode($response_body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response_for_db = json_encode($temp, JSON_UNESCAPED_UNICODE);
            } else {
                $response_for_db = $response_body;
            }
        } else {
            $response_for_db = (string)$response_body;
        }
    }

    // error_message نیازی به decode خاص ندارد، ولی می‌توان آن را نیز utf8 کرد
    if ($error_message && !mb_check_encoding($error_message, 'UTF-8')) {
        $error_message = utf8_encode($error_message);
    }

    $stmt = $conn->prepare("UPDATE api_request_log SET 
        response_status_code = ?, 
        response_body = ?, 
        is_success = ?, 
        error_message = ?,
        last_retry_at = NULL
        WHERE id = ?");
    $stmt->execute([$http_code, $response_for_db, $is_success, $error_message, $log_id]);
}

// =========================== توابع داخلی (بدون API) ===========================
function checkUserquiz($user)
{
    $sync = function_exists('cross_panel_sync_oes')
        ? cross_panel_sync_oes('student.get', ['username' => (string)$user])
        : ['ok' => false];
    if (!empty($sync['ok'])) {
        return false;
    }

    /*
     * فراخوانی‌کنندگان قدیمی، پس از true شدن این تابع مستقیماً در اتصال
     * legacy آزمون INSERT می‌زدند. کاربر را در OES با درخواست امضاشده
     * می‌سازیم و false برمی‌گردانیم تا آن INSERT قدیمی اجرا نشود.
     * این کار بخش بانکی/verify/callback پرداخت را تغییر نمی‌دهد.
     */
    $person = function_exists('retPerson') ? retPerson($user) : null;
    $plainPassword = function_exists('passUser') ? (string) passUser($user) : '';
    $result = CreateUserByApi([
        'username' => (string)$user,
        'password' => md5($plainPassword),
        'active' => 1,
        'expiration_date' => '3021-03-21',
        'fname' => (string)($person->fname ?? ''),
        'lname' => (string)($person->lname ?? ''),
    ]);
    if (!empty($result['success'])) {
        return false;
    }
    $_SESSION['cross_panel_warning'] = (string)($result['message'] ?? 'ساخت دانش‌آموز در سامانه آزمون انجام نشد');
    return true;
}

function retIdGroup($code)
{
    $sync = function_exists('cross_panel_sync_oes')
        ? cross_panel_sync_oes('group.get', ['code' => (string)$code])
        : ['ok' => false];
    return !empty($sync['ok']) ? (int)($sync['ugid'] ?? 0) : null;
}

function retIdStudent($username)
{
    $sync = function_exists('cross_panel_sync_oes')
        ? cross_panel_sync_oes('student.get', ['username' => (string)$username])
        : ['ok' => false];
    return !empty($sync['ok']) ? (int)($sync['uid'] ?? 0) : null;
}

function checkGroupAdd($code, $username)
{
    $ugid = retIdGroup($code);
    $uid = retIdStudent($username);
    if (!$ugid || !$uid || !function_exists('cross_panel_sync_oes')) {
        return true;
    }
    $sync = cross_panel_sync_oes('membership.upsert', ['ugid' => (int)$ugid, 'uid' => (int)$uid]);
    if (!empty($sync['ok'])) {
        return false; // عضویت امن ثبت شده؛ INSERT مستقیم legacy اجرا نشود.
    }
    $_SESSION['cross_panel_warning'] = (string)($sync['message'] ?? 'ثبت عضویت دانش‌آموز در سامانه آزمون انجام نشد');
    return true;
}

/**
 * Keep an LMS course/class enrolment aligned with its OES grade and subgroup.
 *
 * A class subgroup is optional in LMS, but when it is configured for quizzes
 * the student must belong to both the parent grade and that subgroup in OES.
 * The OES membership endpoint is idempotent, so this may safely be called
 * after every class assignment or transfer.
 */
function syncStudentCourseClassToOes(string $username, int $courseCode, int $classId): array
{
    global $conn;

    if ($username === '' || $courseCode <= 0 || $classId <= 0) {
        return ['ok' => false, 'message' => 'اطلاعات دانش‌آموز، پایه یا کلاس معتبر نیست'];
    }
    if (!function_exists('cross_panel_sync_oes')) {
        return ['ok' => false, 'message' => 'ماژول ارتباط با سامانه آزمون بارگذاری نشده است'];
    }

    $course = function_exists('returnCourse') ? returnCourse($courseCode) : false;
    $classStmt = $conn->prepare('SELECT codecourse, ugid FROM classes WHERE id = ? LIMIT 1');
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch(PDO::FETCH_OBJ);
    if (!$class || (int) $class->codecourse !== $courseCode) {
        return ['ok' => false, 'message' => 'کلاس انتخاب‌شده متعلق به این پایه نیست'];
    }

    $gradeUgid = (int) ($course->ugid ?? 0);
    if ($gradeUgid <= 0) {
        $grade = cross_panel_sync_oes('group.get', ['code' => (string) $courseCode]);
        $gradeUgid = !empty($grade['ok']) ? (int) ($grade['ugid'] ?? 0) : 0;
    }
    $classUgid = (int) ($class->ugid ?? 0);
    if ($gradeUgid <= 0 || $classUgid <= 0) {
        return ['ok' => false, 'message' => 'گروه آزمونِ پایه یا زیرپایهٔ کلاس هنوز تنظیم نشده است'];
    }

    $student = cross_panel_sync_oes('student.get', ['username' => $username]);
    $uid = !empty($student['ok']) ? (int) ($student['uid'] ?? 0) : 0;
    if ($uid <= 0) {
        $person = function_exists('retPerson') ? retPerson($username) : null;
        $plainPassword = function_exists('passUser') ? (string) passUser($username) : '';
        $student = cross_panel_sync_oes_student(
            $username,
            (string) ($person->fname ?? ''),
            (string) ($person->lname ?? ''),
            $plainPassword,
            true
        );
        $uid = !empty($student['ok']) ? (int) ($student['uid'] ?? 0) : 0;
    }
    if ($uid <= 0) {
        return ['ok' => false, 'message' => $student['message'] ?? 'ساخت یا یافتن دانش‌آموز در سامانه آزمون ناموفق بود'];
    }

    foreach ([$gradeUgid, $classUgid] as $ugid) {
        $membership = cross_panel_sync_oes('membership.upsert', ['uid' => $uid, 'ugid' => $ugid]);
        if (empty($membership['ok'])) {
            return ['ok' => false, 'message' => $membership['message'] ?? 'ثبت عضویت در سامانه آزمون ناموفق بود'];
        }
    }

    return ['ok' => true, 'uid' => $uid, 'grade_ugid' => $gradeUgid, 'class_ugid' => $classUgid];
}

function checkEntityGroupQuiz($code)
{
    global $ponn;
    $sql = 'SELECT * FROM `_ugroups` WHERE ugtitle=? ';
    $query = $ponn->prepare($sql);
    $query->execute([$code]);
    return $query->rowCount() == 0;
}

function GetQuestionDetails($utid, $qid)
{
    global $ponn;
    $result = [
        'student_choice' => null,
        'correct_choice' => null,
        'choice_count' => 4,
        'status' => 2
    ];

    $sqlChoices = "SELECT qcid FROM `_questions_choices` WHERE qid = ? ORDER BY qcid ASC";
    $stmt = $ponn->prepare($sqlChoices);
    $stmt->execute([$qid]);
    $allChoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['choice_count'] = count($allChoices);
    $choiceMap = [];
    foreach ($allChoices as $index => $row) {
        $choiceMap[$row['qcid']] = $index + 1;
    }

    $sqlStudent = "SELECT utqc.qcid FROM `_user_test_question` AS utq
                   INNER JOIN `_user_test_question_choice` AS utqc ON utq.utqid = utqc.utqid
                   WHERE utq.utid = ? AND utq.qid = ?";
    $stmt = $ponn->prepare($sqlStudent);
    $stmt->execute([$utid, $qid]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $studentQcid = $studentRow ? $studentRow['qcid'] : null;
    $result['student_choice'] = $studentQcid ? $choiceMap[$studentQcid] : null;

    $sqlCorrect = "SELECT qcid FROM `_questions_choices` WHERE qid = ? AND qcis_answer = 1";
    $stmt = $ponn->prepare($sqlCorrect);
    $stmt->execute([$qid]);
    $correctRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $correctQcid = $correctRow ? $correctRow['qcid'] : null;
    $result['correct_choice'] = $correctQcid ? $choiceMap[$correctQcid] : null;

    if (!$studentQcid) {
        $result['status'] = 2;
    } elseif ($studentQcid == $correctQcid) {
        $result['status'] = 1;
    } else {
        $result['status'] = 0;
    }
    return (object)$result;
}

function GetUserId($username)
{
    global $ponn;
    $sql = "SELECT uid FROM _users WHERE uusername = ?";
    $stmt = $ponn->prepare($sql);
    $stmt->execute([$username]);
    return $stmt->fetchColumn();
}

function GetUserTestId($uid, $tid)
{
    global $ponn;
    $sql = "SELECT utid FROM _user_test WHERE uid = ? AND tid = ?";
    $stmt = $ponn->prepare($sql);
    $stmt->execute([$uid, $tid]);
    return $stmt->fetchColumn();
}

function GetTidFromDbQuiz($quizId)
{
    global $conn;
    $sql = "SELECT tid FROM dbquiz WHERE idquiz = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$quizId]);
    $tid = $stmt->fetchColumn();
    return $tid !== false ? $tid : false;
}

function GetTestQuestions($utid)
{
    global $ponn;
    $sql = "SELECT q.*, utq.utqid 
            FROM `_user_test_question` AS utq
            INNER JOIN `_questions` AS q ON utq.qid = q.qid
            WHERE utq.utid = ?";
    $stmt = $ponn->prepare($sql);
    $stmt->execute([$utid]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

// =========================== توابع API با لاگ کامل ===========================

function crossPanelDataSync($action, array $data)
{
    if (!function_exists('cross_panel_sync_oes')) {
        return ['ok' => false, 'message' => 'ماژول ارتباط امن بین پنل‌ها بارگذاری نشده است'];
    }
    return cross_panel_sync_oes($action, $data);
}

function checkUserquizByApi($user)
{
    if (empty($user)) {
        return false;
    }

    $sync = crossPanelDataSync('student.get', ['username' => (string)$user]);
    return !empty($sync['ok']) ? (int)($sync['uid'] ?? 0) : false;

    $url = "https://my.dast-garmi.ir/api/check-user.php?user=" . urlencode($user);
    $log_id = logApiCall('check_user_quiz', 'user', $user, 'GET', $url, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return false;
    }

    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return false;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['success'])) {
        updateLogResult($log_id, $httpCode, $response, 0, "API returned success=false or invalid JSON");
        return false;
    }

    updateLogResult($log_id, $httpCode, $response, 1);
    return (int)$data['uid'];
}

function retIdGroupByApi($code)
{
    if (empty($code)) {
        return null;
    }

    $sync = crossPanelDataSync('group.get', ['code' => (string)$code]);
    return !empty($sync['ok']) ? (int)($sync['ugid'] ?? 0) : null;

    $url = "https://my.dast-garmi.ir/api/get-group-id.php?code=" . urlencode($code);
    $log_id = logApiCall('get_group_id', 'group', 0, 'GET', $url, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return null;
    }

    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['success'])) {
        updateLogResult($log_id, $httpCode, $response, 0, "API returned success=false or invalid JSON");
        return null;
    }

    updateLogResult($log_id, $httpCode, $response, 1);
    return (int)$data['ugid'];
}

function AddUserToGroupByApi($ugid, $uid, $requested_by = null, $maxRetries = 2, $initialDelayMs = 500)
{
    if ($ugid <= 0 || $uid['uid'] <= 0) {
        return false;
    }

    $sync = crossPanelDataSync('membership.upsert', ['ugid' => (int)$ugid, 'uid' => (int)$uid['uid']]);
    return !empty($sync['ok']);

    $url = "https://my.dast-garmi.ir/api/add-user-to-group.php";
    $payload = ['ugid' => $ugid, 'uid' => $uid['uid']];

    // لاگ اولیه (با is_success = 0 فعلاً)
    $log_id = logApiCall(
        'add_user_to_group', 'user', $uid['uid'], 'POST', $url, $payload,
        ['Content-Type: application/json', 'User-Agent: LMS Server'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null,
        null // target_identifier (اختیاری)
    );

    $attempt = 0;
    $delayMs = $initialDelayMs;
    $lastResponse = null;
    $lastHttpCode = null;

    while ($attempt <= $maxRetries) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: LMS Server'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $lastResponse = $response;
        $lastHttpCode = $httpCode;

        // در صورت موفقیت (کد 200 و success=true)
        if ($curlError === '' && $httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data['success'])) {
                updateLogResult($log_id, $httpCode, $response, 1, null);
                return true;
            }
        }

        $attempt++;
        if ($attempt > $maxRetries) break;

        // تأخیر قبل از تلاش مجدد (به میلی‌ثانیه)
        usleep($delayMs * 1000);
        // افزایش تأخیر به صورت تصاعدی (اختیاری)
        $delayMs *= 2; // 500, 1000, 2000 ms
    }

    // اگر همه تلاش‌ها ناموفق بود
    $errorMsg = $curlError ?: "HTTP $httpCode - " . substr($lastResponse, 0, 200);
    updateLogResult($log_id, $lastHttpCode, $lastResponse, 0, $errorMsg);
    return false;
}

function AddUserTestPaymentByApi($packid, $uid, $requested_by = null, $maxRetries = 2, $initialDelayMs = 500)
{
    if ($packid <= 0 || $uid['uid'] <= 0) {
        return false;
    }

    // This grants package access only; no payment verification is performed here.
    $sync = crossPanelDataSync('package.assign', ['package_id' => (int)$packid, 'uid' => (int)$uid['uid']]);
    return !empty($sync['ok']);

    $url = "https://my.dast-garmi.ir/api/add-user-test-payment.php";
    $payload = ['packid' => $packid, 'uid' => $uid['uid']];

    // لاگ اولیه (با is_success = 0 فعلاً)
    $log_id = logApiCall(
        'add_user_test_payment', 'user', $uid['uid'], 'POST', $url, $payload,
        ['Content-Type: application/json', 'User-Agent: LMS Server'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null,
        null // target_identifier (اختیاری)
    );

    $attempt = 0;
    $delayMs = $initialDelayMs;
    $lastResponse = null;
    $lastHttpCode = null;

    while ($attempt <= $maxRetries) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: LMS Server'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $lastResponse = $response;
        $lastHttpCode = $httpCode;

        // در صورت موفقیت (کد 200 و success=true)
        if ($curlError === '' && $httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data['success'])) {
                updateLogResult($log_id, $httpCode, $response, 1, null);
                return true;
            }
        }

        $attempt++;
        if ($attempt > $maxRetries) break;

        // تأخیر قبل از تلاش مجدد (به میلی‌ثانیه)
        usleep($delayMs * 1000);
        // افزایش تأخیر به صورت تصاعدی (اختیاری)
        $delayMs *= 2; // 500, 1000, 2000 ms
    }

    // اگر همه تلاش‌ها ناموفق بود
    $errorMsg = $curlError ?: "HTTP $httpCode - " . substr($lastResponse, 0, 200);
    updateLogResult($log_id, $lastHttpCode, $lastResponse, 0, $errorMsg);
    return false;
}

function checkGroupAddByApi($code, $username)
{
    // ابتدا لاگ برای کل عملیات (چون شامل دو مرحله است، اما ساده‌ترین کار لاگ کل درخواست check-group است)
    $ugid = retIdGroupByApi($code);
    if (!$ugid) {
        // retIdGroupByApi لاگ خود را زده، اینجا فقط false برگردان
        return false;
    }
    $uidResult = GetUserIdByApi($username);
    if (!$uidResult['success']) {
        return false;
    }
    $uid = $uidResult['uid'];

    $url = "https://my.dast-garmi.ir/api/check-group.php?ugid=" . urlencode($ugid) . "&uid=" . urlencode($uid);
    $payload = ['ugid' => $ugid, 'uid' => $uid];

    $log_id = logApiCall('check_group_membership', 'user', $uid, 'POST', $url, $payload,
        ['Content-Type: application/json', 'User-Agent: LMS Server'],
        $_SESSION['admin']['user'] ?? null
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: LMS Server'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return false;
    }
    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return false;
    }
    $data = json_decode($response, true);
    $success = is_array($data) && isset($data['success']) && $data['success'];
    updateLogResult($log_id, $httpCode, $response, $success ? 1 : 0, $success ? null : ($data['message'] ?? 'API returned success=false'));
    return $success;
}

function GetQuestionDetailsByApi($utid, $qid)
{
    if (empty($utid) || empty($qid)) {
        return ['success' => false, 'error' => 'utid یا qid خالی است'];
    }

    // اصلاح آدرس (اضافه کردن .ir)
    $url = "https://my.dast-garmi.ir/api/get-question-details.php"
        . "?utid=" . urlencode($utid)
        . "&qid=" . urlencode($qid);

    $log_id = logApiCall('get_question_details', 'user_test', $utid, 'GET', $url, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "cURL Error: $curlError"];
    }
    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return ['success' => false, 'error' => "HTTP Error: $httpCode", 'httpCode' => $httpCode];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid JSON response");
        return ['success' => false, 'error' => 'JSON نامعتبر'];
    }

    $is_success = isset($data['success']) && $data['success'];
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'Unknown error'));
    return $data; // برگرداندن همان پاسخ API
}

function GetUserIdByApi($username)
{
    if (empty($username)) {
        return ['success' => false, 'error' => 'نام کاربری خالی است.'];
    }

    $sync = crossPanelDataSync('student.get', ['username' => (string)$username]);
    return !empty($sync['ok'])
        ? ['success' => true, 'uid' => (int)($sync['uid'] ?? 0)]
        : ['success' => false, 'error' => $sync['message'] ?? 'کاربر یافت نشد', 'uid' => null];

    $apiUrl = "https://my.dast-garmi.ir/api/get-user-by-username.php?username=" . urlencode($username);
    $log_id = logApiCall('get_user_by_username', 'user', $username, 'GET', $apiUrl, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }
    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return ['success' => false, 'error' => "خطا در ارتباط با API (کد: $httpCode)"];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid JSON");
        return ['success' => false, 'error' => 'پاسخ JSON معتبر نیست.'];
    }

    $is_success = isset($data['success']) && $data['success'];
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'User not found'));

    if ($is_success) {
        return ['success' => true, 'uid' => (int)$data['uid']];
    }
    return ['success' => false, 'error' => $data['message'] ?? 'کاربر یافت نشد', 'uid' => null];
}

function GetUserTestIdByApi($uid, $tid)
{
    if (empty($uid) || empty($tid)) {
        return ['success' => false, 'error' => 'uid یا tid خالی است'];
    }

    $apiUrl = "https://my.dast-garmi.ir/api/get-user-test.php?uid=" . urlencode($uid) . "&tid=" . urlencode($tid);
    $log_id = logApiCall('get_user_test', 'user_test', 0, 'GET', $apiUrl, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }
    if ($response === false) {
        updateLogResult($log_id, $httpCode, $response, 0, "Empty response");
        return ['success' => false, 'error' => 'پاسخی دریافت نشد'];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid JSON");
        return ['success' => false, 'error' => 'JSON نامعتبر'];
    }

    $is_success = ($httpCode === 200 && isset($data['success']) && $data['success']);
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'Unknown error'));

    if ($is_success) {
        return ['success' => true, 'utid' => (int)$data['utid']];
    }
    return ['success' => false, 'error' => $data['message'] ?? 'خطای نامشخص', 'utid' => null];
}

function GetTestQuestionsByApi($utid)
{
    if (empty($utid)) {
        return ['success' => false, 'error' => 'utid خالی است'];
    }

    $apiUrl = "https://my.dast-garmi.ir/api/get-user-test-questions.php?utid=" . urlencode($utid);
    $log_id = logApiCall('get_test_questions', 'user_test', $utid, 'GET', $apiUrl, null, null, $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }
    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return ['success' => false, 'error' => "HTTP Error: $httpCode"];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid JSON");
        return ['success' => false, 'error' => 'JSON نامعتبر'];
    }

    $is_success = !empty($data['success']);
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'Unknown error'));

    if ($is_success) {
        return [
            'success' => true,
            'utid' => (int)$data['utid'],
            'count' => (int)($data['count'] ?? 0),
            'questions' => $data['questions'] ?? []
        ];
    }
    return ['success' => false, 'error' => $data['message'] ?? 'داده‌ای یافت نشد'];
}

function CreateUserByApi(array $userData, $requested_by = null)
{
    $sync = crossPanelDataSync('student.upsert', [
        'username' => (string)($userData['username'] ?? ''),
        'password_hash' => (string)($userData['password'] ?? ''),
        'password' => (string)($userData['pass'] ?? ''),
        'active' => isset($userData['active']) ? (int)$userData['active'] : 1,
        'expiration_date' => (string)($userData['expiration_date'] ?? '3021-03-21'),
        'first_name' => (string)($userData['fname'] ?? ''),
        'last_name' => (string)($userData['lname'] ?? ''),
    ]);
    return !empty($sync['ok']) ? ['success' => true, 'uid' => (int)($sync['uid'] ?? 0)] : ['success' => false, 'message' => $sync['message'] ?? 'همگام‌سازی کاربر ناموفق بود'];

    $url = "https://my.dast-garmi.ir/api/create-user.php";
    $log_id = logApiCall('create_user', 'user', 0, 'POST', $url, $userData, ['Content-Type: application/json'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($userData),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($data)) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid response from API");
        return ['success' => false, 'error' => 'پاسخ نامعتبر از API'];
    }

    $is_success = isset($data['success']) && $data['success'];
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'Unknown error'));

    if ($is_success && isset($data['uid'])) {
        global $conn;
        $conn->prepare("UPDATE api_request_log SET target_id = ? WHERE id = ?")->execute([$data['uid'], $log_id]);
    }

    return $data; // برگرداندن عین پاسخ API
}

function CreateGroupByApi($aid, $ugtitle, $ugdescription, $requested_by = null)
{
    $sync = crossPanelDataSync('group.upsert', ['code' => (string)$ugtitle, 'title' => (string)$ugdescription]);
    return !empty($sync['ok']) ? ['success' => true, 'ugid' => (int)($sync['ugid'] ?? 0)] : ['success' => false, 'message' => $sync['message'] ?? 'همگام‌سازی پایه ناموفق بود'];

    $url = "https://my.dast-garmi.ir/api/create-group.php";
    $payload = compact('aid', 'ugtitle', 'ugdescription');
    $log_id = logApiCall('create_group', 'group', 0, 'POST', $url, $payload, ['Content-Type: application/json'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        updateLogResult($log_id, $httpCode, $response, 0, "پاسخ نامعتبر از API (کد HTTP: $httpCode)");
        return ['success' => false, 'error' => "پاسخ نامعتبر از API (کد HTTP: $httpCode)"];
    }

    $is_success = isset($data['success']) ? (bool)$data['success'] : ($httpCode === 200);
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'خطای نامشخص'));

    if ($is_success && isset($data['ugid'])) {
        global $conn;
        $conn->prepare("UPDATE api_request_log SET target_id = ? WHERE id = ?")->execute([$data['ugid'], $log_id]);
    }
    return $data;
}

function AddSubGroupByApi($aid, $ugtitle, $ugdescription, $ugid_parent, $requested_by = null)
{
    $sync = crossPanelDataSync('subgroup.upsert', ['code' => (string)$ugtitle, 'title' => (string)$ugdescription, 'parent_ugid' => (int)$ugid_parent]);
    return !empty($sync['ok']) ? ['success' => true, 'ugid' => (int)($sync['ugid'] ?? 0)] : ['success' => false, 'message' => $sync['message'] ?? 'همگام‌سازی کلاس ناموفق بود'];

    $url = "https://my.dast-garmi.ir/api/add-subgroup.php";
    $payload = compact('aid', 'ugtitle', 'ugdescription', 'ugid_parent');
    $log_id = logApiCall('add_subgroup', 'group', 0, 'POST', $url, $payload, ['Content-Type: application/json'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        updateLogResult($log_id, $httpCode, $response, 0, "پاسخ نامعتبر از API (کد HTTP: $httpCode)");
        return ['success' => false, 'error' => "پاسخ نامعتبر از API (کد HTTP: $httpCode)"];
    }

    $is_success = isset($data['success']) ? (bool)$data['success'] : ($httpCode === 200);
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'خطای نامشخص'));

    if ($is_success && isset($data['subgroup_id'])) {
        global $conn;
        $conn->prepare("UPDATE api_request_log SET target_id = ? WHERE id = ?")->execute([$data['subgroup_id'], $log_id]);
    }
    return $data;
}

function UpdateGroupByApi($ugtitleNew, $ugdescription, $ugtitleOld, $requested_by = null)
{
    $sync = crossPanelDataSync('group.upsert', ['code' => (string)$ugtitleNew, 'old_code' => (string)$ugtitleOld, 'title' => (string)$ugdescription]);
    return !empty($sync['ok']) ? ['success' => true, 'ugid' => (int)($sync['ugid'] ?? 0)] : ['success' => false, 'message' => $sync['message'] ?? 'همگام‌سازی پایه ناموفق بود'];

    $url = "https://my.dast-garmi.ir/api/update-group.php";
    $payload = compact('ugtitleNew', 'ugtitleOld', 'ugdescription');
    $log_id = logApiCall('update_group', 'group', 0, 'POST', $url, $payload, ['Content-Type: application/json'],
        $requested_by ?? $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "خطای شبکه: $curlError"];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($data)) {
        updateLogResult($log_id, $httpCode, $response, 0, "پاسخ نامعتبر از API (کد HTTP: $httpCode)");
        return ['success' => false, 'error' => 'پاسخ نامعتبر از API'];
    }

    $is_success = isset($data['success']) ? (bool)$data['success'] : false;
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'خطای نامشخص'));

    if ($is_success && isset($data['ugid'])) {
        global $conn;
        $conn->prepare("UPDATE api_request_log SET target_id = ? WHERE id = ?")->execute([$data['ugid'], $log_id]);
    }
    return $data;
}

function GetQuizSummaryByApi($username)
{
    $url = "https://my.dast-garmi.ir/api/get-quiz-summary.php";
    $payload = ['username' => $username];
    $log_id = logApiCall('get_quiz_summary', 'user', 0, 'POST', $url, $payload, ['Content-Type: application/json'],
        $_SESSION['admin']['user'] ?? null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        // برای دیباگ موقت (در محیط تولید باید true باشد)
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        updateLogResult($log_id, $httpCode, $response, 0, "cURL Error: $curlError");
        return ['success' => false, 'error' => "cURL Error: $curlError"];
    }

    if ($httpCode !== 200) {
        updateLogResult($log_id, $httpCode, $response, 0, "HTTP Error: $httpCode");
        return ['success' => false, 'error' => "HTTP Error: $httpCode"];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        updateLogResult($log_id, $httpCode, $response, 0, "Invalid JSON response");
        return ['success' => false, 'error' => 'JSON نامعتبر'];
    }

    $is_success = isset($data['success']) && $data['success'];
    updateLogResult($log_id, $httpCode, $response, $is_success ? 1 : 0, $is_success ? null : ($data['message'] ?? 'Unknown error'));
    return $data;
}


function getPackageGroups() {

    $apiUrl = "https://my.dast-garmi.ir/api/get-package-groups.php";
    $apiKey = __DGAPIKEY;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-Key: " . $apiKey,
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        // You can log curl_error($ch) here if debugging
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    if ($httpCode === 200) {
        $decodedData = json_decode($response, true);

        if (isset($decodedData['success']) && $decodedData['success'] === true) {
            return $decodedData['data'];
        }
    }

    return null;
}
