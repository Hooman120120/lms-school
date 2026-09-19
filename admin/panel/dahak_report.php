<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once '../Bill/execel.php';

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
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}

function safeJalaliToGregorianDateTime(string $jalali, string $hhmm, string $fallbackDateTime): string {
    if (preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $jalali) && preg_match('/^\d{2}:\d{2}$/', $hhmm)) {
        list($jy,$jm,$jd) = explode('/', $jalali);
        $g = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd, '-');
        return $g . ' ' . $hhmm;
    }
    return $fallbackDateTime; // e.g., "YYYY-MM-DD HH:MM"
}

/* ---------------- Excel Export ----------------  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {

    $startJalali = tr_num($_POST['startdate'] ?? ($_GET['startdate'] ?? ''), 'en');
    $endJalali   = tr_num($_POST['enddate']   ?? ($_GET['enddate']   ?? ''), 'en');
    $queue       = preg_replace('/\D/', '', $_POST['queue'] ?? ($_GET['queue'] ?? '902'));
    $startHour   = $_POST['startHour'] ?? ($_GET['startHour'] ?? '00:00');
    $endHour     = $_POST['endHour']   ?? ($_GET['endHour']   ?? '23:59');

    $gEndDefault   = date('Y-m-d') . ' 23:59';
    $gStartDefault = date('Y-m-d', strtotime('-7 days')) . ' 00:00';

    $gStartFull = safeJalaliToGregorianDateTime($startJalali, $startHour, $gStartDefault);
    $gEndFull   = safeJalaliToGregorianDateTime($endJalali,   $endHour,   $gEndDefault);

    $payload = json_encode([
        "conditions" => ["from" => "", "to" => "", "cuid" => "", "qnum" => $queue],
        "date_range" => ["from" => $gStartFull, "to" => $gEndFull],
        "pagination" => ["start" => 0, "count" => 50000, "sorting" => (object)[]],
        "alike" => "true"
    ], JSON_UNESCAPED_UNICODE);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://'.SIMOTEL_SERVER.'//api/v4/reports/queue/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'X-APIKEY: '.SIMOTEL_API_KEY,
            'Authorization: Basic '.SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json'
        ],
    ]);
    $response = curl_exec($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);
    if ($response === false) { echo "<pre>cURL Error: " . htmlspecialchars($curl_error) . "</pre>"; exit; }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) { echo "<pre>JSON Decode Error: " . json_last_error_msg() . "</pre>"; exit; }
    $queueData = $data['data']['data'] ?? [];
    exportQueueExcel($queueData);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = http_build_query([
        'startdate' => tr_num($_POST['startdate'] ?? '', 'en'),
        'enddate'   => tr_num($_POST['enddate'] ?? '', 'en'),
        'queue'     => preg_replace('/\D/', '', $_POST['queue'] ?? '902'),
        'startHour' => $_POST['startHour'] ?? '00:00',
        'endHour'   => $_POST['endHour'] ?? '23:59'
    ]);
    header("Location: " . $_SERVER['PHP_SELF'] . '?' . $params);
    exit();
}

if (!isset($_GET['startdate'], $_GET['enddate'])) {
    $gEnd   = date('Y-m-d');
    $gStart = date('Y-m-d', strtotime('-7 days'));
    list($gy,$gm,$gd) = explode('-', $gStart);
    $startJalali = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd, '/');
    list($gy,$gm,$gd) = explode('-', $gEnd);
    $endJalali   = gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd, '/');
    $queue = '902';
    $startHour = '00:00';
    $endHour   = '23:59';
} else {
    $startJalali = tr_num($_GET['startdate'], 'en');
    $endJalali   = tr_num($_GET['enddate'], 'en');
    $queue = preg_replace('/\D/', '', $_GET['queue'] ?? '902');
    $startHour = $_GET['startHour'] ?? '00:00';
    $endHour   = $_GET['endHour'] ?? '23:59';
}

$gEndDefault   = date('Y-m-d') . ' 23:59';
$gStartDefault = date('Y-m-d', strtotime('-7 days')) . ' 00:00';

$gStartFull = safeJalaliToGregorianDateTime($startJalali, $startHour, $gStartDefault);
$gEndFull   = safeJalaliToGregorianDateTime($endJalali,   $endHour,   $gEndDefault);

$payload = json_encode([
    "conditions" => ["from" => "", "to" => "", "cuid" => "", "qnum" => $queue],
    "date_range" => ["from" => $gStartFull, "to" => $gEndFull],
    "pagination" => ["start" => 0, "count" => 50000, "sorting" => (object)[]],
    "alike" => "true"
], JSON_UNESCAPED_UNICODE);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://'.SIMOTEL_SERVER.'//api/v4/reports/queue/search',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'X-APIKEY: '.SIMOTEL_API_KEY,
        'Authorization: Basic '.SIMOTEL_BASIC_AUTH,
        'Content-Type: application/json'
    ],
]);
$response = curl_exec($curl);
$curl_error = curl_error($curl);
curl_close($curl);

if ($response === false) { echo "<pre>cURL Error: " . htmlspecialchars($curl_error) . "</pre>"; exit; }
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) { echo "<pre>JSON Decode Error: " . json_last_error_msg() . "</pre>"; exit; }
$queueData = $data['data']['data'] ?? [];
?>


<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>گزارش </title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <form method="post" class="row g-3 mb-3 justify-content-between">
                                <div class="col-md-2">
                                    <label class="form-label">شماره صف (Queue) <span style="color:red">*</span></label>
                                    <input class="form-control" type="text" name="queue" value="<?= htmlspecialchars($queue) ?>">
                                    <small class="form-text text-muted d-block">
                                        902: برای شماره 1<br>903: برای شماره 2
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">از تاریخ <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                        <input class="form-control fc-datepicker" type="text" name="startdate" value="<?= htmlspecialchars($startJalali) ?>">
                                        <input type="time" class="form-control" name="startHour" value="<?= htmlspecialchars($startHour) ?>">
                                    </div>
                                    <small class="form-text text-muted d-block">
                                        *ساعت و تاریخ را با دقت وارد کنید.
                                     </small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">تا تاریخ <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                        <input class="form-control fc-datepicker" type="text" name="enddate" value="<?= htmlspecialchars($endJalali) ?>">
                                        <input type="time" class="form-control" name="endHour" value="<?= htmlspecialchars($endHour) ?>">
                                    </div>
                                    <small class="form-text text-muted d-block">
                                        *ساعت و تاریخ را با دقت وارد کنید.
                                    </small>
                                </div>
                                <div class="col-md-2 d-flex mt-5 ">
                                    <button type="submit" class="btn btn-primary w-100 h-25">جستجو</button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table text-nowrap table-bordered" id="example1">
                                    <thead>
                                    <tr>
                                        <th>#</th><th>شماره تماس</th><th>مقصد</th><th>نام اپراتور</th>
                                        <th>مدت انتظار</th><th>مدت مکالمه</th><th>وضعیت</th><th>تاریخ شروع</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if ($queueData): foreach ($queueData as $i => $row): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= htmlspecialchars($row['src'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['dst'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['dst_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['wait_hhmmss'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['billsec_hhmmss'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['disposition'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['starttime'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="8" class="text-center">داده‌ای یافت نشد</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <form method="post" style="display:inline;" class="d-flex flex-row align-content-center ">
                                    <button type="submit" name="export_excel" value="1" class="btn btn-success">
                                        <i class="fa fa-file-excel-o"></i> دانلود اکسل
                                    </button> &nbsp;
                                    <small class="form-text text-muted mt-2 ">
                                         نکته : اگر شماره 09100458553 در لیست شماره ها بود حذف کنید.(شماره تستر هست)
                                    </small>
                                </form>

                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../footers.php'; ?>
</body>
</html>
