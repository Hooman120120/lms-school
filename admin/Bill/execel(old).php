<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


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
if (!Permision('permision_bill')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_SESSION['execel'])) {
    $type = $_SESSION['execel']['type'];
    $course = $_SESSION['execel']['course'];
    $start = $_SESSION['execel']['start'];
    $end = $_SESSION['execel']['end'];

    switch ($type) {
        case '1':
            $status = 'موفق';
            break;
        case '2':
            $status = 'ناموفق';
            break;
        case '3':
            $status = 'منتظر پرداخت';
            break;
        case '4':
            $status = 'در انتظار بررسی';
            break;
        case '5':
            $status = 'مسترد شده';
            break;
        case '6':
            $status = 'عدم تایید';
            break;
    }
    if ($type == '1') {
        $qdate = 'dateresponse';
    } else {
        $qdate = 'datecreate';
    }
    $startdate = $start;
    $startdate = explode('/', $startdate);
    $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2]);
    $startdate = implode('-', $startdate);
    //
    $enddate = $end;
    $enddate = explode('/', $enddate);
    $enddate = jalali_to_gregorian($enddate[0], $enddate[1], $enddate[2]);
    $enddate = implode('-', $enddate);

    if ($course == 1) {   //همه دوره ها
        $stm = $conn->prepare("SELECT * FROM dbfactor INNER JOIN dbpersonal ON dbfactor.username=dbpersonal.codemeli WHERE status=? AND " . $qdate . " BETWEEN ? AND ? ORDER BY " . $qdate . " DESC  ");
        $stm->execute([$status, $startdate, $enddate]);


        if ($stm) {
            $delimiter = ",";
            $filename = "گزارش صورتحساب همه دوره ها_" . date('Y-m-d h:i:s') . ".csv";

            header('Content-Encoding: UTF-8');
            header('Content-type: application/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

            $fields = ['شماره فاکتور', 'کدملی', 'نام', 'نام خانوادگی','پایه' ,'استان', 'شهر','منطقه', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'مبلغ فاکتور', 'وضعیت', 'تاریخ','دوره های ثبت نامی','ایجاد کننده'];
            fputcsv($f, $fields, $delimiter);

            while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

                $factorfee = $row->wallet + $row->factorfee;
                $dateno = $row->$qdate;
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                if ($row->useradder != NULL) {
                    $adder = retPerson($row->useradder);
                    $creator = $adder->fname . ' ' . $adder->lname;
                } else {
                    $creator = 'خودش';
                }
                switch ($row->paye){
                    case '5':
                        $paye='پنجم ابتدایی';
                        break;
                    case '6':
                        $paye='ششم ابتدایی';
                        break;
                    case '7':
                        $paye='هفتم متوسطه اول';
                        break;
                    case '8':
                        $paye='هشتم متوسطه اول';
                        break;
                    case '9':
                        $paye='نهم متوسطه اول';
                        break;
                    case '10':
                        $paye='دهم '.$row->reshtestudent;
                        break;
                    case '11':
                        $paye='یازدهم  '.$row->reshtestudent;
                        break;
                    case '12':
                        $paye='دوازدهم  '.$row->reshtestudent;
                        break;
                }
                $doreha=retfactorcourse($row->username,$row->idfactor);
                for ($rt = 0; $rt < sizeof($doreha); $rt++) {
                    $hamsepar = returnCourse($doreha[$rt]);
                    $doreha[$rt] = $hamsepar->coursename;
                    $df = implode(' + ', $doreha);
                }

                $linedata = [$row->idfactor, $row->username, $row->fname, $row->lname,$paye, $row->state, $row->city,$row->area, $row->tel1, $row->tel2, $row->tel3, $factorfee, $status, $dateno, $df,$creator];
                fputcsv($f, $linedata, $delimiter);
            }

            fpassthru($f);
        }
    } else {
        $stm = $conn->prepare('SELECT * FROM dbfactor INNER JOIN dbcourse ON dbcourse.numfactor=dbfactor.idfactor INNER JOIN dbpersonal ON dbpersonal.codemeli=dbfactor.username WHERE status=? AND codecourse=? AND ' . $qdate . ' BETWEEN ? AND ? ORDER BY ' . $qdate . ' DESC ');
        $stm->execute([$status, $course, $startdate, $enddate]);


        if ($stm) {
            $delimiter = ",";
            $recourse = returnCourse($course);
            $filename = "گزارش صورتحساب " . $recourse->coursename . " _" . date('Y-m-d h:i:s') . ".csv";

            header('Content-Encoding: UTF-8');
            header('Content-type: application/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

            $fields = ['شماره فاکتور', 'کدملی', 'نام', 'نام خانوادگی', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'مبلغ فاکتور', 'وضعیت', 'تاریخ', 'ایجاد کننده'];
            fputcsv($f, $fields, $delimiter);

            while ($row = $stm->fetch(PDO::FETCH_OBJ)) {

                $factorfee = $row->wallet + $row->factorfee;
                $dateno = $row->$qdate;
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                if ($row->useradder != NULL) {
                    $adder = retPerson($row->useradder);
                    $creator = $adder->fname . ' ' . $adder->lname;
                } else {
                    $creator = 'خودش';
                }



                $linedata = [$row->idfactor, $row->username, $row->fname, $row->lname, $row->state, $row->city, $row->tel1, $row->tel2, $row->tel3, $factorfee, $status, $dateno, $creator];
                fputcsv($f, $linedata, $delimiter);
            }

            fpassthru($f);
        }
    }

    unset($_SESSION['execel']);
}

if (isset($_SESSION['excelCoursereport'])) {
    $type = $_SESSION['excelCoursereport']['type'];
    $start = $_SESSION['excelCoursereport']['start'];
    $end = $_SESSION['excelCoursereport']['end'];
    $startdate = $start;
    $startdate = explode('/', $startdate);
    $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2]);
    $startdate = implode('-', $startdate);
    //
    $enddate = $end;
    $enddate = explode('/', $enddate);
    $enddate = jalali_to_gregorian($enddate[0], $enddate[1], $enddate[2]);
    $enddate = implode('-', $enddate);
    if ($type == 1) {
        $stm = $conn->prepare('SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor WHERE dbcourse . activityuser = ? and dbfactor . dateresponse BETWEEN ? and ? ORDER BY dbfactor . username DESC');
        $stm->execute([1, $startdate, $enddate]);
        if ($stm) {
            $delimiter = ",";
            $filename = "گزارش نفر دوره _" . date('Y-m-d h:i:s') . ".csv";

            header('Content-Encoding: UTF-8');
            header('Content-type: application/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

            $fields = ['کدملی', 'نام', 'نام خانوادگی', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'نام دوره', 'ایجاد کننده','پشتیبان', 'شماره فاکتور'];
            fputcsv($f, $fields, $delimiter);
            while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


                $user = retPerson($row->username);
                if ($row->factoradder != NULL) {
                 
                    $creator = $row->factoradder;
                } else {
                    $creator = 'خودش';
                }
                if($row->suporter != NULL){
                    $suporter=$row->suporter;
                }else{
                    $suporter ='ندارد';
                }


                $linedata = [$row->username, $user->fname, $user->lname, $user->state, $user->city, $user->tel1, $user->tel2, $user->tel3, $row->codecourse, $creator, $suporter,$row->idfactor];
                fputcsv($f, $linedata, $delimiter);
            }

            fpassthru($f);
        }
    }
    if ($type == 2) {
        $codemelli = array();
        $fname = array();
        $lname = array();
        $poshtiban = array();
        $tel1 = array();
        $tel2 = array();
        $tel3 = array();
        $state = array();
        $city = array();
        $paye = array();
        $course = array();
        $fee = array();
        $first = array();
        $suporter = array();
        $useractive = array();
        $idfactor = array();

        $sql = 'SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor WHERE dbcourse . activityuser = 1 and dbfactor . dateresponse BETWEEN ? and ? ORDER BY dbfactor . username DESC';
        $query = $conn->prepare($sql);
        $query->execute([$startdate, $enddate]);
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $res) {
                if (!in_array($res->username, $codemelli)) {
                    array_push($codemelli, $res->username);
                    $student = retPerson($res->username);
                    $firstlogin=retdblogin($res->username);
                    array_push($fname, $student->fname);
                    array_push($lname, $student->lname);
                    array_push($tel1, $student->tel1);
                    array_push($tel2, $student->tel2);
                    array_push($tel3, $student->tel3);
                    array_push($state, $student->state);
                    array_push($city, $student->city);
                    array_push($paye, $student->paye);
                    array_push($first, $firstlogin->firstlogin);
                    if ($res->factoradder != NULL) {
                        array_push($poshtiban, $res->factoradder);
                    } else {
                        array_push($poshtiban, 'خودش');
                    }
                    if ($res->useractive != NULL) {
                        array_push($useractive, $res->useractive);
                    } else {
                        array_push($useractive, 'خودش');
                    }

                    $allfactornum = retallcoursefactor($res->username, $startdate, $enddate);
                    array_push($idfactor, $allfactornum);
                    $doreha = retallcourse($res->username, $startdate, $enddate);
                    array_push($course, $doreha);
                    $support = retallcourseposhtiban($res->username, $startdate, $enddate);
                    array_push($suporter, $support);
                    $sumfee=sumfactoruser($res->username, $startdate, $enddate);
                    array_push($fee, $sumfee);
                }
            }
        }

        if ($codemelli != NULL) {
            $delimiter = ",";
            $filename = "گزارش تجمیعی نفر دوره _" . date('Y-m-d h:i:s') . ".csv";

            header('Content-Encoding: UTF-8');
            header('Content-type: application/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename=' . $filename);

            $f = fopen('php://output', 'w');
            fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

            $fields = ['شماره فاکتور','کدملی', 'نام', 'نام خانوادگی', 'پایه', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'نام دوره ها', 'تعداد دوره ثبت نامی','جمع پرداختی','اولین ورود', 'ایجاد کننده','تاییدکننده فاکتور','پشتیبان'];
            fputcsv($f, $fields, $delimiter);
            for ($num = 0; $num < sizeof($codemelli); $num++) {
                if($suporter[$num]!=NULL) {
                    $sposh = implode('|', $suporter[$num]);

                }else{
                    $sposh='ندارد';
                }
                $factornum = implode('|', $idfactor[$num]);
                for ($rt = 0; $rt < sizeof($course[$num]); $rt++) {
                    $hamsepar = returnCourse($course[$num][$rt]);
                    $course[$num][$rt] = $hamsepar->coursename;
                    $df = implode('|', $course[$num]);

                }

                $linedata = [$factornum,$codemelli[$num], $fname[$num], $lname[$num], $paye[$num], $state[$num], $city[$num], $tel1[$num], $tel2[$num], $tel3[$num], $df, sizeof($course[$num]),$fee[$num],$first[$num] ,$poshtiban[$num],$useractive[$num],$sposh];
                fputcsv($f, $linedata, $delimiter);
            }
            fpassthru($f);
        }


    }
    if ($type == 3) {
        $sql = '
        SELECT
            cs.student_id,
            cs.exam_type,
            cs.registered,
            cs.attended,
            cs.result,
            cs.not_registered_reason,
            dc.username    AS dbcourse_username,
            dc.activityuser,
            dc.color       AS dbcourse_color,
            dc.suporter    AS suporter,
            COALESCE(ss.student_comment, "")     AS student_comment,
            COALESCE(ss.supporter_comment, "")   AS supporter_comment,
            COALESCE(ss.avg_first_semester, "")  AS avg_first_semester,
            COALESCE(ss.avg_second_semester, "") AS avg_second_semester,
            dp.paye AS paye,
            COALESCE(pay.total_paid,0) AS total_paid   -- ✅ جمع پرداختی
        FROM course_status cs
        LEFT JOIN dbcourse dc ON cs.student_id = dc.username
        LEFT JOIN student_status ss ON cs.student_id = ss.student_id
        LEFT JOIN dbpersonal dp ON cs.student_id = dp.codemeli
        LEFT JOIN (
            SELECT dbfactor.username, SUM(dbfactor.subtotal) AS total_paid
            FROM dbfactor
            GROUP BY dbfactor.username
        ) pay ON pay.username = cs.student_id
        ORDER BY cs.student_id DESC
    ';

        $stm = $conn->prepare($sql);
        $stm->execute();

        $rows = $stm->fetchAll(PDO::FETCH_OBJ);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // ✅ Added جمع پرداختی after پشتیبان
        $fields = [
            'کدملی', 'نام', 'نام خانوادگی', 'پایه', 'پشتیبان', 'جمع پرداختی', 'استان', 'شهر',
            'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر',
            'نوع آزمون', 'وضعیت ثبت نام', 'وضعیت آزمون', 'نتیجه',
            'توضیحات',
            'نظر دانش آموز', 'نظر پشتیبان',
            'معدل ترم اول', 'معدل ترم دوم'
        ];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $rowNum = 2;
        $seen = [];

        $studentIds = array_values(array_unique(array_map(fn($r) => $r->student_id, $rows)));
        $colorMapRows = [];
        if (!empty($studentIds)) {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $colorSql = "SELECT username, color FROM dbcourse WHERE username IN ($placeholders)";
            $colorStmt = $conn->prepare($colorSql);
            $colorStmt->execute($studentIds);
            foreach ($colorStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
                $colorMapRows[$cr['username']][] = $cr['color'];
            }
        }

        $hexMap = [0=>'FFFF0000',1=>'FFFFA500',2=>'FFFFFF00',3=>'FF00FF00'];

        foreach ($rows as $r) {
            $keyParts = [$r->student_id, $r->exam_type, $r->registered, $r->attended, $r->result];
            $rowKey = implode('|',$keyParts);
            if (isset($seen[$rowKey])) continue;
            $seen[$rowKey] = true;

            $user = $r->student_id ? retPerson($r->student_id) : false;

            $colorsForUser = $colorMapRows[$r->student_id] ?? [];
            $avgColorHex = null;
            if (!empty($colorsForUser)) {
                $nums = array_map('floatval',$colorsForUser);
                $avgIndex = (int) round(array_sum($nums)/count($nums));
                if(isset($hexMap[$avgIndex])) $avgColorHex = $hexMap[$avgIndex];
            }

            // ✅ Added total_paid after supporter
            $linedata = [
                $r->student_id ?? '',
                $user->fname ?? '',
                $user->lname ?? '',
                $r->paye ?? '',
                $r->suporter ?? '',
                $r->total_paid ?? 0,
                $user->state ?? '',
                $user->city ?? '',
                $user->tel1 ?? '',
                $user->tel2 ?? '',
                $user->tel3 ?? '',
                $r->exam_type ?? '',
                $r->registered ?? '',
                $r->attended ?? '',
                $r->result ?? '',
                $r->not_registered_reason ?? '',
                $r->student_comment ?? '',
                $r->supporter_comment ?? '',
                $r->avg_first_semester ?? '',
                $r->avg_second_semester ?? ''
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci+1) . $rowNum;
                $sheet->setCellValue($cell, $val);
            }

            if($avgColorHex) {
                $sheet->getStyle("B{$rowNum}:C{$rowNum}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($avgColorHex);
            }

            $rowNum++;
        }

        for($i=1;$i<=count($fields);$i++){
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="گزارش_نفر_دوره_' . date('Y-m-d_H-i-s') . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }




    unset($_SESSION['excelCoursereport']);
}

if (isset($_SESSION['supporter_excel']) && isset($_SESSION['supporter_excel']['Op'])) {
    $op = $_SESSION['supporter_excel']['Op'];
    if (preg_match('/^[a-zA-Z0-9]+$/', $op)) {
        $sql = '
SELECT *
FROM dbcourse 
INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor
WHERE codtbl_course.activecourse = 1 
  AND dbcourse.activityuser = 1 
  AND dbcourse.suporter = ?
  AND dbfactor.status = ?
GROUP BY dbcourse.username
';
        $query = $conn->prepare($sql);
        $query->execute([$op, 'موفق']);
        $rows = $query->fetchAll(PDO::FETCH_OBJ);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $fields = ['ردیف', 'کد ملی', 'نام و نام خانوادگی', 'نام دوره', 'آزمون', 'آخرین پشتیبانی', 'پشتیبانی بعدی', 'نوع', 'رنگ', 'پرداختی'];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $rowNum = 2;
        $id = 1;

        foreach ($rows as $ret) {
            $personal = retPerson($ret->username);
            $dore = getCourses($ret->username);
            $doreStr = implode(' || ', $dore);
            $displayDore = mb_strlen($doreStr) > 50 ? mb_substr($doreStr, 0, 50) . '...' : $doreStr;

            $quiz = getTblQuizColor($ret->codecourse, $ret->username);

            $suport = getsuport_user($ret->username);
            if ($suport) {
                $last = $suport['lastdate'];
                $last = explode('-', $last);
                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                $last = implode('/', $last);

                $after = $suport['afterdate'];
                $after = explode('-', $after);
                $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                $after = implode('/', $after);

                $type = $suport['typesupport'] == 1 ? 'ویـژه' : 'عادی';
            } else {
                $last = 'ندارد';
                $after = 'ندارد';
                $type = 'عادی';
            }

            switch ($ret->color) {
                case 0:
                    $color = 'قرمز';
                    $colorCode = 'FF0000'; // Red
                    break;
                case 1:
                    $color = 'نارنجی';
                    $colorCode = 'FFA500'; // Orange
                    break;
                case 2:
                    $color = 'زرد';
                    $colorCode = 'FFFF00'; // Yellow
                    break;
                case 3:
                    $color = 'سبز';
                    $colorCode = '008000'; // Green
                    break;
                default:
                    $color = '';
                    $colorCode = 'FFFFFF'; // White (default)
                    break;
            }

            $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username=?';
            $wer = $conn->prepare($swr);
            $wer->execute([1, 1, $ret->username]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            $total = [];
            $factorCheck = [];
            if ($wer->rowCount() > 0) {
                foreach ($recive as $rey) {
                    if (!in_array($rey->numfactor, $factorCheck)) {
                        $factor = queryFactor($rey->numfactor);
                        $sum = $factor->wallet + $factor->factorfee;
                        array_push($total, $sum);
                        array_push($factorCheck, $rey->numfactor);
                    }
                }
            }
            $sumtoto = array_sum($total);

            $linedata = [
                $id,
                $ret->username,
                $personal->fname . ' ' . $personal->lname,
                $displayDore,
                $quiz[0],
                $last,
                $after,
                $type,
                $color,
                $sumtoto
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                $sheet->setCellValue($cell, $val);
                // Apply background color to the "رنگ" column (index 8, column I)
                if ($ci === 8 && $colorCode !== 'FFFFFF') {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorCode);
                }
            }

            $rowNum++;
            $id++;
        }

        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="پشتیبان دانش آموز '.$op.' اپراتور _' . date('Y-m-d_H-i-s') . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        unset($_SESSION['supporter_excel']);
        exit;
    }
}

if (isset($_SESSION['course_excel']) && isset($_SESSION['course_excel']['code'])) {
    $code = $_SESSION['course_excel']['code'];
    if (preg_match('/^[a-zA-Z0-9]+$/', $code)) {
        error_log("Starting course_learners Excel generation with code: $code");

        $sql = '
SELECT *
FROM dbcourse 
WHERE activityuser = 1 AND codecourse = ?
GROUP BY username
';
        try {
            $query = $conn->prepare($sql);
            $query->execute([$code]);
            $rows = $query->fetchAll(PDO::FETCH_OBJ);
            error_log("Query returned " . count($rows) . " rows for code: $code");
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            header('Location: course_learners.php?error=db_error');
            exit;
        }

        if (count($rows) === 0) {
            error_log("No data found for course_learners with code: $code");
            header('Location: course_learners.php?error=no_data');
            exit;
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $fields = ['ردیف', 'کد ملی', 'نام و نام خانوادگی', 'نام دوره', 'آزمون', 'آخرین پشتیبانی', 'پشتیبانی بعدی', 'نوع', 'رنگ', 'پشتیبان', 'پرداختی'];
            foreach ($fields as $i => $f) {
                $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
                $sheet->setCellValue($cell, $f);
            }

            $colorColumnIndex = array_search('رنگ', $fields);
            error_log("Color column index: " . ($colorColumnIndex !== false ? $colorColumnIndex : 'Not found'));

            $rowNum = 2;
            $id = 1;

            $hexMap = [
                0 => 'FFFF0000', // Red
                1 => 'FFFFA500', // Orange
                2 => 'FFFFFF00', // Yellow
                3 => 'FF00FF00', // Green
            ];

            foreach ($rows as $ret) {
                $personal = retPerson($ret->username);
                $dore = getCourses($ret->username);
                $doreStr = implode(' || ', $dore);
                $course = returnCourse($code);
                $displayDore = mb_strlen($course->coursename) > 50 ? mb_substr($course->coursename, 0, 50) . '...' : $course->coursename;

                $quiz = getTblQuizColor($code, $ret->username);

                $suport = getsuport_user($ret->username);
                if ($suport) {
                    $last = $suport['lastdate'];
                    $last = explode('-', $last);
                    $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                    $last = implode('/', $last);

                    $after = $suport['afterdate'];
                    $after = explode('-', $after);
                    $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                    $after = implode('/', $after);

                    $type = $suport['typesupport'] == 1 ? 'ویـژه' : 'عادی';
                } else {
                    $last = 'ندارد';
                    $after = 'ندارد';
                    $type = 'عادی';
                }

                $color = isset($hexMap[$ret->color]) ? ['قرمز', 'نارنجی', 'زرد', 'سبز'][$ret->color] : '';
                $colorHex = isset($hexMap[$ret->color]) ? $hexMap[$ret->color] : 'FFFFFFFF';

                error_log("Row $rowNum, username {$ret->username}: color=$ret->color, name=$color, hex=$colorHex");

                $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username=?';
                $wer = $conn->prepare($swr);
                $wer->execute([1, 1, $ret->username]);
                $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                $total = [];
                $factorCheck = [];
                if ($wer->rowCount() > 0) {
                    foreach ($recive as $rey) {
                        if (!in_array($rey->numfactor, $factorCheck)) {
                            $factor = queryFactor($rey->numfactor);
                            $sum = $factor->wallet + $factor->factorfee;
                            array_push($total, $sum);
                            array_push($factorCheck, $rey->numfactor);
                        }
                    }
                }
                $sumtoto = array_sum($total);

                $linedata = [
                    $id,
                    $ret->username,
                    $personal->fname . ' ' . $personal->lname,
                    $displayDore,
                    $quiz[0],
                    $last,
                    $after,
                    $type,
                    $color,
                    empty($ret->suporter) ? 'ندارد' : $ret->suporter,
                    number_format($sumtoto)
                ];

                foreach ($linedata as $ci => $val) {
                    $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                    $sheet->setCellValue($cell, $val);
                    if ($ci === $colorColumnIndex && $colorHex !== 'FFFFFFFF') {
                        try {
                            $sheet->getStyle($cell)
                                ->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()
                                ->setARGB($colorHex);
                            error_log("Applied color $colorHex to cell $cell");
                        } catch (Exception $e) {
                            error_log("Error applying color to cell $cell: " . $e->getMessage());
                        }
                    }
                }

                $rowNum++;
                $id++;
            }

            for ($i = 1; $i <= count($fields); $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="دانش آموزان دوره_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            unset($_SESSION['course_excel']);
            exit;
        } catch (Exception $e) {
            error_log("Excel generation error: " . $e->getMessage());
            exit;
        }
    }
}

if (isset($_SESSION['waiting_list_excel']) && isset($_SESSION['waiting_list_excel']['export'])) {
    error_log("Starting waiting_list_excel generation");

    $sql = '
SELECT *
FROM dbcourse 
INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse 
WHERE dbcourse.activityuser = 1 
AND codtbl_course.activecourse = 1 
AND dbcourse.type_process = ?
';
    try {
        $query = $conn->prepare($sql);
        $query->execute([1]);
        $rows = $query->fetchAll(PDO::FETCH_OBJ);
        error_log("Query returned " . count($rows) . " rows for waiting_list");
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        header('Location: waiting_list.php?error=db_error');
        exit;
    }

    if (count($rows) === 0) {
        error_log("No data found for waiting_list");
        header('Location: waiting_list.php?error=no_data');
        exit;
    }

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Exclude "ورود", "تایید", and "عدم تایید"
        $fields = ['#', 'رنگ جدید', 'کد ملی', 'نام و نام خانوادگی', 'نام دوره', 'آزمون', 'آخرین پشتیبانی', 'پشتیبانی بعدی', 'نوع', 'رنگ', 'پشتیبان', 'توضیح'];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $colorColumnIndex = array_search('رنگ', $fields);
        $colorWaitColumnIndex = array_search('رنگ جدید', $fields);
        error_log("Color column index: " . ($colorColumnIndex !== false ? $colorColumnIndex : 'Not found'));
        error_log("Color_wait column index: " . ($colorWaitColumnIndex !== false ? $colorWaitColumnIndex : 'Not found'));

        $rowNum = 2;
        $id = 1;

        $hexMap = [
            0 => 'FFFF0000', // Red
            1 => 'FFFFA500', // Orange
            2 => 'FFFFFF00', // Yellow
            3 => 'FF00FF00', // Green
            4 => 'FF808080', // Gray (for color_wait = انصراف)
        ];

        foreach ($rows as $ret) {
            $personal = retPerson($ret->username);
            $namecourse = returnCourse($ret->codecourse);
            $quiz = getTblQuizColor($ret->codecourse, $ret->username);

            $suport = getsuport_user($ret->username);
            if ($suport) {
                $last = $suport['lastdate'];
                $last = explode('-', $last);
                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                $last = implode('/', $last);
                $after = $suport['afterdate'];
                $after = explode('-', $after);
                $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                $after = implode('/', $after);
                $type = $suport['typesupport'] == 1 ? 'ویـژه' : 'عادی';
            } else {
                $last = 'ندارد';
                $after = 'ندارد';
                $type = 'عادی';
            }

            $color = isset($hexMap[$ret->color]) ? ['قرمز', 'نارنجی', 'زرد', 'سبز', ''][$ret->color ?? 4] : '';
            $colorHex = isset($hexMap[$ret->color]) ? $hexMap[$ret->color] : 'FFFFFFFF';
            $color_wait = isset($hexMap[$ret->color_wait]) ? ['قرمز', 'نارنجی', 'زرد', 'سبز', 'انصراف'][$ret->color_wait ?? 4] : '';
            $colorWaitHex = isset($hexMap[$ret->color_wait]) ? $hexMap[$ret->color_wait] : 'FFFFFFFF';

            error_log("Row $rowNum, username {$ret->username}: color=$ret->color, name=$color, hex=$colorHex, color_wait=$ret->color_wait, name_wait=$color_wait, hex_wait=$colorWaitHex");

            $linedata = [
                $id,
                $color_wait,
                $ret->username,
                $personal->fname . ' ' . $personal->lname,
                $namecourse->coursename,
                $quiz[0],
                $last,
                $after,
                $type,
                $color,
                $ret->suporter ?: 'ندارد',
                $ret->color_detail
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                $sheet->setCellValue($cell, $val);
                // Apply color to "رنگ" column
                if ($ci === $colorColumnIndex && $colorHex !== 'FFFFFFFF') {
                    try {
                        $sheet->getStyle($cell)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB($colorHex);
                        error_log("Applied color $colorHex to cell $cell");
                    } catch (Exception $e) {
                        error_log("Error applying color to cell $cell: " . $e->getMessage());
                    }
                }
                // Optionally apply color to "رنگ جدید" column
                if ($ci === $colorWaitColumnIndex && $colorWaitHex !== 'FFFFFFFF') {
                    try {
                        $sheet->getStyle($cell)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB($colorWaitHex);
                        error_log("Applied color $colorWaitHex to cell $cell");
                    } catch (Exception $e) {
                        error_log("Error applying color to cell $cell: " . $e->getMessage());
                    }
                }
            }

            $rowNum++;
            $id++;
        }

        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="waiting_list_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        unset($_SESSION['waiting_list_excel']);
        exit;
    } catch (Exception $e) {
        error_log("Excel generation error: " . $e->getMessage());
        header('Location: waiting_list.php?error=excel_error');
        exit;
    }
}



if (isset($_SESSION['course_color_excel']) && isset($_SESSION['course_color_excel']['export'])) {
    error_log("Starting course_color_excel generation");

    $code = $_SESSION['course_color_excel']['code'] ?? '';
    $color = $_SESSION['course_color_excel']['color'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9]+$/', $code) || !in_array($color, ['0', '1', '2', '3'])) {
        error_log("Invalid code or color in session: code=$code, color=$color");
        header('Location: course_color.php?error=invalid_params');
        exit;
    }

    $sql = '
SELECT *
FROM dbcourse 
INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse 
WHERE dbcourse.codecourse = ? 
AND dbcourse.activityuser = 1 
AND codtbl_course.activecourse = 1 
AND dbcourse.color = ?
';
    try {
        $query = $conn->prepare($sql);
        $query->execute([$code, $color]);
        $rows = $query->fetchAll(PDO::FETCH_OBJ);
        error_log("Query returned " . count($rows) . " rows for course_color with code: $code, color: $color");
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        header('Location: course_color.php?error=db_error');
        exit;
    }

    if (count($rows) === 0) {
        error_log("No data found for course_color");
        header('Location: course_color.php?error=no_data');
        exit;
    }

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Exclude "ورود"
        $fields = ['ردیف', 'کد ملی', 'نام و نام خانوادگی', 'نام دوره', 'آزمون', 'آخرین پشتیبانی', 'پشتیبانی بعدی', 'نوع', 'رنگ', 'پشتیبان', 'پرداختی'];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $colorColumnIndex = array_search('رنگ', $fields);
        error_log("Color column index: " . ($colorColumnIndex !== false ? $colorColumnIndex : 'Not found'));

        $rowNum = 2;
        $id = 1;

        $hexMap = [
            0 => 'FFFF0000', // Red
            1 => 'FFFFA500', // Orange
            2 => 'FFFFFF00', // Yellow
            3 => 'FF00FF00', // Green
        ];

        foreach ($rows as $ret) {
            $personal = retPerson($ret->username);
            if (!$personal) {
                error_log("No personal data for username: {$ret->username}");
                $personal = (object)['fname' => '', 'lname' => ''];
            }

            $namecourse = returnCourse($ret->codecourse);
            if (!$namecourse) {
                error_log("No course data for codecourse: {$ret->codecourse}");
                $namecourse = (object)['coursename' => ''];
            }

            $quiz = getTblQuizColor($ret->codecourse, $ret->username);
            if (!$quiz || !isset($quiz[0])) {
                error_log("No quiz data for username: {$ret->username}, codecourse: {$ret->codecourse}");
                $quiz = [''];
            }

            $suport = getsuport_user($ret->username);
            if ($suport) {
                $last = $suport['lastdate'];
                $lastParts = explode('-', $last);
                if (count($lastParts) === 3) {
                    $last = gregorian_to_jalali($lastParts[0], $lastParts[1], $lastParts[2]);
                    $last = implode('/', $last);
                } else {
                    error_log("Invalid lastdate format for username: {$ret->username}");
                    $last = 'ندارد';
                }

                $after = $suport['afterdate'];
                $afterParts = explode('-', $after);
                if (count($afterParts) === 3) {
                    $after = gregorian_to_jalali($afterParts[0], $afterParts[1], $afterParts[2]);
                    $after = implode('/', $after);
                } else {
                    error_log("Invalid afterdate format for username: {$ret->username}");
                    $after = 'ندارد';
                }

                $type = $suport['typesupport'] == 1 ? 'ویـژه' : 'عادی';
            } else {
                $last = 'ندارد';
                $after = 'ندارد';
                $type = 'عادی';
            }

            $colorText = isset($hexMap[$ret->color]) ? ['قرمز', 'نارنجی', 'زرد', 'سبز'][$ret->color] : '';
            $colorHex = isset($hexMap[$ret->color]) ? $hexMap[$ret->color] : 'FFFFFFFF';
            error_log("Row $rowNum, username {$ret->username}: color=$ret->color, name=$colorText, hex=$colorHex");

            $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username=?';
            $wer = $conn->prepare($swr);
            $wer->execute([1, 1, $ret->username]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            $total = [];
            $factorCheck = [];
            if ($wer->rowCount() > 0) {
                foreach ($recive as $rey) {
                    if (!in_array($rey->numfactor, $factorCheck)) {
                        $factor = queryFactor($rey->numfactor);
                        if ($factor) {
                            $sum = $factor->wallet + $factor->factorfee;
                            array_push($total, $sum);
                            array_push($factorCheck, $rey->numfactor);
                        } else {
                            error_log("No factor data for numfactor: {$rey->numfactor}");
                        }
                    }
                }
            }
            $sumtoto = array_sum($total);

            $linedata = [
                $id,
                $ret->username ?? '',
                $personal->fname . ' ' . $personal->lname,
                $namecourse->coursename,
                $quiz[0],
                $last,
                $after,
                $type,
                $colorText,
                $ret->suporter ?: 'ندارد',
                number_format($sumtoto)
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                $sheet->setCellValue($cell, $val);
                // Apply color to "رنگ" column
                if ($ci === $colorColumnIndex && $colorHex !== 'FFFFFFFF') {
                    try {
                        $sheet->getStyle($cell)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB($colorHex);
                        error_log("Applied color $colorHex to cell $cell");
                    } catch (Exception $e) {
                        error_log("Error applying color to cell $cell: " . $e->getMessage());
                    }
                }
            }

            $rowNum++;
            $id++;
        }

        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="course_color_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $output = ob_get_clean();
        if (empty($output)) {
            error_log("Excel output is empty");
            header('Location: course_color.php?error=excel_empty');
            exit;
        }
        echo $output;
        unset($_SESSION['course_color_excel']);
        exit;
    } catch (Exception $e) {
        error_log("Excel generation error: " . $e->getMessage());
        header('Location: course_color.php?error=excel_error');
        exit;
    }
}

if (isset($_SESSION['special_user_excel']) && isset($_SESSION['special_user_excel']['export'])) {
    error_log("Starting special_user_excel generation");

    $sql = 'SELECT * FROM suport_user WHERE typesupport=1';
    try {
        $query = $conn->prepare($sql);
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_OBJ);
        error_log("Query returned " . count($rows) . " rows for special_user");
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        header('Location: special_user.php?error=db_error');
        exit;
    }

    if (count($rows) === 0) {
        error_log("No data found for special_user");
        header('Location: special_user.php?error=no_data');
        exit;
    }

    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Include all table columns
        $fields = ['ردیف', 'کد ملی', 'نام و نام خانوادگی', 'پایه', 'استان', 'شهر', 'تلفن1', 'تلفن2', 'تلفن3'];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $rowNum = 2;
        $id = 1;

        foreach ($rows as $ret) {
            $personal = retPerson($ret->username);
            if (!$personal) {
                error_log("No personal data for username: {$ret->username}");
                $personal = (object)['fname' => '', 'lname' => '', 'paye' => '', 'state' => '', 'city' => '', 'tel1' => '', 'tel2' => '', 'tel3' => ''];
            }

            $linedata = [
                $id,
                $ret->username ?? '',
                $personal->fname . ' ' . $personal->lname,
                $personal->paye,
                $personal->state,
                $personal->city,
                $personal->tel1,
                $personal->tel2,
                $personal->tel3
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                $sheet->setCellValue($cell, $val);
            }

            $rowNum++;
            $id++;
        }

        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="اعضای ویژه_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $output = ob_get_clean();
        if (empty($output)) {
            error_log("Excel output is empty");
            header('Location: special_user.php?error=excel_empty');
            exit;
        }
        echo $output;
        unset($_SESSION['special_user_excel']);
        exit;
    } catch (Exception $e) {
        error_log("Excel generation error: " . $e->getMessage());
        header('Location: special_user.php?error=excel_error');
        exit;
    }
}

function exportQueueExcel(array $queueData): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $fields = ['#','شماره تماس','مقصد','نام اپراتور','مدت انتظار','مدت مکالمه','وضعیت','تاریخ شروع'];
    foreach ($fields as $i => $f) {
        $sheet->setCellValueByColumnAndRow($i+1, 1, $f);
    }

    $rowNum = 2;
    foreach ($queueData as $i => $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum, $i+1);
        $sheet->setCellValueByColumnAndRow(2, $rowNum, $row['src'] ?? '');
        $sheet->setCellValueByColumnAndRow(3, $rowNum, $row['dst'] ?? '');
        $sheet->setCellValueByColumnAndRow(4, $rowNum, $row['dst_name'] ?? '');
        $sheet->setCellValueByColumnAndRow(5, $rowNum, $row['wait_hhmmss'] ?? '');
        $sheet->setCellValueByColumnAndRow(6, $rowNum, $row['billsec_hhmmss'] ?? '');
        $sheet->setCellValueByColumnAndRow(7, $rowNum, $row['disposition'] ?? '');
        $sheet->setCellValueByColumnAndRow(8, $rowNum, $row['starttime'] ?? '');
        $rowNum++;
    }

    foreach (range(1, count($fields)) as $col) {
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    // 🔥 CRUCIAL: Clean any prior output
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="گزارش لیست دهک ها_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportMissedCallsExcel(array $misscallData): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $fields = ['#','شماره تماس','مقصد','مدت مکالمه','وضعیت','تاریخ شروع'];
    foreach ($fields as $i => $f) {
        $sheet->setCellValueByColumnAndRow($i+1, 1, $f);
    }

    $rowNum = 2;
    foreach ($misscallData as $i => $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum, $i+1);
        $sheet->setCellValueByColumnAndRow(2, $rowNum, $row['src'] ?? '');
        $sheet->setCellValueByColumnAndRow(3, $rowNum, $row['dst'] ?? '');
        $sheet->setCellValueByColumnAndRow(4, $rowNum, $row['billsec_hhmmss'] ?? '');
        $sheet->setCellValueByColumnAndRow(5, $rowNum, $row['disposition'] ?? '');
        $sheet->setCellValueByColumnAndRow(6, $rowNum, $row['starttime'] ?? '');
        $rowNum++;
    }

    foreach (range(1, count($fields)) as $col) {
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Missed_Calls_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}


?>


