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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!Permision('permision_reportstudent')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if(isset($_GET['state'])){
    $stm = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN  dblogin ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=? AND `dbpersonal`.`state`=?  ");
    $stm->execute([1,$_GET['state']]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش دانش آموزان استان " . $_GET['state'] ." _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن دانش آموز', 'تلفن مادر', 'تلفن پدر','ملیت','تاریخ تولد','استان','شهر','نام مدرسه','نوع مدرسه','پایه','رشته','منطقه','جنسیت','تاریخ عضویت'];
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

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2, $row->tel3, $row->melliat,$dateno,$row->state,$row->city,$row->nameschool,$row->typeschool,$row->paye,$row->reshtestudent,$row->area,$row->gender,$datereg,];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['uncourse'])){
    $stm = $conn->prepare("SELECT * FROM dblogin LEFT JOIN dbcourse ON dblogin.username=dbcourse.username RIGHT JOIN dbpersonal ON dbpersonal.codemeli=dblogin.username WHERE dblogin.role=? AND dbcourse.username IS NULL   ");
    $stm->execute([1]);
    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش دانش آموزان بدون دوره " . $_GET['state'] ." _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن دانش آموز', 'تلفن مادر', 'تلفن پدر','ملیت','تاریخ تولد','استان','شهر','نام مدرسه','نوع مدرسه','پایه','رشته','منطقه','جنسیت','تاریخ عضویت'];
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

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2, $row->tel3, $row->melliat,$dateno,$row->state,$row->city,$row->nameschool,$row->typeschool,$row->paye,$row->reshtestudent,$row->area,$row->gender,$datereg,];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['away'])){
    $stm = $conn->prepare("SELECT * FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username  INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE dblogin.role=? AND dbcourse.activityuser=? AND codtbl_course.courseactive=?;   ");
    $stm->execute([1,0,1]);
    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش دانش آموزان منتظر پرداخت " . $_GET['away'] ." _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن دانش آموز', 'تلفن مادر', 'تلفن پدر','ملیت','تاریخ تولد','استان','شهر','نام مدرسه','نوع مدرسه','پایه','رشته','منطقه','جنسیت','تاریخ عضویت','شماره فاکتور','نام دوره','وضعیت فاکتور'];
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
            $factor=queryFactor($row->numfactor);

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2, $row->tel3, $row->melliat,$dateno,$row->state,$row->city,$row->nameschool,$row->typeschool,$row->paye,$row->reshtestudent,$row->area,$row->gender,$datereg,$row->numfactor,$row->coursename,$factor->status];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}
if(isset($_GET['all'])){
    $stm = $conn->prepare("SELECT * FROM `dbpersonal` INNER JOIN `dblogin` ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=?;  ");
    $stm->execute([1]);

    if ($stm) {
        $delimiter = ",";
        $filename = "گزارش همه دانش آموزان " . $_GET['state'] ." _ " . date('Y-m-d h:i:s') . ".csv";

        header('Content-Encoding: UTF-8');
        header('Content-type: application/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $f = fopen('php://output', 'w');
        fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

        $fields = ['کدملی', 'نام', 'نام خانوادگی','نام پدر' ,'تلفن دانش آموز', 'تلفن مادر', 'تلفن پدر','ملیت','تاریخ تولد','استان','شهر','نام مدرسه','نوع مدرسه','پایه','رشته','منطقه','جنسیت','تاریخ عضویت'];
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

            $linedata = [$row->codemeli, $row->fname, $row->lname, $row->fathername, $row->tel1, $row->tel2, $row->tel3, $row->melliat,$dateno,$row->state,$row->city,$row->nameschool,$row->typeschool,$row->paye,$row->reshtestudent,$row->area,$row->gender,$datereg,];
            fputcsv($f, $linedata, $delimiter);
        }

        fpassthru($f);
    }

}


$excelOptions = [];
if (isset($_POST['excel_options'])) {
    $decoded = json_decode($_POST['excel_options'], true);
    if (is_array($decoded)) {
        $excelOptions = $decoded;
    }
} elseif (isset($_SESSION['excelStudentReport']['options'])) {
    $excelOptions = $_SESSION['excelStudentReport']['options'];
}

// دریافت نوع گزارش
$reportType = 'all';
$stateFilter = null;

if (isset($_POST['all']) || isset($_GET['all'])) $reportType = 'all';
elseif (isset($_POST['uncourse']) || isset($_GET['uncourse'])) $reportType = 'uncourse';
elseif (isset($_POST['away']) || isset($_GET['away'])) $reportType = 'away';
elseif (isset($_POST['state'])) {
    $reportType = 'state';
    $stateFilter = $_POST['state'] ?? $_SESSION['excelStudentReport']['state'] ?? null;
}

if (!$reportType) {
    die('نوع گزارش مشخص نشده است.');
}


$sql = "SELECT 
            dblogin.*,
            dbpersonal.*,
            dbfactor.*,
            course_status.*,
            student_status.*,
            dbcourse.*
        FROM dbcourse
        INNER JOIN dblogin ON dblogin.username = dbcourse.username
        INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor
        INNER JOIN course_status ON dbcourse.username = course_status.student_id
        INNER JOIN student_status ON dbcourse.username = student_status.student_id
        INNER JOIN dbpersonal ON dbcourse.username = dbpersonal.codemeli
        WHERE dblogin.role = 1";

$params = [];

if ($reportType === 'uncourse') {
    $sql .= " AND dblogin.username NOT IN (SELECT username FROM dbcourse WHERE username IS NOT NULL)";
} elseif ($reportType === 'away') {
    $sql .= " AND EXISTS (
                SELECT 1 FROM dbcourse
                INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
                WHERE dbcourse.username = dblogin.username AND dbccourse.activityuser = 0 AND codtbl_course.courseactive = 1
            )";
} elseif ($reportType === 'state' && $stateFilter) {
    $sql .= " AND dbpersonal.state = ?";
    $params[] = $stateFilter;
}

$sql .= " ORDER BY dbpersonal.fname, dbpersonal.lname";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_OBJ);

if (empty($students)) {
    die('هیچ دانش‌آموزی یافت نشد.');
}

// === Mapping کامل ستون‌ها ===
$columnMapping = [
    // اطلاعات پایه
    'state' => ['title' => 'استان', 'callback' => fn($s) => $s->state ?? ''],
    'city' => ['title' => 'شهر', 'callback' => fn($s) => $s->city ?? ''],
    'student_phone' => ['title' => 'تلفن دانش آموز', 'callback' => fn($s) => $s->tel1 ?? ''],
    'father_phone' => ['title' => 'تلفن پدر', 'callback' => fn($s) => $s->tel3 ?? ''],
    'mother_phone' => ['title' => 'تلفن مادر', 'callback' => fn($s) => $s->tel2 ?? ''],
    'student_type' => ['title' => 'نوع دانش آموز', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT tv.name FROM dbpersonal_type_vip ptv 
                             INNER JOIN type_vip tv ON ptv.type_vip_id = tv.id 
                             WHERE ptv.dbpersonal_id = ?");
        $q->execute([$s->idpersonal]);
        $types = $q->fetchAll(PDO::FETCH_COLUMN);
        return !empty($types) ? implode('|', $types) : 'تعریف نشده';
    }],
    'shift' => ['title' => 'شیفت دانش آموز', 'callback' => function ($s) {
        return match ($s->shift ?? null) {
            '0' => 'صبح',
            '1' => 'بعدازظهر',
            '2' => 'گردشی',
            default => 'تعریف نشده'
        };
    }],
    'birthdate' => ['title' => 'تاریخ تولد', 'callback' => function ($s) {
        if (!$s->birthdate || $s->birthdate == '0000-00-00') return '';
        $d = explode('-', $s->birthdate);
        return gregorian_to_jalali($d[0], $d[1], $d[2], '/');
    }],
    'school' => ['title' => 'نام مدرسه', 'callback' => fn($s) => $s->nameschool ?? ''],
    'typeschool' => ['title' => 'نوع مدرسه', 'callback' => fn($s) => $s->typeschool ?? ''],
    'area' => ['title' => 'منطقه آموزش و پرورش', 'callback' => fn($s) => $s->area ?? ''],
    'reshte' => ['title' => 'رشته', 'callback' => fn($s) => $s->reshtestudent ?? ''],

    // اطلاعات دوره‌ای (تجمیعی)
    'course_names' => ['title' => 'نام دوره‌ها', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT ct.coursename FROM dbcourse dc 
                             INNER JOIN codtbl_course ct ON dc.codecourse = ct.codecourse 
                             WHERE dc.username = ? AND ct.courseactive = 1");
        $q->execute([$s->codemeli]);
        $names = $q->fetchAll(PDO::FETCH_COLUMN);
        return implode('|', $names);
    }],
    'course_count' => ['title' => 'تعداد دوره ثبت نامی', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT COUNT(*) FROM dbcourse dc 
                             INNER JOIN codtbl_course ct ON dc.codecourse = ct.codecourse 
                             WHERE dc.username = ? AND ct.courseactive = 1");
        $q->execute([$s->codemeli]);
        return $q->fetchColumn();
    }],
    'total_payment' => ['title' => 'جمع پرداختی', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT 
            SUM(factorfee + wallet) AS total_amount
            FROM dbfactor
            WHERE username = ?;");
        $q->execute([$s->codemeli]);
        return number_format($q->fetchColumn()) . ' تومان';
    }],

    // سایر اطلاعات
    'first_login' => ['title' => 'اولین ورود', 'callback' => fn($s) => $s->firstlogin == 1 ? 'بله' : 'خیر'],
    'creator' => ['title' => 'ایجاد کننده', 'callback' => function ($s) use ($conn) {
        if (!$s->useradder) return 'ندارد';
        $p = retPerson($s->useradder);
        return $p ? trim($p->fname . ' ' . $p->lname) : 'نامشخص';
    }],
    'approver' => ['title' => 'تاییدکننده فاکتور', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT useractive FROM dbfactor WHERE username = ? AND status = 'موفق'");
        $q->execute([$s->codemeli]);
        $admins = $q->fetchAll(); 
        
        if (!$admins) return '';
        
        return implode('|', $admins);
    }],
    'supporter' => ['title' => 'پشتیبان', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT DISTINCT suporter FROM dbcourse WHERE username = ? AND suporter IS NOT NULL");
        $q->execute([$s->codemeli]);
        $sups = $q->fetchAll(PDO::FETCH_COLUMN);
        return !empty($sups) ? implode(' | ', $sups) : '';
    }],
    'factor_number' => ['title' => 'شماره فاکتور', 'callback' => function ($s) use ($conn) {
        $q = $conn->prepare("SELECT idfactor FROM dbfactor WHERE username = ? AND status = 1 ORDER BY id DESC");
        $q->execute([$s->codemeli]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        return implode('|', $ids);
    }],
    'datecreate' => ['title' => 'تاریخ ثبت نام', 'callback' => function ($s) {
        if (!$s->datecreated || $s->datecreated == '0000-00-00') return '';
        $d = explode('-', $s->datecreated);
        $jalali = gregorian_to_jalali($d[0], $d[1], $d[2], '/');
        return $jalali . ' ' . ($s->timecreated ?? '');
    }],

    // آزمون کشوری و نظرات
    'exam_type' => ['title' => 'نوع آزمون کشوری', 'callback' => fn($s) => $s->exam_type ?? ''],
    'registered' => ['title' => 'وضعیت ثبت نام آزمون کشوری', 'callback' => fn($s) => $s->registered_exam ?? ''],
    'attended' => ['title' => 'وضعیت شرکت در آزمون کشوری', 'callback' => fn($s) => $s->attended_exam ?? ''],
    'result' => ['title' => 'نتیجه آزمون کشوری', 'callback' => fn($s) => $s->exam_result ?? ''],
    'not_registered_reason' => ['title' => 'توضیحات عدم ثبت نام', 'callback' => fn($s) => $s->not_registered_reason ?? ''],
    'student_comment' => ['title' => 'نظر دانش آموز', 'callback' => fn($s) => $s->student_comment ?? ''],
    'supporter_comment' => ['title' => 'نظر پشتیبان', 'callback' => fn($s) => $s->supporter_comment ?? ''],
    'avg_first_semester' => ['title' => 'معدل ترم اول', 'callback' => fn($s) => $s->avg_first_semester ?? ''],
    'avg_second_semester' => ['title' => 'معدل ترم دوم', 'callback' => fn($s) => $s->avg_second_semester ?? ''],
    'coloring' => ['title' => 'وضعیت عملکرد(رنگ بندی)', 'callback' => fn($s) => $s->coloring ?? ''],
];

// ستون‌های پایه همیشه نمایش داده می‌شوند
$baseColumns = ['کد ملی', 'نام', 'نام خانوادگی', 'پایه'];

// ستون‌های انتخاب شده توسط کاربر
$selectedTitles = [];
foreach ($excelOptions as $opt) {
    if (isset($columnMapping[$opt])) {
        $selectedTitles[] = $columnMapping[$opt]['title'];
    }
}
$allHeaders = array_merge($baseColumns, $selectedTitles);

// === ساخت اکسل ===
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('گزارش دانش‌آموزان');

// هدر
foreach ($allHeaders as $i => $title) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . '1', $title);
}

// استایل هدر
$headerRange = 'A1:' . Coordinate::stringFromColumnIndex(count($allHeaders)) . '1';
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('D9E1F2');

// داده‌ها
$row = 2;
foreach ($students as $s) {
    $data = [
        $s->codemeli,
        $s->fname ?? '',
        $s->lname ?? '',
        $s->paye ?? ''
    ];

    foreach ($excelOptions as $opt) {
        if (isset($columnMapping[$opt])) {
            $data[] = $columnMapping[$opt]['callback']($s);
        }
    }

    foreach ($data as $i => $value) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $row, $value);
    }
    $row++;
}

// راست‌چین کردن برای فارسی
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow())
    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// اتوسایز ستون‌ها
foreach ($sheet->getColumnIterator() as $column) {
    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
}

// نام فایل
$titleMap = [
    'all' => 'همه دانش‌آموزان',
    'uncourse' => 'دانش‌آموزان بدون دوره',
    'away' => 'منتظر پرداخت فاکتور',
    'state' => 'دانش‌آموزان استان ' . ($stateFilter ?? '')
];
$filename = $titleMap[$reportType] . "_" . jdate('Y-m-d_H-i') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;