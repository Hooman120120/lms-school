<?php
include_once '../dbconfig.php';
define('PDF_MODE', true);

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

if (!isset($_GET['quiz'])) {
    die('Quiz ID not found');
}
if (!isset($_SESSION['auth'])) {
    die('لطفا ابتدا وارد سیستم شوید');
}

// تنظیمات mPDF برای A4 افقی و جا دادن همه چیز در یک صفحه
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L',                 // A4 افقی
    'orientation' => 'L',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'default_font' => 'xbriyaz',
    'default_font_size' => 9,           // فونت کوچیک برای جا شدن محتوا
    'directionality' => 'rtl',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
    'shrink_tables_to_fit' => 1,        // این هنوز کار می‌کنه و مهمه
]);

$mpdf->SetDirectionality('rtl');

// تنظیمات معتبر برای کنترل جدول‌ها و جلوگیری از صفحه دوم (نسخه جدید mPDF)
$mpdf->use_kwt = true;                          // کیفیت بهتر
$mpdf->ignore_table_percents = true;            // عرض جدول‌ها رو انعطاف‌پذیر می‌کنه
$mpdf->keep_table_proportions = false;          // اجازه می‌ده جدول‌ها فشرده بشن
// این خط رو کامل حذف کن:
// $mpdf->draftTable = false;  ← باعث خطا می‌شه

// گرفتن HTML
ob_start();
$_GET['pdf'] = 1;
include __DIR__ . '/print-quiz.php';
$html = ob_get_clean();

// استایل‌های خیلی جزئی اضافی (در صورت نیاز)
$extra_css = '
<style>
    body { line-height: 1.8; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 8px; border: 1px solid #ddd; }
    .title { font-size: 20pt; margin: 25px 0 20px; }
</style>
';

$mpdf->WriteHTML($extra_css, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html);

// فوتر ساده
$mpdf->SetFooter('<div style="text-align: center; font-size: 10pt; color: #555; padding-top: 10px; border-top: 1px solid #ccc;">صفحه {PAGENO} از {nbpg}</div>');

$user_id = $_SESSION['auth']['user'];
$quiz_id = $_GET['quiz'];
$filename = "کارنامه_{$user_id}_{$quiz_id}.pdf";

$mpdf->Output($filename, 'D');
exit;
