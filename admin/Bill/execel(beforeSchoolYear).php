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
use PhpOffice\PhpSpreadsheet\Cell\DataType;


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

$SIMOTEL_API_URL = 'http://93.114.106.208:9880//api/v4/reports/quick/search';
$SIMOTEL_API_HEADERS = [
    'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC', //dnZuHlsNje67iVPYqBoTjrGzu8LlxpKDlwcxNe03oM8yOPg0q3
    'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
    'Content-Type: application/json'
];

set_time_limit(600);
ini_set('max_execution_time', '600');

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

    // Prepare the count query once for better performance
    $activeCoursesQuery = "SELECT COUNT(dbcourse.idcourse) as active_count 
                           FROM dbcourse 
                           INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse 
                           WHERE dbcourse.username = ? 
                           AND dbcourse.activityuser = 1 
                           AND codtbl_course.activecourse = 1";
    $countStm = $conn->prepare($activeCoursesQuery);

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

            // ADDED: 'تعداد دوره های فعال' to the end of headers
            $fields = ['شماره فاکتور', 'کدملی', 'نام', 'نام خانوادگی', 'پایه', 'استان', 'شهر', 'منطقه', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'مبلغ فاکتور', 'وضعیت', 'تاریخ', 'دوره های ثبت نامی', 'ایجاد کننده', 'تعداد دوره های فعال'];
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
                switch ($row->paye) {
                    case '5': $paye = 'پنجم ابتدایی'; break;
                    case '6': $paye = 'ششم ابتدایی'; break;
                    case '7': $paye = 'هفتم متوسطه اول'; break;
                    case '8': $paye = 'هشتم متوسطه اول'; break;
                    case '9': $paye = 'نهم متوسطه اول'; break;
                    case '10': $paye = 'دهم ' . $row->reshtestudent; break;
                    case '11': $paye = 'یازدهم  ' . $row->reshtestudent; break;
                    case '12': $paye = 'دوازدهم  ' . $row->reshtestudent; break;
                }

                $doreha = retfactorcourse($row->username, $row->idfactor);
                $df = "";
                if(is_array($doreha)){
                    for ($rt = 0; $rt < sizeof($doreha); $rt++) {
                        $hamsepar = returnCourse($doreha[$rt]);
                        $doreha[$rt] = $hamsepar->coursename;
                    }
                    $df = implode(' + ', $doreha);
                }

                // NEW LOGIC: Fetch the active courses count for this user
                $countStm->execute([$row->username]);
                $countResult = $countStm->fetch(PDO::FETCH_OBJ);
                $activeCoursesCount = $countResult ? $countResult->active_count : 0;

                // ADDED: $activeCoursesCount to the end of linedata
                $linedata = [$row->idfactor, $row->username, $row->fname, $row->lname, $paye, $row->state, $row->city, $row->area, $row->tel1, $row->tel2, $row->tel3, $factorfee, $status, $dateno, $df, $creator, $activeCoursesCount];
                fputcsv($f, $linedata, $delimiter);
            }

            fpassthru($f);
        }
    } else { // دوره خاص (Specific Course)
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

            // ADDED: 'تعداد دوره های فعال' to the end of headers
            $fields = ['شماره فاکتور', 'کدملی', 'نام', 'نام خانوادگی', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'مبلغ فاکتور', 'وضعیت', 'تاریخ', 'ایجاد کننده', 'تعداد دوره های فعال'];
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

                // NEW LOGIC: Fetch the active courses count for this user
                $countStm->execute([$row->username]);
                $countResult = $countStm->fetch(PDO::FETCH_OBJ);
                $activeCoursesCount = $countResult ? $countResult->active_count : 0;

                // ADDED: $activeCoursesCount to the end of linedata
                $linedata = [$row->idfactor, $row->username, $row->fname, $row->lname, $row->state, $row->city, $row->tel1, $row->tel2, $row->tel3, $factorfee, $status, $dateno, $creator, $activeCoursesCount];
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
    $courses = $_SESSION['excelCoursereport']['codecourse'];
    $status = $_SESSION['excelCoursereport']['status'];
    $excelOptions = $_SESSION['excelCoursereport']['excel_options'] ?? [];
    $needTalkTime = in_array('talk_time', $excelOptions, true);
    $selectedEvents = $_SESSION['excelCoursereport']['active_events'] ?? [];
    $activeEventColumns = [];   // eventId => eventTitle
    if (!empty($selectedEvents)) {
        $evPlaceholders = implode(',', array_fill(0, count($selectedEvents), '?'));
        $eventSql = "SELECT id, title FROM calendar_events WHERE id IN ($evPlaceholders) ORDER BY title ASC";
        $eventStmt = $conn->prepare($eventSql);
        $eventStmt->execute($selectedEvents);
        $eventTitles = $eventStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($eventTitles as $eventId => $title) {
            $activeEventColumns[$eventId] = $title;
        }
    }
    switch ($status) {
        case 'active':
            $studentsActiveStatus = 1;
            break;
        case 'inactive':
            $studentsActiveStatus = 0;
            break;
        case 'all':
            $studentsActiveStatus = 2;
            break;
        case 'suspended':
            $studentsActiveStatus = 3;
            break;
    }

    $isAllActive = in_array('all_active', $courses);
    $isAllInactive = in_array('all_inactive', $courses);

    if ($isAllActive || $isAllInactive) {
        $courseQuery = 'SELECT codecourse FROM codtbl_course WHERE activecourse = ?';
        $courseStmt = $conn->prepare($courseQuery);
        $courseStmt->execute([$isAllActive ? 1 : 0]);
        $courses = $courseStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $placeholders = !empty($courses) ? implode(',', array_fill(0, count($courses), '?')) : '0';
    //تبدیل تاریخ
    $startdate = $start;
    $startdate = explode('/', $startdate);
    $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2]);
    $startdate = implode('-', $startdate);
    //
    $enddate = $end;
    $enddate = explode('/', $enddate);
    $enddate = jalali_to_gregorian($enddate[0], $enddate[1], $enddate[2]);
    $enddate = implode('-', $enddate);

    if ($type == 1 or $type == 2) {
//        $sql = "SELECT dbcourse.*, codtbl_course.coursename, dbfactor.*, course_status.*, student_status.*,dbpersonal.*
//                FROM dbcourse
//                INNER JOIN dbpersonal ON dbpersonal.codemeli = dbcourse.username
//                INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor
//                INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
//                LEFT JOIN course_status ON dbcourse.username = course_status.student_id
//                LEFT JOIN student_status ON dbcourse.username = student_status.student_id
//                WHERE dbfactor.dateresponse BETWEEN ? AND ?";
//        $sql = "
//SELECT
//    dbcourse.*,
//    codtbl_course.coursename,
//    dbfactor.*,
//    dbpersonal.*,
//
//    -- aggregation برای course_status
//    cs.exam_type,
//    cs.registered,
//    cs.attended,
//    cs.result_exam,
//    cs.not_registered_reason,
//
//    -- aggregation برای student_status
//    ss.student_comment,
//    ss.supporter_comment,
//    ss.avg_first_semester,
//    ss.avg_second_semester
//
//FROM dbcourse
//
//INNER JOIN dbpersonal
//    ON dbpersonal.codemeli = dbcourse.username
//
//INNER JOIN dbfactor
//    ON dbcourse.numfactor = dbfactor.idfactor
//
//INNER JOIN codtbl_course
//    ON codtbl_course.codecourse = dbcourse.codecourse
//
//-- 🔹 جایگزین LEFT JOIN ساده course_status با aggregation
//LEFT JOIN (
//    SELECT
//        student_id,
//        codecourse,
//        GROUP_CONCAT(exam_type ORDER BY id SEPARATOR ' | ') AS exam_type,
//        GROUP_CONCAT(registered ORDER BY id SEPARATOR ' | ') AS registered,
//        GROUP_CONCAT(attended ORDER BY id SEPARATOR ' | ') AS attended,
//        GROUP_CONCAT(result ORDER BY id SEPARATOR ' | ') AS result_exam,
//        GROUP_CONCAT(not_registered_reason ORDER BY id SEPARATOR ' | ') AS not_registered_reason
//    FROM course_status
//    GROUP BY student_id, codecourse
//) cs ON dbcourse.username = cs.student_id AND dbcourse.codecourse = cs.codecourse
//
//-- 🔹 جایگزین LEFT JOIN ساده student_status با aggregation
//LEFT JOIN (
//    SELECT
//        student_id,
//        codecourse,
//        GROUP_CONCAT(student_comment ORDER BY id SEPARATOR ' | ') AS student_comment,
//        GROUP_CONCAT(supporter_comment ORDER BY id SEPARATOR ' | ') AS supporter_comment,
//        GROUP_CONCAT(avg_first_semester ORDER BY id SEPARATOR ' | ') AS avg_first_semester,
//        GROUP_CONCAT(avg_second_semester ORDER BY id SEPARATOR ' | ') AS avg_second_semester
//    FROM student_status
//    GROUP BY student_id, codecourse
//) ss ON dbcourse.username = ss.student_id AND dbcourse.codecourse = ss.codecourse
//
//WHERE dbfactor.dateresponse BETWEEN ? AND ?
//
//GROUP BY dbcourse.numfactor, dbcourse.codecourse
//ORDER BY dbfactor.username DESC
//";
//
//
//        $params = [$startdate, $enddate];
//
//
//        if (!empty($courses)) {
//            $sql .= " AND dbcourse.codecourse IN ($placeholders)";
//            $params = array_merge($params, $courses);
//        } else {
//            $sql .= " AND 1=0";
//        }
//
//        if ($studentsActiveStatus != 2 and $studentsActiveStatus != 3) {
//            $sql .= " AND dbcourse.activityuser = ?";
//            $params[] = $studentsActiveStatus;
//        } elseif ($studentsActiveStatus == 3) {
//            $sql .= " AND dbpersonal.suspension_mode = 1";
//        }
//
//        $sql .= " GROUP BY dbcourse.numfactor, dbcourse.codecourse ORDER BY dbfactor.username DESC";
//
//        $stm = $conn->prepare($sql);
//        $stm->execute($params);

        $sql = "
SELECT 
    dbcourse.*,
    codtbl_course.coursename,
    dbfactor.*,
    dbpersonal.*,
    cs.exam_type,
    cs.registered,
    cs.attended,
    cs.result_exam,
    cs.not_registered_reason,
    ss.student_comment,
    ss.supporter_comment,
    ss.avg_first_semester,
    ss.avg_second_semester
FROM dbcourse
INNER JOIN dbpersonal 
    ON dbpersonal.codemeli = dbcourse.username
INNER JOIN dbfactor 
    ON dbcourse.numfactor = dbfactor.idfactor
INNER JOIN codtbl_course 
    ON codtbl_course.codecourse = dbcourse.codecourse
LEFT JOIN (
    SELECT 
        student_id,
        GROUP_CONCAT(exam_type ORDER BY id SEPARATOR ' | ') AS exam_type,
        GROUP_CONCAT(registered ORDER BY id SEPARATOR ' | ') AS registered,
        GROUP_CONCAT(attended ORDER BY id SEPARATOR ' | ') AS attended,
        GROUP_CONCAT(result ORDER BY id SEPARATOR ' | ') AS result_exam,
        GROUP_CONCAT(not_registered_reason ORDER BY id SEPARATOR ' | ') AS not_registered_reason
    FROM course_status
    GROUP BY student_id
) cs ON dbcourse.username = cs.student_id

LEFT JOIN (
    SELECT 
        student_id,
        GROUP_CONCAT(student_comment ORDER BY id SEPARATOR ' | ') AS student_comment,
        GROUP_CONCAT(supporter_comment ORDER BY id SEPARATOR ' | ') AS supporter_comment,
        GROUP_CONCAT(avg_first_semester ORDER BY id SEPARATOR ' | ') AS avg_first_semester,
        GROUP_CONCAT(avg_second_semester ORDER BY id SEPARATOR ' | ') AS avg_second_semester
    FROM student_status
    GROUP BY student_id
) ss ON dbcourse.username = ss.student_id
WHERE dbfactor.dateresponse BETWEEN ? AND ?
";

// آماده کردن پارامترها
        $params = [$startdate, $enddate];

// اضافه کردن شرط دوره‌ها
        if (!empty($courses)) {
            $placeholders = implode(',', array_fill(0, count($courses), '?'));
            $sql .= " AND dbcourse.codecourse IN ($placeholders)";
            $params = array_merge($params, $courses);
        } else {
            $sql .= " AND 1=0";
        }

// اضافه کردن وضعیت فعال دانش آموز
        if ($studentsActiveStatus != 2 and $studentsActiveStatus != 3) {
            $sql .= " AND dbcourse.activityuser = ?";
            $params[] = $studentsActiveStatus;
        } elseif ($studentsActiveStatus == 3) {
            $sql .= " AND dbpersonal.suspension_mode = 1";
        }

// فقط یک بار GROUP BY + ORDER BY
        $sql .= " GROUP BY dbcourse.numfactor, dbcourse.codecourse 
          ORDER BY dbfactor.username DESC";
        $stm = $conn->prepare($sql);
        $stm->execute($params);


        if ($stm) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('گزارش نفر دوره');

            // نقشه رنگ‌ها
            $hexMap = [
                0 => 'FFFF0000', // قرمز
                1 => 'FFFFA500', // نارنجی
                2 => 'FFFFFF00', // زرد
                3 => 'FF00FF00'  // سبز
            ];

            // تعریف mapping برای ستون‌ها
            $columnMapping = [
                'state' => ['title' => 'استان', 'callback' => function ($user, $row) {
                    return $user->state ?? '';
                }],
                'city' => ['title' => 'شهر', 'callback' => function ($user, $row) {
                    return $user->city ?? '';
                }],
                'gender' => ['title' => 'جنسیت', 'callback' => function ($user, $row) {
                    return $user->gender ?? '';
                }],
                'student_phone' => ['title' => 'تلفن دانش آموز', 'callback' => function ($user, $row) {
                    return $user->tel1 ?? '';
                }],
                'father_phone' => ['title' => 'تلفن پدر', 'callback' => function ($user, $row) {
                    return $user->tel3 ?? '';
                }],
                'mother_phone' => ['title' => 'تلفن مادر', 'callback' => function ($user, $row) {
                    return $user->tel2 ?? '';
                }],
                'sms_count' => [
                    'title' => 'تعداد اس‌ام‌اس',
                    'callback' => function ($user, $row) use ($conn, $startdate, $enddate) {
                        $codemeli = '';
                        if (is_object($user) && !empty($user->codemeli)) {
                            $codemeli = $user->codemeli;
                        } elseif (!empty($row->username)) {
                            $codemeli = $row->username;
                        }
                        return $codemeli ? getSmsCountForStudent($conn, $codemeli, $startdate, $enddate) : 0;
                    }
                ],
                'talk_time' => [
                    'title' => 'مدت زمان مکالمه',
                    'callback' => function ($user, $row) use ($startdate, $enddate, $SIMOTEL_API_URL, $SIMOTEL_API_HEADERS) {

                        $fromDT = $startdate . ' 00:00:00';
                        $toDT   = $enddate   . ' 23:59:59';

                        $nums = [
                            norm10($user->tel1 ?? null),
                            norm10($user->tel2 ?? null),
                            norm10($user->tel3 ?? null),
                            norm10($user->tel_home ?? null),
                        ];
                        $nums = array_values(array_unique(array_filter($nums)));

                        $sec = getTalkTimeForStudent($nums, $fromDT, $toDT, $SIMOTEL_API_URL, $SIMOTEL_API_HEADERS);

                        return secToHMS((int)$sec);
                    }
                ],

                'student_status' => ['title' => 'وضعیت دانش آموز', 'callback' => function ($user, $row) {
                    if ($row->suspension_mode == 1) {
                        return ' تعلیق';
                    } elseif ($row->activityuser == 1) {
                        return 'فعال';
                    } else {
                        return 'غیرفعال';
                    }
                }],
                'suspension_reason' => ['title' => 'علت تعلیق', 'callback' => function ($user, $row) {
                    return $user->suspension_reason;
                }],
                'student_type' => ['title' => 'نوع دانش آموز', 'callback' => function ($user, $row) use ($conn) {
                    $query = $conn->prepare("
                        SELECT tv.name
                        FROM dbpersonal_type_vip ptv
                        INNER JOIN type_vip tv ON ptv.type_vip_id = tv.id
                        WHERE ptv.dbpersonal_id = ?
                    ");
                    $query->execute([$user->idpersonal]);
                    $types = $query->fetchAll(PDO::FETCH_COLUMN);

                    return !empty($types) ? implode(' | ', $types) : 'تعریف نشده';
                }],
                'shift' => ['title' => 'شیفت دانش آموز', 'callback' => function ($user, $row) {
                    if (!isset($user->shift)) return 'تعریف نشده';
                    switch ($user->shift) {
                        case '0':
                            return 'صبح';
                        case '1':
                            return 'بعدازظهر';
                        case '2':
                            return 'گردشی';
                        default:
                            return 'تعریف نشده';
                    }
                }],
                'first_login' => ['title' => 'اولین ورود', 'callback' => function ($user, $row) {
                    $first = retdblogin($user->codemeli);
                    return $first->firstlogin == 1 ? 'بله' : 'خیر';
                }],
                'birthdate' => ['title' => 'تاریخ تولد دانش آموز', 'callback' => function ($user, $row) {
                    if (empty($user->birthdate)) return '';

                    // فرض بر اینه که تاریخ میلادی به صورت YYYY-MM-DD ذخیره شده
                    list($gy, $gm, $gd) = explode('-', $user->birthdate);
                    $jalali = gregorian_to_jalali($gy, $gm, $gd, '/');

                    return $jalali;
                }],
                'school' => ['title' => 'نام مدرسه', 'callback' => function ($user, $row) {
                    return $user->nameschool ?? '';
                }],
                'typeschool' => ['title' => 'نوع مدرسه', 'callback' => function ($user, $row) {
                    return $user->typeschool ?? '';
                }],
                'area' => ['title' => 'منطقه آموزش و پرورش', 'callback' => function ($user, $row) {
                    return $user->area ?? '';
                }],
                'reshte' => ['title' => 'رشته دانش آموز', 'callback' => function ($user, $row) {
                    return $user->reshtestudent ?? '';
                }],
                'datecreate' => ['title' => 'تاریخ ثبت نام', 'callback' => function ($user, $row) {
                    if (empty($user->datecreated)) return '';

                    // فرض بر اینه که تاریخ میلادی به صورت YYYY-MM-DD ذخیره شده
                    list($gy, $gm, $gd) = explode('-', $user->datecreated);
                    $jalali = gregorian_to_jalali($gy, $gm, $gd, '/');

                    return $jalali . ' ' . $user->timecreated ?? '';
                }],
                'course_name' => ['title' => 'نام دوره', 'callback' => function ($user, $row) {
                    return $row->coursename ?? '';
                }],
                'creator' => ['title' => 'ایجاد کننده', 'callback' => function ($user, $row) {
                    return $row->factoradder ?? 'خودش';
                }],
                'supporter' => ['title' => 'پشتیبان', 'callback' => function ($user, $row) {
                    return $row->suporter ?? 'ندارد';
                }],
                'factor_number' => ['title' => 'شماره فاکتور', 'callback' => function ($user, $row) {
                    return $row->idfactor ?? '';
                }],
                'exam_type' => ['title' => 'نوع آزمون کشوری', 'callback' => function ($user, $row) {
                    return $row->exam_type ?? '';
                }],
                'registered' => ['title' => 'وضعیت ثبت نام آزمون کشوری', 'callback' => function ($user, $row) {
                    return $row->registered ?? '';
                }],
                'attended' => ['title' => 'وضعیت شرکت در آزمون کشوری', 'callback' => function ($user, $row) {
                    return $row->attended ?? '';
                }],
                'result' => ['title' => 'نتیجه آزمون کشوری', 'callback' => function ($user, $row) {
                    return $row->result_exam ?? '';
                }],
                'not_registered_reason' => ['title' => 'توضیحات چرایی عدم ثبت نام در آزمون کشوری', 'callback' => function ($user, $row) {
                    return $row->not_registered_reason ?? '';
                }],
                'student_comment' => ['title' => 'نظر دانش آموز', 'callback' => function ($user, $row) {
                    return $row->student_comment ?? '';
                }],
                'supporter_comment' => ['title' => 'نظر پشتیبان', 'callback' => function ($user, $row) {
                    return $row->supporter_comment ?? '';
                }],
                'avg_first_semester' => ['title' => 'معدل ترم اول', 'callback' => function ($user, $row) {
                    return $row->avg_first_semester ?? '';
                }],
                'avg_second_semester' => ['title' => 'معدل ترم دوم', 'callback' => function ($user, $row) {
                    return $row->avg_second_semester ?? '';
                }],
                'last_balance_single_test' => ['title' => 'آخرین تراز آزمون تک درس', 'callback' => function ($user, $row) use ($conn) {
                    $query = $conn->prepare("SELECT taraz FROM result_quiz INNER JOIN dbquiz ON result_quiz.quizid = dbquiz.idquiz  WHERE result_quiz.username = ? AND dbquiz.codecourse = ? ORDER BY result_quiz.idresult DESC LIMIT 1");
                    $query->execute([$user->codemeli, $row->codecourse]);
                    $lastBalance = $query->fetchColumn();
                    return empty($lastBalance) ? '---' : number_format($lastBalance, 0);
                }],
                'avg_balance_single_test' => ['title' => 'میانگین تراز های آزمون تک درس', 'callback' => function ($user, $row) use ($conn) {
                    $query = $conn->prepare("SELECT AVG(taraz) FROM result_quiz INNER JOIN dbquiz ON result_quiz.quizid = dbquiz.idquiz WHERE result_quiz.username = ? AND dbquiz.codecourse = ?");
                    $query->execute([$user->codemeli, $row->codecourse]);
                    $avgBalance = $query->fetchColumn();
                    return empty($avgBalance) ? '---' : number_format($avgBalance, 0);
                }],
                'last_balance_multi_test' => ['title' => 'آخرین تراز آزمون جامع', 'callback' => function ($user, $row) use ($conn) {
                    $query = $conn->prepare("SELECT taraz FROM result_quizzes_jame INNER JOIN dbquiz ON result_quizzes_jame.quiz_id = dbquiz.idquiz WHERE result_quizzes_jame.username = ? AND dbquiz.codecourse = ? ORDER BY id DESC LIMIT 1");
                    $query->execute([$user->codemeli, $row->codecourse]);
                    $lastBalance = $query->fetchColumn();
                    return empty($lastBalance) ? '---' : number_format($lastBalance, 0);
                }],
                'avg_balance_multi_test' => ['title' => 'میانگین تراز های آزمون جامع', 'callback' => function ($user, $row) use ($conn) {
                    $query = $conn->prepare("SELECT AVG(taraz) FROM result_quizzes_jame INNER JOIN dbquiz ON result_quizzes_jame.quiz_id = dbquiz.idquiz WHERE result_quizzes_jame.username = ? AND dbquiz.codecourse = ?");
                    $query->execute([$user->codemeli, $row->codecourse]);
                    $avgBalance = $query->fetchColumn();
                    return empty($avgBalance) ? '---' : number_format($avgBalance, 0);
                }],
                'about' => ['title' => 'شرح حال', 'callback' => function ($user, $row) {
                    return $user->aboutsuporter ?? '';
                }]
            ];

            // ستون‌های ثابت اولیه
            $baseColumns = [
                'کدملی',
                'نام',
                'نام خانوادگی',
                'پایه'
            ];

            $selectedColumns = [];
            if (isset($_SESSION['excelCoursereport']['excel_options'])) {
                $excelOptions = $_SESSION['excelCoursereport']['excel_options'];
                foreach ($excelOptions as $option) {
                    if (isset($columnMapping[$option])) {
                        $selectedColumns[$option] = $columnMapping[$option]['title'];
                    }
                }
            }

            $allColumns = array_merge($baseColumns, array_values($selectedColumns));
            if (!empty($activeEventColumns)) {
                foreach ($activeEventColumns as $eventId => $title) {
                    $allColumns[] = $title . ' - گزینه';
                    $allColumns[] = $title . ' - دلیل';
                }
            }
            foreach ($allColumns as $col => $field) {
                $cell = Coordinate::stringFromColumnIndex($col + 1) . '1';
                $sheet->setCellValue($cell, $field);
            }

            $highestColumn = Coordinate::stringFromColumnIndex(count($allColumns));
            $headerRange = 'A1:' . $highestColumn . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');


            $allRows = $stm->fetchAll(PDO::FETCH_OBJ);
            $studentCodes = array_unique(array_column($allRows, 'username'));

            $eventAnswers = [];
            if (!empty($activeEventColumns)) {
                $eventAnswers = getEventAnswers($conn, $studentCodes, array_keys($activeEventColumns));
            }

            $rowNum = 2;
            foreach ($allRows as $row) {
                $user = retPerson($row->username);
                if (strlen($user->codemeli) == 11) {
                    continue;
                }

                // داده‌های پایه
                $lineData = [
                    $row->username,
                    $user->fname,
                    $user->lname,
                    $user->paye
                ];

                // داده‌های انتخابی (modal options)
                if (isset($_SESSION['excelCoursereport']['excel_options'])) {
                    $excelOptions = $_SESSION['excelCoursereport']['excel_options'];
                    foreach ($excelOptions as $option) {
                        if (isset($columnMapping[$option])) {
                            $lineData[] = $columnMapping[$option]['callback']($user, $row);
                        }
                    }
                }

                // Append event columns (automatically if any active events selected)
                if (!empty($activeEventColumns)) {
                    foreach ($activeEventColumns as $eventId => $title) {
                        $ans = $eventAnswers[$row->username][$eventId] ?? ['option' => '', 'reason' => ''];
                        $lineData[] = $ans['option'];
                        $lineData[] = $ans['reason'];
                    }
                }

                // نوشتن داده در اکسل
                foreach ($lineData as $col => $cellValue) {
                    $cell = Coordinate::stringFromColumnIndex($col + 1) . $rowNum;
                    $sheet->setCellValue($cell, $cellValue);
                }

                // اعمال رنگ‌بندی اگر گزینه coloring انتخاب شده باشد
                if (in_array('coloring', $_SESSION['excelCoursereport']['excel_options'] ?? [])) {
                    $colorIndex = isset($row->color) ? (int)$row->color : null;
                    if ($colorIndex !== null && isset($hexMap[$colorIndex])) {
                        $sheet->getStyle("B{$rowNum}:C{$rowNum}")
                            ->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB($hexMap[$colorIndex]);
                    }
                }

                $rowNum++;
            }

            //            // autosize ستون‌ها
            //            $highestColumn = $sheet->getHighestColumn();
            //            foreach (range('A', $highestColumn) as $col) {
            //                $sheet->getColumnDimension($col)->setAutoSize(true);
            //            }

            // AUTOSIZE REAL
            \PhpOffice\PhpSpreadsheet\Shared\Font::setAutoSizeMethod(
                \PhpOffice\PhpSpreadsheet\Shared\Font::AUTOSIZE_METHOD_EXACT
            );

            foreach ($sheet->getColumnIterator() as $column) {
                $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }


            // ارسال فایل
            $filename = "گزارش نفر دوره _" . date('Y-m-d_H-i-s') . ".xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
    }

    if ($type == 3 or $type == 4) {
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
        $first = array();
        $suporter = array();
        $useractive = array();
        $viptype = array();
        $shift = array();
        $fee = array();
        $birthdate = array();
        $school = array();
        $reshte = array();
        $factor_number = array();
        $typeschool = array();
        $area = array();
        $datecreate = array();
        $timecreate = array();
        $exam_type = array();
        $registered = array();
        $attended = array();
        $result_exam = array();
        $not_registered_reason = array();
        $student_comment = array();
        $supporter_comment = array();
        $avg_first_semester = array();
        $avg_second_semester = array();
        $suspension_reason = array();
        $gender = array();
        $about = array();
        $sms_count = [];
        $talk_time = [];


        // کوئری دیتا
        $sql = "SELECT 
    dbcourse.*, 
    dbfactor.*, 
    dbpersonal.*, 
    type_vip.name AS vip_type,

    cs.exam_type,
    cs.registered,
    cs.attended,
    cs.result_exam,
    cs.not_registered_reason,

    ss.student_comment,
    ss.supporter_comment,
    ss.avg_first_semester,
    ss.avg_second_semester

FROM dbcourse

INNER JOIN dbfactor 
    ON dbcourse.numfactor = dbfactor.idfactor    

INNER JOIN dbpersonal 
    ON dbfactor.username = dbpersonal.codemeli 

LEFT JOIN dbpersonal_type_vip 
    ON dbpersonal.idpersonal = dbpersonal_type_vip.dbpersonal_id    

LEFT JOIN type_vip 
    ON dbpersonal_type_vip.type_vip_id = type_vip.id

-- 🔹 اول course_status رو aggregate می‌کنیم
LEFT JOIN (
    SELECT 
        student_id,
        GROUP_CONCAT(exam_type ORDER BY id SEPARATOR '\r\n') AS exam_type,
        GROUP_CONCAT(registered ORDER BY id SEPARATOR '\r\n') AS registered,
        GROUP_CONCAT(attended ORDER BY id SEPARATOR '\r\n') AS attended,
        GROUP_CONCAT(result ORDER BY id SEPARATOR '\r\n') AS result_exam,
        GROUP_CONCAT(not_registered_reason ORDER BY id SEPARATOR '\r\n') AS not_registered_reason
    FROM course_status
    GROUP BY student_id
) cs ON dbcourse.username = cs.student_id

-- 🔹 بعد student_status رو جدا aggregate می‌کنیم
LEFT JOIN (
    SELECT 
        student_id,
        GROUP_CONCAT(student_comment ORDER BY id SEPARATOR '\r\n') AS student_comment,
        GROUP_CONCAT(supporter_comment ORDER BY id SEPARATOR '\r\n') AS supporter_comment,
        GROUP_CONCAT(avg_first_semester ORDER BY id SEPARATOR '\r\n') AS avg_first_semester,
        GROUP_CONCAT(avg_second_semester ORDER BY id SEPARATOR '\r\n') AS avg_second_semester
    FROM student_status
    GROUP BY student_id
) ss ON dbcourse.username = ss.student_id

INNER JOIN codtbl_course 
    ON codtbl_course.codecourse = dbcourse.codecourse

WHERE dbfactor.dateresponse BETWEEN ? AND ?
";

        $params = [$startdate, $enddate];

        if (!empty($courses)) {
            $sql .= " AND dbcourse.codecourse IN ($placeholders)";
            $params = array_merge($params, $courses);
        } else {
            $sql .= " AND 1=0";
        }

        if ($studentsActiveStatus != 2 && $studentsActiveStatus != 3) {
            $sql .= " AND dbcourse.activityuser = ?";
            $params[] = $studentsActiveStatus;
        } elseif ($studentsActiveStatus == 3) {
            $sql .= " AND dbpersonal.suspension_mode = 1";
        }

        $sql .= " GROUP BY dbcourse.username ORDER BY dbfactor.username DESC";
        $query = $conn->prepare($sql);
        $query->execute($params);

        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $res) {
                if (!in_array($res->username, $codemelli)) {
                    $student = retPerson($res->username);
                    if (strlen($student->codemeli) == 11) {
                        continue;
                    }

                    // این کار جهت جلوگیری از تکرار دیتا انجام می شود
                    array_push($codemelli, $res->username);
                    $firstlogin = retdblogin($res->username);

                    array_push($fname, $student->fname);
                    array_push($lname, $student->lname);
                    array_push($tel1, $student->tel1);
                    array_push($tel2, $student->tel2);
                    array_push($tel3, $student->tel3);
                    array_push($state, $student->state);
                    array_push($gender, $student->gender);
                    array_push($city, $student->city);
                    array_push($paye, $student->paye);
                    array_push($first, $firstlogin->firstlogin);
                    array_push($birthdate, $student->birthdate ?? '');
                    array_push($school, $student->nameschool ?? '');
                    array_push($reshte, $student->reshtestudent ?? '');
                    array_push($viptype, $student->idpersonal ?? '');
                    array_push($typeschool, $student->typeschool ?? '');
                    array_push($area, $student->area ?? '');
                    array_push($datecreate, $student->datecreated ?? '');
                    array_push($timecreate, $student->timecreated ?? '');
                    array_push($exam_type, $res->exam_type ?? '');
                    array_push($registered, $res->registered ?? '');
                    array_push($attended, $res->attended ?? '');
                    array_push($result_exam, $res->result_exam ?? '');
                    array_push($not_registered_reason, $res->not_registered_reason ?? '');
                    array_push($student_comment, $res->student_comment ?? '');
                    array_push($supporter_comment, $res->supporter_comment ?? '');
                    array_push($avg_first_semester, $res->avg_first_semester ?? '');
                    array_push($avg_second_semester, $res->avg_second_semester ?? '');
                    array_push($suspension_reason, $student->suspension_reason ?? '');
                    array_push($about, $student->aboutsuporter ?? '');
                    $codemeli = $res->username;
                    array_push($sms_count, getSmsCountForStudent($conn, $codemeli, $startdate, $enddate));

                    if ($needTalkTime) {
                        $fromDT = $startdate . ' 00:00:00';
                        $toDT   = $enddate   . ' 23:59:59';

                        $nums = [
                            norm10($student->tel1 ?? null),
                            norm10($student->tel2 ?? null),
                            norm10($student->tel3 ?? null),
                            norm10($student->tel_home ?? null),
                        ];
                        $nums = array_values(array_unique(array_filter($nums)));

                        $sec = getTalkTimeForStudent($nums, $fromDT, $toDT, $SIMOTEL_API_URL, $SIMOTEL_API_HEADERS);
                        $talk_time[] = secToHMS((int)$sec);
                    } else {
                        // keep indexes aligned if later you reference talk_time[$index]
                        $talk_time[] = '00:00:00';
                    }



                    // ✅ Add نوع دانش‌آموز (VIP type)
                    // $viptype[] = $res->vip_type ?? 'تعریف نشده';

                    // ✅ Add شیفت دانش‌آموز
                    switch ($res->shift) {
                        case 0:
                            $shiftName = 'صبح';
                            break;
                        case 1:
                            $shiftName = 'بعدازظهر';
                            break;
                        case 2:
                            $shiftName = 'گردشی';
                            break;
                        default:
                            $shiftName = 'تعریف نشده';
                    }
                    $shift[] = $shiftName;

                    $allfactornum = retallcoursefactor($res->username, $startdate, $enddate);
                    array_push($factor_number, $allfactornum);

                    // تعیین کاربر ایجاد کننده
                    if ($res->factoradder != NULL) {
                        array_push($poshtiban, $res->factoradder);
                    } else {
                        array_push($poshtiban, 'خودش');
                    }

                    // تعیین کاربر تایید کننده فاکتور
                    if ($res->useractive != NULL) {
                        array_push($useractive, $res->useractive);
                    } else {
                        array_push($useractive, 'خودش');
                    }

                    // گرفتن همه دوره ها
                    if ($type == 3) {
                        $doreha = returnAllCoursesWithStatus($res->username, $startdate, $enddate, 1, $studentsActiveStatus);
                        array_push($course, $doreha);
                    } else {
                        $doreha = returnAllCoursesWithStatus($res->username, $startdate, $enddate, 0, $studentsActiveStatus);
                        array_push($course, $doreha);
                    }

                    // گرفتن همه پشتیبان ها
                    if ($type == 3) {
                        $support = returnAllSupportersWithStatus($res->username, $startdate, $enddate, 1, $studentsActiveStatus);
                        array_push($suporter, $support);
                    } else {
                        $support = returnAllSupportersWithStatus($res->username, $startdate, $enddate, 0, $studentsActiveStatus);
                        array_push($suporter, $support);
                    }

                    $sumfee = sumfactoruser($res->username, $startdate, $enddate);
                    array_push($fee, $sumfee);
                }
            }
        }
        $eventAnswers = [];
        if (!empty($activeEventColumns)) {
            $eventAnswers = getEventAnswers($conn, $codemelli, array_keys($activeEventColumns));
        }
        if (!empty($codemelli)) {
            // ایجاد شیء Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('گزارش تجمیعی نفر دوره');

            // جمع‌آوری داده‌های رنگ
            $studentIds = array_unique($codemelli);
            $colorMapRows = [];
            if (!empty($studentIds)) {
                $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
                $colorSql = "SELECT dbcourse.username, dbcourse.color FROM dbcourse INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor WHERE dbcourse.username IN ($placeholders) AND dbfactor.dateresponse BETWEEN ? AND ?";
                $colorStmt = $conn->prepare($colorSql);
                $colorStmt->execute(array_merge($studentIds, [$startdate, $enddate]));
                foreach ($colorStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
                    $colorMapRows[$cr['username']][] = $cr['color'];
                }
            }

            // نقشه رنگ‌ها
            $hexMap = [
                0 => 'FFFF0000', // قرمز
                1 => 'FFFFA500', // نارنجی
                2 => 'FFFFFF00', // زرد
                3 => 'FF00FF00'  // سبز
            ];

            // تعریف mapping برای ستون‌ها
            $columnMapping = [
                'state' => ['title' => 'استان', 'callback' => function ($index, $state) {
                    return $state[$index] ?? '';
                }],
                'city' => ['title' => 'شهر', 'callback' => function ($index, $city) {
                    return $city[$index] ?? '';
                }],
                'gender' => ['title' => 'جنسیت', 'callback' => function ($index, $gender) {
                    return $gender[$index] ?? '';
                }],
                'student_phone' => ['title' => 'تلفن دانش آموز', 'callback' => function ($index, $tel1) {
                    return $tel1[$index] ?? '';
                }],
                'father_phone' => ['title' => 'تلفن پدر', 'callback' => function ($index, $tel3) {
                    return $tel3[$index] ?? '';
                }],
                'mother_phone' => ['title' => 'تلفن مادر', 'callback' => function ($index, $tel2) {
                    return $tel2[$index] ?? '';
                }],
                'sms_count' => [
                    'title' => 'تعداد اس‌ام‌اس',
                    'callback' => function ($index, $sms_count) {
                        return $sms_count[$index] ?? 0;
                    }
                ],
                'talk_time' => [
                    'title' => 'مدت زمان مکالمه',
                    'callback' => function ($index, $talk_time) {
                        return $talk_time[$index] ?? '00:00:00';
                    }
                ],

                'suspension_reason' => ['title' => 'علت تعلیق', 'callback' => function ($index, $suspension_reason) {
                    return $suspension_reason[$index];
                }],
                'course_names' => ['title' => 'نام دوره‌ها', 'callback' => function ($index, $course) {
                    if (!empty($course[$index]) && is_array($course[$index])) {
                        $courseNames = [];
                        foreach ($course[$index] as $c) {
                            $hamsepar = returnCourse($c);
                            $courseNames[] = $hamsepar->coursename;
                        }
                        return implode("\n", $courseNames);
                    }
                    return '';
                }],
                'course_count' => ['title' => 'تعداد دوره ثبت نامی', 'callback' => function ($index, $course) {
                    return !empty($course[$index]) && is_array($course[$index]) ? count($course[$index]) : 0;
                }],
                'total_payment' => ['title' => 'جمع پرداختی', 'callback' => function ($index, $fee) {
                    $value = isset($fee[$index]) ? intval($fee[$index]) : 0;
                    return number_format($value);
                }],
                'first_login' => ['title' => 'اولین ورود', 'callback' => function ($index, $first) {
                    return ($first[$index] ?? 0) == 1 ? 'بله' : 'خیر';
                }],
                'creator' => ['title' => 'ایجاد کننده', 'callback' => function ($index, $poshtiban) {
                    return $poshtiban[$index] ?? '';
                }],
                'approver' => ['title' => 'تاییدکننده فاکتور', 'callback' => function ($index, $useractive) {
                    return $useractive[$index] ?? '';
                }],
                'supporter' => ['title' => 'پشتیبان', 'callback' => function ($index, $suporter) {
                    return !empty($suporter[$index]) ? implode('|', (array)$suporter[$index]) : 'ندارد';
                }],
                'student_type' => ['title' => 'نوع دانش آموز', 'callback' => function ($index, $viptype) use ($conn) {
                    $query = $conn->prepare("
                        SELECT tv.name 
                        FROM dbpersonal_type_vip ptv
                        INNER JOIN type_vip tv ON ptv.type_vip_id = tv.id
                        WHERE ptv.dbpersonal_id = ?
                    ");
                    $query->execute([$viptype[$index]]);
                    $types = $query->fetchAll(PDO::FETCH_COLUMN);

                    return !empty($types) ? implode(' | ', $types) : 'تعریف نشده';
                }],
                'shift' => ['title' => 'شیفت دانش آموز', 'callback' => function ($index, $shift) {
                    return $shift[$index] ?? 'تعریف نشده';
                }],
                'birthdate' => ['title' => 'تاریخ تولد دانش آموز', 'callback' => function ($index, $birthdate) {
                    if (empty($birthdate[$index])) return '';

                    // فرض بر اینه که تاریخ میلادی به صورت YYYY-MM-DD ذخیره شده
                    list($gy, $gm, $gd) = explode('-', $birthdate[$index]);
                    $jalali = gregorian_to_jalali($gy, $gm, $gd, '/');

                    return $jalali;
                }],
                'school' => ['title' => 'نام مدرسه', 'callback' => function ($index, $school) {
                    return $school[$index] ?? '';
                }],
                'reshte' => ['title' => 'رشته دانش آموز', 'callback' => function ($index, $reshte) {
                    return $reshte[$index] ?? '';
                }],
                'factor_number' => ['title' => 'شماره فاکتور', 'callback' => function ($index, $factor_number) {
                    // اینجا باید منطق مربوط به شماره فاکتور رو اضافه کنی
                    return implode('|', $factor_number[$index]);
                }],
                'typeschool' => ['title' => 'نوع مدرسه', 'callback' => function ($index, $typeschool) {
                    return $typeschool[$index] ?? '';
                }],
                'area' => ['title' => 'منطقه آموزش و پرورش', 'callback' => function ($index, $area) {
                    return $area[$index] ?? '';
                }],
                'datecreate' => ['title' => 'تاریخ ثبت نام', 'callback' => function ($index, $data) {
                    if (empty($data['datecreate'][$index]) && empty($data['timecreate'][$index])) return '';
                    list($gy, $gm, $gd) = explode('-', $data['datecreate'][$index]);
                    $jalali = gregorian_to_jalali($gy, $gm, $gd, '/');
                    return $jalali . ' ' . ($data['timecreate'][$index] ?? '');
                }],
                'exam_type' => ['title' => 'نوع آزمون کشوری', 'callback' => function ($index, $exam_type) {
                    return $exam_type[$index] ?? '';
                }],
                'registered' => ['title' => 'وضعیت ثبت نام آزمون کشوری', 'callback' => function ($index, $registered) {
                    return $registered[$index] ?? '';
                }],
                'attended' => ['title' => 'وضعیت شرکت در آزمون کشوری', 'callback' => function ($index, $attended) {
                    return $attended[$index] ?? '';
                }],
                'result' => ['title' => 'نتیجه آزمون کشوری', 'callback' => function ($index, $result_exam) {
                    return $result_exam[$index] ?? '';
                }],
                'not_registered_reason' => ['title' => 'توضیحات چرایی عدم ثبت نام در آزمون کشوری', 'callback' => function ($index, $not_registered_reason) {
                    return $not_registered_reason[$index] ?? '';
                }],
                'student_comment' => ['title' => 'نظر دانش آموز', 'callback' => function ($index, $student_comment) {
                    return $student_comment[$index] ?? '';
                }],
                'supporter_comment' => ['title' => 'نظر پشتیبان', 'callback' => function ($index, $supporter_comment) {
                    return $supporter_comment[$index] ?? '';
                }],
                'avg_first_semester' => ['title' => 'معدل ترم اول', 'callback' => function ($index, $avg_first_semester) {
                    return $avg_first_semester[$index] ?? '';
                }],
                'avg_second_semester' => ['title' => 'معدل ترم دوم', 'callback' => function ($index, $avg_second_semester) {
                    return $avg_second_semester[$index] ?? '';
                }],
                'about' => ['title' => 'شرح حال', 'callback' => function ($index, $about) {
                    return $about[$index] ?? '';
                }]
            ];

            $dataMap = [
                'state' => $state,
                'city' => $city,
                'gender' => $gender,
                'student_phone' => $tel1,
                'father_phone' => $tel3,
                'mother_phone' => $tel2,
                'sms_count' => $sms_count,
                'talk_time' => $talk_time,
                'course_names' => $course,
                'course_count' => $course,
                'total_payment' => $fee,
                'first_login' => $first,
                'creator' => $poshtiban,
                'approver' => $useractive,
                'supporter' => $suporter,
                'student_type' => $viptype,
                'shift' => $shift,
                'birthdate' => $birthdate,
                'school' => $school,
                'reshte' => $reshte,
                'factor_number' => $factor_number,
                'typeschool' => $typeschool,
                'area' => $area,
                'datecreate' => ['datecreate' => $datecreate, 'timecreate' => $timecreate],
                'exam_type' => $exam_type,
                'registered' => $registered,
                'attended' => $attended,
                'result' => $result_exam,
                'not_registered_reason' => $not_registered_reason,
                'student_comment' => $student_comment,
                'supporter_comment' => $supporter_comment,
                'avg_first_semester' => $avg_first_semester,
                'avg_second_semester' => $avg_second_semester,
                'suspension_reason' => $suspension_reason,
                'about' => $about
            ];

            // ستون‌های ثابت اولیه
            $baseColumns = [
                'کدملی',
                'نام',
                'نام خانوادگی',
                'پایه'
            ];

            // ستون‌های انتخابی از مودال
            $selectedColumns = [];
            if (isset($_SESSION['excelCoursereport']['excel_options'])) {
                $excelOptions = $_SESSION['excelCoursereport']['excel_options'];
                foreach ($excelOptions as $option) {
                    if (isset($columnMapping[$option])) {
                        $selectedColumns[$option] = $columnMapping[$option]['title'];
                    }
                }
            }

            // ترکیب همه ستون‌ها
            $allColumns = array_merge($baseColumns, array_values($selectedColumns));
            if (!empty($activeEventColumns)) {
                foreach ($activeEventColumns as $eventId => $title) {
                    $allColumns[] = $title . ' - گزینه';
                    $allColumns[] = $title . ' - دلیل';
                }
            }
            $specialColumns = [
                'نام دوره‌ها' => 90,
                'توضیحات چرایی عدم ثبت نام در آزمون کشوری' => 30,
                'نظر دانش آموز' => 30,
                'نظر پشتیبان' => 30,
                'شرح حال' => 30,
                'نوع آزمون کشوری' => 30,
                'وضعیت ثبت نام آزمون کشوری' => 30,
                'نتیجه آزمون کشوری' => 30,
                'وضعیت شرکت در آزمون کشوری' => 30,
            ];

            $foundSpecialLetters = [];

            // نوشتن هدرها (ردیف 1)
            foreach ($allColumns as $col => $field) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue($columnLetter . '1', $field);

                // If this column title is in our special list, remember its letter
                if (array_key_exists($field, $specialColumns)) {
                    $foundSpecialLetters[$columnLetter] = $specialColumns[$field];
                }
            }
            // foreach ($allColumns as $col => $field) {
            //     $columnLetter = Coordinate::stringFromColumnIndex($col + 1);
            //     $sheet->setCellValue($columnLetter . '1', $field);
            // }

            // نوشتن داده‌ها از ردیف 2 به بعد
            $row = 2;
            for ($num = 0; $num < count($codemelli); $num++) {
                // داده‌های پایه
                $lineData = [
                    $codemelli[$num] ?? '',
                    $fname[$num] ?? '',
                    $lname[$num] ?? '',
                    $paye[$num] ?? ''
                ];

                // داده‌های انتخابی
                if (isset($_SESSION['excelCoursereport']['excel_options'])) {
                    $excelOptions = $_SESSION['excelCoursereport']['excel_options'];
                    foreach ($excelOptions as $option) {
                        if (isset($columnMapping[$option])) {
                            $lineData[] = $columnMapping[$option]['callback']($num, $dataMap[$option]);
                        }
                    }
                }

                if (!empty($activeEventColumns)) {
                    foreach ($activeEventColumns as $eventId => $title) {
                        $ans = $eventAnswers[$codemelli[$num]][$eventId] ?? ['option' => '', 'reason' => ''];
                        $lineData[] = $ans['option'];
                        $lineData[] = $ans['reason'];
                    }
                }

                // نوشتن هر سلول در ردیف فعلی
                foreach ($lineData as $col => $val) {
                    $columnLetter = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->setCellValue($columnLetter . $row, $val);
                }
                if (in_array('coloring', $_SESSION['excelCoursereport']['excel_options'])) {
                    // محاسبه و اعمال رنگ
                    $colorsForUser = $colorMapRows[$codemelli[$num]] ?? [];
                    $avgColorHex = null;
                    if (!empty($colorsForUser)) {
                        $nums = array_map('floatval', $colorsForUser);
                        $avgIndex = (int)round(array_sum($nums) / count($nums));
                        if (isset($hexMap[$avgIndex])) {
                            $avgColorHex = $hexMap[$avgIndex];
                        }
                    }
                    if ($avgColorHex) {
                        $sheet->getStyle("B{$row}:C{$row}")
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB($avgColorHex);
                    }
                }
                $row++;
            }

            // استایل هدر: بولد و پس‌زمینه
            $highestColumn = $sheet->getHighestColumn();
            $headerRange = 'A1:' . $highestColumn . '1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');

            // راست‌چین کردن کل شیت (برای فارسی)
            // $lastRow = $sheet->getHighestRow();
            // $sheet->getStyle('A1:' . $highestColumn . $lastRow)
            //     ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Autosize ستون‌ها
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            $highestRow = $sheet->getHighestRow();
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                $sheet->getStyle($colLetter . '1:' . $colLetter . $highestRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            // --- APPLY SPECIAL STYLES DYNAMICALLY ---
            foreach ($foundSpecialLetters as $letter => $width) {
                // Disable autosize for these specific columns so our manual width works
                $sheet->getColumnDimension($letter)->setAutoSize(false);
                $sheet->getColumnDimension($letter)->setWidth($width);

                // Set Wrap Text
                $sheet->getStyle($letter . '1:' . $letter . $highestRow)
                    ->getAlignment()->setWrapText(true);
            }

            // $sheet->getColumnDimension('Q')->setWidth(70);

            // $sheet->getStyle('Q1:Q' . $highestRow)->getAlignment()->setWrapText(true);

            // $sheet->getColumnDimension('W')->setWidth(30);

            // $sheet->getStyle('W1:W' . $highestRow)->getAlignment()->setWrapText(true);

            // $sheet->getColumnDimension('X')->setWidth(30);

            // $sheet->getStyle('X1:X' . $highestRow)->getAlignment()->setWrapText(true);

            // $sheet->getColumnDimension('AE')->setWidth(30);

            // $sheet->getStyle('AE1:AE' . $highestRow)->getAlignment()->setWrapText(true);

            // $sheet->getColumnDimension('AF')->setWidth(30);

            // $sheet->getStyle('AF1:AF' . $highestRow)->getAlignment()->setWrapText(true);

            $filename = "گزارش تجمیعی نفر دوره_" . date('Y-m-d_H-i-s') . ".xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"" . rawurlencode($filename) . "\"; filename*=UTF-8''" . rawurlencode($filename));
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
    }
    // if ($type == 1) {
    //     $stm = $conn->prepare('SELECT * FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor WHERE dbcourse . activityuser = ? and dbfactor . dateresponse BETWEEN ? and ? ORDER BY dbfactor . username DESC');
    //     $stm->execute([1, $startdate, $enddate]);
    //     if ($stm) {
    //         $delimiter = ",";
    //         $filename = "گزارش نفر دوره _" . date('Y-m-d h:i:s') . ".csv";

    //         header('Content-Encoding: UTF-8');
    //         header('Content-type: application/csv; charset=UTF-8');
    //         header('Content-Disposition: attachment; filename=' . $filename);

    //         $f = fopen('php://output', 'w');
    //         fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

    //         $fields = ['کدملی', 'نام', 'نام خانوادگی', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'نام دوره', 'ایجاد کننده','پشتیبان', 'شماره فاکتور'];
    //         fputcsv($f, $fields, $delimiter);
    //         while ($row = $stm->fetch(PDO::FETCH_OBJ)) {


    //             $user = retPerson($row->username);
    //             if ($row->factoradder != NULL) {

    //                 $creator = $row->factoradder;
    //             } else {
    //                 $creator = 'خودش';
    //             }
    //             if($row->suporter != NULL){
    //                 $suporter=$row->suporter;
    //             }else{
    //                 $suporter ='ندارد';
    //             }


    //             $linedata = [$row->username, $user->fname, $user->lname, $user->state, $user->city, $user->tel1, $user->tel2, $user->tel3, $row->codecourse, $creator, $suporter,$row->idfactor];
    //             fputcsv($f, $linedata, $delimiter);
    //         }

    //         fpassthru($f);
    //     }
    // }
    // if ($type == 2) {
    //     $codemelli = array();
    //     $fname = array();
    //     $lname = array();
    //     $poshtiban = array();
    //     $tel1 = array();
    //     $tel2 = array();
    //     $tel3 = array();
    //     $state = array();
    //     $city = array();
    //     $paye = array();
    //     $course = array();
    //     $fee = array();
    //     $first = array();
    //     $suporter = array();
    //     $useractive = array();
    //     $idfactor = array();
    //     $viptype = array();
    //     $shift = array();

    //     $sql = '    SELECT         dbcourse.*,        dbfactor.*,        dbpersonal.shift,        type_vip.name AS vip_type    FROM dbcourse    INNER JOIN dbfactor         ON dbcourse.numfactor = dbfactor.idfactor    INNER JOIN dbpersonal         ON dbfactor.username = dbpersonal.codemeli    LEFT JOIN dbpersonal_type_vip         ON dbpersonal.idpersonal = dbpersonal_type_vip.dbpersonal_id    LEFT JOIN type_vip         ON dbpersonal_type_vip.type_vip_id = type_vip.id    WHERE dbcourse.activityuser = 1       AND dbfactor.dateresponse BETWEEN ? AND ?   ORDER BY dbfactor.username DESC';
    //     $query = $conn->prepare($sql);
    //     $query->execute([$startdate, $enddate]);
    //     $result = $query->fetchAll(PDO::FETCH_OBJ);
    //     if ($query->rowCount() > 0) {
    //         foreach ($result as $res) {
    //             if (!in_array($res->username, $codemelli)) {
    //                 array_push($codemelli, $res->username);
    //                 $student = retPerson($res->username);
    //                 $firstlogin=retdblogin($res->username);
    //                 array_push($fname, $student->fname);
    //                 array_push($lname, $student->lname);
    //                 array_push($tel1, $student->tel1);
    //                 array_push($tel2, $student->tel2);
    //                 array_push($tel3, $student->tel3);
    //                 array_push($state, $student->state);
    //                 array_push($city, $student->city);
    //                 array_push($paye, $student->paye);
    //                 array_push($first, $firstlogin->firstlogin);
    //                 // ✅ Add نوع دانش‌آموز (VIP type)
    //                 $viptype[] = $res->vip_type ?? 'تعریف نشده';

    //                 // ✅ Add شیفت دانش‌آموز
    //                 switch ($res->shift) {
    //                     case 0:  $shiftName = 'صبح'; break;
    //                     case 1:  $shiftName = 'بعدازظهر'; break;
    //                     case 2:  $shiftName = 'گردشی'; break;
    //                     default: $shiftName = 'تعریف نشده';
    //                 }
    //                 $shift[] = $shiftName;

    //                 if ($res->factoradder != NULL) {
    //                     array_push($poshtiban, $res->factoradder);
    //                 } else {
    //                     array_push($poshtiban, 'خودش');
    //                 }
    //                 if ($res->useractive != NULL) {
    //                     array_push($useractive, $res->useractive);
    //                 } else {
    //                     array_push($useractive, 'خودش');
    //                 }

    //                 $allfactornum = retallcoursefactor($res->username, $startdate, $enddate);
    //                 array_push($idfactor, $allfactornum);
    //                 $doreha = retallcourse($res->username, $startdate, $enddate);
    //                 array_push($course, $doreha);
    //                 $support = retallcourseposhtiban($res->username, $startdate, $enddate);
    //                 array_push($suporter, $support);
    //                 $sumfee=sumfactoruser($res->username, $startdate, $enddate);
    //                 array_push($fee, $sumfee);
    //             }
    //         }
    //     }

    //     if ($codemelli != NULL) {
    //         $delimiter = ",";
    //         $filename = "گزارش تجمیعی نفر دوره _" . date('Y-m-d h:i:s') . ".csv";

    //         header('Content-Encoding: UTF-8');
    //         header('Content-type: application/csv; charset=UTF-8');
    //         header('Content-Disposition: attachment; filename=' . $filename);

    //         $f = fopen('php://output', 'w');
    //         fputs($f, "\xEF\xBB\xBF"); // UTF-8 BOM !!!!!

    //         $fields = ['شماره فاکتور','کدملی', 'نام', 'نام خانوادگی', 'پایه', 'استان', 'شهر', 'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر', 'نام دوره ها', 'تعداد دوره ثبت نامی','جمع پرداختی','اولین ورود', 'ایجاد کننده','تاییدکننده فاکتور','پشتیبان','نوع دانش آموز','شیفت دانش آموز'];
    //         fputcsv($f, $fields, $delimiter);
    //         for ($num = 0; $num < sizeof($codemelli); $num++) {
    //             if($suporter[$num]!=NULL) {
    //                 $sposh = implode('|', $suporter[$num]);

    //             }else{
    //                 $sposh='ندارد';
    //             }
    //             $factornum = implode('|', $idfactor[$num]);
    //             for ($rt = 0; $rt < sizeof($course[$num]); $rt++) {
    //                 $hamsepar = returnCourse($course[$num][$rt]);
    //                 $course[$num][$rt] = $hamsepar->coursename;
    //                 $df = implode('|', $course[$num]);

    //             }

    //             $linedata = [
    //                 $factornum,
    //                 $codemelli[$num],
    //                 $fname[$num],
    //                 $lname[$num],
    //                 $paye[$num],
    //                 $state[$num],
    //                 $city[$num],
    //                 $tel1[$num],
    //                 $tel2[$num],
    //                 $tel3[$num],
    //                 $df,
    //                 sizeof($course[$num]),
    //                 $fee[$num],
    //                 $first[$num],
    //                 $poshtiban[$num],
    //                 $useractive[$num],
    //                 $sposh,
    //                 $viptype[$num],
    //                 $shift[$num]
    //             ];
    //             fputcsv($f, $linedata, $delimiter);
    //         }
    //         fpassthru($f);
    //     }


    // }
    // if ($type == 3) {
    //     $sql = '
    //     SELECT
    //         cs.student_id,
    //         cs.exam_type,
    //         cs.registered,
    //         cs.attended,
    //         cs.result,
    //         cs.not_registered_reason,
    //         dc.username    AS dbcourse_username,
    //         dc.activityuser,
    //         dc.color       AS dbcourse_color,
    //         dc.suporter    AS suporter,
    //         COALESCE(ss.student_comment, "")     AS student_comment,
    //         COALESCE(ss.supporter_comment, "")   AS supporter_comment,
    //         COALESCE(ss.avg_first_semester, "")  AS avg_first_semester,
    //         COALESCE(ss.avg_second_semester, "") AS avg_second_semester,
    //         dp.paye AS paye,
    //         COALESCE(pay.total_paid,0) AS total_paid   -- ✅ جمع پرداختی
    //     FROM course_status cs
    //     LEFT JOIN dbcourse dc ON cs.student_id = dc.username
    //     LEFT JOIN student_status ss ON cs.student_id = ss.student_id
    //     LEFT JOIN dbpersonal dp ON cs.student_id = dp.codemeli
    //     LEFT JOIN (
    //         SELECT dbfactor.username, SUM(dbfactor.subtotal) AS total_paid
    //         FROM dbfactor
    //         GROUP BY dbfactor.username
    //     ) pay ON pay.username = cs.student_id
    //     ORDER BY cs.student_id DESC
    // ';

    //     $stm = $conn->prepare($sql);
    //     $stm->execute();

    //     $rows = $stm->fetchAll(PDO::FETCH_OBJ);

    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();

    //     // ✅ Added جمع پرداختی after پشتیبان
    //     $fields = [
    //         'کدملی', 'نام', 'نام خانوادگی', 'پایه', 'پشتیبان', 'جمع پرداختی', 'استان', 'شهر',
    //         'تلفن دانش آموز', 'تلفن پدر', 'تلفن مادر',
    //         'نوع آزمون', 'وضعیت ثبت نام', 'وضعیت آزمون', 'نتیجه',
    //         'توضیحات',
    //         'نظر دانش آموز', 'نظر پشتیبان',
    //         'معدل ترم اول', 'معدل ترم دوم'
    //     ];
    //     foreach ($fields as $i => $f) {
    //         $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
    //         $sheet->setCellValue($cell, $f);
    //     }

    //     $rowNum = 2;
    //     $seen = [];

    //     $studentIds = array_values(array_unique(array_map(fn($r) => $r->student_id, $rows)));
    //     $colorMapRows = [];
    //     if (!empty($studentIds)) {
    //         $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    //         $colorSql = "SELECT username, color FROM dbcourse WHERE username IN ($placeholders)";
    //         $colorStmt = $conn->prepare($colorSql);
    //         $colorStmt->execute($studentIds);
    //         foreach ($colorStmt->fetchAll(PDO::FETCH_ASSOC) as $cr) {
    //             $colorMapRows[$cr['username']][] = $cr['color'];
    //         }
    //     }

    //     $hexMap = [0=>'FFFF0000',1=>'FFFFA500',2=>'FFFFFF00',3=>'FF00FF00'];

    //     foreach ($rows as $r) {
    //         $keyParts = [$r->student_id, $r->exam_type, $r->registered, $r->attended, $r->result];
    //         $rowKey = implode('|',$keyParts);
    //         if (isset($seen[$rowKey])) continue;
    //         $seen[$rowKey] = true;

    //         $user = $r->student_id ? retPerson($r->student_id) : false;

    //         $colorsForUser = $colorMapRows[$r->student_id] ?? [];
    //         $avgColorHex = null;
    //         if (!empty($colorsForUser)) {
    //             $nums = array_map('floatval',$colorsForUser);
    //             $avgIndex = (int) round(array_sum($nums)/count($nums));
    //             if(isset($hexMap[$avgIndex])) $avgColorHex = $hexMap[$avgIndex];
    //         }

    //         // ✅ Added total_paid after supporter
    //         $linedata = [
    //             $r->student_id ?? '',
    //             $user->fname ?? '',
    //             $user->lname ?? '',
    //             $r->paye ?? '',
    //             $r->suporter ?? '',
    //             $r->total_paid ?? 0,
    //             $user->state ?? '',
    //             $user->city ?? '',
    //             $user->tel1 ?? '',
    //             $user->tel2 ?? '',
    //             $user->tel3 ?? '',
    //             $r->exam_type ?? '',
    //             $r->registered ?? '',
    //             $r->attended ?? '',
    //             $r->result ?? '',
    //             $r->not_registered_reason ?? '',
    //             $r->student_comment ?? '',
    //             $r->supporter_comment ?? '',
    //             $r->avg_first_semester ?? '',
    //             $r->avg_second_semester ?? ''
    //         ];

    //         foreach ($linedata as $ci => $val) {
    //             $cell = Coordinate::stringFromColumnIndex($ci+1) . $rowNum;
    //             $sheet->setCellValue($cell, $val);
    //         }

    //         if($avgColorHex) {
    //             $sheet->getStyle("B{$rowNum}:C{$rowNum}")
    //                 ->getFill()->setFillType(Fill::FILL_SOLID)
    //                 ->getStartColor()->setARGB($avgColorHex);
    //         }

    //         $rowNum++;
    //     }

    //     for($i=1;$i<=count($fields);$i++){
    //         $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    //     }

    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="گزارش_نفر_دوره_' . date('Y-m-d_H-i-s') . '.xlsx"');
    //     header('Cache-Control: max-age=0');
    //     $writer = new Xlsx($spreadsheet);
    //     $writer->save('php://output');
    //     unset($_SESSION['excelCoursereport']);
    //     exit;
    // }


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

                if ($suport['typesupport'] == 1) {
                    $type = 'ویـژه';
                } elseif ($suport['typesupport'] == 2) {
                    $type = 'ویژه فعال';
                } else {
                    $type = 'عــادی';
                }
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
        header('Content-Disposition: attachment; filename="پشتیبان دانش آموز ' . $op . ' اپراتور _' . date('Y-m-d_H-i-s') . '.xlsx"');
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

                    if ($suport['typesupport'] == 1) {
                        $type = 'ویـژه';
                    } elseif ($suport['typesupport'] == 2) {
                        $type = 'ویژه فعال';
                    } else {
                        $type = 'عــادی';
                    }
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
                if ($suport['typesupport'] == 1) {
                    $type = 'ویـژه';
                } elseif ($suport['typesupport'] == 2) {
                    $type = 'ویژه فعال';
                } else {
                    $type = 'عــادی';
                }
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

                if ($suport['typesupport'] == 1) {
                    $type = 'ویـژه';
                } elseif ($suport['typesupport'] == 2) {
                    $type = 'ویژه فعال';
                } else {
                    $type = 'عــادی';
                }
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

    $fields = ['#', 'شماره تماس', 'مقصد', 'نام اپراتور', 'مدت انتظار', 'مدت مکالمه', 'وضعیت', 'تاریخ شروع'];
    foreach ($fields as $i => $f) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $f);
    }

    $rowNum = 2;
    foreach ($queueData as $i => $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum, $i + 1);
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

    $fields = ['#', 'شماره تماس', 'مقصد', 'مدت مکالمه', 'وضعیت', 'تاریخ شروع'];
    foreach ($fields as $i => $f) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $f);
    }

    $rowNum = 2;
    foreach ($misscallData as $i => $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum, $i + 1);
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

if (isset($_POST['export_suspended_students'])) {

    $startDateJalali = $_POST['startdate'] ?? '';
    $endDateJalali = $_POST['enddate'] ?? '';

    if (!empty($startDateJalali) && !empty($endDateJalali)) {

        // --- 1. تبدیل تاریخ شمسی به میلادی ---
        list($jy1, $jm1, $jd1) = explode('/', $startDateJalali);
        $gregorianStart = jalali_to_gregorian($jy1, $jm1, $jd1, '-');
        $gregorianStart .= ' 00:00:00';

        list($jy2, $jm2, $jd2) = explode('/', $endDateJalali);
        $gregorianEnd = jalali_to_gregorian($jy2, $jm2, $jd2, '-');
        $gregorianEnd .= ' 23:59:59';

        $sql = "
            SELECT p.codemeli, p.fname, p.lname, p.tel1, p.tel2, p.tel3, req.latest_date
            FROM dbpersonal p
            INNER JOIN (
                SELECT student_username, MAX(created_at) AS latest_date
                FROM cancel_student_operator_request
                GROUP BY student_username
            ) req ON p.codemeli = req.student_username
            WHERE p.suspension_mode = 1 
            AND req.latest_date >= ? 
            AND req.latest_date <= ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$gregorianStart, $gregorianEnd]);
        $suspendedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 3. تولید فایل اکسل با PhpSpreadsheet ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        // هدر ستون‌ها (ستون تاریخ آخرین درخواست را هم برای شفافیت اضافه کردم)
        $fields = ['ردیف', 'کد ملی', 'نام', 'نام خانوادگی', 'تلفن ۱', 'تلفن ۲', 'تلفن ۳', 'تاریخ'];
        foreach ($fields as $i => $f) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $rowNum = 2;
        $id = 1;

        foreach ($suspendedStudents as $student) {
            // تبدیل تاریخ میلادی دیتابیس به شمسی برای نمایش زیباتر در اکسل (اختیاری)
            $jalaliDate = '';
            if (!empty($student['latest_date'])) {
                $gDate = explode(' ', $student['latest_date'])[0];
                list($gy, $gm, $gd) = explode('-', $gDate);
                $jalaliDate = gregorian_to_jalali($gy, $gm, $gd, '/');
            }

            $linedata = [
                $id,
                $student['codemeli'],
                $student['fname'],
                $student['lname'],
                $student['tel1'],
                $student['tel2'],
                $student['tel3'],
                $jalaliDate // تاریخ آخرین درخواست
            ];

            foreach ($linedata as $ci => $val) {
                $cell = Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                // ذخیره به صورت متنی برای جلوگیری از حذف صفرهای ابتدای شماره/کد ملی
                $sheet->setCellValueExplicit($cell, (string)$val, DataType::TYPE_STRING);
            }

            $rowNum++;
            $id++;
        }

        // AutoSize کردن ستون‌ها
        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // --- 4. خروجی دادن فایل ---
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="دانش آموزان_تعلیق_شده_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

if (isset($_POST['export_suspended_students_list'])) {

    $startDateJalali = $_POST['startdate'] ?? '';
    $endDateJalali = $_POST['enddate'] ?? '';

    if (!empty($startDateJalali) && !empty($endDateJalali)) {

        // --- 1. تبدیل تاریخ شمسی به میلادی ---
        list($jy1, $jm1, $jd1) = explode('/', $startDateJalali);
        $gregorianStart = jalali_to_gregorian($jy1, $jm1, $jd1, '-');
        $gregorianStart .= ' 00:00:00';

        list($jy2, $jm2, $jd2) = explode('/', $endDateJalali);
        $gregorianEnd = jalali_to_gregorian($jy2, $jm2, $jd2, '-');
        $gregorianEnd .= ' 23:59:59';


        $sql = "
            SELECT p.codemeli, p.fname, p.lname, p.tel1, p.tel2, p.tel3, p.suspension_reason, req.latest_date
            FROM dbpersonal p
            INNER JOIN (
                SELECT student_username, MAX(created_at) AS latest_date
                FROM student_suspension_requests
                GROUP BY student_username
            ) req ON p.codemeli = req.student_username
            WHERE p.suspension_mode = 1 
            AND req.latest_date >= ? 
            AND req.latest_date <= ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$gregorianStart, $gregorianEnd]);
        $suspendedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 3. تولید فایل اکسل ---
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        $fields = ['ردیف', 'کد ملی', 'نام', 'نام خانوادگی', 'تلفن ۱', 'تلفن ۲', 'تلفن ۳', 'علت تعلیق', 'تاریخ '];

        // تنظیم هدرها
        foreach ($fields as $i => $f) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . "1";
            $sheet->setCellValue($cell, $f);
        }

        $rowNum = 2;
        $id = 1;

        foreach ($suspendedStudents as $student) {
            // تبدیل تاریخ میلادی به شمسی جهت نمایش در اکسل
            $jalaliDate = '';
            if (!empty($student['latest_date'])) {
                $gDate = explode(' ', $student['latest_date'])[0];
                list($gy, $gm, $gd) = explode('-', $gDate);
                $jDateArr = gregorian_to_jalali($gy, $gm, $gd);
                $jalaliDate = implode('/', $jDateArr);
            }

            $linedata = [
                $id,
                $student['codemeli'],
                $student['fname'],
                $student['lname'],
                $student['tel1'],
                $student['tel2'],
                $student['tel3'],
                $student['suspension_reason'],
                $jalaliDate
            ];

            // ثبت اطلاعات سطرها
            foreach ($linedata as $ci => $val) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1) . $rowNum;
                // استفاده از مسیر کامل DataType برای جلوگیری از خطای Class Not Found
                $sheet->setCellValueExplicit($cell, (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $rowNum++;
            $id++;
        }

        // AutoSize کردن ستون‌ها
        for ($i = 1; $i <= count($fields); $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // --- 4. خروجی دادن فایل ---
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="دانش آموزان_تعلیق_شده_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

