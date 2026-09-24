<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}

if (!isset($_GET['id'])) {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
if ($_GET['id'] == '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
if (!isset($_GET['demo'])) {
    $result = retIdMovie($_GET['id']);
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND `codtbl_course`.`codecourse`=?';
    $query = $conn->prepare($sql);
    $query->execute([1, 1, $_SESSION['auth']['user'], $result['codecourse']]);
    if (!$query->rowCount() > 0) {
        header('location: ' . url('Course/allcourse.php'));
        die();
    }
} else {
    $sql = 'SELECT * FROM dbmovie_demo WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql2 = 'SELECT * FROM dbcourse_demo INNER JOIN course_demo ON dbcourse_demo.codecourse = course_demo.codecourse WHERE dbcourse_demo.username = ? AND course_demo.codecourse = ?';
    $query2 = $conn->prepare($sql2);
    $query2->execute([$_SESSION['auth']['user'], $result['codecourse']]);
    if (!$query2->rowCount() > 0) {
        header('location: ' . url('Course/allcourse.php'));
        die();
    }
}


//
$view = $result['view'];
$view++;
if (!isset($_GET['demo'])) {
    $update = 'UPDATE dbmovie SET view=? WHERE id=?';
    $query1 = $conn->prepare($update);
    $query1->execute([$view, $_GET['id']]);
} else {
    $update = 'UPDATE dbmovie_demo SET view = ? WHERE id = ?';
    $query1 = $conn->prepare($update);
    $query1->execute([$view, $_GET['id']]);
}

//
if (!isset($_GET['demo'])) {
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    $log = 'INSERT INTO logviewfilm (username,codecourse,idfilm,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
    $quelog = $conn->prepare($log);
    $quelog->execute([$_SESSION['auth']['user'], $result['codecourse'], $_GET['id'], $datenow, $timenow, $ip, $os]);
} else { 
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    $log = 'INSERT INTO logviewfilm_demo (username,codecourse,idfilm,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
    $quelog = $conn->prepare($log);
    $quelog->execute([$_SESSION['auth']['user'], $result['codecourse'], $_GET['id'], $datenow, $timenow, $ip, $os]);
}
header('location:' . $result['linkfilm']);
exit;
