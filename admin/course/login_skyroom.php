<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
require_once '../../inc/chahard_rooms.php';
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
//* End Check Session JUST ONE Session
// Teachers join the online class of a lesson assigned to them (class-detail.php);
// every other use of this page stays with staff who may view courses.
$isTeacher = (int)($_SESSION['admin']['adminrole'] ?? -1) === 3;
if ($isTeacher && !isset($_GET['logindars'])) {
    http_response_code(403);
    exit(m('perm.teacher_no_online_class'));
}
if (!$isTeacher && !Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if(isset($_GET['loginskyroom'])) {
    $courseCode = (int)$_GET['loginskyroom'];
    if ($courseCode <= 0 || (StaffCourseScopeRequired() && !StaffHasCourseAccess($courseCode))) {
        http_response_code(403);
        exit('شما به اتاق آنلاین این پایه دسترسی ندارید.');
    }
    $course = returnCourse($courseCode);
    $student = retAdmin($_SESSION['admin']['user']);
    $sql = 'select per_skyroom FROM dbadmin WHERE username=?';
    $query = $conn->prepare($sql);
    $per = $query->execute([$_SESSION['admin']['user']]);
    $per = $query->fetch();


    require_once '../../api/Skyroom.php';
    $apiUrl = __SKY;
    $api = new Skyroom($apiUrl);
    $action = 'createLoginUrl';
    $params = array(
        'room_id' => intval($course->link_class),
        'user_id' => $_SESSION['admin']['user'],
        'language' => 'fa',
        'nickname' => $student->fname . ' ' . $student->lname,
        'access' => intval($per['per_skyroom']),
        'concurrent' => 1,
        'ttl' => 30,
    );
    $result10 = $api->call($action, $params);

    if (Skyroom\HttpError::IsError($result10)) {
        printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
    } else {
        $sky = output($result10);
        $skytoken = json_decode($sky);
        $skytoken = $skytoken->{'result'};
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
        $ver = $conn->prepare($token);
        $ver->execute([$_SESSION['admin']['user'], $_GET['loginskyroom'], $datenow, $timenow, $skytoken, $ip, $os]);
        header('location:' . $skytoken);

    }
}
if(isset($_GET['logindars'])) {
    $darsId = (int)$_GET['logindars'];
    if ($darsId <= 0) {
        http_response_code(400);
        exit('درس انتخاب‌شده معتبر نیست.');
    }
    $sql = 'SELECT * FROM dbdars WHERE id = ?';
    $query = $conn->prepare($sql);
    $query->execute([$darsId]);
    $dars = $query->fetch(PDO::FETCH_OBJ);

    if (!$dars or trim((string)$dars->link_class) === '') {
        die("برای این درس شناسه اتاق چهاردیواری ثبت نشده است.");
    }
    if ($isTeacher && !TeacherHasDarsAccess((int)($_GET['class_id'] ?? 0), $darsId)) {
        http_response_code(403);
        exit('شما به اتاق آنلاین این درس دسترسی ندارید.');
    }
    if (StaffCourseScopeRequired() && !StaffHasCourseAccess((int)$dars->codecourse)) {
        http_response_code(403);
        exit('شما به اتاق آنلاین این درس دسترسی ندارید.');
    }

    $student = retAdmin($_SESSION['admin']['user']);

    $apiUrl = chahard_join_url();
    $params = array(
        'roomId'   => trim($dars->link_class),
        'username' => $_SESSION['admin']['user'],
        'password' => $_SESSION['admin']['user'],
        'name'     => $student->fname,
        'lastName' => $student->lname,
        'role'     => 'admin',
        // role in the room (icon and list order): the school manager (admin id 1)
        // is «مدیر اصلی», a teacher «استاد»
        'panelRole' => (int) ($student->idadmin ?? 0) === 1 ? 'manager' : ($isTeacher ? 'teacher' : '')
    );

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
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode == 200 && isset($result['success']) && $result['success'] === true) {

        $joinLink = $result['data']['joinUrl'];

        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
        $ver = $conn->prepare($token);
        $ver->execute([$_SESSION['admin']['user'], (int)$dars->codecourse, $datenow, $timenow, $joinLink, $ip, $os]);

        // 🔴 خیلی مهم: قبلش هیچ echo / print نداشته باشی
        header("Location: " . $joinLink);
        exit;
    } else {
        die("خطا در اتصال به کلاس آنلاین");
    }
}
if(isset($_GET['loginPublick'])) {
    $student = retAdmin($_SESSION['admin']['user']);
    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
    $query = $conn->prepare($sql);
    $query->execute([1]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    if ($query->rowCount() > 0) {
        $sql = 'select per_skyroom FROM dbadmin WHERE username=?';
        $query = $conn->prepare($sql);
        $per = $query->execute([$_SESSION['admin']['user']]);
        $per = $query->fetch();


        require_once '../../api/Skyroom.php';
        $apiUrl = __SKY;
        $api = new Skyroom($apiUrl);
        $action = 'createLoginUrl';
        $params = array(
            'room_id' => 5517921/*intval($result->link_general)*/,
            'user_id' => $_SESSION['admin']['user'],
            'language' => 'fa',
            'nickname' => $student->fname . ' ' . $student->lname,
            'access' => intval($per['per_skyroom']),
            'concurrent' => 1,
            'ttl' => 30,
        );

        $result10 = $api->call($action, $params);

        if (Skyroom\HttpError::IsError($result10)) {
            printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
        } else {
            $sky = output($result10);
            $skytoken = json_decode($sky);
            $skytoken = $skytoken->{'result'};
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $ip = GetIp();
            $os = GetOs();
            $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
            $ver = $conn->prepare($token);
            $ver->execute([$_SESSION['admin']['user'], $result->link_general, $datenow, $timenow, $skytoken, $ip, $os]);
            header('location:' . $skytoken);

        }

    }
}
