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
$isGroupExport = false;
foreach (array_keys($_GET) as $exportKey) {
    if (str_starts_with((string)$exportKey, 'g')) { $isGroupExport = true; break; }
}
$exportScope = $isGroupExport
    ? 'public-relations.safe-call.manage.total-statistics.export'
    : 'public-relations.safe-call.manage.operator-statistics.export';
if (!AdminCanUsePermissionScope($exportScope, ['permision_managecall'])) {
    http_response_code(403);
    exit('مجوز دریافت این گزارش تماس ندارید.');
}
if(isset($_GET['gexportAll'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=?");
    $stm->execute([$_GET['gexportAll']]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی تماس_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['guncall'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['guncall'],0]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی تماس گرفته نشده_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['gbegin'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['gbegin'],1]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی نیاز به تماس مجدد_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['gfinish'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['gfinish'],2]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی تماس های خاتمه یافته_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['gdublicate'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['gdublicate'],4]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی مخاطبین تکراری_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['gwrong'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['gwrong'],3]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی شماره های اشتباه_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['gunres'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`group`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['gunres'],5]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش کلی شماره های عدم پاسخ_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
// report personal

if(isset($_GET['exportAll'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=?");
    $stm->execute([$_GET['exportAll']]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی تماس_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['uncall'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['uncall'],0]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی تماس نگرفته_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['begin'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['begin'],1]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی تماس مجدد_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['finish'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['finish'],2]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی تماس خاتمه یافته_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['dublicate'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['dublicate'],4]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی مخاطبین تکراری_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['wrong'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['wrong'],3]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی شماره اشتباه_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['unres'])){
    $stm = $conn->prepare("SELECT * FROM tel_db LEFT JOIN  tel_detail ON `tel_db`.`id`=`tel_detail`.`dbtel` WHERE `tel_db`.`dakheli`=? AND `tel_db`.`act`=? ");
    $stm->execute([$_GET['unres'],4]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش انفرادی شماره عدم پاسخ_" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['ردیف تلفن', 'نام', 'نام خانوادگی', 'راهنما', 'استان', 'شهر','موبایل1','موبایل2','موبایل3','ثابت1','ثابت2','ثابت3','اقدام','داخلی','شرح تماس','نوع دانش آموز','تماس با','ایجاد کننده','تاریخ ایجاد','زمان ایجاد'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            switch ($row->act){
                case '0':
                    $act = 'تماس گرفته نشده ';
                    break;
                case '1':
                    $act = 'نیاز به تماس مجدد';
                    break;
                case '2':
                    $act = 'تماس پایان یافته';
                    break;
                case '3':
                    $act = 'شماره اشتباه';
                    break;
                case '4':
                    $act = 'شماره تکراری';
                    break;
                case '5':
                    $act = 'عدم پاسخ';
                    break;
            }
            switch ($row->box){
                case 'mob1':
                    $tel = 'موبایل1';
                    break;
                case 'mob2':
                    $tel = 'موبایل2';
                    break;
                case 'mob3':
                    $tel = 'موبایل3';
                    break;
                case 'static1':
                    $tel = 'ثابت 1';
                    break;
                case 'static2':
                    $tel = 'ثابت 2';
                    break;
                case 'static3':
                    $tel = 'ثابت 3';
                    break;
                default:
                    $tel='NULL';
            }
            $admin=retAdmin($row->useradder);
            if($admin) {
                $name = $admin->fname . ' ' . $admin->lname;
            }else{
                $name='NULL';
            }

            $dateno = $row->datecreated;
            if($dateno) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
            }else{
                $dateno='NULL';
            }

            $linedata = [$row->id, $row->fname, $row->lname, $row->guidance, $row->state, $row->city, $row->mob1, $row->mob2,$row->mob3,$row->tel1,$row->tel2,$row->tel3,$act,$row->dakheli,$row->txt,$row->txt2,$tel,$name,$dateno,$row->timecreated];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
