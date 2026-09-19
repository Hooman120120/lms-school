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

if (!Permision('permision_viewtest')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_GET['exp'])) {
    $db = retDbquiz($_GET['exp']);
    $course=returnCourse($db->codecourse);
    $stm = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username WHERE dbcourse.activityuser=? AND dbcourse.codecourse=?");
    $stm->execute([1, $db->codecourse]);
    if ($stm) {

        $delimiter = ",";
        $filename = "گزارش غائبین آزمون " . $db->quizname . " _" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی', 'نام پدر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'استان', 'شهر','پایه', 'نام مدرسه', 'نام پایه', 'نام آزمون','نام درس','توالی', 'کدملی همکار', 'نام همکار', 'تلفن همکار'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

                $requiz = 'SELECT * FROM result_quiz WHERE quizid=? AND username=?';
                $qres = $conn->prepare($requiz);
                $qres->execute([$_GET['exp'], $row->username]);
                $req = $qres->fetchAll(PDO::FETCH_OBJ);
                //
                if ($qres->rowCount() == 0) {
                    if($row->useradder!= NULL) {
                        $collague = retPerson($row->useradder);
                        $collname=$collague->fname.' '.$collague->lname;
                        $colltel=$collague->tel1;
                    }else{
                        $collname='';
                        $colltel='';
                    }
                    $dars=getdars($db->dars);
                    $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$row->tel3,$row->state,$row->city,$row->paye,$row->nameschool,$course->coursename,$db->quizname,$dars,$db->tasalsol,$row->useradder,$collname,$colltel];
                    fputcsv($f, $linedata, $delimiter);

                }



        }
    }
}
if (isset($_GET['rsp'])) {
    $db = retDbquiz($_GET['rsp']);
    $course=returnCourse($db->codecourse);
    $stm = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username WHERE dbcourse.activityuser=? AND dbcourse.codecourse=?");
    $stm->execute([1, $db->codecourse]);
    if ($stm) {

        $delimiter = ",";
        $filename = "گزارش کارنامه آزمون " . $db->quizname . " _" . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی', 'نام پدر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'استان', 'شهر','پایه', 'نام مدرسه', 'نام پایه','نام آزمون','درس', 'توالی','تعداد سوالات','پاسخ صحیح','پاسخ غلط','بدون پاسخ','مدت حضور','نمره','تراز','رتبه', 'کدملی همکار', 'نام همکار', 'تلفن همکار'];
        fputcsv($f, $fields, $delimiter);

        while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

            $requiz = 'SELECT * FROM result_quiz WHERE quizid=? AND username=?';
            $qres = $conn->prepare($requiz);
            $qres->execute([$_GET['rsp'], $row->username]);
            $req = $qres->fetch(PDO::FETCH_OBJ);
            //
            if ($qres->rowCount() > 0) {
                if($row->useradder!= NULL) {
                    $collague = retPerson($row->useradder);
                    $collname=$collague->fname.' '.$collague->lname;
                    $colltel=$collague->tel1;
                }else{
                    $collname='';
                    $colltel='';
                }
                $dars=getdars($db->dars);
                $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2,$row->tel3,$row->state,$row->city,$row->paye,$row->nameschool,$course->coursename,$db->quizname,$dars,$db->tasalsol,$req->allquestion,$req->trueresponse,$req->falseresponse,$req->unresponse,$req->duration,$req->nomre,$req->taraz,$req->rotbe,$row->useradder,$collname,$colltel];
                fputcsv($f, $linedata, $delimiter);

            }



        }
    }
}