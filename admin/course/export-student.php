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
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!checkvalidcourse(intval($_GET['code']))) {
    header('location: ' . url('admin/index.php'));
    die();
}

$stm = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN  dblogin ON `dbpersonal`.`codemeli`=`dblogin`.`username` INNER JOIN dbcourse ON `dbpersonal`.`codemeli`=`dbcourse`.`username`  WHERE `dbcourse`.`codecourse`=? AND `dbcourse`.`activityuser`=?  ");
$stm->execute([$_GET['code'], 1]);
if ($stm) {
    $delimiter = ",";
    $filename = "لیست نفرات پایه_" . date('Y-m-d h:i:s') . ".csv";

    header('Content-Encoding: UTF-8');
    header('Content-type: application/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $f = fopen('php://output', 'w');
    fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!
    $fields = ['کدملی','رمز عبور','نام','نام خانوادگی','نام پدر','تلفن دانش آموز','تلفن پدر','تلفن مادر','نام پایه','شماره فاکتور','استان','شهر','پایه','رشته','مدرسه','همکار آموزشی','اولین ورود','ایجاد کننده','پشتیبان'];
    fputcsv($f, $fields, $delimiter);
    while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
        $dore=returnCourse($row->codecourse);
        $factor=queryFactor($row->numfactor);
        if($row->useradder!=NULL){
            $adder=retPerson($row->useradder);
            $collague=$adder->fname.' '.$adder->lname;
        }else{
            $collague='ندارد';
        }
        if($factor->factoradder==NULL){
            $creator='خودش';
        }else{
            $creator=$factor->factoradder;
        }
        if($row->suporter==NULL){
            $suporter='ندارد';
        }else{
            $suporter=$row->suporter;
        }
        $linedata = [$row->codemeli,$row->password,$row->fname,$row->lname,$row->fathername,$row->tel1,$row->tel2,$row->tel3,$dore->coursename,$row->numfactor,$row->state,$row->city,$row->paye,$row->reshtestudent,$row->nameschool,$collague,$row->firstlogin,$creator,$suporter];
        fputcsv($f, $linedata, $delimiter);

        fpassthru($f);
    }
}




