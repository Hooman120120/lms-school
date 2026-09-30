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

if (isset($_GET['type'])) {
    $type = intval($_GET['type']);
    if($type == 1 or $type == 3) {
        $sql = 'SELECT * FROM codtbl_course WHERE activecourse = 1 ORDER BY idcourse DESC';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } elseif ($type == 2 or $type == 4) {
        $sql = 'SELECT * FROM codtbl_course WHERE activecourse = 0 ORDER BY idcourse DESC';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        $sql = 'SELECT * FROM codtbl_course ';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($courses);
}