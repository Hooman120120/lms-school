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
use PhpOffice\PhpSpreadsheet\Style\Border;

if (CheckBlacklist_ip(GetIp())) {
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    die();
}

$username = $_SESSION['admin']['user'];
$canViewAll = false;

if (!Permision('installment_permission')) {
    die('دسترسی ندارید');
} else {
    $canViewAll = true;
}

function toEnglishNum($input) {
    if ($input === null || $input === '') return '';
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $input = str_replace($persian, $english, (string)$input);
    return str_replace($arabic, $english, $input);
}

// --- Date Filtering Logic ---
$startDateJalali = trim($_GET['start_date'] ?? '');
$endDateJalali   = trim($_GET['end_date'] ?? '');

$whereConditions = [];
$params = [];

if (!$canViewAll) {
    $whereConditions[] = "p.operator_username = :username";
    $params[':username'] = $username;
}

if (!empty($startDateJalali)) {
    $p = explode('/', str_replace('-', '/', $startDateJalali));
    if (count($p) === 3) {
        $g = jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        $whereConditions[] = "p.created_at >= :start_date";
        $params[':start_date'] = sprintf('%04d-%02d-%02d 00:00:00', $g[0], $g[1], $g[2]);
    }
}

if (!empty($endDateJalali)) {
    $p = explode('/', str_replace('-', '/', $endDateJalali));
    if (count($p) === 3) {
        $g = jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        $whereConditions[] = "p.created_at <= :end_date";
        $params[':end_date'] = sprintf('%04d-%02d-%02d 23:59:59', $g[0], $g[1], $g[2]);
    }
}

$whereSql = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Fetch Payments + Installments
try {
    $query = "
        SELECT 
            p.id AS payment_id,
            p.codemeli,
            p.total_amount,
            p.paid_amount,
            p.status AS payment_status,
            p.created_at AS payment_created_at,
            p.operator_username AS payment_operator,
            p.comment AS payment_comment,
            dp.fname, 
            dp.lname,
            CASE 
                WHEN p.status IN ('refund', 'canceled') THEN 0 
                ELSE (p.total_amount - p.paid_amount) 
            END AS balance,
            i.id AS installment_id,
            i.amount_due,
            i.due_date,
            i.paid_date,
            i.status AS installment_status,
            i.comment AS installment_comment
        FROM payments p
        LEFT JOIN dbpersonal dp ON p.codemeli = dp.codemeli
        LEFT JOIN payment_installments i ON p.id = i.payment_id
        {$whereSql}
        ORDER BY p.created_at DESC, i.due_date ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group Installments by Payment ID
    $payments = [];
    foreach ($rawRows as $row) {
        $pid = $row['payment_id'];
        if (!isset($payments[$pid])) {
            $payments[$pid] = [
                'info' => [
                    'codemeli'           => $row['codemeli'],
                    'fname'              => $row['fname'],
                    'lname'              => $row['lname'],
                    'total_amount'       => $row['total_amount'],
                    'paid_amount'        => $row['paid_amount'],
                    'balance'            => $row['balance'],
                    'payment_status'     => $row['payment_status'],
                    'payment_created_at' => $row['payment_created_at'],
                    'payment_operator'   => $row['payment_operator'],
                    'payment_comment'    => $row['payment_comment'],
                ],
                'installments' => []
            ];
        }

        if (!empty($row['installment_id'])) {
            $payments[$pid]['installments'][] = [
                'amount_due'          => $row['amount_due'],
                'due_date'            => $row['due_date'],
                'paid_date'           => $row['paid_date'],
                'installment_status'  => $row['installment_status'],
                'installment_comment' => $row['installment_comment'],
            ];
        }
    }

} catch (PDOException $e) {
    error_log("Excel query error: " . $e->getMessage());
    die("خطا در استخراج اطلاعات برای اکسل");
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setRightToLeft(true);
$sheet->setTitle('پرداخت‌ها و اقساط');

// Define Headers
$headers = [
    'کد ملی',
    'نام و نام خانوادگی',
    'مبلغ کل (تومان)',
    'پرداخت شده (تومان)',
    'باقی مانده (تومان)',
    'وضعیت پرداخت',
    'تاریخ ایجاد پرداخت',
    'ثبت‌کننده',
    'توضیحات پرداخت',
    'مبلغ قسط (تومان)',
    'تاریخ سررسید قسط',
    'تاریخ پرداخت قسط',
    'وضعیت قسط',
    'توضیحات قسط'
];

// Write Headers
foreach ($headers as $colIndex => $headerText) {
    $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
    $sheet->setCellValue("{$colLetter}1", $headerText);
}

// Header Styling
$lastHeaderCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A1:{$lastHeaderCol}1")->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F2937']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$sheet->getRowDimension(1)->setRowHeight(28);

// Status Map
$statusMap = [
    'active'   => 'فعال',
    'paid'     => 'پرداخت شده',
    'pending'  => 'در انتظار پرداخت',
    'overdue'  => 'معوقه',
    'canceled' => 'کنسل شده',
    'refund'   => 'بازگشت وجه'
];

$rowIndex = 2;
$paymentBlockToggle = false;

// Populate Data
foreach ($payments as $payment) {
    $info = $payment['info'];
    $installments = $payment['installments'];
    $installmentCount = count($installments);
    $rowCount = max(1, $installmentCount);
    $startRow = $rowIndex;
    $endRow = $startRow + $rowCount - 1;

    // Format Values
    $paymentStatus = $statusMap[$info['payment_status']] ?? $info['payment_status'];
    $paymentDate   = !empty($info['payment_created_at']) ? toEnglishNum(jdate('Y/m/d', strtotime($info['payment_created_at']), '', '', 'en')) : '-';

    // Populate Parent Payment Columns
    $sheet->setCellValueExplicit("A{$startRow}", toEnglishNum($info['codemeli'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("B{$startRow}", trim(($info['fname'] ?? '') . ' ' . ($info['lname'] ?? '')));
    $sheet->setCellValue("C{$startRow}", (float)($info['total_amount'] ?? 0));
    $sheet->setCellValue("D{$startRow}", (float)($info['paid_amount'] ?? 0));
    $sheet->setCellValue("E{$startRow}", (float)($info['balance'] ?? 0));
    $sheet->setCellValue("F{$startRow}", $paymentStatus);
    $sheet->setCellValue("G{$startRow}", $paymentDate);
    $sheet->setCellValue("H{$startRow}", $info['payment_operator'] ?? '');
    $sheet->setCellValue("I{$startRow}", $info['payment_comment'] ?? '');

    // Merge Parent Columns vertically if multiple installments exist
    if ($rowCount > 1) {
        foreach (range('A', 'I') as $col) {
            $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
        }
    }

    // Populate Child Installments
    if ($installmentCount > 0) {
        foreach ($installments as $i => $inst) {
            $currRow = $startRow + $i;
            $instStatus = $statusMap[$inst['installment_status']] ?? $inst['installment_status'];
            $dueDate    = !empty($inst['due_date']) ? toEnglishNum(jdate('Y/m/d', strtotime($inst['due_date']), '', '', 'en')) : '-';
            $paidDate   = !empty($inst['paid_date']) ? toEnglishNum(jdate('Y/m/d', strtotime($inst['paid_date']), '', '', 'en')) : '-';

            $sheet->setCellValue("J{$currRow}", (float)$inst['amount_due']);
            $sheet->setCellValue("K{$currRow}", $dueDate);
            $sheet->setCellValue("L{$currRow}", $paidDate);
            $sheet->setCellValue("M{$currRow}", $instStatus);
            $sheet->setCellValue("N{$currRow}", $inst['installment_comment'] ?? '');
        }
    } else {
        $sheet->setCellValue("J{$startRow}", '-');
        $sheet->setCellValue("K{$startRow}", '-');
        $sheet->setCellValue("L{$startRow}", '-');
        $sheet->setCellValue("M{$startRow}", '-');
        $sheet->setCellValue("N{$startRow}", '-');
    }

    // Zebra Background Toggle per Payment Group
    if ($paymentBlockToggle) {
        $sheet->getStyle("A{$startRow}:N{$endRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F9FAFB');
    }
    $paymentBlockToggle = !$paymentBlockToggle;

    $rowIndex = $endRow + 1;
}

$lastDataRow = $rowIndex - 1;

if ($lastDataRow >= 2) {
    // 1. Set Number Formats in Bulk
    $sheet->getStyle("C2:E{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("J2:J{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');

    // 2. Set Alignments in Bulk
    $sheet->getStyle("A2:N{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A2:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("F2:M{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // 3. Set Borders in Bulk
    $sheet->getStyle("A1:N{$lastDataRow}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D1D5DB']
            ]
        ]
    ]);
}

// Set Fast Explicit Column Widths (Avoids slow auto-calculation)
$columnWidths = [
    'A' => 15, 'B' => 20, 'C' => 16, 'D' => 16, 'E' => 16,
    'F' => 15, 'G' => 15, 'H' => 15, 'I' => 25, 'J' => 16,
    'K' => 15, 'L' => 15, 'M' => 15, 'N' => 25
];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Download Headers
$filename = 'installment_excel_' . date('Y_m_d_H_i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;