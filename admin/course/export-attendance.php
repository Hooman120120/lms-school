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
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
//if (!checkvalidcourse(intval($_GET['code']))) {
//    header('location: ' . url('admin/index.php'));
//    die();
//}
$stm = $conn->prepare("SELECT * FROM dbattendance INNER JOIN  tbl_attendanced ON `dbattendance`.`coding`=`tbl_attendanced`.`id` WHERE dbattendance.coding=? ");
$stm->execute([$_GET['code']]);
if ($stm) {
    $delimiter = ",";
    $filename = "گزارش حضورغیاب_" . date('Y-m-d h:i:s') . ".csv";

    header('Content-Encoding: UTF-8');
    header('Content-type: application/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $f = fopen('php://output', 'w');
    fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!
    $fields = ['کدملی', 'نام', 'نام خانوادگی', 'نام پدر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'نام دوره', 'شماره فاکتور','جمع پرداختی فاکتور', 'استان', 'شهر', 'پایه', 'رشته', 'مدرسه', 'همکار آموزشی', 'اولین ورود', 'ایجاد کننده', 'پشتیبان', 'نام کلاس و جلسه', 'تاریخ برگزاری', 'زمان حضور', 'وضعیت'];
    fputcsv($f, $fields, $delimiter);
    while ($row = $stm->fetch(PDO::FETCH_OBJ)) {
        $person = retPerson($row->username);
        $dore = returnCourse($row->codecourse);
        $factor = retnumFactor($row->username, $row->codecourse);
        $ufactor=queryFactor($factor->numfactor);
        $login=retdblogin($row->username);
        if ($person->useradder != NULL) {
            $adder = retPerson($person->useradder);
            $collague = $adder->fname . ' ' . $adder->lname;
        } else {
            $collague = 'ندارد';
        }
        if ($ufactor->factoradder == NULL) {
            $creator = 'خودش';
        } else {
            $creator = $ufactor->factoradder;
        }
        if ($factor->suporter == NULL) {
            $suporter = 'ندارد';
        } else {
            $suporter = $factor->suporter;
        }
        $sumfactor=$ufactor->wallet+$ufactor->factorfee;
        $dateno = $row->datestart;
        $dateno = explode('-', $dateno);
        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
        $dateno = implode('/', $dateno);
        $linedata = [$row->username, $person->fname, $person->lname, $person->fathername, $person->tel1, $person->tel2, $person->tel3, $dore->coursename, $factor->numfactor,$sumfactor, $person->state, $person->city, $person->paye, $person->reshtestudent, $person->nameschool, $collague, $login->firstlogin, $creator, $suporter,$row->classname,$dateno,$row->timehozor,$row->status];
        fputcsv($f, $linedata, $delimiter);

        fpassthru($f);
    }
}
