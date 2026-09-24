<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

require_once '../../vendor/autoload.php';
use Melipayamak\MelipayamakApi;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// -------------------- امنیت و نشست --------------------
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
if (!Permision('permision_viewcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}

// -------------------- تنظیمات --------------------
if (!defined('__USERSMS')) define('__USERSMS', 'your_username_here');
if (!defined('__PASSSMS')) define('__PASSSMS', (string) getenv('PASSSMS'));

if (!extension_loaded('soap')) {
    die('خطا: اکستنشن SOAP در PHP فعال نیست.');
}

// -------------------- توابع تبدیل تاریخ --------------------
function jalaliToGregorian($jalaliDate) {
    list($jy, $jm, $jd) = explode('/', $jalaliDate);
    $jy = (int)$jy; $jm = (int)$jm; $jd = (int)$jd;
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (int)($jy / 33) * 8 + (int)((($jy % 33) + 3) / 4) + $jd;
    $days += ($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186);
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * (int)(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $months = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) $months[2] = 29;
    $gm = 0;
    for ($i = 1; $i <= 12; $i++) {
        if ($gd <= $months[$i]) { $gm = $i; break; }
        $gd -= $months[$i];
    }
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// -------------------- دریافت شماره‌های اختصاصی (فقط از API) --------------------
$numbers = [];

try {
    $api = new MelipayamakApi(__USERSMS, __PASSSMS);
    $smsRest = $api->sms();
    $response = $smsRest->getNumbers();

    if (is_string($response)) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $response = $decoded;
        }
    }

    $api_numbers = [];
    if (is_array($response) && isset($response['Data']) && is_array($response['Data'])) {
        $api_numbers = array_column($response['Data'], 'Number');
    } elseif (is_object($response) && property_exists($response, 'Data')) {
        $data = $response->Data;
        if (is_array($data)) {
            $api_numbers = array_column($data, 'Number');
        } elseif (is_object($data) && property_exists($data, 'Data')) {
            $inner = $data->Data;
            if (is_array($inner)) {
                $api_numbers = array_column($inner, 'Number');
            }
        }
    }

    $numbers = array_values(array_filter(array_map('trim', $api_numbers)));
} catch (Exception $e) {
    error_log("Error getting numbers: " . $e->getMessage());
    $numbers = [];
}

// ===================== خروجی اکسل =====================
if (isset($_GET['export']) && $_GET['export'] === 'excel' && isset($_SESSION['excel_data'])) {
    $data = $_SESSION['excel_data'];
    if (empty($data)) {
        die('داده‌ای برای خروجی وجود ندارد.');
    }
    // ایجاد شیء Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // عنوان ستون‌ها
    $headers = ['شناسه', 'متن پیام', 'تاریخ ارسال', 'گیرنده', 'فرستنده'];
    $colIndex = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($colIndex . '1', $header);
        $sheet->getStyle($colIndex . '1')->getFont()->setBold(true);
        $sheet->getStyle($colIndex . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $colIndex++;
    }

    // پر کردن داده‌ها
    $row = 2;
    foreach ($data as $msg) {
        $sendDate = $msg->SendDate ?? '';
        $dateTime = explode('T', $sendDate);
        $datePart = $dateTime[0] ?? '';
        $timePart = isset($dateTime[1]) ? substr($dateTime[1], 0, 8) : '';
        if ($datePart) {
            list($gy, $gm, $gd) = explode('-', $datePart);
            if (function_exists('gregorian_to_jalali')) {
                list($jy, $jm, $jd) = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
                $jalaliDate = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
            } else {
                $jalaliDate = $datePart;
            }
        } else {
            $jalaliDate = $sendDate;
        }
        $displayTime = $timePart ?: '';
        $fullDate = $jalaliDate . ' ' . $displayTime;

        $sheet->setCellValue('A' . $row, $msg->MsgID ?? '');
        $sheet->setCellValue('B' . $row, $msg->Body ?? '');
        $sheet->setCellValue('C' . $row, $fullDate);
        $sheet->setCellValue('D' . $row, $msg->Receiver ?? '');
        $sheet->setCellValue('E' . $row, $msg->Sender ?? '');
        $row++;
    }

    // تنظیم عرض ستون‌ها به صورت خودکار
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // ایجاد فایل و دانلود
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="sms_export_' . date('Ymd_His') . '.xlsx"');
    $writer->save('php://output');
    exit;
}
// =====================================================

// -------------------- پردازش فرم جستجو --------------------
$result = null;
$errors = [];
$selectedLocation = -1;
$selectedPhone = '';
$totalCount = 0;

// مقادیر پیش‌فرض ساعت و دقیقه
$startHour = '00';
$startMinute = '00';
$endHour = '23';
$endMinute = '59';

// بررسی اینکه آیا کاربر روی دکمه جستجو کلیک کرده یا نه
$isSearchRequest = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_submit']);

if ($isSearchRequest) {
    $selectedPhone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $startdate = isset($_POST['startdate']) ? trim($_POST['startdate']) : '';
    $enddate = isset($_POST['enddate']) ? trim($_POST['enddate']) : '';
    $startHour = isset($_POST['start_hour']) ? trim($_POST['start_hour']) : '00';
    $startMinute = isset($_POST['start_minute']) ? trim($_POST['start_minute']) : '00';
    $endHour = isset($_POST['end_hour']) ? trim($_POST['end_hour']) : '23';
    $endMinute = isset($_POST['end_minute']) ? trim($_POST['end_minute']) : '59';
    $location = isset($_POST['location']) ? (int)$_POST['location'] : -1;
    $selectedLocation = $location;

    // ترکیب ساعت و دقیقه به فرمت HH:MM
    $startTime = sprintf('%02d:%02d', (int)$startHour, (int)$startMinute);
    $endTime = sprintf('%02d:%02d', (int)$endHour, (int)$endMinute);

    // اگر شماره انتخاب شده در لیست اختصاصی نیست، آن را خالی در نظر بگیر (معادل همه سرشماره‌ها)
    if (!empty($selectedPhone) && !in_array($selectedPhone, $numbers)) {
        $selectedPhone = '';
    }

    // اعتبارسنجی
    if (empty($startdate) || empty($enddate)) {
        $errors[] = 'تاریخ شروع و پایان الزامی است.';
    }
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $startdate) || !preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $enddate)) {
        $errors[] = 'فرمت تاریخ نامعتبر است. از YYYY/MM/DD استفاده کنید.';
    }
    if (!is_numeric($startHour) || !is_numeric($startMinute) || !is_numeric($endHour) || !is_numeric($endMinute)) {
        $errors[] = 'ساعت و دقیقه باید عدد باشند.';
    }
    if ($startHour < 0 || $startHour > 23 || $endHour < 0 || $endHour > 23 || $startMinute < 0 || $startMinute > 59 || $endMinute < 0 || $endMinute > 59) {
        $errors[] = 'ساعت باید بین ۰ تا ۲۳ و دقیقه بین ۰ تا ۵۹ باشد.';
    }

    if (empty($errors)) {
        try {
            // تبدیل تاریخ شمسی به میلادی
            $gregorian_start_date = jalaliToGregorian($startdate);
            $gregorian_end_date = jalaliToGregorian($enddate);

            // ترکیب تاریخ و ساعت با فرمت ISO 8601 (استفاده از 'T' به جای فاصله)
            $gregorian_start = $gregorian_start_date . 'T' . $startTime . ':00';
            $gregorian_end = $gregorian_end_date . 'T' . $endTime . ':59';

            $api = new MelipayamakApi(__USERSMS, __PASSSMS);
            $sms = $api->sms('soap');

            // از from خالی استفاده می‌کنیم تا همه پیام‌ها را بگیریم (فیلتر در سمت سرور انجام می‌شود)
            $response = $sms->getMessagesByDate($location, 0, 100, $selectedPhone, $gregorian_start, $gregorian_end);

            // استخراج پیام‌ها
            $messages = [];
            if (is_object($response) && property_exists($response, 'MessagesBL')) {
                $messages = $response->MessagesBL;
            } elseif (is_object($response) && property_exists($response, 'GetMessagesByDateResult')) {
                $messages = $response->GetMessagesByDateResult;
            } elseif (is_array($response)) {
                $messages = $response;
            }
            if (is_object($messages) && property_exists($messages, 'MessagesBL')) {
                $messages = $messages->MessagesBL;
            }

            // اگر $messages یک شیء باشد، آن را به آرایه تبدیل کن
            if (is_object($messages)) {
                $messages = [$messages];
            }

            if (is_array($messages) && count($messages) > 0) {
                // فیلتر بر اساس شماره انتخاب شده (Sender یا Receiver)
                if (!empty($selectedPhone)) {
                    $filtered = [];
                    foreach ($messages as $msg) {
                        $sender = isset($msg->Sender) ? trim($msg->Sender) : '';
                        $receiver = isset($msg->Receiver) ? trim($msg->Receiver) : '';
                        if ($sender == $selectedPhone || $receiver == $selectedPhone) {
                            $filtered[] = $msg;
                        }
                    }
                    $messages = $filtered;
                }

                if (count($messages) > 0) {
                    $result = $messages;
                    $totalCount = count($messages);
                    // ذخیره در سشن برای خروجی اکسل
                    $_SESSION['excel_data'] = $result;
                } else {
                    $result = [];
                    $errors[] = !empty($selectedPhone) ? 'هیچ پیامکی برای شماره انتخاب شده یافت نشد.' : 'هیچ پیامکی در بازه زمانی انتخاب شده یافت نشد.';
                    unset($_SESSION['excel_data']);
                }
            } else {
                $result = [];
                $errors[] = 'هیچ پیامکی در بازه زمانی انتخاب شده یافت نشد.';
                unset($_SESSION['excel_data']);
            }

            // هشدار درباره محدودیت ۱۰۰ تایی
            if (is_array($messages) && count($messages) == 100 && !empty($selectedPhone)) {
                $errors[] = 'توجه: حداکثر ۱۰۰ پیام قابل دریافت است. برای مشاهده بیشتر، تاریخ را محدودتر کنید.';
            }

        } catch (Exception $e) {
            $errors[] = 'خطا در ارتباط با وب سرویس: ' . $e->getMessage();
            unset($_SESSION['excel_data']);
        }
    } else {
        unset($_SESSION['excel_data']);
    }
} else {
    // در صورت عدم ارسال POST یا عدم کلیک روی دکمه جستجو، سشن را پاک نکنید
    // تا داده‌های قبلی برای خروجی اکسل باقی بماند
}

// دریافت تعداد پیامک‌های خوانده‌نشده برای نمایش روی دکمه
$unreadStmt = $conn->query("SELECT COUNT(*) FROM sms_inbox WHERE is_read = 0");
$unreadCount = $unreadStmt->fetchColumn();

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>مدیریت پیامک‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .safe-call-menu {
            margin-top: 30px;
            justify-content: center;
        }
        .safe-call-card {
            display: block;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            background: #f7f7f7;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.2s ease-in-out;
            height: 120px;
            text-decoration: none !important;
        }
        .safe-call-card:hover {
            text-decoration: none;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            background: #ffffff;
        }
        .safe-call-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .safe-call-card-desc {
            font-size: 13px;
            color: #666;
        }
        .menu-info-trigger {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }
        .badge-unread {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 10px;
            font-size: 14px;
            margin-right: 5px;
        }
        .msg-body { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
        .modal-body { white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow-y: auto; }
        .table-responsive { overflow-x: auto; }
        .time-select-group { display: flex; align-items: center; gap: 5px; direction: ltr; }
        .time-select-group select { width: 60px; padding: 0.375rem 0.2rem; }
        .time-select-group span { font-weight: bold; font-size: 1.2rem; }
        .export-btn { margin-right: 10px; }
        .search-container {
            display: none;
        }
        .search-container.active {
            display: block;
        }
        .back-to-menu {
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="main-body leftmenu">
<div id="global-loader"><img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری"></div>
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- ===== منوی دکمه‌ها ===== -->
                <p class="p-2" style="font-size: large; font-weight: bold;">مدیریت پیامک‌ها</p>
                <div class="row row-sm safe-call-menu">

                    <!-- دکمه ۱: گزارش کل پیامک‌ها -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="#" class="safe-call-card" onclick="toggleSearchSection(); return false;">
                            <div class="safe-call-card-title">
                                <span> گزارش کل پیامک‌ها</span>
                                <span class="menu-info-trigger"
                                      data-tooltip="جستجو و مشاهده تمام پیامک‌های ارسالی و دریافتی بر اساس بازه زمانی، شماره و نوع پیامک. قابلیت خروجی اکسل نیز در این بخش وجود دارد."
                                      aria-label="راهنمای گزارش کل پیامک‌ها"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <div class="safe-call-card-desc">
                                مشاهده و جستجوی تمام پیامک‌ها
                            </div>
                        </a>
                    </div>

                    <!-- دکمه ۲: پیامک‌های خوانده نشده -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/notifications/unread_sms.php'); ?>" class="safe-call-card">
                            <div class="safe-call-card-title">
                                <span> پیامک‌های خوانده نشده</span>
                                <span class="menu-info-trigger"
                                      data-tooltip="مشاهده پیامک‌های دریافتی جدید که هنوز خوانده نشده‌اند. امکان علامت‌گذاری به عنوان خوانده شده و حذف پیام وجود دارد."
                                      aria-label="راهنمای پیامک‌های خوانده نشده"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge-unread"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="safe-call-card-desc">
                                مدیریت پیامک‌های جدید دریافتی
                            </div>
                        </a>
                    </div>

                    <!-- دکمه ۳: ارسال پیامک انبوه -->
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                        <a href="<?php echo url('admin/Tel/bulk_sms.php'); ?>" class="safe-call-card">
                            <div class="safe-call-card-title">
                                <span> ارسال پیامک انبوه</span>
                                <span class="menu-info-trigger"
                                      data-tooltip="ارسال پیامک به تعداد زیادی از مخاطبان به صورت یکجا. امکان ارسال فوری یا زمان‌بندی شده با انتخاب سرشماره و وارد کردن شماره‌ها و متن پیام."
                                      aria-label="راهنمای ارسال پیامک انبوه"
                                      onclick="event.preventDefault(); event.stopPropagation();">
                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <div class="safe-call-card-desc">
                                ارسال پیامک به تعداد زیاد
                            </div>
                        </a>
                    </div>

                </div>
                <!-- ========================================= -->

                <!-- ===== بخش جستجو (قابل نمایش/مخفی‌سازی) ===== -->
                <div id="searchSection" class="<?php echo ($isSearchRequest) ? 'active' : ''; ?> search-container" style="display: <?php echo ($isSearchRequest) ? 'block' : 'none'; ?>;">
                    <div class="row row-sm mt-4">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="mb-0">جستجوی پیامک‌ها</h4>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleSearchSection();">
                                            <i class="fa fa-times"></i> بستن
                                        </button>
                                    </div>
                                    <form method="post" class="row g-3 mb-3">
                                        <input type="hidden" name="search_submit" value="1">
                                        <div class="col-md-2">
                                            <label>از تاریخ:</label>
                                            <input type="text" class="form-control fc-datepicker" name="startdate"
                                                   placeholder="1402/09/10" required
                                                   value="<?php echo isset($_POST['startdate']) ? htmlspecialchars($_POST['startdate']) : ''; ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label>ساعت شروع:</label>
                                            <div class="time-select-group">
                                                <select class="form-control" name="start_hour">
                                                    <?php for ($i = 0; $i <= 23; $i++): ?>
                                                        <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($startHour == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                                            <?php echo sprintf('%02d', $i); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span>:</span>
                                                <select class="form-control" name="start_minute">
                                                    <?php for ($i = 0; $i <= 59; $i++): ?>
                                                        <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($startMinute == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                                            <?php echo sprintf('%02d', $i); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label>تا تاریخ:</label>
                                            <input type="text" class="form-control fc-datepicker" name="enddate"
                                                   placeholder="1402/09/11" required
                                                   value="<?php echo isset($_POST['enddate']) ? htmlspecialchars($_POST['enddate']) : ''; ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label>ساعت پایان:</label>
                                            <div class="time-select-group">
                                                <select class="form-control" name="end_hour">
                                                    <?php for ($i = 0; $i <= 23; $i++): ?>
                                                        <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($endHour == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                                            <?php echo sprintf('%02d', $i); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span>:</span>
                                                <select class="form-control" name="end_minute">
                                                    <?php for ($i = 0; $i <= 59; $i++): ?>
                                                        <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($endMinute == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                                            <?php echo sprintf('%02d', $i); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label>نوع پیامک:</label>
                                            <select class="form-control" name="location">
                                                <option value="-1" <?php echo ($selectedLocation == -1) ? 'selected' : ''; ?>>همه</option>
                                                <option value="1" <?php echo ($selectedLocation == 1) ? 'selected' : ''; ?>>دریافتی</option>
                                                <option value="2" <?php echo ($selectedLocation == 2) ? 'selected' : ''; ?>>ارسالی</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label>شماره (فرستنده/گیرنده):</label>
                                            <select class="form-control" name="phone" id="phoneSelect">
                                                <option value="">همه سرشماره‌ها</option>
                                                <?php foreach ($numbers as $num): ?>
                                                    <option value="<?php echo htmlspecialchars($num); ?>" <?php echo ($selectedPhone == $num) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($num); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2 align-self-end">
                                            <button type="submit" class="btn btn-primary w-100">جستجو</button>
                                        </div>
                                    </form>

                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
                                    <?php endif; ?>

                                    <?php if ($result && count($result) > 0): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span>تعداد نتایج: <?php echo count($result); ?></span>
                                            <a href="?export=excel" class="btn btn-success btn-sm export-btn">
                                                <i class="fa fa-file-excel-o"></i> خروجی اکسل
                                            </a>
                                        </div>
                                        <div class="table-responsive mt-3">
                                            <table id="example1" class="table table-bordered table-hover text-center align-middle">
                                                <thead class="table-light"><tr><th>شناسه</th><th>متن پیام</th><th>تاریخ ارسال</th><th>گیرنده</th><th>فرستنده</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($result as $msg):
                                                    $sendDate = $msg->SendDate ?? '';
                                                    $dateTime = explode('T', $sendDate);
                                                    $datePart = $dateTime[0] ?? '';
                                                    $timePart = isset($dateTime[1]) ? substr($dateTime[1], 0, 8) : '';
                                                    if ($datePart) {
                                                        list($gy, $gm, $gd) = explode('-', $datePart);
                                                        if (function_exists('gregorian_to_jalali')) {
                                                            list($jy, $jm, $jd) = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd);
                                                            $jalaliDate = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
                                                        } else {
                                                            $jalaliDate = $datePart;
                                                        }
                                                    } else {
                                                        $jalaliDate = $sendDate;
                                                    }
                                                    $displayTime = $timePart ?: '';
                                                    $body = $msg->Body ?? '';
                                                    $shortBody = mb_substr($body, 0, 30, 'UTF-8');
                                                    $isLong = mb_strlen($body, 'UTF-8') > 30;
                                                    $msgId = $msg->MsgID ?? 0;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($msgId); ?></td>
                                                        <td>
                                                            <span class="msg-body" title="<?php echo htmlspecialchars($body); ?>">
                                                                <?php echo htmlspecialchars($shortBody) . ($isLong ? '...' : ''); ?>
                                                            </span>
                                                            <button type="button" class="btn btn-sm btn-info view-msg" data-msgbody="<?php echo htmlspecialchars($body); ?>">
                                                                <i class="fa fa-eye"></i> مشاهده کامل
                                                            </button>
                                                        </td>
                                                        <td><?php echo $jalaliDate . ' ' . $displayTime; ?></td>
                                                        <td><?php echo htmlspecialchars($msg->Receiver ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($msg->Sender ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php if (count($result) == 100): ?>
                                                <div class="alert alert-warning mt-2">حداکثر ۱۰۰ پیام قابل نمایش است. برای مشاهده بیشتر، بازه تاریخی را محدودتر کنید.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($isSearchRequest && empty($errors)): ?>
                                        <p class="text-warning mt-3">هیچ پیامکی یافت نشد.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ============================================= -->

            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<!-- مودال -->
<div class="modal fade" id="msgModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">متن کامل پیام</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="بستن">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="msgFullBody">در حال بارگذاری...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSearchSection() {
        var section = document.getElementById('searchSection');
        if (section.style.display === 'none' || section.style.display === '') {
            section.style.display = 'block';
            section.classList.add('active');
            // اسکرول به بخش جستجو
            setTimeout(function() {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        } else {
            section.style.display = 'none';
            section.classList.remove('active');
        }
    }

    $(document).ready(function() {
        $('.fc-datepicker').datepicker({ format: 'yyyy/mm/dd' });

        $(document).on('click', '.view-msg', function() {
            var fullBody = $(this).data('msgbody');
            $('#msgFullBody').html(fullBody || 'متن پیام موجود نیست.');
            $('#msgModal').modal('show');
        });

        $('#msgModal .close, #msgModal .btn-secondary').on('click', function() {
            $('#msgModal').modal('hide');
        });

        // اگر قبلاً جستجو انجام شده بود، بخش جستجو باز باشد
        <?php if ($isSearchRequest): ?>
        // بخش جستجو قبلاً با کلاس active نمایش داده شده
        <?php endif; ?>
    });
</script>
</body>
</html>