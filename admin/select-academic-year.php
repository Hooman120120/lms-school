<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: login.php');
    exit;
}
if (!inst_is('school')) {
    http_response_code(404);
    exit('بایگانی سال تحصیلی در نسخه آموزشگاهی فعال نیست.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['academic_year_token'] ?? '', $_POST['token'] ?? '')) {
    http_response_code(403);
    exit('درخواست نامعتبر است.');
}

$currentYear = getSchoolAcademicYearRange(time())['base_jalali_year'];
$year = isset($_POST['academic_year']) ? filter_var($_POST['academic_year'], FILTER_VALIDATE_INT) : false;
if ($year === false || $year < 1400 || $year > $currentYear) {
    $_SESSION['flash_message'] = 'سال تحصیلی انتخاب‌شده معتبر نیست.';
} elseif ((int)$year === (int)$currentYear) {
    setSelectedAcademicYear(null);
    $_SESSION['flash_message'] = 'نمای سال تحصیلی جاری فعال شد.';
} else {
    setSelectedAcademicYear((int)$year);
    $_SESSION['flash_message'] = 'نمای بایگانی سال تحصیلی تغییر کرد.';
}

$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
