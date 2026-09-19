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

if (!isset($_GET['id'])){
    header('location: ' . url('Course/allcourse.php'));
    die();
}
if ($_GET['id']==''){
    header('location: ' . url('Course/allcourse.php'));
    die();
}
$result=retIdMovie($_GET['id']);
$sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND `codtbl_course`.`codecourse`=?';
$query = $conn->prepare($sql);
$query->execute([1, 1, $_SESSION['auth']['user'], $result['codecourse']]);
if (!$query->rowCount() > 0) {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
//
$view=$result['view'];
$view++;
$update='UPDATE dbmovie SET view=? WHERE id=?';
$query1=$conn->prepare($update);
$query1->execute([$view,$_GET['id']]);
//
date_default_timezone_set("Iran");
$datenow = date("Y-m-d");
$timenow = date('H:i:s');
$ip=GetIp();
$os=GetOs();
$log='INSERT INTO logviewfilm (username,codecourse,idfilm,datecreated,timecreated,ip,os) VALUES (?,?,?,?,?,?,?)';
$quelog=$conn->prepare($log);
$quelog->execute([$_SESSION['auth']['user'],$result['codecourse'],$_GET['id'],$datenow,$timenow,$ip,$os]);
//

$filename = $result['linkfilm'];
$mimme = $result['typefilm'];
$name=$result['filmname'];
header('Content-Type:" ' . $mimme . ' " ');
header('Content-Disposition: inline; filename="' . $name . '"');
ini_set("zlib.output_compression", "Off");
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache');
header('X-Pad: avoid browser bug');
header('Accept-Ranges: bytes');
@readfile($filename);



