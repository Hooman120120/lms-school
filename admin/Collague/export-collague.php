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
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if(isset($_GET['state'])){
    $stm = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN  dblogin ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=? AND `dbpersonal`.`state`=?  ");
    $stm->execute([2,$_GET['state']]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش همکاران استان " . $_GET['state'] ." _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن همکار', 'تلفن رابط','تاریخ تولد','استان','شهر','نام محل خدمت','سمت','مقطع تحصیلی','رشته تحصیلی','جنسیت','تاریخ عضویت','نحوه آشنایی'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


            $dateno = $row->birthdate;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }
            $datereg = $row->datecreated;
            if($datereg) {
                $datereg = explode('-', $datereg);
                $datereg = gregorian_to_jalali($datereg[0], $datereg[1], $datereg[2]);
                $datereg = implode('/', $datereg);
            }else{
                $datereg='NULL';
            }

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$dateno,$row->state,$row->city,$row->workname,$row->semat,$row->maghta,$row->reshtehamkar,$row->gender,$datereg,$row->presented];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['all'])){
    $stm = $conn->prepare("SELECT * FROM `dbpersonal` INNER JOIN `dblogin` ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=?;   ");
    $stm->execute([2]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش  همه همکاران   _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن همکار', 'تلفن رابط','تاریخ تولد','استان','شهر','نام محل خدمت','سمت','مقطع تحصیلی','رشته تحصیلی','جنسیت','تاریخ عضویت','نحوه آشنایی'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


            $dateno = $row->birthdate;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }
            $datereg = $row->datecreated;
            if($datereg) {
                $datereg = explode('-', $datereg);
                $datereg = gregorian_to_jalali($datereg[0], $datereg[1], $datereg[2]);
                $datereg = implode('/', $datereg);
            }else{
                $datereg='NULL';
            }

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$dateno,$row->state,$row->city,$row->workname,$row->semat,$row->maghta,$row->reshtehamkar,$row->gender,$datereg,$row->presented];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['uncollague'])){
    $co_student = 'SELECT * FROM dblogin LEFT JOIN  dbpersonal ON dblogin.username=dbpersonal.codemeli WHERE dblogin.role=?;  ';
    $query_st = $conn->prepare($co_student);
    $query_st->execute([2]);
    $result_student = $query_st->fetchAll(PDO::FETCH_OBJ);
    $id = 0;
    if ($query_st->rowCount() > 0) {
        foreach ($result_student as $rey) {
            $ghj = 'SELECT COUNT(*) as coi from dbpersonal WHERE useradder=?';
            $qghj = $conn->prepare($ghj);
            $qghj->execute([$rey->codemeli]);
            $rty = $qghj->fetch(PDO::FETCH_OBJ);
            if ($rty->coi == 0) {
                $insert='INSERT INTO reportcollague(codemeli,fname,lname,fathername,tel1,tel2,birthdate,`state`,city,workname,semat,maghta,reshtehamkar,gender,datecreated,presented) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $query=$conn->prepare($insert);
                $query->execute([$rey->codemeli,$rey->fname,$rey->lname,$rey->fathername,$rey->tel1,$rey->tel2,$rey->birthdate,$rey->state,$rey->city,$rey->workname,$rey->semat,$rey->maghta,$rey->reshtehamkar,$rey->gender,$rey->datecreated,$rey->presented]);
            }
        }
    }

    $stm = $conn->prepare("SELECT * FROM `reportcollague` WHERE 1 ;   ");
    $stm->execute();

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش  همکاران بدون زیر مجموعه_  "  . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن همکار', 'تلفن رابط','تاریخ تولد','استان','شهر','نام محل خدمت','سمت','مقطع تحصیلی','رشته تحصیلی','جنسیت','تاریخ عضویت','نحوه آشنایی'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


            $dateno = $row->birthdate;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }
            $datereg = $row->datecreated;
            if($datereg) {
                $datereg = explode('-', $datereg);
                $datereg = gregorian_to_jalali($datereg[0], $datereg[1], $datereg[2]);
                $datereg = implode('/', $datereg);
            }else{
                $datereg='NULL';
            }

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$dateno,$row->state,$row->city,$row->workname,$row->semat,$row->maghta,$row->reshtehamkar,$row->gender,$datereg,$row->presented];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }
$del='DELETE FROM reportcollague WHERE 1';
    $qdel=$conn->prepare($del);
    $qdel->execute();
}
if(isset($_GET['subset'])){
    $stm = $conn->prepare("SELECT * FROM `dbpersonal` INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username WHERE dbpersonal.useradder=?;   ");
    $stm->execute([$_GET['subset']]);


    if ($stm) {
        $usermin=retPerson($_GET['subset']);
        $delimiter = ",";
        $filename = "گزارش  زیرمجموعه همکار ".$usermin->fname." ".$usermin->lname." _" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن دانش آموز', 'تلفن پدر','تلفن مادر','تاریخ تولد','استان','شهر','نام مدرسه','پایه','رشته تحصیلی','جنسیت','تاریخ عضویت','تاییدیه','تعداد پایه فعال','کدملی همکار','نام همکار','فامیلی همکار','تلفن همکار'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


            $dateno = $row->birthdate;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }
            $datereg = $row->datecreated;
            if($datereg) {
                $datereg = explode('-', $datereg);
                $datereg = gregorian_to_jalali($datereg[0], $datereg[1], $datereg[2]);
                $datereg = implode('/', $datereg);
            }else{
                $datereg='NULL';
            }
            if($row->keyactive != NULL and $row->true_key == 1){
                $status='تایید';
            }else{
                $status='عدم تایید';
            }
            $cop = 'SELECT COUNT(*) as coco FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE dbcourse.username=? AND dbcourse.activityuser=? AND activecourse=?';
            $zat = $conn->prepare($cop);
            $zat->execute([$row->codemeli, 1, 1]);
            $cin = $zat->fetch(PDO::FETCH_OBJ);

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$row->tel3,$dateno,$row->state,$row->city,$row->nameschool,$row->paye,$row->reshtestudent,$row->gender,$datereg,$status,$cin->coco,$_GET['subset'],$usermin->fname,$usermin->lname,$usermin->tel1];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }
}