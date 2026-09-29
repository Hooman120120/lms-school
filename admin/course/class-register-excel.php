<?php
/**
 * خروجی اکسل دفتر کلاسی
 *
 * scope=week : فقط ستون های هفته ی انتخاب شده (پارامتر week، کلید همان هفته)
 * scope=all  : همه ی هفته های سال تحصیلی، ستون ها زیر نام هفته ی خودشان گروه می شوند
 *
 * دامنه ی دید دقیقاً مثل صفحه ی دفتر کلاسی است: استاد فقط ستون های خودش را می گیرد.
 */

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
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
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$adminrole = $_SESSION['admin']['adminrole'];
$admin_id  = $_SESSION['admin']['idadmin'] ?? $_SESSION['admin']['id'] ?? 0;
$class_id  = isset($_GET['class']) ? intval($_GET['class']) : 0;
$is_teacher = ((int)$adminrole === 3);

if ($class_id <= 0) {
    die('فاقد کلاس');
}
if ($is_teacher && !TeacherHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دفتر کلاسی این کلاس دسترسی ندارید.');
}
if (!$is_teacher && StaffCourseScopeRequired() && !StaffHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دفتر کلاسی این پایه و کلاس دسترسی ندارید.');
}

// استاد فقط ستون ها و رکوردهای خودش را می بیند، نقش های بالاتر همه را
$sees_all_columns = !$is_teacher;

$scope = ($_GET['scope'] ?? 'week') === 'all' ? 'all' : 'week';

// ---------------------------------------------------------------
// داده ها، دقیقاً با همان دامنه ی دید صفحه ی دفتر کلاسی
// ---------------------------------------------------------------
$students = getRegisterClassStudents($class_id);

if ($sees_all_columns) {
    $events        = getRegisterClassEvents($class_id, 0, 0);
    $evaluations   = getRegisterClassEvaluations($class_id, 0, 0);
    $attendances   = getRegisterClassAttendance($class_id, 0, 0);
    $disciplinaries = getClassDisciplinaries($class_id, 0, 0);
} else {
    $events        = getRegisterClassEvents($class_id, $admin_id, (int)$adminrole);
    $evaluations   = getRegisterClassEvaluations($class_id, $admin_id, (int)$adminrole);
    $attendances   = getRegisterClassAttendance($class_id, $admin_id, (int)$adminrole);
    $disciplinaries = getClassDisciplinaries($class_id, $admin_id, (int)$adminrole);
}

$academicYear   = registerClassAcademicYear(time());
$weeks          = registerClassWeeks($academicYear);
$currentWeekKey = registerClassWeekKeyFor($weeks, time());

$selected_week = null;
foreach ($weeks as $week) {
    if ($week['key'] === ($_GET['week'] ?? '')) {
        $selected_week = $week;
        break;
    }
}
if ($selected_week === null) {
    foreach ($weeks as $week) {
        if ($week['key'] === $currentWeekKey) {
            $selected_week = $week;
            break;
        }
    }
}
if ($selected_week === null) {
    $selected_week = $weeks[0];
}

// خروجی هفتگی فقط ستون های همان هفته را دارد
if ($scope === 'week') {
    $events = array_values(array_filter($events, function ($evt) use ($weeks, $selected_week) {
        return registerClassEventWeekKey($evt, $weeks) === $selected_week['key'];
    }));
}

$teacherUsernames = getTeacherFullNamesByIds(array_unique(array_column($events, 'teacher_id')));
$qualityOptions   = registerClassQualityOptions();

$kindMeta = [];
foreach (registerClassColumnKinds() as $kind) {
    $kindMeta[(string)$kind['type']] = $kind;
}
$defaultKind = ['title' => '', 'date_bound' => true, 'input' => 'attendance'];

// نمره، حضور و غیاب و توضیحات هر دانش آموز در هر ستون
$log_lookup = [];
foreach ($evaluations as $row) {
    $log_lookup[$row['username']][$row['event_id']]['score']   = $row['score'];
    $log_lookup[$row['username']][$row['event_id']]['note']    = $row['note'];
    $log_lookup[$row['username']][$row['event_id']]['quality'] = $row['quality'];
}
foreach ($attendances as $row) {
    $log_lookup[$row['username']][$row['event_id']]['status_code'] = ($row['status'] === '1') ? 1 : 2;
}

$discipline_lookup = [];
foreach ($disciplinaries as $disc) {
    $ts = strtotime($disc['created_at']);
    $discipline_lookup[$disc['username']][] = [
        'reason'     => $disc['reason'],
        'created_at' => $ts ? jdate('Y/m/d H:i', $ts, '', 'Asia/Tehran', 'en') : $disc['created_at'],
        'created_by' => $disc['created_by_username'] ?? 'نامشخص',
    ];
}

// نام کلاس برای سربرگ گزارش
$className = '';
$stmtClass = $conn->prepare("SELECT name FROM classes WHERE id = ?");
$stmtClass->execute([$class_id]);
$className = (string)($stmtClass->fetchColumn() ?: ('کلاس ' . $class_id));

// در خروجی کل سال، ستون ها بر اساس هفته گروه بندی می شوند
$weekLabels = [];
foreach ($weeks as $week) {
    $weekLabels[$week['key']] = $week['label'];
}
$eventWeekKey = [];
foreach ($events as $evt) {
    $eventWeekKey[$evt['id']] = registerClassEventWeekKey($evt, $weeks);
}
// ترتیب ستون ها در خروجی کل سال: اول هفته، بعد ترتیب خود جدول
if ($scope === 'all') {
    $order = array_flip(array_column($weeks, 'key'));
    usort($events, function ($a, $b) use ($eventWeekKey, $order) {
        $wa = $order[$eventWeekKey[$a['id']]] ?? 0;
        $wb = $order[$eventWeekKey[$b['id']]] ?? 0;
        if ($wa !== $wb) return $wa <=> $wb;
        return ((int)$a['id']) <=> ((int)$b['id']);
    });
}

// ---------------------------------------------------------------
// ساخت فایل
// ---------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle($scope === 'all' ? 'دفتر کلاسی (کل سال)' : 'دفتر کلاسی (هفتگی)');
$sheet->setRightToLeft(true);

$FIXED_COLS  = 3;                       // ردیف، نام و نام خانوادگی، موارد انضباطی
$totalCols   = $FIXED_COLS + count($events);
$lastColLtr  = Coordinate::stringFromColumnIndex(max(1, $totalCols));

$PURPLE_DARK = '9A62F4';
$PURPLE_MID  = 'BB8FF9';
$PURPLE_SOFT = 'C2A0F9';
$ROW_A       = 'EFE3FF';
$ROW_B       = 'E2D0FC';

// --- سطر ۱: عنوان گزارش ---
$sheet->setCellValue('A1', 'دفتر کلاسی — ' . $className);
$sheet->mergeCells('A1:' . $lastColLtr . '1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $PURPLE_DARK);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(26);

// --- سطر ۲: بازه ی گزارش ---
$scopeLabel = ($scope === 'all')
    ? ('کل سال تحصیلی ' . $academicYear . ' - ' . ($academicYear + 1))
    : ($selected_week['label'] . ' (' . jdate('Y/m/d', strtotime($selected_week['start']), '', 'Asia/Tehran', 'en')
        . ' تا ' . jdate('Y/m/d', strtotime($selected_week['end']), '', 'Asia/Tehran', 'en') . ')');
$sheet->setCellValue('A2', $scopeLabel . '  |  تعداد دانش آموزان: ' . count($students)
    . '  |  تاریخ خروجی: ' . jdate('Y/m/d H:i', time(), '', 'Asia/Tehran', 'en'));
$sheet->mergeCells('A2:' . $lastColLtr . '2');
$sheet->getStyle('A2')->getFont()->setBold(true);
$sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $ROW_A);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(2)->setRowHeight(20);

// --- سطر ۳: نام هفته بالای هر گروه از ستون ها ---
// --- سطر ۴: عنوان ستون، تاریخ و استاد ---
$HEAD_GROUP = 3;
$HEAD_COL   = 4;
$FIRST_DATA = 5;

$fixedHeaders = ['ردیف', 'نام و نام خانوادگی', 'موارد انضباطی'];
foreach ($fixedHeaders as $i => $text) {
    $letter = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($letter . $HEAD_GROUP, $text);
    $sheet->mergeCells($letter . $HEAD_GROUP . ':' . $letter . $HEAD_COL);
}

$colIndex = $FIXED_COLS + 1;
$groupStart = null;
$groupKey   = null;
foreach ($events as $pos => $evt) {
    $letter = Coordinate::stringFromColumnIndex($colIndex);

    $ts   = !empty($evt['event_date']) ? strtotime($evt['event_date']) : false;
    $date = $ts ? jdate('Y/m/d', $ts, '', 'Asia/Tehran', 'en') : 'بدون تاریخ';
    $teacher = $teacherUsernames[$evt['teacher_id']] ?? 'نامشخص';

    $headLines = [$evt['title'], $date];
    if ((int)$adminrole !== 3) {
        $headLines[] = $teacher;
    }
    $sheet->setCellValue($letter . $HEAD_COL, implode("\n", $headLines));

    // گروه بندی ستون ها زیر نام هفته (در خروجی هفتگی فقط یک گروه است)
    $thisKey = $eventWeekKey[$evt['id']] ?? '';
    if ($groupKey === null) {
        $groupKey = $thisKey;
        $groupStart = $colIndex;
    } elseif ($thisKey !== $groupKey) {
        registerClassWriteGroupHeader($sheet, $groupStart, $colIndex - 1, $weekLabels[$groupKey] ?? '', $HEAD_GROUP, $PURPLE_SOFT);
        $groupKey   = $thisKey;
        $groupStart = $colIndex;
    }
    if ($pos === count($events) - 1) {
        registerClassWriteGroupHeader($sheet, $groupStart, $colIndex, $weekLabels[$groupKey] ?? '', $HEAD_GROUP, $PURPLE_SOFT);
    }

    $colIndex++;
}

/**
 * نام هفته را روی چند ستون ادغام شده می نویسد
 */
function registerClassWriteGroupHeader($sheet, int $fromCol, int $toCol, string $label, int $row, string $color): void
{
    $from = Coordinate::stringFromColumnIndex($fromCol);
    $to   = Coordinate::stringFromColumnIndex($toCol);
    $sheet->setCellValue($from . $row, $label);
    if ($fromCol !== $toCol) {
        $sheet->mergeCells($from . $row . ':' . $to . $row);
    }
    $sheet->getStyle($from . $row . ':' . $to . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $color);
}

// استایل دو سطر سربرگ
$headRange = 'A' . $HEAD_GROUP . ':' . $lastColLtr . $HEAD_COL;
$sheet->getStyle($headRange)->getFont()->setBold(true);
$sheet->getStyle($headRange)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$sheet->getStyle('A' . $HEAD_COL . ':' . $lastColLtr . $HEAD_COL)->getFill()
    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $PURPLE_MID);
$sheet->getStyle('A' . $HEAD_GROUP . ':' . Coordinate::stringFromColumnIndex($FIXED_COLS) . $HEAD_COL)
    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $PURPLE_SOFT);
$sheet->getRowDimension($HEAD_GROUP)->setRowHeight(20);
$sheet->getRowDimension($HEAD_COL)->setRowHeight(46);

// --- سطرهای دانش آموزان ---
$row = $FIRST_DATA;
foreach ($students as $index => $student) {
    $fullName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
    if ($fullName === '') {
        $fullName = $student['username'];
    }
    $discCount = count($discipline_lookup[$student['username']] ?? []);

    $sheet->setCellValue('A' . $row, $index + 1);
    $sheet->setCellValue('B' . $row, $fullName);
    $sheet->setCellValue('C' . $row, $discCount > 0 ? $discCount : '');

    $colIndex = $FIXED_COLS + 1;
    foreach ($events as $evt) {
        $letter = Coordinate::stringFromColumnIndex($colIndex);
        $cell   = $log_lookup[$student['username']][$evt['id']] ?? [];
        $kind   = $kindMeta[(string)$evt['type']] ?? $defaultKind;

        $value = '';
        if (!empty($cell)) {
            if ($kind['input'] === 'score') {
                $score   = $cell['score'] ?? '';
                $quality = $cell['quality'] ?? '';
                $parts = [];
                if ($score !== '' && $score !== null) {
                    $parts[] = $score;
                }
                // ستون نمره کلاسی علاوه بر عدد، ارزیابی کیفی هم دارد
                if (!empty($kind['quality']) && $quality !== '' && isset($qualityOptions[(int)$quality])) {
                    $parts[] = $qualityOptions[(int)$quality];
                }
                $value = implode(' - ', $parts);
            } elseif ($kind['input'] === 'note') {
                $value = trim((string)($cell['note'] ?? ''));
            } elseif ($kind['input'] === 'attendance') {
                $status = $cell['status_code'] ?? '';
                if ($status !== '') {
                    $value = ((int)$status === 1) ? 'حاضر' : 'غایب';
                }
            }
        }

        // عدد خالص به صورت عدد نوشته می شود تا در اکسل قابل محاسبه باشد
        if ($value !== '' && is_numeric($value)) {
            $sheet->setCellValueExplicit($letter . $row, $value + 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($letter . $row, $value);
        }
        $colIndex++;
    }

    // رنگ یک در میان سطرها، مثل خود دفتر کلاسی
    $sheet->getStyle('A' . $row . ':' . $lastColLtr . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF' . (($index % 2 === 0) ? $ROW_A : $ROW_B));

    $row++;
}
$lastDataRow = $row - 1;

if (empty($students)) {
    $sheet->setCellValue('A' . $FIRST_DATA, 'هیچ دانش آموزی برای این کلاس ثبت نشده است.');
    $sheet->mergeCells('A' . $FIRST_DATA . ':' . $lastColLtr . $FIRST_DATA);
    $lastDataRow = $FIRST_DATA;
}
if (empty($events)) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($FIXED_COLS + 1) . $HEAD_COL, 'ستونی ثبت نشده است');
}

// قاب و ترازبندی کل جدول
$tableRange = 'A' . $HEAD_GROUP . ':' . $lastColLtr . $lastDataRow;
$sheet->getStyle($tableRange)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A' . $FIRST_DATA . ':' . $lastColLtr . $lastDataRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$sheet->getStyle('B' . $FIRST_DATA . ':B' . $lastDataRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(26);
$sheet->getColumnDimension('C')->setWidth(14);
for ($c = $FIXED_COLS + 1; $c <= $totalCols; $c++) {
    $letter = Coordinate::stringFromColumnIndex($c);
    $evt  = $events[$c - $FIXED_COLS - 1];
    $kind = $kindMeta[(string)$evt['type']] ?? $defaultKind;
    // ستون توضیحات متن بلند دارد و پهن تر است
    $sheet->getColumnDimension($letter)->setWidth($kind['input'] === 'note' ? 34 : 18);
}

// ستون ردیف و نام هنگام اسکرول افقی ثابت می مانند
$sheet->freezePane(Coordinate::stringFromColumnIndex($FIXED_COLS + 1) . $FIRST_DATA);

// ---------------------------------------------------------------
// برگه ی دوم: موارد انضباطی
// ---------------------------------------------------------------
$discSheet = $spreadsheet->createSheet();
$discSheet->setTitle('موارد انضباطی');
$discSheet->setRightToLeft(true);

$discHeaders = ['ردیف', 'نام و نام خانوادگی', 'علت', 'تاریخ ثبت'];
if ((int)$adminrole !== 3) {
    $discHeaders[] = 'ثبت کننده';
}
foreach ($discHeaders as $i => $text) {
    $letter = Coordinate::stringFromColumnIndex($i + 1);
    $discSheet->setCellValue($letter . '1', $text);
}
$discLastCol = Coordinate::stringFromColumnIndex(count($discHeaders));
$discSheet->getStyle('A1:' . $discLastCol . '1')->getFont()->setBold(true);
$discSheet->getStyle('A1:' . $discLastCol . '1')->getFill()
    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF' . $PURPLE_MID);
$discSheet->getStyle('A1:' . $discLastCol . '1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$dRow = 2;
$dNum = 1;
foreach ($students as $student) {
    $fullName = trim(($student['fname'] ?? '') . ' ' . ($student['lname'] ?? ''));
    if ($fullName === '') {
        $fullName = $student['username'];
    }
    foreach ($discipline_lookup[$student['username']] ?? [] as $disc) {
        $discSheet->setCellValue('A' . $dRow, $dNum++);
        $discSheet->setCellValue('B' . $dRow, $fullName);
        $discSheet->setCellValue('C' . $dRow, $disc['reason']);
        $discSheet->setCellValue('D' . $dRow, $disc['created_at']);
        if ((int)$adminrole !== 3) {
            $discSheet->setCellValue('E' . $dRow, $disc['created_by']);
        }
        $dRow++;
    }
}
if ($dRow === 2) {
    $discSheet->setCellValue('A2', 'موردی ثبت نشده است.');
    $discSheet->mergeCells('A2:' . $discLastCol . '2');
}
$discSheet->getColumnDimension('A')->setWidth(6);
$discSheet->getColumnDimension('B')->setWidth(26);
$discSheet->getColumnDimension('C')->setWidth(55);
$discSheet->getColumnDimension('D')->setWidth(18);
if ((int)$adminrole !== 3) {
    $discSheet->getColumnDimension('E')->setWidth(20);
}
$discSheet->getStyle('C2:C' . max(2, $dRow - 1))->getAlignment()->setWrapText(true);

$spreadsheet->setActiveSheetIndex(0);

// ---------------------------------------------------------------
// ارسال فایل
// ---------------------------------------------------------------
$fileSuffix = ($scope === 'all')
    ? 'all-' . $academicYear
    : 'week-' . jdate('Ymd', strtotime($selected_week['start']), '', 'Asia/Tehran', 'en');
$filename = 'class-register-' . $class_id . '-' . $fileSuffix . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
