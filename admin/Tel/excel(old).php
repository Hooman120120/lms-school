<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// Start Check Session JUST ONE Session
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

set_time_limit(600);
ini_set('max_execution_time', '600');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['export_excel'])) {
    die('درخواست نامعتبر است');
}

$ids = $_SESSION['temporary_personal_export_ids'] ?? [];

$ids = array_values(array_filter(array_map('intval', $ids), function ($id) {
    return $id > 0;
}));

if (empty($ids)) {
    die('هیچ داده‌ای برای خروجی اکسل وجود ندارد');
}

/*
|--------------------------------------------------------------------------
| گرفتن اطلاعات همان لیستی که در صفحه نمایش داده شده
|--------------------------------------------------------------------------
*/

$personsById = [];

$chunks = array_chunk($ids, 900);

foreach ($chunks as $chunk) {
    $placeholders = implode(',', array_fill(0, count($chunk), '?'));

    $stmt = $conn->prepare("
        SELECT 
            id,
            first_name,
            last_name,
            phone,
            state,
            city,
            gender,
            school,
            grade,
            major
        FROM temporary_personal
        WHERE id IN ($placeholders)
    ");

    $stmt->execute($chunk);

    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
        $personsById[(int)$row->id] = $row;
    }
}

// حفظ ترتیب دقیق همان لیستی که در صفحه نمایش داده شده
$persons = [];

foreach ($ids as $id) {
    if (isset($personsById[$id])) {
        $persons[] = $personsById[$id];
    }
}

if (empty($persons)) {
    die('هیچ داده‌ای برای خروجی اکسل یافت نشد');
}

/*
|--------------------------------------------------------------------------
| ساخت فایل اکسل
|--------------------------------------------------------------------------
*/

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('لیست افراد');
$sheet->setRightToLeft(true);

$spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);

$headers = [
    'A1' => 'ردیف',
    'B1' => 'نام',
    'C1' => 'نام خانوادگی',
    'D1' => 'شماره تماس',
    'E1' => 'استان',
    'F1' => 'شهر',
    'G1' => 'جنسیت',
    'H1' => 'مدرسه',
    'I1' => 'پایه',
    'J1' => 'رشته',
];

foreach ($headers as $cell => $title) {
    $sheet->setCellValue($cell, $title);
}

$sheet->getStyle('A1:J1')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => [
            'rgb' => 'FFFFFF',
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '198754',
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]);

$rowNumber = 2;
$index = 1;

foreach ($persons as $person) {
    $sheet->setCellValue('A' . $rowNumber, $index);
    $sheet->setCellValue('B' . $rowNumber, $person->first_name ?? '');
    $sheet->setCellValue('C' . $rowNumber, $person->last_name ?? '');

    // شماره تماس باید text باشد که صفر اول حذف نشود
    $sheet->setCellValueExplicit(
        'D' . $rowNumber,
        (string)($person->phone ?? ''),
        DataType::TYPE_STRING
    );

    $sheet->setCellValue('E' . $rowNumber, $person->state ?? '');
    $sheet->setCellValue('F' . $rowNumber, $person->city ?? '');
    $sheet->setCellValue('G' . $rowNumber, $person->gender ?? '');
    $sheet->setCellValue('H' . $rowNumber, $person->school ?? '');
    $sheet->setCellValue('I' . $rowNumber, $person->grade ?? '');
    $sheet->setCellValue('J' . $rowNumber, $person->major ?? '');

    $rowNumber++;
    $index++;
}

$lastRow = $rowNumber - 1;

$sheet->getStyle('A1:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1:J' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$sheet->getStyle('B2:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('E2:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('J2:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

foreach (range('A', 'J') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:J' . $lastRow);

/*
|--------------------------------------------------------------------------
| خروجی دانلود
|--------------------------------------------------------------------------
*/

$fileName = 'list_' . date('Y-m-d_H-i-s') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
