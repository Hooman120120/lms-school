<?php
/*
 * «خروجی اکسل همین صفحه» — the calendar view on screen (week, month or day),
 * cell for cell, as an xlsx. The page sends the table it is showing; nothing is
 * read from the database here, so the file can only hold what the user already
 * sees, and the page's own visibility rules stay the only rules.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once '../../dbconfig.php';
require_once '../../inc/function_login.php';
require_once '../../inc/function.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    exit('دسترسی غیرمجاز');
}
$sessionRow = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    http_response_code(401);
    exit('نشست کاربری معتبر نیست.');
}

$data = json_decode($_POST['table'] ?? '', true);
if (!is_array($data) || empty($data['header']) || !is_array($data['rows'] ?? null)) {
    http_response_code(400);
    exit('جدولی برای خروجی دریافت نشد.');
}

/** '#9ec5e8' or 'rgb(158, 197, 232)' → '9EC5E8'; anything else → null. */
function viewColor($value): ?string
{
    $value = trim((string)$value);
    if (preg_match('/^#?([0-9a-f]{6})$/i', $value, $m)) return strtoupper($m[1]);
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m)) return strtoupper($m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3]);
    if (preg_match('/rgba?\(\s*(\d+)\D+(\d+)\D+(\d+)(?:\D+([\d.]+))?/', $value, $m)) {
        if (isset($m[4]) && (float)$m[4] === 0.0) return null;
        return sprintf('%02X%02X%02X', $m[1], $m[2], $m[3]);
    }
    return null;
}

/** Dark text on light fills, white on dark ones — same rule as the page. */
function viewTextColor(string $hex): string
{
    [$r, $g, $b] = array_map(fn($c) => hexdec($c) / 255, str_split($hex, 2));
    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    return (0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b)) > 0.4 ? '1D212F' : 'FFFFFF';
}

$cell = fn($c) => is_array($c) ? $c : ['text' => (string)$c];
$header = array_map($cell, array_slice($data['header'], 0, 60));
$rows = array_map(fn($r) => array_map($cell, array_slice((array)$r, 0, 60)), array_slice($data['rows'], 0, 400));
$cols = count($header);
$last = Coordinate::stringFromColumnIndex($cols);

$book = new Spreadsheet();
$sheet = $book->getActiveSheet();
$sheet->setTitle('تقویم');
$sheet->setRightToLeft(true);
$book->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);

// Title row: what the toolbar says (e.g. «۴ مهر – ۱۰ مهر ۱۴۰۵»).
$sheet->mergeCells("A1:{$last}1");
$sheet->setCellValue('A1', (string)($data['title'] ?? 'تقویم'));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(26);

$paint = function (string $ref, array $c, bool $bold) use ($sheet) {
    $style = $sheet->getStyle($ref);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
    if ($bold) $style->getFont()->setBold(true);
    if ($hex = viewColor($c['bg'] ?? '')) {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($hex);
        $style->getFont()->getColor()->setRGB(viewTextColor($hex));
    }
};

foreach ($header as $i => $h) {
    $ref = Coordinate::stringFromColumnIndex($i + 1) . '2';
    $sheet->setCellValueExplicit($ref, $h['text'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $paint($ref, $h, true);
}
$sheet->getRowDimension(2)->setRowHeight(24);

$widths = array_fill(0, $cols, 10);
foreach ($rows as $r => $row) {
    $excelRow = $r + 3;
    $lines = 1;
    for ($i = 0; $i < $cols; $i++) {
        $c = $row[$i] ?? ['text' => ''];
        $text = str_replace("\r", '', (string)($c['text'] ?? ''));
        $ref = Coordinate::stringFromColumnIndex($i + 1) . $excelRow;
        $sheet->setCellValueExplicit($ref, $text, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $paint($ref, $c, !empty($c['bold']));
        $parts = explode("\n", $text);
        $lines = max($lines, count($parts));
        foreach ($parts as $p) $widths[$i] = max($widths[$i], min(45, mb_strlen($p) + 4));
    }
    // Excel does not grow a row for wrapped text written by a library; size it here.
    $sheet->getRowDimension($excelRow)->setRowHeight(max(22, 15 * $lines + 6));
}
foreach ($header as $i => $h) {
    $widths[$i] = max($widths[$i], min(45, mb_strlen($h['text'] ?? '') + 4));
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($widths[$i]);
}
$sheet->freezePane('B3');

$name = preg_replace('/[^\w\-]+/u', '-', (string)($data['file'] ?? 'calendar')) ?: 'calendar';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"calendar.xlsx\"; filename*=UTF-8''" . rawurlencode($name) . '.xlsx');
header('Cache-Control: max-age=0');
(new Xlsx($book))->save('php://output');
