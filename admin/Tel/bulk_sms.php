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
if (!defined('__USERSMS')) define('__USERSMS', '0912794563');
if (!defined('__PASSSMS')) define('__PASSSMS', (string) getenv('SMS_PASSWORD'));

if (!extension_loaded('soap')) {
    die('خطا: اکستنشن SOAP در PHP فعال نیست.');
}

// ===== شماره فرستنده ثابت برای ارسال انبوه =====
$BULK_SENDER_NUMBER = '2170007653';

// ===== تابع تبدیل تاریخ شمسی به میلادی =====
function convertJalaliToGregorianForBulk($jalaliDate, $hour = '00', $minute = '00') {
    if (empty($jalaliDate)) return '';
    list($jy, $jm, $jd) = explode('/', $jalaliDate);
    $jy = (int)$jy; $jm = (int)$jm; $jd = (int)$jd;

    if (function_exists('jalali_to_gregorian')) {
        list($gy, $gm, $gd) = jalali_to_gregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02dT%02d:%02d:00', $gy, $gm, $gd, (int)$hour, (int)$minute);
    } else {
        return sprintf('%04d-%02d-%02dT%02d:%02d:00', $jy + 621, $jm, $jd, (int)$hour, (int)$minute);
    }
}

// ===== تابع دریافت متن وضعیت =====
function getStatusText($code) {
    $statuses = [
        0 => 'ارسال شده به مخابرات',
        1 => 'رسیده به گوشی',
        2 => 'نرسیده به گوشی',
        3 => 'خطای مخابراتی',
        5 => 'خطای نامشخص',
        8 => 'رسیده به مخابرات',
        16 => 'نرسیده به مخابرات',
        35 => 'لیست سیاه',
        100 => 'نامشخص',
        200 => 'ارسال شده',
        300 => 'فیلتر شده',
        400 => 'در لیست ارسال',
        500 => 'عدم پذیرش'
    ];
    return $statuses[$code] ?? 'نامشخص (' . $code . ')';
}

// ===== تابع دریافت رنگ وضعیت =====
function getStatusColor($code) {
    $colors = [
        0 => 'success',
        1 => 'success',
        2 => 'danger',
        3 => 'warning',
        5 => 'danger',
        8 => 'success',
        16 => 'danger',
        35 => 'danger',
        100 => 'secondary',
        200 => 'success',
        300 => 'danger',
        400 => 'info',
        500 => 'warning'
    ];
    return $colors[$code] ?? 'secondary';
}

// ===== دریافت گروه‌های تماس =====
$telGroups = [];
try {
    $sql = 'SELECT id, namegroup FROM tel_group ORDER BY namegroup ASC';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $telGroups = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Error getting tel groups: " . $e->getMessage());
}

// ===== دریافت پایه‌ها =====
$courses = [];
try {
    $sql = 'SELECT codecourse, coursename FROM codtbl_course ORDER BY coursename ASC';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Error getting courses: " . $e->getMessage());
}

// ===== دریافت لیست ارسال‌های قبلی برای نمایش وضعیت =====
$bulkHistory = [];
try {
    $sql = "SELECT * FROM sms_bulk_log ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $bulkHistory = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    error_log("Error getting bulk history: " . $e->getMessage());
}

// ===== پردازش فرم ارسال =====
$messageResult = null;
$errorMessage = '';
$bulkId = null;
$statusResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_bulk_sms'])) {
    if (!isset($_SESSION['token']) || $_SESSION['token'] != $_POST['token']) {
        $errorMessage = 'خطای امنیتی! لطفاً مجدداً تلاش کنید.';
    } else {
        $senderNumber = $BULK_SENDER_NUMBER;
        $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
        $sendType = isset($_POST['send_type']) ? $_POST['send_type'] : 'manual';
        $selectedGroup = isset($_POST['tel_group']) ? (int)$_POST['tel_group'] : 0;
        $selectedCourse = isset($_POST['codecourse']) ? trim($_POST['codecourse']) : '';

        $dateToSend = isset($_POST['date_to_send']) ? trim($_POST['date_to_send']) : '';
        $hourToSend = isset($_POST['hour_to_send']) ? trim($_POST['hour_to_send']) : '';
        $minuteToSend = isset($_POST['minute_to_send']) ? trim($_POST['minute_to_send']) : '';

        if (empty($messageText)) {
            $errorMessage = 'لطفاً متن پیام را وارد کنید.';
        } else {
            $receivers = [];

            if ($sendType === 'manual') {
                $manualNumbers = isset($_POST['manual_numbers']) ? trim($_POST['manual_numbers']) : '';
                if (!empty($manualNumbers)) {
                    $manualNumbers = preg_replace('/[^0-9,\n]/', '', $manualNumbers);
                    $manualNumbers = preg_replace('/\s+/', '', $manualNumbers);
                    $receivers = array_filter(array_map('trim', explode(',', $manualNumbers)));
                }
            } elseif ($sendType === 'tel_group' && $selectedGroup > 0) {
                $sql = "SELECT mob1, mob2, mob3, tel1, tel2, tel3 FROM tel_db WHERE `group` = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$selectedGroup]);
                $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                foreach ($rows as $row) {
                    if (!empty($row->mob1)) $receivers[] = $row->mob1;
                    if (!empty($row->mob2)) $receivers[] = $row->mob2;
                    if (!empty($row->mob3)) $receivers[] = $row->mob3;
                    if (!empty($row->tel1)) $receivers[] = $row->tel1;
                    if (!empty($row->tel2)) $receivers[] = $row->tel2;
                    if (!empty($row->tel3)) $receivers[] = $row->tel3;
                }
            } elseif ($sendType === 'course' && !empty($selectedCourse)) {
                $sql = "SELECT p.tel1
                        FROM dbcourse d 
                        INNER JOIN dbpersonal p ON d.username = p.codemeli 
                        WHERE d.codecourse = ? AND d.activityuser = 1";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$selectedCourse]);
                $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                foreach ($rows as $row) {
//                    if (!empty($row->mob1)) $receivers[] = $row->mob1;
//                    if (!empty($row->mob2)) $receivers[] = $row->mob2;
                    if (!empty($row->tel1)) $receivers[] = $row->tel1;
                }
            }

            $receivers = array_unique(array_filter($receivers));
            $receivers = array_values($receivers);

            if (empty($receivers)) {
                $errorMessage = 'هیچ شماره‌ای برای ارسال یافت نشد.';
            } elseif (count($receivers) < 10) {
                $errorMessage = 'حداقل ۱۰ شماره برای ارسال انبوه مورد نیاز است. (' . count($receivers) . ' شماره وارد شده)';
            }  else {
                try {
                    $sendDateTime = '';
                    if (!empty($dateToSend) && !empty($hourToSend) && !empty($minuteToSend)) {
                        $sendDateTime = convertJalaliToGregorianForBulk($dateToSend, $hourToSend, $minuteToSend);
                    } else {
                        $futureTime = new DateTime('now', new DateTimeZone('Asia/Tehran'));
                        $futureTime->modify('+2 minutes');
                        $sendDateTime = $futureTime->format('Y-m-d\TH:i:s');
                    }

                    $receiversStr = implode(',', $receivers);

                    $soapClient = new SoapClient("https://api.payamak-panel.com/post/newbulks.asmx?wsdl", [
                        'encoding' => 'UTF-8',
                        'trace' => true,
                        'exceptions' => true,
                        'soap_version' => SOAP_1_1
                    ]);

                    $params = [
                        'username' => __USERSMS,
                        'password' => __PASSSMS,
                        'from' => $senderNumber,
                        'title' => 'ارسال انبوه از پنل مدیریت - ' . date('Y-m-d H:i'),
                        'message' => $messageText,
                        'receivers' => $receiversStr,
                        'DateToSend' => $sendDateTime
                    ];

                    $response = $soapClient->AddNumberBulk($params);

                    if (isset($response->AddNumberBulkResult)) {
                        $bulkId = (int)$response->AddNumberBulkResult;
                        if ($bulkId > 0) {
                            $messageResult = '✅ پیامک‌ها با موفقیت ثبت شدند. کد پیگیری: ' . $bulkId;
                            $messageResult .= ' (تعداد گیرندگان: ' . count($receivers) . ')';

                            error_log("Bulk SMS sent. Bulk ID: $bulkId, Receivers: " . count($receivers));

                            try {
                                $logSql = "INSERT INTO sms_bulk_log (bulk_id, sender, receivers_count, message, send_date, created_at) 
                                           VALUES (?, ?, ?, ?, ?, NOW())";
                                $logStmt = $conn->prepare($logSql);
                                $logStmt->execute([$bulkId, $senderNumber, count($receivers), $messageText, $sendDateTime]);
                            } catch (Exception $e) {
                                error_log("Error saving bulk log: " . $e->getMessage());
                            }
                        } else {
                            $errorMessage = 'خطا در ارسال. کد خطا: ' . $bulkId;
                        }
                    } else {
                        $errorMessage = 'پاسخ نامعتبر از وب سرویس دریافت شد.';
                    }

                } catch (SoapFault $e) {
                    $errorMessage = 'خطا در ارتباط با وب سرویس: ' . $e->getMessage();
                    error_log("SOAP Error: " . $e->getMessage());
                } catch (Exception $e) {
                    $errorMessage = 'خطا: ' . $e->getMessage();
                }
            }
        }
    }
}

// ===== پردازش درخواست بررسی وضعیت ارسال =====
if (isset($_GET['check_status']) && isset($_GET['bulk_id'])) {
    $checkBulkId = (int)$_GET['bulk_id'];
    $statusResult = [];
    $statusError = '';

    try {
        // ===== مرحله 1: دریافت RecIdها با SOAP (بدون تغییر) =====
        $soapClient = new SoapClient("https://api.payamak-panel.com/post/newbulks.asmx?wsdl", [
            'encoding' => 'UTF-8',
            'trace' => true,
            'exceptions' => true,
            'soap_version' => SOAP_1_1
        ]);

        $receptionParams = [
            'username' => __USERSMS,
            'password' => __PASSSMS,
            'bulkId' => $checkBulkId,
            'maximumRows' => 100,
            'startRowIndex' => 0
        ];

        $receptionResponse = $soapClient->BulkReception($receptionParams);
        // استخراج RecIdها (همان کد قبلی)
        $recIds = [];
        if (isset($receptionResponse->BulkReceptionResult)) {
            $result = $receptionResponse->BulkReceptionResult->BulkReceptionExport;
            if (is_object($result)) {
                if (isset($result->int)) {
                    $recIds = [(int)$result->int];
                } else {
                    foreach ((array)$result as $key => $value) {
                        if (is_numeric($key) || $key === 'int' || $key === 'ReportID') {
                            $recIds[] = (int)$value;
                        }
                    }
                }
            } elseif (is_array($result)) {
                foreach ($result as $item) {
                    if (is_object($item)) {
                        $recIds[] = isset($item->ReportID) ? (int)$item->ReportID : (int)(string)$item;
                    } else {
                        $recIds[] = (int)$item;
                    }
                }
            } elseif (is_numeric($result)) {
                $recIds = [(int)$result];
            }
        }
        $recIds = array_unique(array_filter($recIds));
        $recIds = array_values($recIds);
        // ===== مرحله 2: دریافت وضعیت با cURL =====
        if (count($recIds) > 0) {
            // ===== ساخت رشته با کلیدهای تکراری =====
            $postFields = 'username=' . urlencode(__USERSMS) . '&password=' . urlencode(__PASSSMS);
            foreach ($recIds as $recId) {
                $postFields .= '&recIds=' . urlencode($recId);
                break;
            }

            // ===== لاگ برای دیباگ =====
            error_log("Post Fields: " . $postFields);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.payamak-panel.com/post/newbulks.asmx/GetBulkDeliveries");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: text/xml',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // ===== افزایش زمان timeout =====
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);  // 30 ثانیه برای اتصال
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);        // 120 ثانیه برای کل درخواست

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $info = curl_getinfo($ch);

            // ===== لاگ کامل =====
            error_log("HTTP Code: " . $httpCode);
            error_log("cURL Error: " . $curlError);
            error_log("Response Length: " . strlen($response));
            error_log("Info: " . print_r($info, true));

            curl_close($ch);

            if ($curlError) {
                $statusError = 'خطا در ارتباط با وب سرویس: ' . $curlError;
                error_log("cURL Error: " . $curlError);
            } elseif ($httpCode != 200) {
                $statusError = 'خطا در ارتباط با وب سرویس. کد وضعیت: ' . $httpCode;
                error_log("HTTP Error: " . $httpCode . " - Response: " . $response);
            } else {
                // ===== پردازش پاسخ XML =====
                $statusArray = [];

                $response = trim($response);
                $xml = simplexml_load_string($response);

                if ($xml === false) {
                    $statusError = 'پاسخ نامعتبر از وب سرویس دریافت شد.';
                    error_log("Invalid XML response: " . $response);
                } else {
                    foreach ($xml->int as $int) {
                        $statusArray[] = (int)$int;
                    }
                    error_log("Statuses extracted: " . print_r($statusArray, true));
                }

                if (!empty($statusArray)) {
                    foreach ($recIds as $index => $recId) {
                        $statusResult[] = [
                            'recId' => $recId,
                            'status' => $statusArray[$index] ?? null,
                            'status_text' => isset($statusArray[$index]) ? getStatusText($statusArray[$index]) : 'نامشخص',
                            'status_color' => isset($statusArray[$index]) ? getStatusColor($statusArray[$index]) : 'secondary'
                        ];
                    }
                } else {
                    $statusError = 'هنوز وضعیتی برای این ارسال ثبت نشده است. لطفاً چند دقیقه دیگر مجدداً بررسی کنید.';
                }
            }
        } else {
            $statusError = 'هیچ شناسه‌ای برای این ارسال یافت نشد.';
        }

    } catch (SoapFault $e) {
        $statusError = 'خطا در دریافت شناسه‌ها: ' . $e->getMessage();
        error_log("SOAP Fault in BulkReception: " . $e->getMessage());
    } catch (Exception $e) {
        $statusError = 'خطا: ' . $e->getMessage();
        error_log("General Error: " . $e->getMessage());
    }
}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ارسال پیامک انبوه | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .sms-card { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-right: 4px solid #6259CB; }
        .sms-card h6 { color: #6259CB; font-weight: 600; }
        .receiver-count { background: #e9ecef; padding: 5px 12px; border-radius: 20px; font-size: 14px; }
        .manual-numbers-textarea { font-family: monospace; direction: ltr; }
        .status-info { font-size: 12px; color: #6c757d; }
        .bulk-id-display { background: #e9ecef; padding: 10px 15px; border-radius: 8px; font-weight: bold; color: #6259CB; }
        .time-select-group { display: flex; align-items: center; gap: 5px; direction: ltr; }
        .time-select-group select { width: 60px; padding: 0.375rem 0.2rem; }
        .time-select-group span { font-weight: bold; font-size: 1.2rem; }
        .sender-display { background: #e9ecef; padding: 8px 15px; border-radius: 8px; font-weight: bold; color: #28a745; font-size: 18px; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 13px; }
        .history-table { font-size: 14px; }
    </style>
</head>
<body class="main-body leftmenu">
<div id="global-loader"><img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری"></div>
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0">📤 ارسال پیامک انبوه</h4>
                                    <div>
                                        <a href="<?php echo url('admin/Tel/safe_sms.php'); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-arrow-right"></i> مدیریت پیامک‌ها
                                        </a>
                                        <a href="<?php echo url('admin/notifications/unread_sms.php'); ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fa fa-inbox"></i> پیامک‌های خوانده نشده
                                        </a>
                                    </div>
                                </div>

                                <?php if (!empty($errorMessage)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($messageResult): ?>
                                    <div class="alert alert-success">
                                        <i class="fa fa-check-circle"></i> <?php echo $messageResult; ?>
                                        <?php if ($bulkId): ?>
                                            <div class="mt-2 bulk-id-display">
                                                کد پیگیری ارسال: <?php echo $bulkId; ?>
                                                <a href="?check_status=1&bulk_id=<?php echo $bulkId; ?>" class="btn btn-sm btn-info ms-2">
                                                    <i class="fa fa-refresh"></i> بررسی وضعیت
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i>
                                    شماره فرستنده برای ارسال انبوه:
                                    <span class="sender-display"><?php echo $BULK_SENDER_NUMBER; ?></span>
                                    <small class="d-block mt-1 text-muted">(این شماره به‌طور خودکار توسط سیستم پیامک برای ارسال انبوه تعیین شده است)</small>
                                </div>

                                <form method="post" class="row g-3" id="bulkSmsForm">
                                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                                    <input type="hidden" name="send_bulk_sms" value="1">

                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">نوع ارسال <span class="text-danger">*</span></label>
                                        <select class="form-control" name="send_type" id="sendTypeSelect" required>
                                            <option value="manual">📝 ورود دستی شماره‌ها</option>
                                            <option value="tel_group">👥 از گروه تماس</option>
                                            <option value="course">🎓 از دانش آموزان پایه</option>
                                        </select>
                                    </div>

                                    <div class="col-md-8">
                                        <label class="form-label fw-bold">زمان ارسال</label>
                                        <div class="row">
                                            <div class="col-4">
                                                <input type="text" class="form-control fc-datepicker" name="date_to_send" placeholder="1402/09/10">
                                            </div>
                                            <div class="col-4">
                                                <div class="time-select-group">
                                                    <select class="form-control" name="hour_to_send">
                                                        <?php for ($i = 0; $i <= 23; $i++): ?>
                                                            <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($i == 0) ? 'selected' : ''; ?>>
                                                                <?php echo sprintf('%02d', $i); ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <span>:</span>
                                                    <select class="form-control" name="minute_to_send">
                                                        <?php for ($i = 0; $i <= 59; $i++): ?>
                                                            <option value="<?php echo sprintf('%02d', $i); ?>" <?php echo ($i == 0) ? 'selected' : ''; ?>>
                                                                <?php echo sprintf('%02d', $i); ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-4 align-self-end">
                                                <small class="text-muted">خالی = ارسال فوری (حداکثر ۲ دقیقه تأخیر)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12" id="manualSection">
                                        <div class="sms-card">
                                            <h6>📝 شماره‌های دستی</h6>
                                            <p class="text-muted small">شماره‌ها را با کاما (,) یا در خطوط جداگانه وارد کنید. حداقل ۱۰ شماره مورد نیاز است.</p>
                                            <textarea class="form-control manual-numbers-textarea" name="manual_numbers" rows="5" placeholder="09123456789,09123456788,09123456787" id="manualNumbers"></textarea>
                                            <div class="mt-2 d-flex justify-content-between">
                                                <span class="receiver-count" id="manualCount">0 شماره</span>
                                                <span class="status-info">حداقل ۱۰ شماره</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12" id="telGroupSection" style="display:none;">
                                        <div class="sms-card">
                                            <h6>👥 انتخاب گروه تماس</h6>
                                            <select class="form-control select2" name="tel_group" id="telGroupSelect">
                                                <option value="">انتخاب گروه...</option>
                                                <?php foreach ($telGroups as $group): ?>
                                                    <option value="<?php echo $group->id; ?>">
                                                        <?php echo htmlspecialchars($group->namegroup); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="mt-2 d-flex justify-content-between">
                                                <span class="receiver-count" id="telGroupCount">0 شماره</span>
                                                <span class="status-info">شماره‌های موبایل و ثابت</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12" id="courseSection" style="display:none;">
                                        <div class="sms-card">
                                            <h6>🎓 انتخاب پایه</h6>
                                            <select class="form-control select2" name="codecourse" id="courseSelect">
                                                <option value="">انتخاب پایه...</option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course->codecourse; ?>">
                                                        <?php echo htmlspecialchars($course->coursename); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="mt-2 d-flex justify-content-between">
                                                <span class="receiver-count" id="courseCount">0 شماره</span>
                                                <span class="status-info">دانش‌آموزان فعال</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="sms-card">
                                            <h6>✏️ متن پیام <span class="text-danger">*</span></h6>
                                            <textarea class="form-control" name="message_text" rows="5" required placeholder="متن پیامک را وارد کنید..." id="messageText"></textarea>
                                            <div class="mt-2 d-flex justify-content-between">
                                                <span class="receiver-count" id="charCount">0 کاراکتر</span>
                                                <span class="receiver-count" id="smsCount">0 پیامک</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                            <i class="fa fa-send"></i> ارسال پیامک انبوه
                                        </button>
                                        <span class="status-info d-block mt-2">حداقل ۱۰ و حداکثر ۱۰۰,۰۰۰ شماره</span>
                                    </div>
                                </form>

                                <!-- ===== بخش نمایش وضعیت ارسال ===== -->
                                <?php if (isset($_GET['check_status']) && isset($_GET['bulk_id'])): ?>
                                    <div class="mt-4">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">📊 وضعیت ارسال (کد پیگیری: <?php echo htmlspecialchars($_GET['bulk_id']); ?>)</h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($statusError)): ?>
                                                    <div class="alert alert-danger"><?php echo $statusError; ?></div>
                                                <?php elseif (count($statusResult) > 0): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-hover">
                                                            <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>RecId</th>
                                                                <th>کد وضعیت</th>
                                                                <th>وضعیت</th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php foreach ($statusResult as $index => $item): ?>
                                                                <tr>
                                                                    <td><?php echo $index + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($item['recId']); ?></td>
                                                                    <td>
                                                                            <span class="badge bg-<?php echo $item['status_color']; ?>">
                                                                                <?php echo $item['status']; ?>
                                                                            </span>
                                                                    </td>
                                                                    <td>
                                                                            <span class="badge bg-<?php echo $item['status_color']; ?> status-badge">
                                                                                <?php echo $item['status_text']; ?>
                                                                            </span>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            <strong>مجموع:</strong> <?php echo count($statusResult); ?> پیامک
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-warning">هیچ اطلاعاتی برای این ارسال یافت نشد.</div>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <a href="?" class="btn btn-sm btn-secondary">بازگشت</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- ===== تاریخچه ارسال‌ها ===== -->
                                <?php if (count($bulkHistory) > 0): ?>
                                    <div class="mt-4">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">📋 تاریخچه ارسال‌های انبوه</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover history-table" id="example1">
                                                        <thead>
                                                        <tr>
                                                            <th>کد پیگیری</th>
                                                            <th>فرستنده</th>
                                                            <th>تعداد گیرندگان</th>
                                                            <th>تاریخ ارسال</th>
                                                            <th>عملیات</th>
                                                        </tr>
                                                        </thead>
                                                        <tbody>
                                                        <?php foreach ($bulkHistory as $log): ?>
                                                            <tr>
                                                                <td><strong><?php echo $log->bulk_id; ?></strong></td>
                                                                <td><?php echo htmlspecialchars($log->sender); ?></td>
                                                                <td><?php echo $log->receivers_count; ?></td>
                                                                <td><?php echo jdate('Y/m/d H:i', strtotime($log->created_at)); ?></td>
                                                                <td>
                                                                    <a href="?check_status=1&bulk_id=<?php echo $log->bulk_id; ?>" class="btn btn-sm btn-info">
                                                                        <i class="fa fa-refresh"></i> بررسی وضعیت
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- ===== راهنمای کدهای وضعیت ===== -->
                                <div class="mt-4">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">📊 راهنمای کدهای وضعیت ارسال</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <span class="badge bg-success">0</span> ارسال شده به مخابرات<br>
                                                    <span class="badge bg-success">1</span> رسیده به گوشی<br>
                                                    <span class="badge bg-danger">2</span> نرسیده به گوشی
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-warning">3</span> خطای مخابراتی<br>
                                                    <span class="badge bg-danger">5</span> خطای نامشخص<br>
                                                    <span class="badge bg-success">8</span> رسیده به مخابرات
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-danger">16</span> نرسیده به مخابرات<br>
                                                    <span class="badge bg-danger">35</span> لیست سیاه<br>
                                                    <span class="badge bg-warning">100</span> نامشخص
                                                </div>
                                                <div class="col-md-3">
                                                    <span class="badge bg-success">200</span> ارسال شده<br>
                                                    <span class="badge bg-danger">300</span> فیلتر شده<br>
                                                    <span class="badge bg-warning">500</span> عدم پذیرش
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <strong>کدهای خطا:</strong> 1- ارسال نشده | 2- ارسال بیش از ۱۰۰ کد | 3- نام کاربری/رمز اشتباه | 10- خطا در دریافت گزارش | 100- نامشخص
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<script>
    $(document).ready(function() {
        $('.fc-datepicker').datepicker({ format: 'yyyy/mm/dd' });

        $('#sendTypeSelect').change(function() {
            var type = $(this).val();
            $('#manualSection').hide();
            $('#telGroupSection').hide();
            $('#courseSection').hide();

            if (type === 'manual') {
                $('#manualSection').show();
            } else if (type === 'tel_group') {
                $('#telGroupSection').show();
            } else if (type === 'course') {
                $('#courseSection').show();
            }
        });

        $('#messageText').on('input', function() {
            var text = $(this).val();
            var length = text.length;
            $('#charCount').text(length + ' کاراکتر');
            var smsCount = Math.ceil(length / 160);
            $('#smsCount').text(smsCount + ' پیامک');
        });

        function loadTelGroupCount(groupId) {
            if (groupId) {
                $.ajax({
                    url: 'ajax/get_tel_group_count.php',
                    type: 'POST',
                    data: { group_id: groupId },
                    dataType: 'json',
                    success: function(res) {
                        $('#telGroupCount').text((res.count || 0) + ' شماره');
                    },
                    error: function() {
                        $('#telGroupCount').text('خطا در دریافت تعداد');
                    }
                });
            } else {
                $('#telGroupCount').text('0 شماره');
            }
        }

        function loadCourseCount(courseCode) {
            if (courseCode) {
                $.ajax({
                    url: 'ajax/get_course_students_count.php',
                    type: 'POST',
                    data: { codecourse: courseCode },
                    dataType: 'json',
                    success: function(res) {
                        $('#courseCount').text((res.count || 0) + ' شماره');
                    },
                    error: function() {
                        $('#courseCount').text('خطا در دریافت تعداد');
                    }
                });
            } else {
                $('#courseCount').text('0 شماره');
            }
        }

        $('#telGroupSelect').change(function() {
            loadTelGroupCount($(this).val());
        });

        $('#courseSelect').change(function() {
            loadCourseCount($(this).val());
        });

        $('#manualNumbers').on('input', function() {
            var text = $(this).val();
            var numbers = text.split(/[,\n\r]+/).filter(function(n) {
                return n.trim().length > 0;
            });
            var count = numbers.length;
            $('#manualCount').text(count + ' شماره');

            if (count > 0 && count < 10) {
                $('#manualCount').css('color', '#dc3545');
            } else {
                $('#manualCount').css('color', '');
            }
        });

        $('#bulkSmsForm').on('submit', function(e) {
            var sendType = $('#sendTypeSelect').val();
            var isValid = true;
            var errorMsg = '';

            if (sendType === 'manual') {
                var numbers = $('#manualNumbers').val();
                var count = numbers.split(/[,\n\r]+/).filter(function(n) {
                    return n.trim().length > 0;
                }).length;
                if (count > 0 && count < 10) {
                    isValid = false;
                    errorMsg = 'حداقل ۱۰ شماره برای ارسال انبوه مورد نیاز است. (' + count + ' شماره وارد شده)';
                }
            }

            if (!isValid) {
                e.preventDefault();
                alert(errorMsg);
                return false;
            }
        });
    });
</script>
</body>
</html>
