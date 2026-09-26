<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
require_once '../../inc/chahard_rooms.php';
displayerror(1);
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
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

// پشتیبانی از لینک‌های قدیمی (Id/code) و لینک‌های جدید (user/course)
$requestedStudent = trim((string)($_GET['Id'] ?? $_GET['user'] ?? ''));
$requestedCourse = trim((string)($_GET['code'] ?? $_GET['course'] ?? ''));
if ($requestedStudent === '') {
    http_response_code(400);
    exit('شناسه دانش‌آموز ارسال نشده است.');
}
$_GET['Id'] = $requestedStudent;
if ($requestedCourse !== '') {
    $_GET['code'] = $requestedCourse;
}

//if (isset($_GET['generate'])) {
//    if (!act_classSkyroomDemo($_GET['code'])) {
//        // If false, alert the user and refresh/redirect back
//        echo "<script>
//            alert('کلاس اسکای‌روم در حال حاضر فعال نیست.');
//            window.location.href = '" . $_SERVER['PHP_SELF'] . "?Id=" . htmlspecialchars($_GET['Id']) . "';
//        </script>";
//        exit(); // Stop further execution
//    }
//    $student = retPerson($_GET['Id']);
//    $course = returnCourseDemo($_GET['code']);
//    require_once '../../api/Skyroom.php';
//    $apiUrl = __SKY;
//    $api = new Skyroom($apiUrl);
//    $action = 'createLoginUrl';
//    $params = array(
//        'room_id' => intval($course->link_class),
//        'user_id' => $_GET['Id'],
//        'language' => 'fa',
//        'nickname' => $student->fname . ' ' . $student->lname,
//        'access' => 1,
//        'concurrent' => 1,
//        'ttl' => 5400,
//    );
//
//    $result10 = $api->call($action, $params);
//
//    if (Skyroom\HttpError::IsError($result10)) {
//        printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
//    } else {
//        $sky = output($result10);
//        $skytoken = json_decode($sky);
//        $skytoken = $skytoken->{'result'};
//        date_default_timezone_set("Iran");
//        $datenow = date("Y-m-d");
//        $timenow = date('H:i:s');
//        $ip = GetIp();
//        $os = GetOs();
//        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os,is_generated) VALUES (?,?,?,?,?,?,?,?)';
//        $ver = $conn->prepare($token);
//        $ver->execute([$_GET['Id'], $_GET['code'], $datenow, $timenow, $skytoken, $ip, $os, 1]);
//        $_SESSION['skyroom_success'] = [
//            'token' => $skytoken,
//            'student_name' => $student->fname . ' ' . $student->lname,
//            'course' => $course->coursename
//        ];
//
//        header("Location: " . $_SERVER['PHP_SELF'] . "?Id=" . $_GET['Id']);
//        exit();
//    }
//}

if (isset($_GET['generate'])) {
    // (اختیاری) در صورت نیاز تابع act_classSkyroomDemo را با معادل آن برای اتاق مجازی جایگزین کنید
    // اگر همان تابع وضعیت کلاس را در دیتابیس چک می‌کند، می‌توانید همان را نگه دارید
    if (!act_classSkyroomDemo($_GET['code'])) {
        echo "<script>
            alert('کلاس آنلاین در حال حاضر فعال نیست.');
            window.location.href = '" . $_SERVER['PHP_SELF'] . "?Id=" . htmlspecialchars($_GET['Id']) . "';
        </script>";
        exit();
    }

    $student = retPerson($_GET['Id']);
    $course  = returnCourseDemo($_GET['code']);

    // ------------------------------------------------------------
    //  بخش جدید: فراخوانی API سامانه‌ی اتاق مجازی خودتان
    // ------------------------------------------------------------
    $apiUrl = chahard_join_url();
    $params = [
        'roomId'   => trim($course->link_class),  // شناسه اتاق (همان roomId)
        'username' => $_GET['Id'],
        'password' => $_GET['Id'],
        'name'     => $student->fname,
        'lastName' => $student->lname,
        'role'     => 'student'
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . chahard_api_key()
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // بررسی موفقیت‌آمیز بودن درخواست
    $result = json_decode($response, true);
    if ($httpCode == 200 && isset($result['success']) && $result['success'] === true) {
        $joinLink = $result['data']['joinUrl'];   // لینک ورود یک‌بارمصرف نیست (همان لینک ثابت)

        // ------------------------------------------------------------
        //  لاگ‌گیری مشابه قبل (جدول log_skyroom)
        // ------------------------------------------------------------
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();

        $sql = 'INSERT INTO log_skyroom(username, codecourse, datelogin, timelogin, url, ip, os, is_generated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['Id'], $_GET['code'], $datenow, $timenow, $joinLink, $ip, $os, 1]);

        // ذخیره در جلسه برای نمایش پیام موفقیت (اختیاری)
        $_SESSION['skyroom_success'] = [
            'token'        => $joinLink,
            'student_name' => $student->fname . ' ' . $student->lname,
            'course'       => $course->coursename
        ];

        // ریدایرکت به همان صفحه برای نمایش لینک (یا می‌توانید مستقیماً به joinLink بروید)
        header("Location: " . $_SERVER['PHP_SELF'] . "?Id=" . $_GET['Id']);
        exit();
    } else {
        // مدیریت خطا (درخواست ناموفق)
        $errorMsg = isset($result['message']) ? $result['message'] : 'خطا در اتصال به کلاس آنلاین';
        echo "<script>
            alert('$errorMsg');
            window.location.href = '" . $_SERVER['PHP_SELF'] . "?Id=" . htmlspecialchars($_GET['Id']) . "';
        </script>";
        exit();
    }
}
//* End Check Session JUST ONE Session
$user = retPerson($_GET['Id']);
$studentCodemeli = isset($user->codemeli) ? trim($user->codemeli) : '';
$adminUsername = $_SESSION['admin']['user'] ?? '';

$hasOperatorSql = 'SELECT * FROM dbcourse WHERE username = ?';
$hasOperatorStmt = $conn->prepare($hasOperatorSql);
$hasOperatorStmt->execute([$_GET['Id']]);
$thisStudentCourses = $hasOperatorStmt->fetchAll(PDO::FETCH_OBJ);
$hasThisUserNoOperator = true;
foreach ($thisStudentCourses as $course) {
    if ($course->has_operator == 1) {
        $hasThisUserNoOperator = false;
        break;
    }
}
if (isset($_GET['opHandle'])) {
    date_default_timezone_set('Asia/Tehran');
    $sql = 'INSERT INTO cancel_student_operator_request (student_username, requested_by, requested_mode, status, created_at) VALUES (?, ?, 1, 0, ?)';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$studentCodemeli, $adminUsername, date("Y-m-d H:i:s")]);
    $_SESSION['cancel_success'] = 'درخواست لغو انجام شد و در انتظار بررسی است.';
    header('Location: suporting.php?Id=' . urlencode($studentCodemeli));
    exit;
}

$pendingOperatorSql = 'SELECT id FROM cancel_student_operator_request WHERE student_username = ? AND status = 0 LIMIT 1';
$pendingOperatorStmt = $conn->prepare($pendingOperatorSql);
$pendingOperatorStmt->execute([$studentCodemeli]);
if ($pendingOperatorStmt->rowCount() > 0) {
    $pendingOperator = true;
} else {
    $pendingOperator = false;
}

// =========================
// Suspension Request (POST)
// =========================
$suspendFlashErrors = $_SESSION['suspend_flash_errors'] ?? [];
$suspendFlashSuccess = $_SESSION['suspend_flash_success'] ?? [];
unset($_SESSION['suspend_flash_errors'], $_SESSION['suspend_flash_success']);


$isSuspended = false;
$hasPendingSuspendRequest = false;

if ($studentCodemeli !== '') {
    // Check suspended status
    $stS = $conn->prepare("SELECT suspension_mode FROM dbpersonal WHERE codemeli = ? LIMIT 1");
    $stS->execute([$studentCodemeli]);
    $rS = $stS->fetch(PDO::FETCH_ASSOC);

    if ($rS && (int)$rS['suspension_mode'] === 1) {
        $isSuspended = true;
    } else {
        // Check pending request
        $stP = $conn->prepare("SELECT id FROM student_suspension_requests WHERE student_username = ? AND status = 0 LIMIT 1");
        $stP->execute([$studentCodemeli]);
        $hasPendingSuspendRequest = (bool)$stP->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle submit (separate from your other POSTs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_suspend_request'])) {

    $reason = trim($_POST['suspend_reason'] ?? '');
    $postedCodemeli = trim($_POST['student_codemeli'] ?? '');

    $errs = [];

    if ($postedCodemeli === '' || $postedCodemeli !== $studentCodemeli) {
        $errs[] = 'شناسه دانش آموز نامعتبر است.';
    }
    if ($reason === '') {
        $errs[] = 'لطفاً علت تعلیق را وارد کنید.';
    }

    if (empty($errs)) {
        try {
            // Re-check suspended
            $stS2 = $conn->prepare("SELECT suspension_mode FROM dbpersonal WHERE codemeli = ? LIMIT 1");
            $stS2->execute([$postedCodemeli]);
            $rS2 = $stS2->fetch(PDO::FETCH_ASSOC);

            if (!$rS2) {
                $errs[] = 'دانش آموز یافت نشد.';
            } elseif ((int)$rS2['suspension_mode'] === 1) {
                $errs[] = 'این دانش آموز در حالت تعلیق قرار دارد.';
            } else {
                // Re-check pending request
                $stP2 = $conn->prepare("SELECT id FROM student_suspension_requests WHERE student_username = ? AND status = 0 LIMIT 1");
                $stP2->execute([$postedCodemeli]);

                if ($stP2->fetch()) {
                    $errs[] = 'برای این دانش آموز درخواست در حال بررسی وجود دارد.';
                } else {
                    // Insert request (requested_mode=1 means suspend)
                    $stIns = $conn->prepare("
                        INSERT INTO student_suspension_requests
                            (student_username, requested_by, requested_mode, requested_reason, status)
                        VALUES
                            (?, ?, 1, ?, 0)
                    ");
                    $stIns->execute([$postedCodemeli, $adminUsername, $reason]);

                    $_SESSION['suspend_flash_success'] = ['درخواست تعلیق ثبت شد و در انتظار بررسی است.'];
                }
            }
        } catch (Exception $e) {
            $errs[] = 'خطا در ثبت درخواست: ' . $e->getMessage();
        }
    }

    if (!empty($errs)) {
        $_SESSION['suspend_flash_errors'] = $errs;
    }

    // PRG redirect: prevents "resubmission request" on refresh
    header('Location: suporting.php?Id=' . urlencode($studentCodemeli));
    exit;
}


// ==== DEBUG: check attendance counters for a specific course & student ====
if (isset($_GET['debug_att']) && $_GET['debug_att'] == '1') {

    // ⬇️ set the course & student you want to inspect
    $debugCodecourse = 140422;          // your course code
    $debugUser = '0252415086';    // your student codemeli / username

    if (function_exists('getTblattendance')) {
        $dbg = getTblattendance($debugCodecourse, $debugUser);

        echo '<pre style="background:#222;color:#0f0;padding:10px;margin:10px;border-radius:5px;">';
        echo "DEBUG getTblattendance({$debugCodecourse}, {$debugUser})\n";
        echo "hozor  (حاضر):  " . ($dbg[0] ?? 'N/A') . "\n";
        echo "ghayeb (غایب): " . ($dbg[1] ?? 'N/A') . "\n";
        echo "</pre>";
    } else {
        echo '<pre style="background:#222;color:#f00;padding:10px;margin:10px;border-radius:5px;">';
        echo "getTblattendance() not found!";
        echo "</pre>";
    }
}


if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}

date_default_timezone_set('Asia/Tehran');

$username = $_SESSION['admin']['user'] ?? null;

$suport = getsuport_user($user->codemeli);
date_default_timezone_set("Iran");
$datenow = date("Y-m-d");
$datenow = explode('-', $datenow);
$datenow = gregorian_to_jalali($datenow[0], $datenow[1], $datenow[2]);
$datenow = implode('/', $datenow);
//Start Calling
if (isset($_GET['calls']) and $_GET['calls'] == 'true') {

    $semat = 'دانش آموز';
    $tel = $user->tel1;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['callp']) and $_GET['callp'] == 'true') {

    $semat = 'پدر ';
    $tel = $user->tel3;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['callm']) and $_GET['callm'] == 'true') {

    $semat = 'مادر';
    $tel = $user->tel2;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}

if (isset($_GET['callh']) and $_GET['callh'] == 'true') {

    $semat = 'خانه';
    $tel_home = $user->tel_home;
    $name = $semat . ' ' . $user->fname . ' ' . $user->lname;

    if (!empty($tel_home)) {
        Call($tel_home, $_SESSION['admin']['dakheli'], $name);
        $_SESSION['call'] = array("name" => $name);
    } else {
        $_SESSION['call_message'] = 'این دانش‌آموز شماره تلفن ثابت ندارد.';
    }

    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
//end Calling
if (isset($_GET['changepublic'])) {
    if ($suport) {
        $userchange = $_GET['changepublic'];
        $update = 'UPDATE suport_user SET typesupport=? WHERE username=? ';
        $query1 = $conn->prepare($update);
        $query1->execute([0, $userchange]);
        header('location: suporting.php?Id=' . $_GET['changepublic'] . ' ');
        die();
    } else {
        header('location: suporting.php?Id=' . $_GET['changepublic'] . ' ');
        die();
    }
}
if (isset($_GET['changevip'])) {
    if ($suport) {
        $userchange = $_GET['changevip'];
        $update = 'UPDATE suport_user SET typesupport=? WHERE username=? ';
        $query1 = $conn->prepare($update);
        $query1->execute([1, $userchange]);
        header('location: suporting.php?Id=' . $_GET['changevip'] . ' ');
        die();
    } else {
        header('location: suporting.php?Id=' . $_GET['changevip'] . ' ');
        die();
    }
}
if (isset($_POST['addposhtibani'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (!$suport) {
            $last = $_POST['lastdate'];
            $last = explode('/', $last);
            $last = jalali_to_gregorian($last[0], $last[1], $last[2]);
            $last = implode('-', $last);
            $after = $_POST['afterdate'];
            $after = explode('/', $after);
            $after = jalali_to_gregorian($after[0], $after[1], $after[2]);
            $after = implode('-', $after);
            $sql = 'INSERT INTO suport_user (username,lastdate,afterdate) VALUES (?,?,?)';
            $query2 = $conn->prepare($sql);
            $query2->execute([$user->codemeli, $last, $after]);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $type = $_POST['noposhtibani'];
            $status = $_POST['status'];
            $priority = (int)($_POST['priority'] ?? 0);
            if (!in_array($priority, [0, 1, 2], true)) $priority = 0;
            $ip = GetIp();
            $os = GetOs();
            $detail = $_POST['detail'];
            $poshtiban = $_SESSION['admin']['user'];
            $sql = 'INSERT INTO suport_report(username,`type`,status,datesupport,detail,poshtiban,priority,ip,os,datenow,timenow)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)';
            $qr = $conn->prepare($sql);
            $qr->execute([$user->codemeli, $type, $status, $last, $detail, $poshtiban, $priority, $ip, $os, $datenow, $timenow]);
            if (isset($_POST['sendtime']) and $_POST['sendtime'] == 'on') {
                $sender_type = 'admin';
                $sender_username = $_SESSION['admin']['user'];
                $sender_ip = GetIp();
                $receiver_type = 'student';
                $receiver_username = $_GET['Id'];
                $after = $_POST['afterdate'];
                $message_text = "تاریخ پشتیبانی بعدی شما به $after انتقال یافت";
                $status = 'sent';
                date_default_timezone_set('Asia/Tehran');
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $sql = "INSERT INTO suport_message(sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time) VALUES (?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $sender_ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
            header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
            die();
        } else {
            $last = $_POST['lastdate'];
            $last = explode('/', $last);
            $last = jalali_to_gregorian($last[0], $last[1], $last[2]);
            $last = implode('-', $last);
            $after = $_POST['afterdate'];
            $after = explode('/', $after);
            $after = jalali_to_gregorian($after[0], $after[1], $after[2]);
            $after = implode('-', $after);
            $sql = 'UPDATE suport_user SET lastdate=?,afterdate=? WHERE username=? ';
            $query2 = $conn->prepare($sql);
            $query2->execute([$last, $after, $user->codemeli]);
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $type = $_POST['noposhtibani'];
            $status = $_POST['status'];
            $priority = (int)($_POST['priority'] ?? 0);
            if (!in_array($priority, [0, 1, 2], true)) $priority = 0;
            $ip = GetIp();
            $os = GetOs();
            $detail = $_POST['detail'];
            $poshtiban = $_SESSION['admin']['user'];
            $sql = 'INSERT INTO suport_report(username,`type`,status,datesupport,detail,poshtiban,priority,ip,os,datenow,timenow)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)';
            $qr = $conn->prepare($sql);
            $qr->execute([$user->codemeli, $type, $status, $last, $detail, $poshtiban, $priority, $ip, $os, $datenow, $timenow]);

            if (isset($_POST['sendtime']) and $_POST['sendtime'] == 'on') {
                $sender_type = 'admin';
                $sender_username = $_SESSION['admin']['user'];
                $sender_ip = GetIp();
                $receiver_type = 'student';
                $receiver_username = $_GET['Id'];
                $after = $_POST['afterdate'];
                $message_text = "تاریخ پشتیبانی بعدی شما به $after انتقال یافت";
                $status = 'sent';
                date_default_timezone_set('Asia/Tehran');
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $sql = "INSERT INTO suport_message(sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time) VALUES (?,?,?,?,?,?,?,?,?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $sender_ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
            header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
            die();
        }
    } else {
        echo '<script>window.alert("خطای غیر منتظره رخ داد ") </script>';
    }
}
if (isset($_GET['delsupport'])) {
    $delid = intval($_GET['delsupport']);
    $sql = 'DELETE FROM suport_report WHERE id=? ';
    $del = $conn->prepare($sql);
    $del->execute([$delid]);
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_GET['delhistorysupport']) and $_GET['delhistorysupport'] == 'true') {
    $sql = 'DELETE FROM suport_report WHERE username=? ';
    $del = $conn->prepare($sql);
    $del->execute([$_GET['Id']]);
    header('location: suporting.php?Id=' . $_GET['Id'] . ' ');
    die();
}
if (isset($_POST['addaboutsuporter'])) {
    if (isset($_SESSION['token']) && $_SESSION['token'] === $_POST['token']) {
        $sql = "INSERT INTO student_supporter_comments (codemeli, comment, created_by, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['Id'], $_POST['about_suporter'], $_SESSION['admin']['user']]);

        header('location: suporting.php?Id=' . $_GET['Id']);
        die();
    } else {
        echo '<script>window.alert("خطای غیر منتظره رخ داد ") </script>';
    }
}
$sql = 'SELECT * FROM suport_message WHERE sender_username = ? AND status = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$_GET['Id'], 'sent']);
if ($stmt->rowCount() > 0) {
    $update = 'UPDATE suport_message SET status = ? WHERE sender_username = ?';
    $stmt = $conn->prepare($update);
    $stmt->execute(['seen', $_GET['Id']]);
}
if (isset($_POST['sub'])) {
    if (isset($_SESSION['tokenn']) and $_SESSION['tokenn'] == $_POST['token']) {
        $message_text = $_POST['txt'];
        $sender_username = $_SESSION['admin']['user'];
        $sender_type = 'admin';
        $ip = GetIp();
        $receiver_type = 'student';
        $receiver_username = $_GET['Id'];
        $status = 'sent';
        if ($_FILES['fileto'] and $_FILES['fileto']['error'] === 0 and $_FILES['fileto']['size'] > 0) {
            $maxSize = 3 * 1024 * 1024;
            $file = $_FILES['fileto'];
            if ($_FILES['fileto']['size'] > $maxSize) {
                $error = 1;
                $errorMessage = 'حجم فایل نباید بیشتر از 3 مگابایت باشد.';
            } else {
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                $fileType = mime_content_type($file['tmp_name']);

                if (!in_array($fileType, $allowedTypes)) {
                    $error = 1;
                    $errorMessage = 'فرمت فایل بارگزاری شده مجاز نیست. فرمت های مجاز : png,jpg,jpeg,pdf';
                } else {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/public';
                    $fileName = uniqid() . '_' . basename($file['name']);
                    $uploadPath = $uploadDir . '/' . $fileName;
                    $upload_path_for_db = '/assets/public/' . $fileName;

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        date_default_timezone_set('Asia/Tehran');
                        $datenow = date("Y-m-d");
                        $timenow = date('H:i:s');
                        if (isset($_GET['edit'])) {
                            $sql = 'SELECT * FROM suport_message WHERE id = ?';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$_GET['edit']]);
                            $message = $stmt->fetch(PDO::FETCH_OBJ);
                            $datetime = $message->message_date . ' ' . $message->message_time;
                            if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
                                if (!empty($message->file_path)) {
                                    $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
                                    if (file_exists($file_path)) {
                                        unlink($file_path);
                                    }
                                }
                                $sql = 'UPDATE suport_message SET sender_username = ?, message_text = ?, file_path = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$_SESSION['admin']['user'], $message_text, $upload_path_for_db, $datenow, $timenow, $_GET['edit']]);
                                header('Location: suporting.php?Id=' . $_GET['Id']);
                                die();
                            } else {
                                $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                                header('Location: suporting.php?Id=' . $_GET['Id']);
                                die();
                            }
                        } else {
                            $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,file_path,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?,?)';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message_text, $status, $upload_path_for_db, $datenow, $timenow]);
                        }
                    }
                }
            }
        } elseif (!empty($_FILES['fileto']['name']) and $_FILES['fileto']['error']) {
            $error = 1;
            $errorMessage = 'خطای غیرمنتظره در بارگزاری فایل ! حجم و فرمت فایل را چک نمایید';
        } else {
            date_default_timezone_set('Asia/Tehran');
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            if (isset($_GET['edit'])) {
                $sql = 'SELECT * FROM suport_message WHERE id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$_GET['edit']]);
                $message = $stmt->fetch(PDO::FETCH_OBJ);
                $datetime = $message->message_date . ' ' . $message->message_time;
                if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
                    $sql = 'UPDATE suport_message SET sender_username = ?, message_text = ?, edit_message_date = ?, edit_message_time = ? WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_SESSION['admin']['user'], $message_text, $datenow, $timenow, $_GET['edit']]);
                    header('Location: suporting.php?Id=' . $_GET['Id']);
                    die();
                } else {
                    $_SESSION['error_message'] = 'امکان ویرایش پیام بعد از یک ساعت وجود ندارد.';
                    header('Location: suporting.php?Id=' . $_GET['Id']);
                    die();
                }
            } else {
                $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?)';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message_text, $status, $datenow, $timenow]);
            }
        }
    }
}

if (isset($_POST['send-sms'])) {
    if (isset($_SESSION['token_sms']) && $_SESSION['token_sms'] === $_POST['token']) {
        $sms_text = trim($_POST['sms_text']);
        $student_id = $_GET['Id'];
        $sender_admin = $_SESSION['admin']['user'];

        if (!empty($sms_text)) {
            // تنظیمات ارسال پیامک
            $username = '09127945631'; // نام کاربری پیامکی
            $password = '&Amoozesh!1404%'; // پسورد پیامکی
            $from = '50004001945631'; // شماره خط پیامکی

            $recipients = [];
            $success_count = 0;
            $failed_count = 0;

            // بررسی گیرندگان انتخاب شده و ارسال پیامک
            if (isset($_POST['send_to_student']) && $_POST['send_to_student'] == '1' && !empty($user->tel1)) {
                $to = $user->tel1;
                $result = sendSmss($username, $password, $from, $to, $sms_text);
                $recipients[] = ['type' => 'student', 'phone' => $to, 'status' => $result['status'], 'details' => $result['message']];
                $result['success'] ? $success_count++ : $failed_count++;
            }

            if (isset($_POST['send_to_father']) && $_POST['send_to_father'] == '1' && !empty($user->tel3)) {
                $to = $user->tel3;
                $result = sendSmss($username, $password, $from, $to, $sms_text);
                $recipients[] = ['type' => 'father', 'phone' => $to, 'status' => $result['status'], 'details' => $result['message']];
                $result['success'] ? $success_count++ : $failed_count++;
            }

            if (isset($_POST['send_to_mother']) && $_POST['send_to_mother'] == '1' && !empty($user->tel2)) {
                $to = $user->tel2;
                $result = sendSmss($username, $password, $from, $to, $sms_text);
                $recipients[] = ['type' => 'mother', 'phone' => $to, 'status' => $result['status'], 'details' => $result['message']];
                $result['success'] ? $success_count++ : $failed_count++;
            }

            // ثبت در دیتابیس
            if (!empty($recipients)) {
                date_default_timezone_set('Asia/Tehran');
                $datenow = date("Y-m-d");
                $timenow = date("H:i:s");

                $status = ($success_count > 0) ? 'ارسال شده' : 'ناموفق';
                $recipients_json = json_encode($recipients, JSON_UNESCAPED_UNICODE);

                $insert = "INSERT INTO sms_logs (student_id, message_text, recipients, sender_admin, send_date, send_time, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert);
                $stmt->execute([$student_id, $sms_text, $recipients_json, $sender_admin, $datenow, $timenow, $status]);

                // نمایش پیام موفقیت
                if ($success_count > 0) {
                    $_SESSION['sms_success'] = "پیامک با موفقیت به $success_count نفر ارسال شد.";
                    if ($failed_count > 0) {
                        $_SESSION['sms_success'] .= " ($failed_count نفر ارسال نشد)";
                    }
                } else {
                    $_SESSION['sms_error'] = "ارسال پیامک با شکست مواجه شد.";
                }
            } else {
                $_SESSION['sms_error'] = "هیچ گیرنده معتبری انتخاب نشده است.";
            }
        } else {
            $_SESSION['sms_error'] = "متن پیامک نمی‌تواند خالی باشد.";
        }

        // بازگشت به صفحه
        header('Location: suporting.php?Id=' . $_GET['Id']);
        die();
    } else {
        echo '<script>window.alert("توکن نامعتبر است!")</script>';
    }
}

// تابع ارسال پیامک با هندل کردن تمام وضعیت‌ها
function sendSmss($username, $password, $from, $to, $text)
{
    $data = [
        'username' => $username,
        'password' => $password,
        'to' => $to,
        'from' => $from,
        'text' => $text
    ];

    $ch = curl_init('https://rest.payamak-panel.com/api/SendSMS/SendSMS');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);

    // بررسی پاسخ API
    $response_data = json_decode($response, true);

    // مقدار پیش‌فرض
    $status_message = '';
    $success = false;

    // لیست مقادیر خطا از مستندات (همه مقادیر غیر از recId یکتا)
    $error_values = [
        -110,
        -109,
        -108,  // مقادیر منفی
        0,
        2,
        3,
        4,
        5,
        6,
        7,
        9,
        10,
        11,
        12,
        14,
        15,
        16,
        17,
        18,
        35  // مقادیر مثبت خطا
    ];

    // بررسی وضعیت پاسخ
    if (isset($response_data['RetStatus'])) {
        // شرط اصلی: RetStatus باید 1 باشد، StrRetStatus باید 'Ok' باشد
        // و Value باید وجود داشته باشد و در لیست مقادیر خطا نباشد
        if (
            $response_data['RetStatus'] == 1 &&
            isset($response_data['StrRetStatus']) &&
            $response_data['StrRetStatus'] == 'Ok' &&
            isset($response_data['Value']) &&
            !in_array($response_data['Value'], $error_values)
        ) {
            // ارسال موفقیت‌آمیز - Value یک recId یکتا است
            $success = true;
            $status_message = 'ارسال موفقیت‌آمیز (recId: ' . $response_data['Value'] . ')';
        } else {
            // بررسی دقیق خطاها بر اساس Value
            if (isset($response_data['Value'])) {
                $value = $response_data['Value'];

                // بررسی همه وضعیت‌های خطا
                switch ($value) {
                    case -110:
                        $status_message = 'الزام استفاده از ApiKey به جای رمز عبور';
                        break;
                    case -109:
                        $status_message = 'الزام تنظیم IP مجاز برای استفاده از API';
                        break;
                    case -108:
                        $status_message = 'مسدود شدن IP به دلیل تلاش ناموفق استفاده از API';
                        break;
                    case 0:
                        $status_message = 'نام کاربری یا رمز عبور اشتباه می باشد';
                        break;
                    case 2:
                        $status_message = 'اعتبار کافی نمی باشد';
                        break;
                    case 3:
                        $status_message = 'محدودیت در ارسال روزانه';
                        break;
                    case 4:
                        $status_message = 'محدودیت در حجم ارسال';
                        break;
                    case 5:
                        $status_message = 'شماره فرستنده معتبر نمی باشد';
                        break;
                    case 6:
                        $status_message = 'سامانه در حال بروزرسانی می باشد';
                        break;
                    case 7:
                        $status_message = 'متن حاوی کلمه فیلتر شده می باشد';
                        break;
                    case 9:
                        $status_message = 'ارسال از خطوط عمومی از طریق وب سرویس امکان پذیر نمی باشد';
                        break;
                    case 10:
                        $status_message = 'کاربر مورد نظر فعال نمی باشد';
                        break;
                    case 11:
                        $status_message = 'ارسال نشده';
                        break;
                    case 12:
                        $status_message = 'مدارک کاربر کامل نمی باشد';
                        break;
                    case 14:
                        $status_message = 'متن حاوی لینک می باشد';
                        break;
                    case 15:
                        $status_message = 'ارسال به بیش از 1 شماره همراه بدون درج "لغو11" ممکن نیست';
                        break;
                    case 16:
                        $status_message = 'شماره گیرنده ای یافت نشد';
                        break;
                    case 17:
                        $status_message = 'متن پیامک خالی می باشد';
                        break;
                    case 18:
                        $status_message = 'شماره گیرنده نامعتبر است';
                        break;
                    case 35:
                        $status_message = 'وجود شماره در لیست سیاه مخاطرات';
                        break;
                    default:
                        // اگر Value یک عدد بزرگ است (احتمالاً recId یکتا) اما شرایط اولیه برقرار نبوده
                        if (is_numeric($value) && $value > 1000) {
                            $success = true;
                            $status_message = 'ارسال موفقیت‌آمیز (recId: ' . $value . ')';
                        } else {
                            // بررسی RetStatus به عنوان جایگزین
                            $retStatus = $response_data['RetStatus'];
                            if (isset($response_data['StrRetStatus'])) {
                                $status_message = 'خطا: ' . $response_data['StrRetStatus'] . ' (RetStatus: ' . $retStatus . ', Value: ' . $value . ')';
                            } else {
                                $status_message = 'خطا (RetStatus: ' . $retStatus . ', Value: ' . $value . ')';
                            }
                        }
                }
            } // اگر Value وجود ندارد
            else {
                $retStatus = $response_data['RetStatus'];
                if (isset($response_data['StrRetStatus'])) {
                    $status_message = 'خطا: ' . $response_data['StrRetStatus'] . ' (RetStatus: ' . $retStatus . ')';
                } else {
                    $status_message = 'خطا (RetStatus: ' . $retStatus . ')';
                }
            }
        }
    } // اگر RetStatus وجود ندارد
    else {
        $status_message = 'پاسخ نامعتبر از سرور پیامکی';
    }

    return [
        'success' => $success,
        'status' => $success ? 'ارسال شده' : 'ناموفق',
        'message' => $status_message,
        'response' => $response
    ];
}

if (isset($_GET['Id']) and isset($_GET['send'])) {
    $sql = "SELECT * FROM suport_message_constant WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['send']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    if (isset($_SESSION['token'])) {
        $sender_username = $_SESSION['admin']['user'];
        $sender_type = 'admin';
        $ip = GetIp();
        $receiver_type = 'student';
        $receiver_username = $_GET['Id'];
        $status = 'sent';
        date_default_timezone_set('Asia/Tehran');
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $sql = 'INSERT INTO suport_message (sender_type,sender_username,sender_ip,receiver_type,receiver_username,message_text,status,message_date,message_time) VALUES (?,?,?,?,?,?,?,?,?)';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$sender_type, $sender_username, $ip, $receiver_type, $receiver_username, $message->content, $status, $datenow, $timenow]);
    }
}

if (isset($_GET['delm'])) {
    $sql = 'SELECT * FROM suport_message WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delm']]);
    $message = $stmt->fetch(PDO::FETCH_OBJ);
    $datetime = $message->message_date . ' ' . $message->message_time;
    if (time() - strtotime($datetime) <= 3600 or Permision('permision_manage_chat')) {
        if ($message && !empty($message->file_path)) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $sql = 'DELETE FROM suport_message WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['delm']]);
        header('Location: suporting.php?Id=' . $_GET['Id']);
        die();
    } else {
        $_SESSION['error_message'] = 'امکان حذف پیام بعد از یک ساعت وجود ندارد.';
        header('Location: suporting.php?Id=' . $_GET['Id']);
        die();
    }
}

if (isset($_GET['delallmessage'])) {
    $select = "SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ?";
    $query = $conn->prepare($select);
    $query->execute([$_GET['Id'], $_GET['Id']]);
    $messages = $query->fetchAll(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        foreach ($messages as $message) {
            if ($message and !empty($message->file_path)) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

    $sql = "DELETE FROM suport_message WHERE sender_username = ? OR receiver_username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['Id'], $_GET['Id']]);
    header('Location: suporting.php?Id=' . $_GET['Id']);
    die();
}
//here
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course-statuss'])) {

    // 2. Sanitize and validate inputs
    $student_id = htmlspecialchars(strip_tags(trim($_POST['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    $exam_type = htmlspecialchars(strip_tags(trim($_POST['exam_type'] ?? '')), ENT_QUOTES, 'UTF-8');
    $registered = htmlspecialchars(strip_tags(trim($_POST['registered'] ?? '')), ENT_QUOTES, 'UTF-8');
    $attended = htmlspecialchars(strip_tags(trim($_POST['attended'] ?? '')), ENT_QUOTES, 'UTF-8');
    $not_registered_reason = htmlspecialchars(strip_tags(trim($_POST['not_registered_reason'] ?? '')), ENT_QUOTES, 'UTF-8');
    $result = htmlspecialchars(strip_tags(trim($_POST['result'] ?? '')), ENT_QUOTES, 'UTF-8');

    // 3. Validation

    $errors = [];

    if (!$student_id)
        $errors[] = "شناسه دانش آموز الزامی است";
    if (!$exam_type)
        $errors[] = "نوع آزمون الزامی است";
    if (!$registered)
        $errors[] = "وضعیت ثبت نام الزامی است";

    if ($registered === 'ثبت نام کرده') {
        // Only validate attended and result if registered
        if (!$attended)
            $errors[] = "وضعیت آزمون الزامی است";
        if (!$result)
            $errors[] = "نتیجه آزمون الزامی است";
        $not_registered_reason = NULL; // no reason if they registered
    } else {
        // Only validate reason if not registered
        if (!$not_registered_reason)
            $errors[] = "دلیل ثبت نام نکردن الزامی است";
        $attended = NULL; // no exam if not registered
        $result = NULL; // no result if not registered
    }


    // 4. Insert into DB using prepared statements
    $sql = "INSERT INTO course_status 
            (student_id, exam_type, registered, attended,not_registered_reason, result) 
            VALUES 
            (:student_id, :exam_type, :registered, :attended,:not_registered_reason, :result)";

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':exam_type', $exam_type);
    $stmt->bindValue(':registered', $registered);
    $stmt->bindValue(':attended', $attended);
    $stmt->bindValue(':not_registered_reason', $not_registered_reason);
    $stmt->bindValue(':result', $result);

    if ($stmt->execute()) {
        echo "<p style='color:green;'>اطلاعات با موفقیت ثبت شد.</p>";
    } else {
        echo "<p style='color:red;'>خطا در ثبت اطلاعات!</p>";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course-status'])) {

    // ---------------- 1️⃣ Sanitize Inputs ----------------
    $student_id = trim($_POST['student_id'] ?? '');
    $exam_type = trim($_POST['exam_type'] ?? '');
    $registered = trim($_POST['registered'] ?? '');
    $attended = trim($_POST['attended'] ?? '');
    $result = trim($_POST['result'] ?? '');
    $not_registered_reason = trim($_POST['not_registered_reason'] ?? '');
    $not_attended_reason = trim($_POST['not_taken_reason'] ?? ''); // جدید
    $olympiad_fields = $_POST['olympiad_field'] ?? [];

    $errors = [];

    // ---------------- 2️⃣ Base Validation ----------------
    if (!$student_id)
        $errors[] = "شناسه دانش آموز الزامی است";
    if (!$exam_type)
        $errors[] = "نوع آزمون الزامی است";
    if (!$registered)
        $errors[] = "وضعیت ثبت نام الزامی است";

    // المپیاد
    if ($exam_type === 'المپیاد') {
        if (empty($olympiad_fields) || !is_array($olympiad_fields)) {
            $errors[] = "حداقل یک رشته المپیاد انتخاب کنید";
        }
    } else {
        $olympiad_fields = null;
    }

    // ثبت‌نام
    if ($registered === 'ثبت نام کرده') {

        if (!$attended)
            $errors[] = "وضعیت آزمون الزامی است";

        if ($attended === 'آزمون داده') {
            if (!$result)
                $errors[] = "نتیجه آزمون الزامی است";
            $not_attended_reason = null; // ❌ فقط برای آزمون نداده نیست
        } elseif ($attended === 'آزمون نداده') {
            if (!$not_attended_reason)
                $errors[] = "علت ندادن آزمون الزامی است";
            $result = null; // نتیجه وجود ندارد
        } else { // زمان آزمون فرا نرسیده است
            $result = null;
            $not_attended_reason = null;
        }

        $not_registered_reason = null; // چون ثبت‌نام کرده
    } else { // ثبت‌نام نکرده یا بررسی گردد
        if (!$not_registered_reason)
            $errors[] = "علت ثبت نام نکردن الزامی است";
        $attended = null;
        $result = null;
        $not_attended_reason = null;
    }

    // ---------------- 3️⃣ Stop if Errors ----------------
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
        exit;
    }

    // ---------------- 4️⃣ Prepare Data ----------------
    $olympiad_fields_json = $olympiad_fields ? json_encode($olympiad_fields, JSON_UNESCAPED_UNICODE) : null;

    // ---------------- 5️⃣ Insert into DB ----------------
    $sql = "INSERT INTO course_status 
        (student_id, exam_type, registered, attended, not_registered_reason, result, olympiad_fields, not_attended_reason) 
        VALUES 
        (:student_id, :exam_type, :registered, :attended, :not_registered_reason, :result, :olympiad_fields, :not_attended_reason)";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':exam_type', $exam_type);
    $stmt->bindValue(':registered', $registered);
    $stmt->bindValue(':attended', $attended);
    $stmt->bindValue(':not_registered_reason', $not_registered_reason);
    $stmt->bindValue(':result', $result);
    $stmt->bindValue(':olympiad_fields', $olympiad_fields_json);
    $stmt->bindValue(':not_attended_reason', $not_attended_reason);

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        echo "<p style='color:red;'>خطا در ثبت اطلاعات!</p>";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student-status'])) {

    // 2. Sanitize and validate inputs
    $student_id = htmlspecialchars(strip_tags(trim($_POST['student_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    $avg_first_semester = htmlspecialchars(strip_tags(trim($_POST['avg_first_semester'] ?? '')), ENT_QUOTES, 'UTF-8');
    $avg_second_semester = htmlspecialchars(strip_tags(trim($_POST['avg_second_semester'] ?? '')), ENT_QUOTES, 'UTF-8');
    $student_comment = htmlspecialchars(strip_tags(trim($_POST['student_comment'] ?? '')), ENT_QUOTES, 'UTF-8');
    $supporter_comment = htmlspecialchars(strip_tags(trim($_POST['supporter_comment'] ?? '')), ENT_QUOTES, 'UTF-8');

    // 3. Validation
    $errors = [];
    if (!$student_id)
        $errors[] = "شناسه دانش آموز الزامی است";

    if (empty($avg_first_semester) || mb_strlen($avg_first_semester) > 50) {
        $errors[] = "میانگین نیمسال اول نباید خالی باشد و باید حداکثر ۵۰ کاراکتر باشد";
    }

    if (empty($avg_second_semester) || mb_strlen($avg_second_semester) > 50) {
        $errors[] = "میانگین نیمسال دوم نباید خالی باشد و باید حداکثر ۵۰ کاراکتر باشد";
    }

    if (count($errors) > 0) {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
        exit;
    }


    // 4. Insert into DB using prepared statements
    $sql = "INSERT INTO student_status 
            (student_id, avg_first_semester, avg_second_semester, student_comment, supporter_comment) 
            VALUES 
            (:student_id, :avg_first_semester, :avg_second_semester, :student_comment, :supporter_comment)";

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':avg_first_semester', $avg_first_semester);
    $stmt->bindValue(':avg_second_semester', $avg_second_semester);
    $stmt->bindValue(':student_comment', $student_comment);
    $stmt->bindValue(':supporter_comment', $supporter_comment);

    if ($stmt->execute()) {
        echo "<p style='color:green;'>اطلاعات با موفقیت ثبت شد.</p>";
    } else {
        echo "<p style='color:red;'>خطا در ثبت اطلاعات!</p>";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}


$studentCourseStatusRows = [];

if (isset($_GET['Id'])) {
    $studentId = trim($_GET['Id']);
    $sql = "SELECT * FROM course_status WHERE student_id = :student_id ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_STR);
    $stmt->execute();
    $studentCourseStatusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $studentCourseStatusRows = [];
}

$studentStatusRows = [];

if (isset($_GET['Id'])) {
    $studentId = trim($_GET['Id']);
    $sql = "SELECT * FROM student_status WHERE student_id = :student_id ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':student_id', $studentId, PDO::PARAM_STR);
    $stmt->execute();
    $studentStatusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $studentStatusRows = [];
}


function getAcademicYearBaseJalali(int $jy, int $jm, int $jd): int
{
    // From 1 Tir (month 4, day 1) and later -> academic year starts this year
    if ($jm > 4 || ($jm === 4 && $jd >= 1)) {
        return $jy;
    }
    // Before 1 Tir -> still in previous academic year
    return $jy - 1;
}

// ---------- Filter both status lists to ONLY current academic year ----------

$nowTs = time();
$currentJy = (int)jdate('Y', $nowTs, '', '', 'en');
$currentJm = (int)jdate('m', $nowTs, '', '', 'en');
$currentJd = (int)jdate('d', $nowTs, '', '', 'en');

// Base year of the *current* academic year (e.g. 1403 for 1403-1404)
$currentAcademicBase = getAcademicYearBaseJalali($currentJy, $currentJm, $currentJd);

// Helper to keep only rows whose created_at is inside the current academic year
$filterByCurrentAcademicYear = function (array $rows) use ($currentAcademicBase) {
    $filtered = [];

    foreach ($rows as $row) {
        if (empty($row['created_at'])) {
            // No date => treat as old (only visible in history page)
            continue;
        }

        $ts = strtotime($row['created_at']);
        if ($ts <= 0) {
            continue;
        }

        $jy = (int)jdate('Y', $ts, '', '', 'en');
        $jm = (int)jdate('m', $ts, '', '', 'en');
        $jd = (int)jdate('d', $ts, '', '', 'en');

        $rowBase = getAcademicYearBaseJalali($jy, $jm, $jd);

        if ($rowBase === $currentAcademicBase) {
            $filtered[] = $row;
        }
    }

    return $filtered;
};

$getAcademicYearFilter = function (string $tableName, string $startDate, string $endDate, string $tableAlias = ''): string {
    $prefix = $tableAlias ? rtrim($tableAlias, '.') . '.' : '';

    // Extract Y-m-d component if full datetimes are passed
    $startDateOnly = substr($startDate, 0, 10);
    $endDateOnly   = substr($endDate, 0, 10);

    switch ($tableName) {
        // Chat messages table: Uses split date and time columns
        case 'suport_message':
            return "({$prefix}message_date IS NOT NULL AND " .
                "CONCAT({$prefix}message_date, ' ', COALESCE({$prefix}message_time, '00:00:00')) " .
                "BETWEEN '{$startDate}' AND '{$endDate}')";

        // Support reports: Uses separate datesupport / datenow fields
        case 'suport_report':
            return "({$prefix}datenow IS NOT NULL AND " .
                "CONCAT({$prefix}datenow, ' ', COALESCE({$prefix}timenow, '00:00:00')) " .
                "BETWEEN '{$startDate}' AND '{$endDate}')";

        case 'sms_logs':
            return "({$p}send_date IS NOT NULL AND " .
                "CAST(CONCAT({$p}send_date, ' ', COALESCE({$p}send_time, '00:00:00')) AS DATETIME) " .
                "BETWEEN '{$start}' AND '{$end}')";

        // Daily aggregate VoIP calls table: Uses single DATE column
        case 'simotel_calls_data':
            return "({$prefix}snapshot_day BETWEEN '{$startDateOnly}' AND '{$endDateOnly}')";

        // Course assignments and operator assignment history
        case 'dbcourse':
            return "(({$prefix}first_suporter_assigned_at BETWEEN '{$startDate}' AND '{$endDate}') OR " .
                "({$prefix}suporter_updated_at BETWEEN '{$startDate}' AND '{$endDate}') OR " .
                "({$prefix}created_at BETWEEN '{$startDate}' AND '{$endDate}'))";

        // Tickets & Ticket Messages
        case 'admin_tickets':
            return "(({$prefix}created_at BETWEEN '{$startDate}' AND '{$endDate}') OR " .
                "({$prefix}first_suporter_assigned_at BETWEEN '{$startDate}' AND '{$endDate}'))";

        // Standard tables using single datetime/timestamp columns (calls, admin_messages, sales_followup, etc.)
        case 'calls':
        case 'admin_messages':
        case 'cancel_student_operator_request':
        case 'student_suspension_requests':
            return "({$prefix}created_at BETWEEN '{$startDate}' AND '{$endDate}')";

        case 'sales_followup':
            return "({$prefix}assign_date BETWEEN '{$startDate}' AND '{$endDate}')";

        default:
            return "({$prefix}created_at BETWEEN '{$startDate}' AND '{$endDate}')";
    }
};

// Apply filter to both tables used in the “پایان سال” card
$studentCourseStatusRows = $filterByCurrentAcademicYear($studentCourseStatusRows);
$studentStatusRows = $filterByCurrentAcademicYear($studentStatusRows);

if (isset($_POST['delete_exam_status_id'])) {
    $idToDelete = (int)$_POST['delete_exam_status_id'];

    $sqlDel = "DELETE FROM course_status WHERE id = ?";
    $stmtDel = $conn->prepare($sqlDel);
    $stmtDel->execute([$idToDelete]);

    // Redirect back to the same page with Id parameter
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?Id=' . urlencode($_GET['Id']));
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_status'])) {
    $deleteId = htmlspecialchars(strip_tags(trim($_POST['delete_id'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($deleteId) {
        $stmt = $conn->prepare("DELETE FROM student_status WHERE id = :id");
        $stmt->bindValue(':id', $deleteId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            echo "<p style='color:green;'>رکورد با موفقیت حذف شد.</p>";
        } else {
            echo "<p style='color:red;'>خطا در حذف رکورد!</p>";
        }
    }
    header("Location: " . $_SERVER['REQUEST_URI']); // Refresh the page
    exit;
}


if (!function_exists('secToHMS')) {
    function secToHMS(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

// safe defaults
if (!isset($dateno)) {
    if (function_exists('gregorian_to_jalali')) {
        list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
        $dateno = "{$jy}/{$jm}/{$jd}";
    } else {
        $dateno = date('Y/m/d');
    }
}

$studentId = isset($_GET['Id']) ? $_GET['Id'] : (isset($user->codemeli) ? $user->codemeli : null);
$talkResult = null;
$errorMsg = '';
$todayJalali = gregorian_to_jalali(date('Y'), date('m'), date('d'));
$todayJalaliStr = implode('/', $todayJalali);


// ---- Simotel API config (override if you keep them elsewhere) ----
$apiUrl = $apiUrl ?? 'http://5.202.253.246:9880//api/v4/reports/quick/search';
$apiHeaders = $apiHeaders ?? [
        'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
        'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
        'Content-Type: application/json'
    ];

// ---------- helpers ----------
if (!function_exists('secToHMS')) {
    function secToHMS(int $sec): string
    {
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
$personal = retPerson($user->codemeli); // you already use $user->codemeli in the page
$numbers = array_values(array_unique(array_filter([
    $personal->tel1 ?? '',
    $personal->tel2 ?? '',
    $personal->tel3 ?? '',
    $personal->tel_home ?? '',
], function ($v) {
    return trim($v) !== '';
})));

$hasNumbers = !empty($numbers);

// Your Simotel config (same as before)
$apiUrl = 'http://5.202.253.246:9880//api/v4/reports/quick/search';
$apiHeaders = [
    'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
    'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
    'Content-Type: application/json'
];

// ---------------------------------------------
// 1) CURRENT JALALI YEAR RANGE (YTD) in Gregorian
// ---------------------------------------------
list($jy, $jm, $jd) = gregorian_to_jalali((int)date('Y'), (int)date('m'), (int)date('d'));

// Gregorian start of this Jalali year
$ytdStartGDate = jalali_to_gregorian($jy, 1, 1);
$ytdStartG = sprintf('%04d-%02d-%02d 00:00:00', $ytdStartGDate[0], $ytdStartGDate[1], $ytdStartGDate[2]);

// Gregorian end: one second before next Jalali year
$nextJy = $jy + 1;
$nextStartGArr = jalali_to_gregorian($nextJy, 1, 1);
$nextStartG = sprintf('%04d-%02d-%02d 00:00:00', $nextStartGArr[0], $nextStartGArr[1], $nextStartGArr[2]);
$ytdEndG = date('Y-m-d H:i:s', strtotime($nextStartG) - 1);

// For display (Jalali)
$ytdStartJ = sprintf('%04d/%02d/%02d 00:00', $jy, 1, 1);
list($gyEnd, $gmEnd, $gdEnd, $H, $i, $s) = sscanf($ytdEndG, '%d-%d-%d %d:%d:%d');
list($jyEnd, $jmEnd, $jdEnd) = gregorian_to_jalali($gyEnd, $gmEnd, $gdEnd);
$ytdEndJ = sprintf('%04d/%02d/%02d %02d:%02d', $jyEnd, $jmEnd, $jdEnd, $H, $i);

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
        if ($running) curl_multi_select($mh, 1.0);
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

// ---------------------------------------------
// 3) Build the YTD totals (for the banner at page load)
// ---------------------------------------------
//$ytdSeconds = 0;
//$ytdHMS     = '00:00:00';
//if ($hasNumbers) {
//    $ytdSeconds = fetchTalkSecondsForRange($numbers, $ytdStartG, $ytdEndG, $apiUrl, $apiHeaders);
//    // Your existing HMS formatter (assumed available)
//    if (!function_exists('secToHMS')) {
//        function secToHMS($sec)
//        {
//            $sec = (int)$sec;
//            $h = floor($sec / 3600);
//            $m = floor(($sec % 3600) / 60);
//            $s = $sec % 60;
//            return sprintf('%02d:%02d:%02d', $h, $m, $s);
//        }
//    }
//    $ytdHMS = secToHMS($ytdSeconds);
//}
//
//
//$errorMsg   = '';
//$talkResult = null;
//
//if (!empty($_POST['calc_student_talktime'])) {
//    $startDateInput = $_POST['startdate'] ?? '';
//    $endDateInput   = $_POST['enddate'] ?? '';
//
//    if ($startDateInput && $endDateInput) {
//        // Convert J -> G
//        list($sy, $sm, $sd) = jalali_to_gregorian(...explode('/', $startDateInput));
//        list($ey, $em, $ed) = jalali_to_gregorian(...explode('/',  $endDateInput));
//        $startDate = sprintf('%04d-%02d-%02d 00:00:00', $sy, $sm, $sd);
//        $endDate   = sprintf('%04d-%02d-%02d 23:59:59', $ey, $em, $ed);
//
//        if ($hasNumbers) {
//            $sec = fetchTalkSecondsForRange($numbers, $startDate, $endDate, $apiUrl, $apiHeaders);
//            $talkResult = [
//                'seconds' => $sec,
//                'hms'     => secToHMS($sec),
//                'start'   => $startDateInput, // keep Jalali in UI
//                'end'     => $endDateInput,
//            ];
//        } else {
//            $errorMsg = 'شماره تلفن معتبر برای این دانش آموز موجود نیست.';
//        }
//    } else {
//        $errorMsg = 'لطفاً هر دو تاریخ شروع و پایان را وارد کنید.';
//    }
//}


// Fetch payments with overdue status

try {
    $permStmt = $conn->prepare("SELECT installment_permission FROM dbadmin WHERE username = :username LIMIT 1");
    $permStmt->execute([':username' => $username]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canViewAll = ($permRow && (int)$permRow['installment_permission'] === 1);
} catch (PDOException $e) {
    error_log("Permission check error: " . $e->getMessage());
    $canViewAll = false;
}

$overduePayments = [];
$hasOverdue = false;
try {
    $query = "
        SELECT p.id, p.total_amount, p.paid_amount, 
               (p.total_amount - p.paid_amount) AS balance,
               p.status, p.created_at,
               CASE 
                   WHEN EXISTS (
                       SELECT 1 
                       FROM payment_installments i
                       WHERE i.payment_id = p.id
                         AND i.status = 'pending'
                         AND i.due_date < CURDATE()
                   ) THEN 1
                   ELSE 0
               END AS has_overdue
        FROM payments p
        WHERE p.codemeli = :codemeli
    ";
    if (!$canViewAll) {
        $query .= " AND p.operator_username = :username";
    }
    $query .= " ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    $params = [':codemeli' => $user->codemeli];
    if (!$canViewAll) {
        $params[':username'] = $username;
    }
    $stmt->execute($params);
    $overduePayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if any payment has overdue installments
    $hasOverdue = count(array_filter($overduePayments, fn($payment) => $payment['has_overdue'] == 1)) > 0;
} catch (PDOException $e) {
    error_log("Overdue payments query error: " . $e->getMessage());
    $_SESSION['profile_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در دریافت اطلاعات پرداخت‌ها.</div>";
}
//active or not
$statusAlert = '';
$statusData = [];

$codemeli = $_GET['Id'] ?? '';
$codemeli = preg_replace('/\D/', '', $codemeli); // digits only
if (strlen($codemeli) == 11) {
    $movaghat = 1;
} elseif (strlen($codemeli) == 10) {
    $movaghat = 0;
}
if ($codemeli === '') {
    $statusAlert = '<div class="alert alert-danger text-center">کد ملی نامعتبر است.</div>';
} else {
    // 1) Totals and newest timestamp
    $sqlTotals = "
    SELECT
        COUNT(*) AS total,

        SUM(CASE
            WHEN c.activityuser = 1 AND cc.activecourse = 1 THEN 1
            ELSE 0
        END) AS active_count,

        SUM(CASE
            WHEN c.activityuser = 0 AND cc.activecourse = 1 THEN 1
            ELSE 0
        END) AS inactive_count,

        MAX(CASE
            WHEN cc.activecourse = 1 THEN CONCAT(cc.datacreated, ' ', cc.timecreated)
            ELSE NULL
        END) AS last_created_at

    FROM dbcourse c
    JOIN codtbl_course cc ON cc.codecourse = c.codecourse
    WHERE c.username = ?
";
    $stmt = $conn->prepare($sqlTotals);
    $stmt->execute([$codemeli]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $total = (int)($row['total'] ?? 0);
    $active = (int)($row['active_count'] ?? 0);
    $last = $row['last_created_at'] ?? null;

    // 2) Newest course name (and its timestamp) — independent small query
    $lastCourseName = null;
    $lastCourseAt = null;
    if ($total > 0) {
        $sqlLastCourse = "
            SELECT 
                cc.coursename,
                CONCAT(cc.datacreated,' ',cc.timecreated) AS created_at
            FROM dbcourse c
            JOIN codtbl_course cc ON cc.codecourse = c.codecourse
            WHERE c.username = ?
            ORDER BY cc.datacreated DESC, cc.timecreated DESC
            LIMIT 1
        ";
        $stmt2 = $conn->prepare($sqlLastCourse);
        $stmt2->execute([$codemeli]);
        if ($r2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $lastCourseName = $r2['coursename'] ?? null;
            $lastCourseAt = $r2['created_at'] ?? null;
        }
        if ($lastCourseAt) $last = $lastCourseAt;
    }

    if ($total > 0 && $active === 0) {
        $msg = 'دانش‌آموز <strong>غیرفعال</strong> است. ';
        if ($last) {
            list($gDate, $gTime) = explode(' ', $last);
            list($gy, $gm, $gd) = explode('-', $gDate);
            $j = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd, '/');
            $msg .= 'آخرین زمان فعالیت این دانش‌آموز: <span dir="ltr">' . $j . '</span>';
            if ($lastCourseName) {
                $msg .= ' — نام پایه: <strong>' . $lastCourseName . '</strong>';
            }
        } else {
            $msg .= 'هیچ تاریخی برای آخرین فعالیت پیدا نشد.';
        }
        $statusAlert = '<div class="alert alert-warning text-center" role="alert" style="direction:rtl">' . $msg . '</div>';
    } else {
        if ($active > 0) {
            $statusAlert = '<div class="alert alert-success text-center" role="alert" style="direction:rtl">این دانش‌آموز <strong>فعال</strong> است.</div>';
        } else {
            $statusAlert = '<div class="alert alert-info text-center" role="alert" style="direction:rtl">هیچ پایه‌ای برای این دانش‌آموز ثبت نشده است.</div>';
        }
    }
}

$tz = new DateTimeZone('Asia/Tehran');
$today = new DateTime('now', $tz);

list($jy, $jm, $jd) = gregorian_to_jalali(
    (int)$today->format('Y'),
    (int)$today->format('m'),
    (int)$today->format('d')
);

$tir1G = jalali_to_gregorian($jy, 4, 1); // ۱ تیر سال جاری
$tir1DateTime = sprintf('%04d-%02d-%02d 00:00:00', $tir1G[0], $tir1G[1], $tir1G[2]);


if (isset($_POST['change_support_type'])) {
    $newType = (int)$_POST['new_typeuser'];
    $studentCode = $_GET['Id']; // یا $user->codemeli

    // بررسی وجود suport_user
    $checkSql = "SELECT * FROM suport_user WHERE username = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$studentCode]);
    if ($checkStmt->rowCount() > 0) {
        // به‌روزرسانی
        $updateSql = "UPDATE suport_user SET typesupport = ? WHERE username = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$newType, $studentCode]);
    } else {
        // در صورت نبود رکورد، درج کن (با مقادیر پیش‌فرض برای lastdate, afterdate)
        $insertSql = "INSERT INTO suport_user (username, typesupport, lastdate, afterdate) VALUES (?, ?, '2025-01-01', '2025-01-01')";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([$studentCode, $newType]);
    }

    // به‌روزرسانی متغیر session یا refresh
    header("Location: suporting.php?Id=" . urlencode($studentCode));
    exit;
}

// =====================================================================
// Active Events (calendar events with event_type = 1)
// =====================================================================
$studentId = $user->codemeli ?? '';

// Generate CSRF token for active event forms if not exists
if (!isset($_SESSION['active_event_token'])) {
    $_SESSION['active_event_token'] = bin2hex(random_bytes(32));
}

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['active_event_action'])) {
    if (!isset($_SESSION['active_event_token']) || $_SESSION['active_event_token'] !== ($_POST['active_event_token'] ?? '')) {
        $active_event_error = "خطای امنیتی. لطفاً دوباره تلاش کنید.";
    } else {
        $eventId = (int)$_POST['event_id'];
        $optionId = isset($_POST['option_id']) ? (int)$_POST['option_id'] : 0;
        $reason = trim($_POST['reason'] ?? '');
        $operatorId = $_SESSION['admin']['user']; // or idadmin

        if (!$optionId) {
            $active_event_error = "لطفاً یک گزینه را انتخاب کنید.";
        } else {
            // Fetch the option to check if it needs a reason
            $optStmt = $conn->prepare("SELECT needs_reason, `events_option` FROM active_event_options WHERE id = ?");
            $optStmt->execute([$optionId]);
            $option = $optStmt->fetch(PDO::FETCH_OBJ);
            if (!$option) {
                $active_event_error = "گزینه نامعتبر است.";
            } else {
                if ($option->needs_reason && empty($reason)) {
                    $active_event_error = "این گزینه نیاز به توضیح دارد، لطفاً علت را وارد کنید.";
                } else {
                    try {
                        $checkStmt = $conn->prepare("SELECT event_id FROM active_event_answers WHERE event_id = ? AND username = ?");
                        $checkStmt->execute([$eventId, $studentId]);
                        $exists = $checkStmt->fetch();

                        if (!$exists) {
                            $stmt = $conn->prepare("INSERT INTO active_event_answers (event_id, username, option_id, reason, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$eventId, $studentId, $optionId, $reason, $operatorId]);
                            $active_event_success = "پاسخ با موفقیت ثبت شد.";
                        } else {
                            $stmt = $conn->prepare("UPDATE active_event_answers SET option_id = ?, reason = ?, created_by = ?, created_at = NOW() WHERE event_id = ? AND username = ?");
                            $stmt->execute([$optionId, $reason, $operatorId, $eventId, $studentId]);
                            $active_event_success = "پاسخ با موفقیت ویرایش شد.";
                        }
                        // Refresh to show updated state
                        header("Location: suporting.php?Id=" . urlencode($_GET['Id']));
                        exit;
                    } catch (Exception $e) {
                        $active_event_error = "خطا در ثبت پاسخ: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$enrolledCourseCodes = getcourses($studentId, 2); // returns array of codecourse
$enrolledCourseIds = [];
if (!empty($enrolledCourseCodes)) {
    $in = str_repeat('?,', count($enrolledCourseCodes) - 1) . '?';
    $stmt = $conn->prepare("SELECT idcourse FROM codtbl_course WHERE codecourse IN ($in)");
    $stmt->execute($enrolledCourseCodes);
    $enrolledCourseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch all options for active events (to avoid N+1 queries)
$optionsByEvent = [];
$optStmt = $conn->prepare("SELECT id, event_id, `events_option`, needs_reason FROM active_event_options ORDER BY event_id, id");
$optStmt->execute();
while ($optRow = $optStmt->fetch(PDO::FETCH_ASSOC)) {
    $optionsByEvent[$optRow['event_id']][] = $optRow;
}

// Query active events WITHOUT existing answers
$sqlevent = "SELECT e.*, a.option_id AS existing_option_id, a.reason AS existing_reason, a.created_by, a.created_at
        FROM calendar_events e
        LEFT JOIN active_event_answers a ON e.id = a.event_id AND a.username = ?
        WHERE e.event_type = 1
          AND e.target_type IN ('user', 'both')
          AND a.option_id IS NULL";
$params = [$studentId];

// Course filtering
if (empty($enrolledCourseIds)) {
    $sqlevent .= " AND NOT EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id)";
} else {
    $placeholders = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
    $sqlevent .= " AND (
        NOT EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id)
        OR EXISTS (
            SELECT 1 FROM calendar_course_targets ct 
            WHERE ct.event_id = e.id AND ct.course_id IN ($placeholders)
        )
    )";
    $params = array_merge($params, $enrolledCourseIds);
}

$stmtevent = $conn->prepare($sqlevent);
$stmtevent->execute($params);
$activeEvents = $stmtevent->fetchAll(PDO::FETCH_ASSOC);

/**
 * Calculates Gregorian bounds for academic year starting June 21 (1 Tir).
 */
function getCurrentAcademicYearRange(): array {
    $now = new DateTime();
    $currentYear  = (int) $now->format('Y');
    $currentMonth = (int) $now->format('m');
    $currentDay   = (int) $now->format('d');

    // June 21 cutoff
    if ($currentMonth > 6 || ($currentMonth === 6 && $currentDay >= 21)) {
        $startYear = $currentYear;
        $endYear   = $currentYear + 1;
    } else {
        $startYear = $currentYear - 1;
        $endYear   = $currentYear;
    }

    return [
        'start'      => sprintf('%04d-06-21 00:00:00', $startYear),
        'end'        => sprintf('%04d-06-20 23:59:59', $endYear),
        'start_date' => sprintf('%04d-06-21', $startYear),
        'end_date'   => sprintf('%04d-06-20', $endYear),
    ];
}

/**
 * Builds dynamic WHERE clauses targeting Gregorian date/time formats.
 */
function buildAcademicYearWhereClause(string $tableName, array $range, string $alias = ''): string {
    $p = $alias ? rtrim($alias, '.') . '.' : '';

    $start     = $range['start'];
    $end       = $range['end'];
    $startDate = $range['start_date'];
    $endDate   = $range['end_date'];

    switch ($tableName) {
        // Split Date + Time columns
        case 'suport_message':
            return "({$p}message_date IS NOT NULL AND " .
                "CAST(CONCAT({$p}message_date, ' ', COALESCE({$p}message_time, '00:00:00')) AS DATETIME) " .
                "BETWEEN '{$start}' AND '{$end}')";

        case 'suport_report':
            return "({$p}datenow IS NOT NULL AND " .
                "CAST(CONCAT({$p}datenow, ' ', COALESCE({$p}timenow, '00:00:00')) AS DATETIME) " .
                "BETWEEN '{$start}' AND '{$end}')";

        // Date-only tables
        case 'simotel_calls_data':
            return "({$p}snapshot_day BETWEEN '{$startDate}' AND '{$endDate}')";

        // Single Timestamp/Datetime tables
        case 'calls':
        case 'admin_messages':
        case 'cancel_student_operator_request':
        case 'student_suspension_requests':
        default:
            return "({$p}created_at BETWEEN '{$start}' AND '{$end}')";
    }
}

// 1. Get the current active Gregorian academic year bounds
$yearRange = getCurrentAcademicYearRange();

// 2. Query Chat Messages filtered by active academic year
$msgFilter = buildAcademicYearWhereClause('suport_message', $yearRange, 'sm');

// Added parentheses around (sm.sender_username = ? OR sm.receiver_username = ?)
// so the AND $msgFilter applies to BOTH conditions
$sqlMessages = "SELECT sm.* 
                FROM suport_message sm 
                WHERE (sm.sender_username = ? OR sm.receiver_username = ?) 
                AND $msgFilter 
                ORDER BY sm.message_date DESC, sm.message_time DESC";

$stmt_msg = $conn->prepare($sqlMessages);
$stmt_msg->execute([$_GET['Id'], $_GET['Id']]);
$chatMessages = $stmt_msg->fetchAll(PDO::FETCH_OBJ);

// 3. Query Operator Reports filtered by active academic year
$reportFilter = buildAcademicYearWhereClause('suport_report', $yearRange, 'sr');
$sqlReports = "SELECT sr.* 
               FROM suport_report sr 
               WHERE (sr.username = ?) 
               AND $reportFilter 
               ORDER BY sr.id DESC";
$stmt_report = $conn->prepare($sqlReports);
$stmt_report->execute([$_GET['Id']]);
$supportReports = $stmt_report->fetchAll(PDO::FETCH_OBJ);

// 4. Query Call Logs filtered by active academic year
$callFilter = buildAcademicYearWhereClause('calls', $yearRange, 'c');
$sqlCalls = "SELECT c.* 
             FROM calls c 
             WHERE $callFilter 
             ORDER BY c.id DESC";
$callsData = $conn->query($sqlCalls)->fetchAll();

// 5. Query SMS Logs filtered by active academic year
$smsFilter = buildAcademicYearWhereClause('sms_logs', $yearRange, 'sl');
$sqlSmsLogs = "SELECT sl.* 
               FROM sms_logs sl 
               WHERE (sl.student_id = ?) 
               AND $smsFilter 
               ORDER BY sl.send_date DESC, sl.send_time DESC";
$stmt_sms = $conn->prepare($sqlSmsLogs);
$stmt_sms->execute([$_GET['Id']]);
$smsLogsData = $stmt_sms->fetchAll(PDO::FETCH_OBJ);

$supporterCommentSql = "SELECT comment FROM student_supporter_comments 
                        WHERE codemeli = ? 
                        AND created_at BETWEEN ? AND ? 
                        ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($supporterCommentSql);
$stmt->execute([$_GET['Id'], $yearRange['start'], $yearRange['end']]);
$currentCommentRow = $stmt->fetch(PDO::FETCH_OBJ);

$aboutSuporterText = $currentCommentRow ? $currentCommentRow->comment : null;
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<style>
    #ChatBody {
        height: 400px !important;
        overflow-y: auto !important;
        /* Changed from scroll to auto for cleaner look */
        display: flex !important;
        flex-direction: column-reverse !important;
        direction: rtl !important;
        text-align: right !important;
        padding: 10px !important;
        box-sizing: border-box !important;
        unicode-bidi: plaintext !important;
    }

    .chat-attachment-card {
        background: rgba(255, 255, 255, 0.2);
        /* Semi-transparent */
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        padding: 10px;
        margin-top: 8px;
        transition: all 0.3s ease;
        max-width: 250px;
        margin-bottom: 10px;
    }

    .chat-attachment-card:hover {
        background: rgba(255, 255, 255, 0.4);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .chat-attachment-card a {
        text-decoration: none !important;
        color: inherit;
    }

    .attachment-icon {
        width: 40px;
        height: 40px;
        background: #5eb95e;
        /* Success green to match your theme */
        color: white;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-left: 10px;
        /* Space for RTL */
    }

    .attachment-info {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        text-align: right;
    }

    .file-name {
        font-size: 13px;
        font-weight: bold;
        display: block;
    }

    .file-size {
        font-size: 11px;
    }

    .download-arrow {
        font-size: 18px;
        color: #888;
        margin-right: 5px;
    }

    /* Specific styling for admin vs student bubbles */
    .media.flex-row-reverse .attachment-icon {
        background: #3858f9;
        /* Blue for students */
    }

    .main-msg-wrapper {
        /* Limit the bubble width so it doesn't hit the other side */
        max-width: 80% !important;

        /* Ensure the bubble itself behaves as a block for the text */
        display: flex !important;
        flex-direction: column !important;

        /* Add word-wrap to handle long strings like links or "sssssssss" */
        word-break: break-word !important;
        overflow-wrap: break-word !important;

        /* Polish the padding and shape */
        padding: 10px 15px !important;
        border-radius: 12px !important;
        margin-bottom: 5px !important;
    }

    /* Fix for the text inside the bubble */
    .main-msg-wrapper span {
        display: block !important;
        line-height: 1.6;
    }

    .action-btn {
        width: 100px;
        /* pick what looks best for your layout */
        height: 32px;
        /* consistent height */
        padding: 0 10px;
        display: inline-flex;
        /* makes <a> and <button> behave the same */
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        /* prevents wrapping */
        font-size: 12px;
        /* consistent font size */
        line-height: 1;
    }
</style>

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>پشتیبانی دانش آموز | <?php echo __TITLE ?></title>
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
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-11">
                                    <div>
                                        <h6 class="main-content-label mb-1">پشتیبانی دانش آموز : <span
                                                    class="text-primary"><?php echo $user->fname . ' ' . $user->lname ?></span>
                                            | اولین ورود :
                                            <?php
                                            $firstlogin = retdblogin($user->codemeli);
                                            if ($firstlogin->firstlogin == 1) {
                                                echo '<span class="badge badge-success-light">بله</span>';
                                            } else {
                                                echo '<span class="badge badge-warning-light">خیــر</span>';
                                            }

                                            ?>
                                            |
                                            پایه تحصیلی و رشته: <span
                                                    class="badge badge-success-light"><?php echo $user->paye . ' - ' . $user->reshtestudent ?></span>
                                            |
                                            همکار آموزشی: <span class="badge badge-warning-light">
                                                    <?php
                                                    if ($user->useradder == NULL) {
                                                        echo 'ندارد';
                                                    } else {
                                                        $collague = retPerson($user->useradder);
                                                        echo $collague->fname . ' ' . $collague->lname;
                                                    }

                                                    ?></span>
                                            |
                                            استان: <span
                                                    class="badge badge-success-light"><?php echo $user->state ?></span>
                                            |
                                            شهر: <span
                                                    class="badge badge-success-light"><?php echo $user->city ?></span> |
                                            تاریخ تولد: <span
                                                    class="badge badge-primary-light"><?php if ($movaghat == 0) {

                                                    $dateno = $user->birthdate;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);

                                                    $dateno = implode('/', $dateno);
                                                    echo $dateno;
                                                } elseif ($movaghat == 1) {
                                                    echo '<span class="badge badge-warning">تعریف نشده</span>';
                                                }
                                                ?></span> |
                                            نوع دانش آموز:<?php
                                            $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN type_vip ON dbpersonal_type_vip.type_vip_id = type_vip.id WHERE dbpersonal_id = ?";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute([$user->idpersonal]);
                                            $typeVips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                            if ($stmt->rowCount() > 0) {
                                                foreach ($typeVips as $typeVip) {
                                                    echo "<span class='badge badge-success'>$typeVip->name</span>";
                                                }
                                            } else {
                                                echo '<span class="badge badge-success">تعریف نشده</span>';
                                            }
                                            ?> |
                                            شیفت دانش آموز: <span class="badge badge-danger"><?php
                                                if (!isset($user->shift)) {
                                                    echo 'تعریف نشده';
                                                } else {
                                                    switch ($user->shift) {
                                                        case 0:
                                                            echo 'صبح';
                                                            break;
                                                        case 1:
                                                            echo 'بعدازظهر';
                                                            break;
                                                        case 2:
                                                            echo 'گردشی';
                                                            break;
                                                    }
                                                }
                                                ?></span> |
                                            <?php if ($hasOverdue): ?>
                                                <div class="alert alert-danger alert-overdue" role="alert">
                                                    <strong>هشدار:</strong> این دانش‌آموز دارای پرداخت‌های معوقه است!
                                                    <ul class="mb-0">
                                                        &nbsp;
                                                        <?php foreach ($overduePayments as $payment): ?>
                                                            <?php if ($payment['has_overdue']): ?>
                                                                <li>
                                                                    پرداخت
                                                                    شماره: <?= htmlspecialchars($payment['id']) ?>،

                                                                    مبلغ
                                                                    باقی‌مانده: <?= number_format($payment['balance']) ?>
                                                                    تومان،
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>


                                        </h6>
                                        <?php

                                        if ($suport) {
                                            $last = $suport['lastdate'];
                                            $last = explode('-', $last);
                                            $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                                            $last = implode('/', $last);
                                            //
                                            $after = $suport['afterdate'];
                                            $after = explode('-', $after);
                                            $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                            $after = implode('/', $after);
                                            $typeuser = $suport['typesupport'];
                                            if ($typeuser == 1) {
                                                $type = 'ویــژه';
                                            } else {
                                                $type = 'عــادی';
                                            }
                                        } else {
                                            $last = 'ندارد';
                                            $after = 'ندارد';
                                            $type = 'عــادی';
                                            $typeuser = 0;
                                        }
                                        ?>
                                        <p class="text-muted card-sub-title"> آخرین پشتیبانی :<span
                                                    class="text-info"><?php echo $last ?></span> | پشتیبانی بعدی :
                                            <span class="text-info"><?php echo $after ?></span>
                                            | نوع پشتیبانی :
                                            <span class="text-info"> <?php echo $type ?> </span>
                                            <?php
                                            $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                            $wer = $conn->prepare($swr);
                                            $wer->execute([1, 1, $_GET['Id']]);
                                            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                            $total = array();
                                            $factorCheck = array();
                                            if ($wer->rowCount() > 0) {
                                                foreach ($recive as $res) {
                                                    if (!in_array($res->numfactor, $factorCheck)) {
                                                        $factor = queryFactor($res->numfactor);
                                                        $sum = $factor->wallet + $factor->factorfee;
                                                        array_push($total, $sum);
                                                        array_push($factorCheck, $res->numfactor);
                                                    }
                                                }
                                            }
                                            $sumtoto = 0;
                                            foreach ($total as $sto) {
                                                $sumtoto = $sto + $sumtoto;
                                            }

                                            ?>
                                            | وضعیت پرداختی :
                                            <span class="text-info"> <?php echo number_format($sumtoto) ?> </span>
                                        </p>

                                    </div>
                                </div>


                                <div class="col-lg-1 d-flex flex-column justify-content-center align-items-center">
                                    <div class="main-img-user my-2">
                                        <img alt="آواتار" src="<?php echo url('assets/img/users/1.jpg') ?>">
                                    </div>

                                    <div class="w-100 d-flex flex-column align-items-center gap-2">
                                        <a
                                                href="#"
                                                class="btn btn-sm btn-primary action-btn"
                                                onclick="window.open(
                                                        '<?php echo url('admin/Student/student-history.php') . '?Id=' . urlencode($_GET['Id']); ?>',
                                                        'سوابق',
                                                        'width=1000,height=550,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                        ); return false;">
                                            سوابق
                                        </a>

                                        <?php if ($hasPendingSuspendRequest): ?>
                                            <button type="button" class="btn btn-sm btn-warning action-btn" disabled>در
                                                حال بررسی تعلیق
                                            </button>
                                        <?php elseif ($isSuspended == 0): ?>
                                            <button type="button" class="btn btn-sm btn-warning action-btn"
                                                    data-bs-toggle="modal" data-bs-target="#suspendRequestModal">
                                                درخواست تعلیق
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($pendingOperator): ?>
                                            <button type="button" class="btn btn-sm btn-danger action-btn" disabled>در
                                                حال بررسی لغو پشتیبانی
                                            </button>
                                        <?php elseif (!$hasThisUserNoOperator): ?>
                                            <a class="btn btn-sm btn-danger action-btn"
                                               href="suporting.php?Id=<?= $_GET['Id'] ?>&opHandle"
                                               onclick="return confirm('آیا از لغو پشتیبانی این دانش آموز اطمینان دارید ؟');">
                                                لغو پشتیبانی
                                            </a>
                                        <?php endif; ?>

                                    </div>
                                </div>

                            </div>
                            <p class="text-muted card-sub-title"> شرح حال دانش آموز :<span class="text-info"><?php
                                    if ($aboutSuporterText != NULL) {
                                        echo htmlentities($aboutSuporterText);
                                    } else {
                                        echo 'موردی ثبت نشده است، درصورت نیاز به ثبت شرح حال از پایین صفحه اقدام نمایید';
                                    }
                                    ?></span>
                                <?php if (isset($_SESSION['call'])): ?>
                            <div class="alert alert-outline-success text-center" role="alert">
                                <div class="mg-t-10">
                                    <img style="width:75px; height:75px;" src="../../assets/img/call1.gif"
                                         alt="calling">
                                    در حال برقراری ارتباط با <?php echo $_SESSION['call']['name']; ?>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                            </div>
                            <?php unset($_SESSION['call']); ?>

                            <?php elseif (!empty($_SESSION['call_message'])): ?>
                                <div class="alert alert-outline-warning text-center" role="alert">
                                    <div class="mg-t-10">
                                        <?php echo $_SESSION['call_message']; ?>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>
                                </div>
                                <?php unset($_SESSION['call_message']); ?>

                            <?php /* Optional fallback if you prefer to detect here without session */
                            elseif (isset($_GET['callh']) && $_GET['callh'] === 'true' && isset($user) && empty($user->tel_home)): ?>
                                <div class="alert alert-outline-warning text-center" role="alert">
                                    <div class="mg-t-10">
                                        این دانش‌آموز شماره تلفن ثابت ندارد.
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($isSuspended): ?>
                                <div class="alert alert-warning mb-0">این دانش آموز در حالت تعلیق قرار دارد</div>
                            <?php endif; ?>
                            <?php if ($movaghat == 1): ?>
                                <div class="alert alert-warning mb-0 text-center">این دانش آموز موقت است</div>
                            <?php endif; ?>
                            <?= $statusAlert ?>
                            <?php if (!empty($suspendFlashErrors)): ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <?php foreach ($suspendFlashErrors as $msg): ?>
                                        <div><?php echo htmlspecialchars($msg); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($suspendFlashSuccess)): ?>
                                <div class="alert alert-success text-center" role="alert">
                                    <?php foreach ($suspendFlashSuccess as $msg): ?>
                                        <div><?php echo htmlspecialchars($msg); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($_SESSION['cancel_success'])): ?>
                                <div class="alert alert-success text-center" role="alert">
                                    <div><?php echo htmlspecialchars($_SESSION['cancel_success']); ?></div>
                                </div>
                                <?php unset($_SESSION['cancel_success']);
                            endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <?php if (isset($_SESSION['skyroom_success'])):
                $data = $_SESSION['skyroom_success'];
                $token = $data['token'];
                ?>
                <div class="card shadow-sm border-0 border-start border-primary border-4 mb-3">
                    <div class="card-body p-3">
                        <h6 class="card-title text-primary fw-bold">
                            <i class="bi bi-link-45deg"></i> لینک ساخته شد
                        </h6>
                        <p class="text-muted x-small mb-2">برای: <strong><?= $data['student_name'] ?></strong></p>
                        <p class="text-muted x-small mb-2">پایه: <strong><?= $data['course'] ?></strong></p>

                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control bg-light border-0" value="<?= $token ?>"
                                   id="skyTokenInput" readonly>
                            <button class="btn btn-outline-primary" type="button" id="btnCopy" onclick="copyToken()">
                                کپی
                            </button>
                        </div>

                        <div id="copyFeedback" class="small text-success d-none mt-1">
                            <i class="bi bi-check2"></i> کپی شد!
                        </div>

                    </div>
                </div>

                <?php
                unset($_SESSION['skyroom_success']); // Clear so it only shows once
            endif;
            ?>
        </div>

        <script>
            function copyToken() {
                const copyText = document.getElementById('skyTokenInput');
                const feedback = document.getElementById('copyFeedback');
                const btn = document.getElementById('btnCopy');

                if (!copyText) return;

                navigator.clipboard.writeText(copyText.value).then(() => {
                    // UI Feedback
                    feedback.classList.remove('d-none');
                    btn.classList.replace('btn-outline-primary', 'btn-success');
                    btn.innerText = '✓';

                    setTimeout(() => {
                        feedback.classList.add('d-none');
                        btn.classList.replace('btn-success', 'btn-outline-primary');
                        btn.innerText = 'کپی شد';
                    }, 2500);
                });
            }
        </script>

        <div class="row row-sm">

            <div class="col-lg-4 scroll-height">
                <?php if (isset($error) and $error == 1) { ?>
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        <?= $errorMessage ?>
                    </div>
                <?php } elseif (isset($_SESSION['error_message'])) { ?>
                    <div class="alert alert-danger" role="alert">
                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        <?= $_SESSION['error_message'] ?>
                    </div>
                    <?php unset($_SESSION['error_message']);
                } ?>
                <?php if (isset($_SESSION['sms_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['sms_success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['sms_success']);
                endif; ?>

                <?php if (isset($_SESSION['sms_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $_SESSION['sms_error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['sms_error']);
                endif; ?>

                <!--new form-->
                <div class="card custom-card" style="direction: rtl; text-align: right;">
                    <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                        فرم وضعیت آزمون دانش آموز
                    </div>
                    <div class="card-body">

                        <!-- Exam Status Form -->
                        <form method="post" action="">
                            <input type="hidden" name="student_id"
                                   value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

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
                                    <option value="المپیاد">المپیاد</option>
                                    <option value="موارد دیگر">موارد دیگر</option>
                                </select>
                            </div>

                            <div class="mb-3" id="olympiadFieldDiv" style="display:none;">
                                <label class="form-label" style="color:#222;">انتخاب رشته المپیاد</label>

                                <select id="olympiadField"
                                        name="olympiad_field[]"
                                        class="form-select select2"
                                        multiple>
                                    <option value="سواد رسانه">سواد رسانه</option>
                                    <option value="نانو">نانو</option>
                                    <option value="جغرافیا">جغرافیا</option>
                                    <option value="علوم زمین">علوم زمین</option>
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
                                    <option value="زمان آزمون فرا نرسیده است">زمان آزمون فرا نرسیده است</option>
                                    <option value="آزمون داده ولی جواب آزمون نیامده">آزمون داده ولی جواب آزمون نیامده
                                    </option>

                                </select>
                            </div>

                            <div class="mb-3" id="notTakenReasonDiv" style="display:none;">
                                <label for="notTakenReason" class="form-label" style="color:#222;">
                                    چرا آزمون نداده؟
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="notTakenReason"
                                       name="not_taken_reason"
                                       placeholder="علت را وارد کنید">
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

                        <!-- Divider -->
                        <hr class="my-4">

                        <!-- Student Status Form -->
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                            فرم وضعیت دانش آموز
                        </div>
                        <br>
                        <form method="post" action="">
                            <input type="hidden" name="student_id"
                                   value="<?php echo htmlspecialchars($_GET['Id'] ?? ''); ?>">
                            <input type="hidden" name="token" value="<?= $_SESSION['token'] ?>">

                            <div class="mb-3">
                                <label for="avgFirst" class="form-label" style="color: #222;">معدل ترم اول</label>
                                <input type="text" required class="form-control" id="avgFirst"
                                       name="avg_first_semester" placeholder="میانگین نیمسال اول را وارد کنید"
                                       style="color: #000;">
                            </div>

                            <div class="mb-3">
                                <label for="avgSecond" class="form-label" style="color: #222;">معدل ترم دوم</label>
                                <input type="text" required class="form-control" id="avgSecond"
                                       name="avg_second_semester" placeholder="میانگین نیمسال دوم را وارد کنید"
                                       style="color: #000;">
                            </div>


                            <div class="mb-3">
                                <label for="studentComment" class="form-label" style="color: #222;">نظر دانش
                                    آموز</label>
                                <textarea class="form-control" id="studentComment" required name="student_comment"
                                          rows="3" placeholder="نظر دانش آموز را وارد کنید..."
                                          style="color: #000;"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="supportComment" class="form-label" style="color: #222;">نظر پشتیبان دانش
                                    آموز</label>
                                <textarea class="form-control" id="supportComment" required name="supporter_comment"
                                          rows="3" placeholder="نظر پشتیبان را وارد کنید..."
                                          style="color: #000;"></textarea>
                            </div>

                            <button type="submit" name="student-status" class="btn btn-primary w-100">ثبت</button>
                        </form>
                    </div>
                </div>


                <!--     new form-->
                <div class="card custom-card">
                    <div class="card-header p-3 tx-medium my-auto tx-white bg-success-gradient">
                        گفتگو با دانش آموز
                    </div>
                    <div class="card-body">
                        <div class="main-chat-body" id="ChatBody">
                            <?php
                            $sql = 'SELECT * FROM suport_message WHERE sender_username = ? OR receiver_username = ? ORDER BY message_date DESC, message_time DESC';
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$_GET['Id'], $_GET['Id']]);
                            $messages = $stmt->fetchAll(PDO::FETCH_OBJ);
                            foreach (
                            $chatMessages

                            as $message
                            ) {
                            if ($message->sender_type == 'admin') {
                            ?>
                            <div class="media">
                                <?php } else { ?>
                                <div class="media flex-row-reverse">
                                    <?php } ?>
                                    <div class="main-img-user online">
                                        <?php if ($message->sender_type == 'student') { ?>
                                            <img alt="پروفایل" src="<?= assest('/assets/img/users/1.jpg') ?>"
                                                 class="my-2">
                                        <?php } ?>
                                    </div>
                                    <div class="main-msg-wrapper <?= ($message->sender_type == 'admin') ? 'ml-3' : 'mr-3' ?>">
                                        <?php
                                        if ($message->sender_type == 'admin') {
                                            $admin = retAdmin($message->sender_username);
                                            switch ($admin->dakheli) {
                                                case '201':
                                                    $sender = 'اپراتور ارشد 1';
                                                    break;
                                                case '500':
                                                    $sender = 'مدیریت موسسه';
                                                    break;
                                                case '220':
                                                    $sender = 'اپراتور ارشد 2';
                                                case '501':
                                                    $sender = 'تیم فنی';
                                                    break;
                                                default:
                                                    $sender = "اپراتور $admin->dakheli";
                                                    break;
                                            }
                                            echo '<div class="text-muted small mb-1" style="font-weight: bold;">' . $sender . '</div>';
                                        }
                                        ?>
                                        <!-- <span class="tx-12"></span> -->
                                        <!-- <div class="media-body mr-3"> -->
                                        <span><?= nl2br(htmlspecialchars($message->message_text)) ?></span>
                                        <!-- </div> -->
                                        <div>
                                            <?php

                                            if (!empty($message->edit_message_date)) {
                                                [$year, $month, $day] = explode('-', $message->edit_message_date);
                                                [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                $jDateTime = $message->edit_message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                            } else {
                                                [$year, $month, $day] = explode('-', $message->message_date);
                                                [$jy, $jm, $jd] = gregorian_to_jalali($year, $month, $day);
                                                $jDateTime = $message->message_time . ' | ' . $jy . '/' . $jm . '/' . $jd;
                                            }
                                            if (!empty($message->file_path)) { ?>
                                                <div class="chat-attachment-card">
                                                    <a href="<?= url('Support/attachments.php') . '?fileChat=' . urlencode($message->id) ?>"
                                                       class="d-flex align-items-center">
                                                        <div class="attachment-icon">
                                                            <i class="fe fe-file-text"></i>
                                                        </div>
                                                        <div class="attachment-info">
                                                            <span class="file-name">مشاهده پیوست</span>
                                                            <small class="file-size text-muted">برای دانلود کلیک
                                                                کنید</small>
                                                        </div>
                                                        <div class="download-arrow">
                                                            <i class="fe fe-download"></i>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php }
                                            $datetime = $message->message_date . ' ' . $message->message_time;
                                            if (Permision('permision_manage_chat')) { ?>
                                                <a href="?Id=<?= $_GET['Id'] ?>&edit=<?= $message->id ?>"
                                                   title="ویرایش"><i class="fe fe-edit"></i></a>
                                                <a href="?Id=<?php echo $_GET['Id'] ?>&delm=<?php echo $message->id ?>"
                                                   onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                   title="حذف"> <i class="fe fe-trash-2"></i></a>
                                                <?php if ($message->status == 'sent') { ?>
                                                    <i class=" text-success fa fa-check"></i>
                                                <?php } elseif ($message->status == 'seen') { ?>
                                                    <i class=" text-success fa fa-check"></i>
                                                    <i class=" text-success fa fa-check"></i>
                                                <?php } ?>
                                            <?php }
                                            if ($message->sender_type == 'admin' and !Permision('permision_manage_chat')) {
                                                if (time() - strtotime($datetime) <= 3600) { ?>
                                                    <a href="?Id=<?= $_GET['Id'] ?>&edit=<?= $message->id ?>"
                                                       title="ویرایش"><i class="fe fe-edit"></i></a>
                                                    <a href="?Id=<?php echo $_GET['Id'] ?>&delm=<?php echo $message->id ?>"
                                                       onclick="return confirm('آیا با حذف این پیام موافقید؟')"
                                                       title="حذف"> <i class="fe fe-trash-2"></i></a>
                                                <?php }
                                                if ($message->status == 'sent') { ?>
                                                    <i class=" text-success fa fa-check"></i>
                                                <?php } elseif ($message->status == 'seen') { ?>
                                                    <i class=" text-success fa fa-check"></i>
                                                    <i class=" text-success fa fa-check"></i>
                                                <?php }
                                            }
                                            ?>
                                            <span class="text-muted">
                                                        <?php if (!empty($message->edit_message_date)) { ?>
                                                            تاریخ <strong>ویرایش</strong>
                                                            <?= $jDateTime ?>
                                                        <?php } else { ?>
                                                            تاریخ ارسال
                                                            <?= $jDateTime ?>
                                                        <?php } ?>
                                                    </span>
                                        </div>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <div class="main-chat-footer">
                                <nav class="nav">

                                    <a class="nav-link" data-bs-toggle="tooltip" href="#"
                                       title="یک فایل را پیوست کنید"></a>
                                    <label class="input-group-btn">

                                        <i class="fe fe-paperclip"></i> <input type="file" name="fileto"
                                                                               style="display: none;" multiple="">

                                    </label>
                                </nav>
                                <?php
                                if (isset($_GET['edit'])) {
                                    $sql = "SELECT * FROM suport_message WHERE id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([$_GET['edit']]);
                                    $message = $stmt->fetch(PDO::FETCH_OBJ);
                                }
                                ?>

                                <textarea class="form-control" name="txt" placeholder="پیام خود را اینجا بنویسید ..."
                                          required
                                          style="resize: none; height: 45px; min-height: 45px; max-height: 100px; overflow-y: auto; padding-top: 10px; margin: 0 10px;"
                                ><?php if (isset($_GET['edit'])) {
                                        echo htmlspecialchars($message->message_text);
                                    } ?></textarea>
                                <?php
                                $hashh = bin2hex(random_bytes(32));
                                $_SESSION['tokenn'] = $hashh;
                                ?>
                                <input class="form-control" name="token" type="hidden" value="<?php echo $hashh ?>">
                                <button type="submit" name="sub" class="main-msg-send" style="display: contents"><i
                                            class="far fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card custom-card">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-dark">
                            پیام های آماده
                        </div>
                        <div class="card-body">
                            <?php
                            $sql = "SELECT * FROM suport_message_constant";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            $constantMessages = $stmt->fetchAll(PDO::FETCH_OBJ);
                            if ($stmt->rowCount() > 0) {
                                $row = 1;
                                foreach ($constantMessages as $message) { ?>
                                    <div class="text-muted">پیام شماره <?= $row ?></div>
                                    <p><?= $message->content ?>
                                        <br>
                                    <div class="col-12 text-left ">
                                        <a href="?Id=<?php echo $_GET['Id'] ?>&send=<?= $message->id ?>"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('آیا با ارسال پیام آماده به این دانش آموز موافقید؟')"><i
                                                    class="fe fe-send"></i> ارسال </a>
                                    </div>

                                    <hr>
                                    <?php
                                    $row++;
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <!-- کارت ارسال پیامک - منتقل شده به col-lg-4 -->
                    <div class="card custom-card">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-primary">
                            ارسال پیامک به دانش آموز
                        </div>
                        <div class="card-body">
                            <form method="post" id="form-sms" data-parsley-validate="">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">متن پیامک <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="sms_text" rows="4" required
                                                  placeholder="متن پیامک خود را وارد کنید..." id="smsText"></textarea>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label mb-2">انتخاب گیرندگان (برای انتخاب کلیک کنید):</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <!-- دکمه دانش آموز -->
                                            <button type="button" class="btn recipient-btn"
                                                    data-target="student"
                                                    data-tel="<?php echo htmlspecialchars($user->tel1 ?? ''); ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="selection-indicator me-2">
                                                        <i class="fas fa-user-graduate"></i>
                                                    </div>
                                                    <div class="text-start">
                                                        <div class="fw-bold mb-1">دانش آموز</div>
                                                        <div class="small text-muted">
                                                            <?php if (!empty($user->tel1)): ?>
                                                                <?php echo $user->tel1; ?>
                                                            <?php else: ?>
                                                                <span class="text-danger">شماره ندارد</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>

                                            <!-- دکمه پدر -->
                                            <button type="button" class="btn recipient-btn"
                                                    data-target="father"
                                                    data-tel="<?php echo htmlspecialchars($user->tel3 ?? ''); ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="selection-indicator me-2">
                                                        <i class="fas fa-male"></i>
                                                    </div>
                                                    <div class="text-start">
                                                        <div class="fw-bold mb-1">پدر</div>
                                                        <div class="small text-muted">
                                                            <?php if (!empty($user->tel3)): ?>
                                                                <?php echo $user->tel3; ?>
                                                            <?php else: ?>
                                                                <span class="text-danger">شماره ندارد</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>

                                            <!-- دکمه مادر -->
                                            <button type="button" class="btn recipient-btn"
                                                    data-target="mother"
                                                    data-tel="<?php echo htmlspecialchars($user->tel2 ?? ''); ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="selection-indicator me-2">
                                                        <i class="fas fa-female"></i>
                                                    </div>
                                                    <div class="text-start">
                                                        <div class="fw-bold mb-1">مادر</div>
                                                        <div class="small text-muted">
                                                            <?php if (!empty($user->tel2)): ?>
                                                                <?php echo $user->tel2; ?>
                                                            <?php else: ?>
                                                                <span class="text-danger">شماره ندارد</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>

                                        <!-- فیلدهای مخفی برای ارسال به سرور -->
                                        <input type="hidden" name="send_to_student" id="sendToStudent" value="0">
                                        <input type="hidden" name="send_to_father" id="sendToFather" value="0">
                                        <input type="hidden" name="send_to_mother" id="sendToMother" value="0">
                                    </div>

                                    <div class="col-12">
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token_sms'] = $hash;
                                        ?>
                                        <input type="hidden" name="token" value="<?php echo $hash ?>">
                                        <button type="submit" class="btn btn-primary w-100 fw-bold" name="send-sms"
                                                id="submitBtn">
                                            <i class="fas fa-paper-plane me-2"></i> ارسال پیامک
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            // مدیریت کلیک روی دکمه‌های گیرندگان
                            const recipientButtons = document.querySelectorAll('.recipient-btn');
                            const submitBtn = document.getElementById('submitBtn');
                            const smsText = document.getElementById('smsText');

                            // تابع برای به‌روزرسانی وضعیت دکمه ارسال
                            function updateSubmitButton() {
                                const studentField = document.getElementById('sendToStudent');
                                const fatherField = document.getElementById('sendToFather');
                                const motherField = document.getElementById('sendToMother');

                                // بررسی آیا حداقل یک گیرنده معتبر انتخاب شده است
                                let hasValidSelection = false;
                                recipientButtons.forEach(button => {
                                    const target = button.getAttribute('data-target');
                                    const tel = button.getAttribute('data-tel');
                                    const hiddenField = document.getElementById('sendTo' + target.charAt(0).toUpperCase() + target.slice(1));

                                    if (hiddenField.value === '1' && tel) {
                                        hasValidSelection = true;
                                    }
                                });

                                // بررسی متن پیامک
                                const hasSmsText = smsText.value.trim().length > 0;

                                // به‌روزرسانی دکمه ارسال
                                if (hasValidSelection && hasSmsText) {
                                    submitBtn.disabled = false;
                                    submitBtn.className = 'btn btn-success w-100 fw-bold';
                                } else {
                                    submitBtn.disabled = true;
                                    submitBtn.className = 'btn btn-secondary w-100 fw-bold';
                                }
                            }

                            recipientButtons.forEach(button => {
                                // تنظیم وضعیت اولیه برای دکمه‌هایی که شماره دارند
                                const tel = button.getAttribute('data-tel');
                                if (!tel) {
                                    button.classList.add('disabled', 'no-phone');
                                    button.querySelector('.selection-indicator i').className = 'fas fa-ban text-danger';
                                    button.querySelector('.small').innerHTML = '<span class="text-danger">شماره ندارد</span>';
                                }

                                button.addEventListener('click', function () {
                                    const target = this.getAttribute('data-target');
                                    const tel = this.getAttribute('data-tel');

                                    // اگر شماره تلفن وجود ندارد، نمی‌توان انتخاب کرد
                                    if (!tel) {
                                        return;
                                    }

                                    // تغییر وضعیت انتخاب
                                    const isSelected = this.classList.contains('selected');

                                    if (isSelected) {
                                        // حالت انتخاب نشده
                                        this.classList.remove('selected');
                                        this.querySelector('.selection-indicator').classList.remove('selected');
                                        this.querySelector('.selection-indicator i').className = this.querySelector('.selection-indicator i').className
                                            .replace('text-white', 'text-dark')
                                            .replace('fa-check-circle', this.getAttribute('data-icon') || 'fa-user');

                                        // تغییر آیکون به حالت اولیه
                                        if (target === 'student') {
                                            this.querySelector('.selection-indicator i').className = 'fas fa-user-graduate text-dark';
                                        } else if (target === 'father') {
                                            this.querySelector('.selection-indicator i').className = 'fas fa-male text-dark';
                                        } else if (target === 'mother') {
                                            this.querySelector('.selection-indicator i').className = 'fas fa-female text-dark';
                                        }
                                    } else {
                                        // حالت انتخاب شده
                                        this.classList.add('selected');
                                        this.querySelector('.selection-indicator').classList.add('selected');
                                        this.querySelector('.selection-indicator i').className = this.querySelector('.selection-indicator i').className
                                            .replace('text-dark', 'text-white')
                                            .replace('fa-user-graduate', 'fa-check-circle')
                                            .replace('fa-male', 'fa-check-circle')
                                            .replace('fa-female', 'fa-check-circle');
                                    }

                                    // به‌روزرسانی فیلد مخفی مربوطه
                                    const hiddenField = document.getElementById('sendTo' + target.charAt(0).toUpperCase() + target.slice(1));
                                    hiddenField.value = this.classList.contains('selected') ? '1' : '0';

                                    // به‌روزرسانی وضعیت دکمه ارسال
                                    updateSubmitButton();
                                });
                            });

                            // به‌روزرسانی وضعیت دکمه ارسال بر اساس متن پیامک
                            smsText.addEventListener('input', function () {
                                updateSubmitButton();
                            });

                            // اعتبارسنجی قبل از ارسال فرم
                            document.querySelector('#form-sms').addEventListener('submit', function (e) {
                                if (smsText.value.trim() === '') {
                                    e.preventDefault();
                                    alert('لطفاً متن پیامک را وارد کنید.');
                                    smsText.focus();
                                    return false;
                                }

                                // بررسی حداقل یک گیرنده انتخاب شده باشد
                                const selectedButtons = document.querySelectorAll('.recipient-btn.selected');
                                if (selectedButtons.length === 0) {
                                    e.preventDefault();
                                    alert('لطفاً حداقل یک گیرنده انتخاب کنید.');
                                    return false;
                                }

                                // بررسی شماره‌های تلفن برای گیرندگان انتخاب شده
                                let hasValidRecipient = false;
                                selectedButtons.forEach(button => {
                                    const tel = button.getAttribute('data-tel');
                                    if (tel) {
                                        hasValidRecipient = true;
                                    }
                                });

                                if (!hasValidRecipient) {
                                    e.preventDefault();
                                    alert('هیچ گیرنده معتبری (دارای شماره تلفن) انتخاب نشده است.');
                                    return false;
                                }

                                // تأیید نهایی
                                const selectedCount = selectedButtons.length;
                                const confirmSend = confirm(`آیا از ارسال پیامک به ${selectedCount} نفر اطمینان دارید؟`);
                                if (!confirmSend) {
                                    e.preventDefault();
                                    return false;
                                }

                                return true;
                            });

                            // مقداردهی اولیه دکمه ارسال
                            updateSubmitButton();
                        });
                    </script>

                    <style>
                        /* استایل دکمه‌های گیرنده */
                        .recipient-btn {
                            transition: all 0.2s ease;
                            position: relative;
                            min-width: 140px;
                            padding: 8px 12px;
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            text-align: right;
                            background-color: white;
                            color: #333;
                            margin-bottom: 5px;
                        }

                        /* حالت انتخاب شده */
                        .recipient-btn.selected {
                            background-color: #28a745;
                            color: white !important;
                            border-color: #28a745;
                            box-shadow: 0 2px 5px rgba(40, 167, 69, 0.2);
                        }

                        /* حالت عادی (انتخاب نشده) */
                        .recipient-btn:not(.selected):not(.disabled):hover {
                            border-color: #28a745;
                            background-color: #f8f9fa;
                            transform: translateY(-1px);
                        }

                        /* غیرفعال (بدون شماره) */
                        .recipient-btn.disabled {
                            opacity: 0.6;
                            cursor: not-allowed;
                            border-color: #dc3545;
                            background-color: #f8f9fa;
                        }

                        .recipient-btn.disabled .text-danger {
                            color: #dc3545 !important;
                        }

                        /* نشانگر انتخاب */
                        .selection-indicator {
                            width: 30px;
                            height: 30px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 14px;
                            transition: all 0.2s ease;
                        }

                        /* حالت انتخاب شده */
                        .selection-indicator.selected {
                            background-color: rgba(255, 255, 255, 0.2);
                        }

                        /* حالت عادی (انتخاب نشده) */
                        .selection-indicator:not(.selected) {
                            background-color: #f8f9fa;
                            color: #495057;
                        }

                        /* آیکون‌ها در حالت عادی */
                        .recipient-btn:not(.selected) .selection-indicator i {
                            color: #495057;
                        }

                        /* آیکون‌ها در حالت انتخاب شده */
                        .recipient-btn.selected .selection-indicator i {
                            color: white;
                        }

                        /* متن دکمه‌ها */
                        .recipient-btn .fw-bold {
                            font-size: 14px;
                            font-weight: 600;
                        }

                        .recipient-btn .small {
                            font-size: 12px;
                        }

                        /* متن در حالت انتخاب شده */
                        .recipient-btn.selected .text-muted {
                            color: rgba(255, 255, 255, 0.8) !important;
                        }

                        /* استایل دکمه ارسال */
                        #submitBtn {
                            padding: 10px;
                            font-size: 15px;
                            border-radius: 8px;
                            transition: all 0.2s ease;
                        }

                        #submitBtn:disabled {
                            opacity: 0.7;
                            cursor: not-allowed;
                        }

                        #submitBtn:not(:disabled):hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
                        }
                    </style>

                    <!-- در بخش col-lg-8 بعد از کارتهای دیگر اضافه کنید: -->

                    <div class="card custom-card mt-3">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-indigo">
                            <i class="fas fa-history me-2"></i> تاریخچه پیامک‌های ارسال شده
                        </div>
                        <div class="card-body">
                            <?php
                            // دریافت تاریخچه پیامک‌ها از دیتابیس
                            $smsHistory = [];
                            try {
                                $sql = "SELECT * FROM sms_logs WHERE student_id = ? ORDER BY send_date DESC, send_time DESC";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute([$_GET['Id']]);
                                $smsHistory = $stmt->fetchAll(PDO::FETCH_OBJ);
                            } catch (PDOException $e) {
                                error_log("Error fetching SMS history: " . $e->getMessage());
                            }

                            if (empty($smsLogsData)):
                                ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">هیچ پیامکی برای این دانش‌آموز ارسال نشده است.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                        <tr class="bg-light">
                                            <th width="40">#</th>
                                            <th width="100">تاریخ ارسال</th>
                                            <th width="80">زمان</th>
                                            <th>متن پیامک</th>
                                            <th width="150">گیرندگان</th>
                                            <th width="100">ارسال کننده</th>
                                            <th width="80">وضعیت</th>
                                            <th width="60">عملیات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($smsLogsData as $index => $sms):
                                            // دیکد کردن اطلاعات گیرندگان از JSON
                                            $recipients = json_decode($sms->recipients, true);
                                            if (!is_array($recipients)) {
                                                $recipients = [];
                                            }

                                            // تبدیل تاریخ شمسی
                                            $sendDate = explode('-', $sms->send_date);
                                            if (count($sendDate) === 3) {
                                                $jDate = gregorian_to_jalali($sendDate[0], $sendDate[1], $sendDate[2]);
                                                $persianDate = implode('/', $jDate);
                                            } else {
                                                $persianDate = $sms->send_date;
                                            }
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $index + 1; ?></td>
                                                <td class="text-nowrap"><?php echo $persianDate; ?></td>
                                                <td class="text-nowrap"><?php echo $sms->send_time; ?></td>
                                                <td>
                                                    <div class="message-preview"
                                                         style="max-height: 60px; overflow: hidden;">
                                                        <?php
                                                        $msg = $sms->message_text;
                                                        // mb_strimwidth automatically adds the '...' if the string is too long
                                                        echo htmlspecialchars(mb_strimwidth($msg, 0, 20, "..."));
                                                        ?>
                                                    </div>
                                                    <?php if (strlen($sms->message_text) > 100): ?>
                                                        <a href="#" class="text-primary small show-more"
                                                           data-message="<?php echo htmlspecialchars($sms->message_text); ?>">
                                                            نمایش بیشتر
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="recipients-list">
                                                        <?php foreach ($recipients as $recipient): ?>
                                                            <?php
                                                            $icon = '';
                                                            $color = '';
                                                            switch ($recipient['type']) {
                                                                case 'student':
                                                                    $icon = 'fa-user-graduate';
                                                                    $color = 'primary';
                                                                    $label = 'دانش آموز';
                                                                    break;
                                                                case 'father':
                                                                    $icon = 'fa-male';
                                                                    $color = 'info';
                                                                    $label = 'پدر';
                                                                    break;
                                                                case 'mother':
                                                                    $icon = 'fa-female';
                                                                    $color = 'success';
                                                                    $label = 'مادر';
                                                                    break;
                                                                default:
                                                                    $icon = 'fa-user';
                                                                    $color = 'secondary';
                                                                    $label = $recipient['type'];
                                                            }
                                                            ?>
                                                            <div class="d-flex align-items-center mb-1">
                                                                        <span class="badge bg-<?php echo $color; ?> me-1">
                                                                            <i class="fas <?php echo $icon; ?> fa-xs"></i>
                                                                        </span>
                                                                <small>
                                                                    <?php echo $label; ?>
                                                                    <?php if (!empty($recipient['phone'])): ?>
                                                                        <span class="text-muted">(<?php echo $recipient['phone']; ?>)</span>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo $sms->sender_admin; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusBadge = [
                                                        'ارسال شده' => 'success',
                                                        'ناموفق' => 'danger',
                                                        'در صف ارسال' => 'warning',
                                                        'تحویل شده' => 'info'
                                                    ];
                                                    $status = $sms->status ?? 'ارسال شده';
                                                    $badgeColor = $statusBadge[$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badgeColor; ?>">
                                                                <?php echo $status; ?>
                                                            </span>
                                                </td>
                                                <td>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary view-sms"
                                                            data-id="<?php echo $sms->id; ?>"
                                                            title="مشاهده جزئیات">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- مودال برای نمایش کامل پیام -->
                                <div class="modal fade" id="smsDetailModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">جزئیات پیامک</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="بستن"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div id="smsDetailContent"></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    بستن
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>




                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            // نمایش متن کامل پیامک
                            document.querySelectorAll('.show-more').forEach(link => {
                                link.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    const message = this.getAttribute('data-message');
                                    showSmsDetail({
                                        message_text: message
                                    });
                                });
                            });

                            // مشاهده جزئیات پیامک
                            document.querySelectorAll('.view-sms').forEach(button => {
                                button.addEventListener('click', function () {
                                    const smsId = this.getAttribute('data-id');
                                    fetchSmsDetail(smsId);
                                });
                            });

                            function fetchSmsDetail(smsId) {

                                // استفاده از مسیر مطلق
                                const currentPath = window.location.pathname;
                                const pathParts = currentPath.split('/');

                                // پیدا کردن مسیر تا پوشه admin
                                let adminPath = '';
                                for (let i = 0; i < pathParts.length; i++) {
                                    if (pathParts[i] === 'admin') {
                                        adminPath = pathParts.slice(0, i + 1).join('/');
                                        break;
                                    }
                                }

                                const url = adminPath + '/Bill/ajax/get_sms_detail.php?id=' + smsId;
                                console.log(url);
                                fetch(url)
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            showSmsDetail(data.sms);
                                        } else {
                                            alert('خطا: ' + (data.message || 'خطای نامشخص'));
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('خطا در ارتباط با سرور');
                                    });
                            }

                            function extractFullStatus(details) {
                                if (!details || typeof details !== "string") return "نامشخص";

                                // --- 1. Map of Direct Text Matches ---
                                const textMap = {
                                    "متن حاوی لینک می باشد": "متن حاوی لینک می باشد",
                                    "اعتبار کافی نمی باشد": "اعتبار پنل کافی نیست",
                                    "شماره گیرنده نامعتبر است": "شماره موبایل اشتباه است",
                                    "ارسال نشده": "در لیست سیاه (Blacklist)",
                                    "فیلتر شده": "متن حاوی کلمات ممنوعه",
                                    "ناموفق": "ارسال ناموفق",
                                    "الزام استفاده از ApiKey به جای رمز عبور": "الزام استفاده از ApiKey به جای رمز عبور",
                                    "الزام تنظیم IP مجاز برای استفاده از API": "الزام تنظیم IP مجاز برای استفاده از API",
                                    "مسدود شدن IP به دلیل تلاش ناموفق استفاده از API": "مسدود شدن IP به دلیل تلاش ناموفق استفاده از API",
                                    "نام کاربری یا رمز عبور اشتباه می باشد": "نام کاربری یا رمز عبور اشتباه می باشد",
                                    "اعتبار کافی نمی باشد": "اعتبار کافی نمی باشد",
                                    "محدودیت در ارسال روزانه": "محدودیت در ارسال روزانه",
                                    "محدودیت در حجم ارسال": "محدودیت در حجم ارسال",
                                    "شماره فرستنده معتبر نمی باشد": "شماره فرستنده معتبر نمی باشد",
                                    "سامانه در حال بروزرسانی می باشد": "سامانه در حال بروزرسانی می باشد",
                                    "متن حاوی کلمه فیلتر شده می باشد": "متن حاوی کلمه فیلتر شده می باشد",
                                    "ارسال از خطوط عمومی از طریق وب سرویس امکان پذیر نمی باشد": "ارسال از خطوط عمومی از طریق وب سرویس امکان پذیر نمی باشد",
                                    "کاربر مورد نظر فعال نمی باشد": "کاربر مورد نظر فعال نمی باشد",
                                    "مدارک کاربر کامل نمی باشد": "مدارک کاربر کامل نمی باشد",
                                    "شماره گیرنده ای یافت نشد": "شماره گیرنده ای یافت نشد",
                                    "متن پیامک خالی می باشد": "متن پیامک خالی می باشد",
                                };

                                // Check if the input exactly matches or contains one of our keys
                                for (let key in textMap) {
                                    if (details.includes(key)) {
                                        return textMap[key];
                                    }
                                }

                                // --- 2. Existing Logic for recId (Success) ---
                                const recIdMatch = details.match(/recId:\s*(\d+)/);
                                if (recIdMatch) return "ارسال موفقیت‌آمیز";


                                return "نامشخص";
                            }


                            function showSmsDetail(sms) {
                                // تبدیل تاریخ شمسی
                                let persianDate = sms.send_date;
                                if (sms.send_date && sms.send_date.includes('-')) {
                                    const parts = sms.send_date.split('-');
                                    if (parts.length === 3) {
                                        // در اینجا باید تابع gregorian_to_jalali را در جاوااسکریپت داشته باشید
                                        // اگر ندارید، می‌توانید از کتابخانه‌ای مانند jalali-js استفاده کنید
                                        persianDate = sms.send_date; // یا تبدیل به شمسی
                                    }
                                }

                                let recipientsHtml = '';
                                if (sms.recipients && Array.isArray(sms.recipients)) {
                                    try {
                                        sms.recipients.forEach(recipient => {
                                            let icon = 'fa-user',
                                                color = 'secondary',
                                                label = recipient.type;
                                            switch (recipient.type) {
                                                case 'student':
                                                    icon = 'fa-user-graduate';
                                                    color = 'primary';
                                                    label = 'دانش آموز';
                                                    break;
                                                case 'father':
                                                    icon = 'fa-male';
                                                    color = 'info';
                                                    label = 'پدر';
                                                    break;
                                                case 'mother':
                                                    icon = 'fa-female';
                                                    color = 'success';
                                                    label = 'مادر';
                                                    break;
                                            }
                                            const status = extractFullStatus(recipient.details);
                                            recipientsHtml += `
                                                <div class="alert alert-${color} d-flex justify-content-between align-items-center">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas ${icon} fa-lg me-2"></i>
                                                        <div>
                                                            <strong>${label}</strong>
                                                            ${recipient.phone ? `<div class="small mt-1">${recipient.phone}</div>` : ''}
                                                        </div>
                                                    </div>

                                                    <span class="badge bg-${getStatusColor(recipient.details)}">${status}</span>
                                                </div>
                                            `;
                                        });
                                    } catch (e) {
                                        recipientsHtml = '<div class="text-danger">خطا در نمایش گیرندگان</div>';
                                    }
                                }

                                const html = `
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <i class="fas fa-envelope me-2"></i> متن پیامک
                        </div>
                        <div class="card-body">
                            <p style="white-space: pre-wrap; line-height: 1.8;">${sms.message_text}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <i class="fas fa-info-circle me-2"></i> اطلاعات ارسال
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>تاریخ:</strong> ${sms.persian_date}
                            </div>
                            <div class="mb-2">
                                <strong>زمان:</strong> ${sms.send_time}
                            </div>
                            <div class="mb-2">
                                <strong>ارسال کننده:</strong> ${sms.sender_admin}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <i class="fas fa-users me-2"></i> گیرندگان
                        </div>
                        <div class="card-body">
                            ${recipientsHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;

                                document.getElementById('smsDetailContent').innerHTML = html;
                                const modal = new bootstrap.Modal(document.getElementById('smsDetailModal'));
                                modal.show();
                            }

                            function getStatusColor(status) {
                                const colors = {

                                    // خطاها
                                    'الزام استفاده از ApiKey به جای رمز عبور': 'danger',
                                    'الزام تنظیم IP مجاز برای استفاده از API': 'danger',
                                    'مسدود شدن IP به دلیل تلاش ناموفق استفاده از API': 'danger',
                                    'نام کاربری یا رمز عبور اشتباه می باشد': 'danger',
                                    'اعتبار کافی نمی باشد': 'warning',
                                    'محدودیت در ارسال روزانه': 'warning',
                                    'محدودیت در حجم ارسال': 'warning',
                                    'شماره فرستنده معتبر نمی باشد': 'danger',
                                    'سامانه در حال بروزرسانی می باشد': 'info',
                                    'متن حاوی کلمه فیلتر شده می باشد': 'danger',
                                    'ارسال از خطوط عمومی از طریق وب سرویس امکان پذیر نمی باشد': 'danger',
                                    'کاربر مورد نظر فعال نمی باشد': 'danger',
                                    'ارسال نشده': 'danger',
                                    'مدارک کاربر کامل نمی باشد': 'warning',
                                    'متن حاوی لینک می باشد': 'danger',
                                    'ارسال به بیش از 1 شماره همراه بدون درج "لغو11" ممکن نیست': 'warning',
                                    'شماره گیرنده ای یافت نشد': 'danger',
                                    'متن پیامک خالی می باشد': 'warning',
                                    'شماره گیرنده نامعتبر است': 'danger',
                                    'وجود شماره در لیست سیاه مخاطرات': 'danger',

                                    // موفق
                                    'ارسال موفقیت‌آمیز': 'success',

                                    // وضعیت‌های احتمالی دیگر
                                    'خطا': 'danger',
                                    'در صف ارسال': 'warning',
                                    'منتظر ارسال': 'secondary',
                                    'تحویل شده': 'info',
                                    'ارسال شده': 'success',
                                };

                                // بررسی recId
                                if (status.includes("ارسال موفقیت‌آمیز")) {
                                    return 'success';
                                }

                                return colors[status] || 'secondary';
                            }

                        });
                    </script>

                    <style>
                        .message-preview {
                            line-height: 1.5;
                            display: -webkit-box;
                            -webkit-line-clamp: 3;
                            -webkit-box-orient: vertical;
                        }

                        .recipients-list .badge {
                            width: 24px;
                            height: 24px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            padding: 0;
                        }

                        .table-hover tbody tr:hover {
                            background-color: rgba(0, 123, 255, 0.05);
                        }

                        .sms-history-card .card-header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        }
                    </style>

                </div> <!-- پایان col-lg-4 -->

                <div class="col-lg-8">
                    <!--   here   -->
                    <div class="card custom-card" style="direction: rtl; text-align: right;">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-purple">
                            فرم وضعیت آزمون و شرح حال دانش آموز برای پایان سال
                        </div>
                        <div class="card-body">
                            <!-- Top table: Exam Status -->
                            <div class="col-12 mb-4">
                                <h5 class="mb-3">وضعیت آزمون‌ها</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr class="bg-light">
                                            <th>#</th>
                                            <th>نوع آزمون</th>
                                            <th>ثبت‌نام</th>
                                            <th>آزمون</th>
                                            <th>توضیحات عدم ثبتنام</th>
                                            <th>توضیحات عدم شرکت درآزمون</th>
                                            <th>نتیجه</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($studentCourseStatusRows)):
                                            $num = 1;
                                            foreach ($studentCourseStatusRows as $row):
                                                $exam_type = htmlspecialchars($row['exam_type'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $registered = htmlspecialchars($row['registered'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $attended = htmlspecialchars($row['attended'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $not_registered_reason = htmlspecialchars($row['not_registered_reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $not_attended_reason = htmlspecialchars($row['not_attended_reason'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $result = htmlspecialchars($row['result'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $row_id = (int)($row['id'] ?? 0);
                                                $olympiad_fields_raw = $row['olympiad_fields'] ?? null;
                                                $olympiad_fields_text = '';

                                                if ($exam_type === 'المپیاد' && $olympiad_fields_raw) {
                                                    $fields_array = json_decode($olympiad_fields_raw, true);

                                                    if (is_array($fields_array) && !empty($fields_array)) {
                                                        $olympiad_fields_text = implode('، ', $fields_array);
                                                    }
                                                }

                                                ?>
                                                <tr>
                                                    <td><?= $num ?></td>
                                                    <td>
                                                        <?php if ($exam_type === 'المپیاد' && $olympiad_fields_text): ?>
                                                            <span class="exam-type-text"
                                                                  title="رشته‌ها: <?= htmlspecialchars($olympiad_fields_text, ENT_QUOTES, 'UTF-8') ?>">
                                                                        المپیاد [<?= htmlspecialchars($olympiad_fields_text, ENT_QUOTES, 'UTF-8') ?>]
                                                                    </span>
                                                        <?php else: ?>
                                                            <?= $exam_type ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= $registered ?></td>
                                                    <td><?= $attended ?></td>
                                                    <td><?= $not_registered_reason ?></td>
                                                    <td><?= $not_attended_reason ?></td>
                                                    <td><?= $result ?></td>

                                                </tr>
                                                <?php $num++;
                                            endforeach;
                                        else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">هیچ رکوردی یافت نشد.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Bottom table: Semester & Comments -->
                            <div class="col-12">
                                <h5 class="mb-3">نمرات و نظرات</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr class="bg-light">
                                            <th>معدل ترم اول</th>
                                            <th>معدل ترم دوم</th>
                                            <th>نظر دانش‌آموز</th>
                                            <th>نظر پشتیبان</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($studentStatusRows)):
                                            foreach ($studentStatusRows as $row):
                                                $avg1_display = (!isset($row['avg_first_semester']) || $row['avg_first_semester'] === '') ? '-' : htmlspecialchars($row['avg_first_semester'], ENT_QUOTES, 'UTF-8');
                                                $avg2_display = (!isset($row['avg_second_semester']) || $row['avg_second_semester'] === '') ? '-' : htmlspecialchars($row['avg_second_semester'], ENT_QUOTES, 'UTF-8');
                                                $student_comment = nl2br(htmlspecialchars($row['student_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                $supporter_comment = nl2br(htmlspecialchars($row['supporter_comment'] ?? '', ENT_QUOTES, 'UTF-8'));
                                                ?>
                                                <tr>
                                                    <td><?= $avg1_display ?></td>
                                                    <td><?= $avg2_display ?></td>
                                                    <td><?= $student_comment ?></td>
                                                    <td><?= $supporter_comment ?></td>
                                                </tr>
                                            <?php
                                            endforeach;
                                        else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">هیچ رکوردی یافت نشد.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!--to here-->
                    <div class="card custom-card">
                        <div class="card-header p-3 tx-medium my-auto tx-white bg-pink">
                            خلاصه وضعیت دانش آموز
                        </div>
                        <div class="card-body">
                            <div class="mb-3 text-end">
                                <button class="btn btn-primary btn-sm"
                                        onclick="window.open('<?php echo url('admin/panel/api-viewquiz.php') ?>'+'?user='+'<?php echo $res->username ?>' ,'گزارش آزمون ها'
                                                ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                    <i class="fe fe-file-text"></i> مشاهده اطلاعات آزمون‌ها
                                </button>
                            </div>
                            <table class="table table-bordered">
                                <thead>
                                <tr class="bg-light">
                                    <th>#</th>
                                    <th>پایه‌های در حال اجرا</th>
                                    <th>کلاس</th>
                                    <th>آزمون</th>
                                    <th>فیلم</th>
                                    <th>جزوات</th>
                                    <th>تکالیف</th>
                                    <th>محاسبه</th>

                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                $totalColor = 0;
                                $totalRows = 0;

                                $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                $wer = $conn->prepare($swr);
                                $wer->execute([1, 1, $_GET['Id']]);
                                $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                if ($wer->rowCount() > 0) {
                                    $num = 1;
                                    foreach ($recive as $res) {

                                        ?>
                                        <tr>
                                            <td class="wd-15"><?php echo $num ?></td>
                                            <td><?php echo $res->coursename ?></td>

                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-icon btn-danger "
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewattendance.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-check-square"></i>
                                                </div>
                                                <hr>
                                                <?php

                                                if (hasThisCourseAnyMeetings($res->codecourse)) {
                                                    $totalColor += $res->color;
                                                    $totalRows++;


                                                    if ($res->color == 0) {
                                                        echo '  <div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    }
                                                    if ($res->color == 1) {
                                                        echo ' <div class="rounded-circle bg-warning" data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title="  حضور بین 25% تا 50% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div> ';
                                                    }
                                                    if ($res->color == 2) {
                                                        echo '  <div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px; background-color: #fffb02;"  ></div>';
                                                    }
                                                    if ($res->color == 3) {
                                                        echo '   <div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    }
                                                } else {
                                                    echo '<div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد حضور و غیاب"
                                                 style="width: 30px; height: 30px;"></div>';
                                                }

                                                ?>


                                            </td>
                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-danger btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewquiz.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fa fa-question"></i>
                                                </div>
                                                <hr>
                                                <?php
                                                $colorquiz = getTblQuizColor($res->codecourse, $res->username);
                                                $totalquiz = $colorquiz[0] + $colorquiz[1];
                                                if ($totalquiz > 0) {
                                                    $darsadquiz = $colorquiz[0] / $totalquiz;
                                                    $darsadquiz = $darsadquiz * 100;
                                                } else {
                                                    $darsadquiz = 0;
                                                }

                                                if ($darsadquiz < 25) {
                                                    echo '  <div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در  آزمــون"
                                                         style="width: 30px; height: 30px;"></div>';
                                                }
                                                if ($darsadquiz >= 25 and $darsadquiz < 50) {
                                                    echo ' <div class="rounded-circle bg-warning" data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title="  حضور بین 25% تا 50% در آزمــون "
                                                         style="width: 30px; height: 30px;"></div> ';
                                                }
                                                if ($darsadquiz >= 50 and $darsadquiz < 75) {
                                                    echo '  <div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در آزمــون"
                                                         style="width: 30px; height: 30px; background-color: #fffb02"></div>';
                                                }
                                                if ($darsadquiz >= 75) {
                                                    echo '   <div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در آزمــون"
                                                         style="width: 30px; height: 30px;"></div>';
                                                }
                                                ?>


                                            </td>
                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-secondary btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewFilm.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-video"></i>
                                                </div>
                                                <hr>
                                                <?php
                                                $logfilm = retLogViewFilm($res->codecourse, $res->username);
                                                if ($logfilm) {
                                                    echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                } else {
                                                    echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                }
                                                ?>

                                            </td>
                                            <!--                                            takalif-->

                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-primary btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewdoc.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-book-open"></i>
                                                </div>
                                                <hr>
                                                <?php
                                                $logdoc = retLogViewDoc($res->codecourse, $res->username);
                                                if ($logdoc) {
                                                    echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                } else {
                                                    echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                }
                                                ?>
                                            </td>
                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-secondary btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewhomework.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-video"></i>
                                                </div>
                                                <!--                                                <hr>-->
                                                <!--                                                --><?php
                                                //                                                $logfilm = retLogViewFilm($res->codecourse, $res->username);
                                                //                                                if ($logfilm) {
                                                //                                                    echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                //                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                //                                                 style="width: 30px; height: 30px;"></div>';
                                                //                                                } else {
                                                //                                                    echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                //                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                //                                                 style="width: 30px; height: 30px;"></div>';
                                                //                                                }
                                                //                                                ?>

                                            </td>
                                            <td class="wd-20">
                                                <?php
                                                switch ($res->type_process) {
                                                    case 0:
                                                        $typestatus = 'اتوماتیک';
                                                        break;
                                                    case 1:
                                                        $typestatus = 'منتظر بررسی';
                                                        break;
                                                    case 2:
                                                        $typestatus = 'دستی';
                                                        break;
                                                }
                                                ?>
                                                <div class="badge badge-success"
                                                     onclick="window.open('<?php echo url('admin/panel/requestchange.php') ?>'+'?user='+'<?php echo $res->username ?>'+'&course='+'<?php echo $res->codecourse ?>' ,'تغییر نحوه محاسبه رنگ بندی'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <?php echo $typestatus ?></div>
                                            </td>


                                        </tr>
                                        <?php
                                        $num++;
                                    }
                                }
                                ?>
                                <tr class="bg-light">
                                    <th>#</th>
                                    <th>میانگین</th>
                                    <th><?php
                                        if ($totalRows > 0) {
                                            $avgColor = round($totalColor / $totalRows);
                                            if ($avgColor == 0)
                                                echo "<div class='rounded-circle bg-danger' style='width:30px;height:30px;'></div>";
                                            if ($avgColor == 1)
                                                echo "<div class='rounded-circle bg-warning' style='width:30px;height:30px;'></div>";
                                            if ($avgColor == 2)
                                                echo "<div class='rounded-circle' style='width:30px;height:30px;background-color:#fffb02;'></div>";
                                            if ($avgColor == 3)
                                                echo "<div class='rounded-circle bg-success' style='width:30px;height:30px;'></div>";
                                        }
                                        ?></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                                </tbody>
                            </table>
                            <table class="table table-bordered">
                                <thead>
                                <tr class="bg-light">
                                    <th>#</th>
                                    <th>دوره‌های فوق برنامه</th>
                                    <th>کلاس</th>
                                    <th>فیلم</th>
                                    <th>جزوات</th>
                                    <th>تولید لینک</th>

                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                $totalColor = 0;
                                $totalRows = 0;

                                $swr2 = 'SELECT * FROM dbcourse_demo INNER JOIN course_demo ON dbcourse_demo.codecourse=course_demo.codecourse WHERE dbcourse_demo.username =?';
                                $wer2 = $conn->prepare($swr2);
                                $wer2->execute([$_GET['Id']]);
                                $recive2 = $wer2->fetchAll(PDO::FETCH_OBJ);
                                if ($wer2->rowCount() > 0) {
                                    $num = 1;
                                    foreach ($recive2 as $res1) {

                                        ?>
                                        <tr>
                                            <td class="wd-15"><?php echo $num ?></td>
                                            <td><?php echo $res1->coursename ?></td>

                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-icon btn-danger "
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewattendance.php?demo') ?>'+'&user='+'<?php echo $res1->username ?>'+'&course='+'<?php echo $res1->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-check-square"></i>
                                                </div>
                                                <?php

                                                //                                                $totalColor += $res1->color;
                                                $totalRows++;

                                                //                                                if ($res1->color == 0) {
                                                //                                                    echo '  <div class="rounded-circle bg-danger" data-placement="top"
                                                //                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در کلاس آنلاین"
                                                //                                                         style="width: 30px; height: 30px;"></div>';
                                                //                                                }
                                                //                                                if ($res1->color == 1) {
                                                //                                                    echo ' <div class="rounded-circle bg-warning" data-placement="top"
                                                //                                                         data-bs-toggle="tooltip"
                                                //                                                         title="  حضور بین 25% تا 50% در کلاس آنلاین"
                                                //                                                         style="width: 30px; height: 30px;"></div> ';
                                                //                                                }
                                                //                                                if ($res1->color == 2) {
                                                //                                                    echo '  <div class="rounded-circle " data-placement="top"
                                                //                                                         data-bs-toggle="tooltip"
                                                //                                                         title=" حضور بین 50% تا 75% در کلاس آنلاین"
                                                //                                                         style="width: 30px; height: 30px; background-color: #fffb02;"  ></div>';
                                                //                                                }
                                                //                                                if ($res1->color == 3) {
                                                //                                                    echo '   <div class="rounded-circle bg-success" data-placement="top"
                                                //                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در کلاس آنلاین"
                                                //                                                         style="width: 30px; height: 30px;"></div>';
                                                //                                                }

                                                ?>


                                            </td>
                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-secondary btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewFilm.php?experimental') ?>'+'&user='+'<?php echo $res1->username ?>'+'&course='+'<?php echo $res1->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-video"></i>
                                                </div>
                                                <hr>
                                                <?php
                                                $logfilm = retLogViewFilmDemo($res1->codecourse, $res1->username);
                                                if ($logfilm) {
                                                    echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                } else {
                                                    echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                }
                                                ?>

                                            </td>
                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-primary btn-icon"
                                                     onclick="window.open('<?php echo url('admin/Student/log-viewdoc.php?experimental') ?>'+'&user='+'<?php echo $res1->username ?>'+'&course='+'<?php echo $res1->codecourse ?>' ,'گزارش'
                                                             ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                    <i class="fe fe-book-open"></i>
                                                </div>
                                                <hr>
                                                <?php
                                                $logdoc = retLogViewDocDemo($res1->codecourse, $res1->username);
                                                if ($logdoc) {
                                                    echo '  <div class="rounded-circle bg-teal" data-placement="top"
                                                 data-bs-toggle="tooltip" title="دارای گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                } else {
                                                    echo '  <div class="rounded-circle bg-dark" data-placement="top"
                                                 data-bs-toggle="tooltip" title="فاقد گزارش"
                                                 style="width: 30px; height: 30px;"></div>';
                                                }
                                                ?>
                                            </td>
                                            <td class="wd-20">
                                                <a href="suporting.php?Id=<?= $_GET['Id'] ?>&code=<?= $res1->codecourse ?>&generate"
                                                   class="btn btn-info text-white">لینک ورود</a>
                                            </td>

                                        </tr>
                                        <?php
                                        $num++;
                                    }
                                }
                                ?>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card custom-card">
                                <div class="card-header p-3 tx-medium my-auto tx-white bg-info">
                                    برقراری تماس و ثبت گزارش پشتیبانی
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-lg-3 mg-b-25 ">
                                            <a href="?Id=<?php echo $_GET['Id'] ?>&calls=true"
                                               class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با دانش آموز </a>
                                        </div>
                                        <div class="col-lg-3 mg-b-25 ">
                                            <a href="?Id=<?php echo $_GET['Id'] ?>&callp=true"
                                               class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با پدر </a>
                                        </div>
                                        <div class="col-lg-3 mg-b-25 ">
                                            <a href="?Id=<?php echo $_GET['Id'] ?>&callm=true"
                                               class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با مادر </a>
                                        </div>
                                        <div class="col-lg-3 mg-b-25 ">
                                            <a href="?Id=<?php echo $_GET['Id'] ?>&callh=true"
                                               class="btn btn-block btn-outline-success"><i
                                                        class="fe fe-phone-call"></i> تماس با تلفن ثابت </a>
                                        </div>

                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-xl-4 mb-2">
                                                <label for="sens" class="form-label">نوع پشتیبانی<span
                                                            style="color: red">*</span>
                                                </label>
                                                <select class="form-control select2 " name="noposhtibani"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="اقدام لازم">
                                                    </option>
                                                    <option>تماس تلفنی</option>
                                                    <option>پشتیبانی متنی</option>


                                                </select>
                                            </div>
                                            <?php
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">
                                            <div class="col-xl-4 mb-2">
                                                <label for="sens" class="form-label">وضعیت پشتیبانی<span
                                                            style="color: red">*</span>
                                                </label>
                                                <select class="form-control select2 " name="status"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="اقدام لازم">
                                                    </option>
                                                    <option> موفقیت آمیز</option>
                                                    <option>عدم پاسخگویی</option>


                                                </select>
                                            </div>
                                            <div class="col-xl-4 mb-2">
                                                <label for="sens" class="form-label">تاریخ پشتیبانی<span
                                                            style="color: red">*</span>
                                                </label>
                                                <div class="input-group mb-3">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6"></i>
                                                        </div>
                                                    </div>
                                                    <input class="form-control fc-datepicker"
                                                           placeholder="1404/02/12" value="<?php echo $datenow ?>"
                                                           required type="text" name="lastdate">
                                                </div>
                                            </div>

                                            <div class="col-xl-12 mb-2">
                                                <p class="mg-b-10 "> توضیحات پشتیبانی <span
                                                            style="color: red">*</span></p>
                                                <textarea class="form-control" rows="5" name="detail"
                                                          required></textarea>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-4 col-xl-4 mb-2">
                                                <label for="203" class="form-label">تاریخ پشتیبانی بعدی:<span
                                                            style="color: red">*</span>

                                                    <div class="input-group mg-t-10">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text">
                                                                <i class="fe fe-calendar lh--9 op-6"></i>
                                                            </div>
                                                        </div>
                                                        <input class="form-control fc-datepicker"
                                                               placeholder="1404/02/12" required type="text"
                                                               name="afterdate">
                                                    </div>
                                            </div>
                                            <?php if ($movaghat == 1) : ?>
                                                <div class="col-4 col-xl-4 mb-2">
                                                    <label class="form-label">اولویت<span
                                                                style="color: red">*</span></label>
                                                    <select class="form-control select2" name="priority" required>
                                                        <option value="0" selected>عادی</option>
                                                        <option value="1">مهم</option>
                                                        <option value="2">خیلی مهم</option>
                                                    </select>
                                                </div>
                                            <?php endif ?>
                                            <div class="col-4 col-xl-8 mg-t-30">
                                                <label class="custom-switch">
                                                    <input type="checkbox" name="sendtime"
                                                           class="custom-switch-input">
                                                    <span class="custom-switch-indicator"></span>
                                                    <span class="custom-switch-description">ارسال تاریخ پشتیبانی
                                                            بعدی به گفتگو دانش آموز</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="text-left mg-10">
                                            <button type="submit" class="btn btn-primary " name="addposhtibani"><i
                                                        class="fe fe-log-in"></i> ثبت گزارش پشتیبانی

                                            </button>
                                        </div>
                                    </form>


                                </div>
                            </div>
                            <div class="card custom-card mt-3" id="student-talktime">
                                <div class="card-header p-3 tx-medium my-auto">
                                    محاسبه زمان مکالمه دانش آموز
                                </div>

                                <div class="card-body" style="direction: rtl;">

                                    <!-- YTD summary (current Jalali year) -->
                                    <div class="mt-3">

                                        <?php if (!$hasNumbers): ?>
                                            <div class="alert alert-warning mb-2">برای این دانش آموز هیچ شمارهٔ معتبری ثبت
                                                نشده است.
                                            </div>
                                        <?php else: ?>
                                            <!-- YTD banner -->
                                            <!--                                            <div class="alert alert-secondary mb-2" style="direction:rtl">-->
                                            <!--                                                <strong>زمان مکالمه سال جاری</strong>-->
                                            <!--                                                <small class="text-muted">-->
                                            <!--                                                    (--><? //= htmlspecialchars($ytdStartJ)
                                            ?>
                                            <!--                                                    — --><? //= htmlspecialchars($ytdEndJ)
                                            ?><!--)-->
                                            <!--                                                </small>-->
                                            <!--                                                :-->
                                            <!--                                                <span class="badge bg-primary">--><? //= htmlspecialchars($ytdHMS)
                                            ?><!--</span>-->
                                            <!--                                                <small class="text-muted">(--><? //= number_format($ytdSeconds)
                                            ?>
                                            <!--                                                    ثانیه)</small>-->
                                            <!--                                            </div>-->
                                        <?php endif; ?>

                                    </div>


                                    <!-- Custom range form (Jalali input -> Gregorian API) -->
                                    <form method="post" class="row g-3 align-items-end" id="studentTalktimeForm">
                                        <input type="hidden" name="calc_student_talktime" value="1">

                                        <div class="col-xl-3">
                                            <label class="form-label mb-1">از تاریخ</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                                </div>
                                                <input
                                                        type="text"
                                                        name="startdate"
                                                        class="form-control fc-datepicker"
                                                        placeholder="<?= $todayJalaliStr ?>"
                                                        required
                                                        value="<?= htmlspecialchars($_POST['startdate'] ?? $todayJalaliStr) ?>">
                                            </div>
                                        </div>

                                        <div class="col-xl-3">
                                            <label class="form-label mb-1">تا تاریخ</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                                </div>
                                                <input
                                                        type="text"
                                                        name="enddate"
                                                        class="form-control fc-datepicker"
                                                        placeholder="<?= $todayJalaliStr ?>"
                                                        required
                                                        value="<?= htmlspecialchars($_POST['enddate'] ?? $todayJalaliStr) ?>">
                                            </div>
                                        </div>

                                        <div class="col-xl-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary">محاسبه</button>
                                            <a
                                                    href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['Id']) ? '?Id=' . urlencode($_GET['Id']) : '')) ?>"
                                                    class="btn btn-outline-secondary ms-2">بازنشانی</a>
                                        </div>
                                    </form>

                                    <!-- Custom range result -->
                                    <div class="mt-3">
                                        <?php if (!empty($errorMsg)): ?>
                                            <div class="alert alert-danger mb-0"><?= $errorMsg ?></div>

                                        <?php elseif ($talkResult !== null): ?>
                                            <div class="alert alert-info mb-0">
                                                <strong>
                                                    مجموع زمان مکالمه
                                                    (<?= htmlspecialchars($talkResult['start']) ?>
                                                    — <?= htmlspecialchars($talkResult['end']) ?>):
                                                </strong>
                                                &nbsp;
                                                <span class="badge bg-success"><?= htmlspecialchars($talkResult['hms']) ?></span>
                                                &nbsp;
                                                <small class="text-muted">
                                                    (<?= number_format((int)$talkResult['seconds']) ?> ثانیه)
                                                </small>
                                            </div>

                                        <?php else: ?>
                                            <div class="text-muted">
                                                برای نمایش زمان مکالمه در بازه دلخواه، تاریخ‌ها را انتخاب و «محاسبه» را
                                                فشار دهید.
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>

                            <div class="card custom-card">
                                <div class="card-header p-3 tx-medium my-auto tx-white bg-black-4">
                                    سوابق گزارشات ثبت شده
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive table-hover">
                                        <table class="table   " id="example1">
                                            <thead>
                                            <tr>

                                                <th class="bg-light text-center wd-5"><small>#</small></th>
                                                <th class="bg-light text-center">تاریخ پشتیبانی</th>
                                                <th class="bg-light text-center"><small
                                                            class="ml-1">پشتیبان</small></th>
                                                <th class="bg-light text-center">نوع</th>
                                                <th class="bg-light text-center">وضعیت</th>
                                                <th class="bg-light text-center">شرح</th>
                                                <th class="bg-light text-center">حذف</th>


                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
//                                            $sql = 'SELECT * FROM suport_report WHERE username=? ORDER BY id DESC ';
//                                            $qrt = $conn->prepare($sql);
//                                            $qrt->execute([$user->codemeli]);
//                                            $result = $qrt->fetchAll(PDO::FETCH_OBJ);
                                            if ($stmt_report->rowCount() > 0) {
                                                $is = 1;
                                                foreach ($supportReports as $report) {


                                                    ?>
                                                    <tr>
                                                        <td class="wd-15"><?php echo $is ?></td>
                                                        <td class="wd-15">
                                                            <?php
                                                            $after = $report->datesupport;
                                                            $after = explode('-', $after);
                                                            $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                                            $after = implode('/', $after);
                                                            echo $after;
                                                            ?>

                                                        </td>
                                                        <td class="wd-15"><?php echo $report->poshtiban ?></td>
                                                        <td class="wd-15"><?php echo $report->type ?></td>
                                                        <td class="wd-15"><?php echo $report->status ?></td>
                                                        <td><?php echo htmlentities($report->detail) ?></td>
                                                        <td>
                                                            <a href="?Id=<?php echo $_GET['Id'] ?>&delsupport=<?php echo $report->id ?>"
                                                               onclick="return confirm('آیا با حذف این سابقه پشتیبانی موافقید؟')"
                                                               class="btn btn-sm btn-danger btn-icon">
                                                                <i class="fe fe-x"></i></a>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $is++;
                                                }
                                            }
                                            ?>


                                            </tbody>
                                        </table>
                                    </div>


                                </div>
                            </div>

                            <div class="card custom-card">
                                <div class="card-header p-3 tx-medium my-auto tx-white bg-teal">
                                    ثبت و ویرایش شرح حال دانش آموز موقت
                                </div>
                                <div class="card-body">
                                    <form method="post" data-parsley-validate="">
                                        <div class="col-xl-12 mb-2">
                                            <p class="mg-b-10 "> شرح حال دانش آموز از زبان پشتیبان <span
                                                        style="color: red">*</span></p>
                                            <textarea class="form-control" rows="5" name="about_suporter"
                                                      required><?php if ($aboutSuporterText != NULL) {
                                                    echo $aboutSuporterText;
                                                } ?></textarea>
                                        </div>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">
                                        <div class="text-left mg-10">
                                            <button type="submit" class="btn btn-primary "
                                                    name="addaboutsuporter">ثبت شرح حــال <i
                                                        class="fe fe-check-circle"></i>

                                            </button>
                                        </div>

                                    </form>


                                </div>
                            </div>


                            <div class="card custom-card mt-4">
                                <div class="card-header border-bottom-0 pb-0">
                                    <div>
                                        <div class="d-flex">
                                            <label class="main-content-label my-auto pt-2">رویدادهای فعال</label>
                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted">پاسخ‌های ثبت شده برای این دانش‌آموز</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (isset($active_event_error)): ?>
                                        <div class="alert alert-danger"><?= htmlspecialchars($active_event_error) ?></div>
                                    <?php elseif (isset($active_event_success)): ?>
                                        <div class="alert alert-success"><?= htmlspecialchars($active_event_success) ?></div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table id="example1" class="table table-bordered table-striped">
                                            <thead>
                                            <tr>
                                                <th>عنوان</th>
                                                <th>توضیحات</th>
                                                <th>پاسخ (انتخاب گزینه)</th>
                                                <th>وضعیت</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($activeEvents)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">هیچ رویداد فعال پاسخ‌داده‌نشده‌ای برای این دانش‌آموز یافت نشد.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($activeEvents as $event): ?>
                                                    <?php
                                                    $start_j = gregorian_to_jalali(substr($event['start_date'],0,4), substr($event['start_date'],5,2), substr($event['start_date'],8,2));
                                                    $jalali_start = sprintf("%04d/%02d/%02d %s", $start_j[0], $start_j[1], $start_j[2], date('H:i', strtotime($event['start_date'])));
                                                    $jalali_end = '';
                                                    if ($event['end_date']) {
                                                        $end_j = gregorian_to_jalali(substr($event['end_date'],0,4), substr($event['end_date'],5,2), substr($event['end_date'],8,2));
                                                        $jalali_end = sprintf("%04d/%02d/%02d %s", $end_j[0], $end_j[1], $end_j[2], date('H:i', strtotime($event['end_date'])));
                                                    }
                                                    $options = $optionsByEvent[$event['id']] ?? [];
                                                    $hasOptions = !empty($options);
                                                    ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($event['title']) ?></strong></td>
                                                        <td><?= nl2br(htmlspecialchars($event['description'])) ?></td>
                                                        <td>
                                                            <form method="post" class="mb-0" id="form_event_<?= $event['id'] ?>" onsubmit="return validateActiveEventForm(this)">
                                                                <input type="hidden" name="active_event_token" value="<?= $_SESSION['active_event_token'] ?>">
                                                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                                <input type="hidden" name="active_event_action" value="insert">

                                                                <?php if ($hasOptions): ?>
                                                                    <!-- Dropdown select -->
                                                                    <div class="mb-2">
                                                                        <select class="form-select form-select-sm option-select" name="option_id"
                                                                                data-event-id="<?= $event['id'] ?>"
                                                                                required>
                                                                            <option value="">— انتخاب گزینه —</option>
                                                                            <?php foreach ($options as $opt): ?>
                                                                                <option value="<?= $opt['id'] ?>"
                                                                                        data-needs-reason="<?= $opt['needs_reason'] ?>">
                                                                                    <?= htmlspecialchars($opt['events_option']) ?>
                                                                                    <?php if ($opt['needs_reason']): ?>
                                                                                    <?php endif; ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>

                                                                    <!-- Reason input (shown if selected option needs reason) -->
                                                                    <div class="reason-container mb-2" style="display:none;">
                                                                        <input type="text" class="form-control form-control-sm reason-input"
                                                                               name="reason"
                                                                               placeholder="لطفاً علت را وارد کنید..."
                                                                               data-required-on="1">
                                                                        <small class="text-muted">این گزینه نیاز به توضیح دارد</small>
                                                                    </div>

                                                                    <button type="submit" class="btn btn-sm btn-primary">ثبت پاسخ</button>

                                                                <?php else: ?>
                                                                    <!-- Fallback to textarea if no options defined -->
                                                                    <textarea class="form-control mb-2" name="answer" rows="2" required></textarea>
                                                                    <button type="submit" class="btn btn-sm btn-primary">ثبت پاسخ</button>
                                                                <?php endif; ?>
                                                            </form>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-danger">پاسخ داده نشده</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="col-lg-4">
                            <div class="card custom-card">
                                <div class="card-header p-3 tx-medium my-auto tx-white bg-warning">
                                    تنظیمات پشتیبانی دانش آموز
                                </div>
                                <div class="card-body">
                                    <?php
                                    // $sql = "SELECT * FROM suport_report WHERE username = ?";
                                    // $stmt = $conn->prepare($sql);
                                    // $stmt->execute([$_GET['Id']]);
                                    // if ($stmt->rowCount() > 0) {
                                    ?>
                                    <!--                                    --><?php //if ($typeuser == 0) { ?>
                                    <!--                                        <div class="col-lg-12 mg-b-25 ">-->
                                    <!--                                            <a href="?changevip=--><?php //echo $_GET['Id'] ?><!--&Id=--><?php //echo $_GET['Id'] ?><!--"-->
                                    <!--                                               class="btn btn-block btn-outline-warning"><i-->
                                    <!--                                                        class="fe fe-check-square"></i>-->
                                    <!--                                                تغییر به پشتیبانی ویژه </a>-->
                                    <!--                                        </div>-->
                                    <!--                                    --><?php //} else { ?>
                                    <!--                                        <div class="col-lg-12 mg-b-25 ">-->
                                    <!--                                            <a href="?changepublic=--><?php //echo $_GET['Id'] ?><!--&Id=--><?php //echo $_GET['Id'] ?><!--"-->
                                    <!--                                               class="btn btn-block btn-outline-warning"><i-->
                                    <!--                                                        class="fe fe-check-square"></i>-->
                                    <!--                                                تغییر به پشتیبانی عادی </a>-->
                                    <!--                                        </div>-->
                                    <!--                                    --><?php //}

                                    // } ?>
                                    <div class="col-lg-12 mg-b-25">
                                        <form method="post" id="changeSupportTypeForm">
                                            <input type="hidden" name="change_support_type" value="1">
                                            <div class="form-group">
                                                <label for="supportTypeSelect">نوع پشتیبانی:</label>
                                                <select name="new_typeuser" id="supportTypeSelect" class="form-control select2">
                                                    <option value="0" <?= $typeuser == 0 ? 'selected' : '' ?>>عادی</option>
                                                    <option value="1" <?= $typeuser == 1 ? 'selected' : '' ?>>ویژه</option>
                                                    <option value="2" <?= $typeuser == 2 ? 'selected' : '' ?>>ویژه فعال</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary btn-block">تغییر نوع پشتیبانی</button>
                                        </form>
                                    </div>
                                    <hr>
                                    <?php if (Permision('permision_manage_chat')) { ?>

                                    <div class="col-lg-12 mg-b-25 ">
                                        <a href="?Id=<?php echo $_GET['Id'] ?>&delallmessage=true"
                                           class="btn btn-block btn-outline-danger"
                                           onclick="return confirm('آیا با حذف تمامی سابقه گفتگو موافقید؟')"><i
                                                    class="fe fe-trash-2"></i> پاک کردن سابقه گفتگو </a>
                                    </div>
                                    <div class="col-lg-12 mg-b-25 ">
                                        <a href="?Id=<?php echo $_GET['Id'] ?>&delhistorysupport=true"
                                           class="btn btn-block btn-outline-danger"
                                           onclick="return confirm('آیا با حذف تمامی سابقه پشتیبانی این دانش آموز موافقید؟')"><i
                                                    class="fe fe-trash"></i> پاک کردن سابقه پشتیبانی </a>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                    </div>







                </div>
            </div>
        </div>
        <!-- =========================
            Suspend Request Modal
        ========================= -->
        <div class="modal fade" id="suspendRequestModal" tabindex="-1" aria-labelledby="suspendRequestModalLabel"
             aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" class="modal-content" id="suspendRequestForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="suspendRequestModalLabel">ثبت درخواست تعلیق</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="submit_suspend_request" value="1">
                        <input type="hidden" name="student_codemeli"
                               value="<?php echo htmlspecialchars($studentCodemeli); ?>">

                        <div class="mb-3">
                            <label class="form-label">علت تعلیق <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="suspend_reason" id="suspend_reason" rows="4" required
                                      placeholder="علت را کامل بنویسید..."></textarea>
                            <div class="invalid-feedback">وارد کردن علت تعلیق الزامی است.</div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-danger">ارسال درخواست</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {

                const registered = document.getElementById('registered');
                const examType = document.getElementById('examType');
                const takenExam = document.getElementById('takenExam');

                const reasonDiv = document.getElementById('reasonDiv');
                const examTypeDiv = document.getElementById('examTypeDiv');
                const olympiadFieldDiv = document.getElementById('olympiadFieldDiv');
                const takenExamDiv = document.getElementById('takenExamDiv');
                const passedDiv = document.getElementById('passedDiv');

                const notTakenReasonDiv = document.getElementById('notTakenReasonDiv');
                const notTakenReasonInput = document.getElementById('notTakenReason');

                // ================== Select2 ==================
                $('#olympiadField').select2({
                    placeholder: 'رشته‌های مورد نظر را انتخاب کنید',
                    width: '100%',
                    dir: 'rtl',
                    closeOnSelect: false,
                    dropdownParent: $('#olympiadFieldDiv')
                });

                // ================== Helpers ==================
                function hideAllExtraFields() {
                    reasonDiv.style.display = 'none';
                    examTypeDiv.style.display = 'none';
                    olympiadFieldDiv.style.display = 'none';
                    takenExamDiv.style.display = 'none';
                    passedDiv.style.display = 'none';
                    notTakenReasonDiv.style.display = 'none';

                    notTakenReasonInput.value = '';
                    takenExam.value = '';
                    $('#olympiadField').val(null).trigger('change');
                }

                function resetNotTakenReason() {
                    notTakenReasonDiv.style.display = 'none';
                    notTakenReasonInput.value = '';
                }

                // ================== Initial State ==================
                hideAllExtraFields();

                // ================== Registered Change ==================
                registered.addEventListener('change', function () {

                    hideAllExtraFields();

                    if (this.value === 'ثبت نام نکرده') {
                        reasonDiv.style.display = 'block';
                        examTypeDiv.style.display = 'block';
                    } else if (this.value === 'ثبت نام کرده') {
                        examTypeDiv.style.display = 'block';
                        takenExamDiv.style.display = 'block';
                    } else if (this.value === 'بررسی گردد') {
                        reasonDiv.style.display = 'block';
                    }
                });

                // ================== Exam Type Change ==================
                examType.addEventListener('change', function () {

                    olympiadFieldDiv.style.display = 'none';
                    $('#olympiadField').val(null).trigger('change');

                    if (this.value === 'المپیاد') {
                        olympiadFieldDiv.style.display = 'block';
                    }
                });

                // ================== Taken Exam Change ==================
                takenExam.addEventListener('change', function () {

                    passedDiv.style.display = 'none';
                    resetNotTakenReason();

                    if (this.value === 'آزمون داده') {
                        passedDiv.style.display = 'block';
                    } else if (this.value === 'آزمون نداده') {
                        notTakenReasonDiv.style.display = 'block';
                    } else if (this.value === 'آزمون داده ولی جواب آزمون نیامده') {
                        // End here: nothing else shows
                        // (passedDiv already hidden, notTakenReasonDiv already hidden)
                    }
                });

            });
        </script>


        <script>
            document.querySelector('form').addEventListener('submit', function (e) {
                const fileInput = document.querySelector('input[type="file"]');
                const textInput = document.querySelector('input[name="txt"]');
                const files = fileInput.files;
                const maxSize = 3 * 1024 * 1024;

                for (let i = 0; i < files.length; i++) {
                    if (files[i].size > maxSize) {
                        alert('حجم فایل باید کمتر از 3 مگابایت باشد.');

                        fileInput.value = '';
                        textInput.value = '';

                        e.preventDefault();
                        return false;
                    }
                }
            });
        </script>
        <?php if (!empty($shouldScrollToCard) || !empty($talkResult) || !empty($errorMsg)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById('student-talktime');
                    if (!el) return;
                    // small delay to ensure layout finished (helps in some browsers)
                    setTimeout(function () {
                        el.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        // optional: give focus to the first date input
                        var input = el.querySelector('input[name="startdate"]');
                        if (input) input.focus({
                            preventScroll: true
                        });
                    }, 120);
                });
            </script>
        <?php endif; ?>
        <!--suspension mode js -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('suspendRequestForm');
                if (!form) return;

                form.addEventListener('submit', function (e) {
                    const ta = document.getElementById('suspend_reason');
                    if (!ta) return;

                    if (ta.value.trim() === '') {
                        e.preventDefault();
                        ta.classList.add('is-invalid');
                        ta.focus();
                        return false;
                    }
                    ta.classList.remove('is-invalid');
                });
            });
        </script>

        <script>
            function updateReasonFieldForSelect(select) {
                const form = select.closest('form');
                if (!form) return;
                const container = form.querySelector('.reason-container');
                const reasonInput = form.querySelector('.reason-input');
                const selectedOption = select.options[select.selectedIndex];

                if (!container || !reasonInput) return;

                if (selectedOption && selectedOption.dataset.needsReason === '1') {
                    container.style.display = 'block';
                    reasonInput.required = true;
                    reasonInput.placeholder = 'لطفاً علت را وارد کنید (الزامی)';
                } else {
                    container.style.display = 'none';
                    reasonInput.required = false;
                    reasonInput.placeholder = 'لطفاً علت را وارد کنید...';
                }
            }

            // Attach change event to all option selects
            document.querySelectorAll('.option-select').forEach(select => {
                select.addEventListener('change', function() {
                    updateReasonFieldForSelect(this);
                });

                // Initial state: show reason if pre-selected option needs it
                updateReasonFieldForSelect(select);
            });

            // Validate before submit
            window.validateActiveEventForm = function(form) {
                const select = form.querySelector('.option-select');
                const reasonInput = form.querySelector('.reason-input');

                if (!select) {
                    // Fallback to textarea validation (default HTML validation)
                    return true;
                }

                if (!select.value) {
                    alert('لطفاً یک گزینه را انتخاب کنید.');
                    select.focus();
                    return false;
                }

                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.dataset.needsReason === '1') {
                    if (!reasonInput || reasonInput.value.trim() === '') {
                        alert('این گزینه نیاز به توضیح دارد. لطفاً علت را وارد کنید.');
                        reasonInput.focus();
                        return false;
                    }
                }

                return true;
            };
        </script>
        <!-- Main Footer-->
        <?php include '../../footers.php'; ?>
        <!--End Footer-->


</body>

</html>
