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

/*
 * تقویم آموزشی
 * -------------
 * برخلاف تقویم اجرایی، این صفحه به permission_calendar نیاز ندارد؛
 * هر ادمین واردشده (از جمله استادها) می تواند آن را باز کند.
 * امکان افزودن/ویرایش/حذف همچنان داخل calendar.php با همان
 * permission_calendar کنترل می شود، پس استادها فقط مشاهده می کنند.
 *
 * این متغیر باید پیش از include شدن calendar.php مقدار بگیرد تا
 * هم خروجی get_events و هم خود صفحه فقط رویدادهای «کلاس» را نشان دهد.
 */
$calendar_only_type = 2; // 2 = کلاس
$calendar_force_academic = true;

include_once 'calendar.php';
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>تقویم آموزشی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

</head>

<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <?php renderCalendar(); ?>
        </div>
    </div>
    <?php include '../../footers.php'; ?>

<script>
    // در این صفحه فقط رویدادهای «کلاس» نمایش داده می شوند،
    // پس فرم افزودن رویداد هم از ابتدا روی همین نوع تنظیم می شود.
    document.addEventListener('DOMContentLoaded', function () {
        var typeSelect = document.getElementById('eventTypeSelect');
        if (typeSelect) {
            typeSelect.value = '2';
        }
        var calendarKindSelect = document.getElementById('calendarKindSelect');
        if (calendarKindSelect) {
            calendarKindSelect.value = '1';
            calendarKindSelect.disabled = true;
        }
    });
</script>

</body>
</html>
