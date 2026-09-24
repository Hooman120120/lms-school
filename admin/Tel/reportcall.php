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
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_viewcall') || !Permision('reportcall_permission')) {
    header('location: ' . url('admin/index.php'));
    die();
}


function jalaliToGregorian($jalaliDate)
{
    list($jy, $jm, $jd) = explode('/', $jalaliDate);
    $jy = (int)$jy;
    $jm = (int)$jm;
    $jd = (int)$jd;
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
        if ($gd <= $months[$i]) {
            $gm = $i;
            break;
        }
        $gd -= $months[$i];
    }
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?? '';
    $startdate = filter_input(INPUT_POST, 'startdate', FILTER_SANITIZE_STRING) ?? '';
    $enddate = filter_input(INPUT_POST, 'enddate', FILTER_SANITIZE_STRING) ?? '';
    $search_type = filter_input(INPUT_POST, 'search_type', FILTER_SANITIZE_STRING) ?? 'to';
    error_log("Inputs: phone=$phone, startdate=$startdate, enddate=$enddate, search_type=$search_type");

    $errors = [];
    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }
    if (empty($startdate) || empty($enddate)) {
        $errors[] = 'Start and end dates are required.';
    }
    if (!in_array($search_type, ['to', 'from'])) {
        $errors[] = 'Invalid search type selected.';
    }
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $startdate) || !preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $enddate)) {
        $errors[] = 'Invalid date format. Use YYYY/MM/DD.';
    }

    if (!empty($errors)) {
        error_log("Validation errors: " . implode(', ', $errors));
    }

    if (empty($errors)) {
        try {
            $gregorian_start = jalaliToGregorian($startdate);
            $gregorian_end = jalaliToGregorian($enddate);
            error_log("Converted dates: start=$gregorian_start, end=$gregorian_end");

            $startdate = $gregorian_start . " 00:00";
            $enddate = $gregorian_end . " 23:59";

            // ---------- 1) search as FROM ----------
            $conditionsFrom = [
                "from" => $phone,
                "to" => "",
                "cuid" => ""
            ];

            $payloadFrom = [
                "conditions" => $conditionsFrom,
                "date_range" => ["from" => $startdate, "to" => $enddate],
                "pagination" => ["start" => 0, "count" => 50, "sorting" => ["starttime" => -1]],
                "alike" => false
            ];

            error_log("Payload FROM: " . json_encode($payloadFrom));

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '/api/v4/reports/quick/search',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payloadFrom),
                CURLOPT_HTTPHEADER => [
                    'X-APIKEY: ' . SIMOTEL_API_KEY,
                    'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
            ]);

            $responseFrom = curl_exec($curl);
            if ($responseFrom === false) {
                $curl_error = curl_error($curl);
                error_log("cURL FROM error: $curl_error");
                throw new Exception('cURL FROM error: ' . $curl_error);
            }

            $httpCodeFrom = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            error_log("HTTP status code FROM: $httpCodeFrom");
            curl_close($curl);

            if ($httpCodeFrom !== 200) {
                throw new Exception('API FROM request failed with status code: ' . $httpCodeFrom);
            }

            $resultFrom = json_decode($responseFrom, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode FROM error: " . json_last_error_msg());
                throw new Exception('Invalid JSON FROM response: ' . json_last_error_msg());
            }

            // ---------- 2) search as TO ----------
            $conditionsTo = [
                "from" => "",
                "to" => $phone,
                "cuid" => ""
            ];

            $payloadTo = [
                "conditions" => $conditionsTo,
                "date_range" => ["from" => $startdate, "to" => $enddate],
                "pagination" => ["start" => 0, "count" => 50, "sorting" => ["starttime" => -1]],
                "alike" => false
            ];

            error_log("Payload TO: " . json_encode($payloadTo));

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '/api/v4/reports/quick/search',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($payloadTo),
                CURLOPT_HTTPHEADER => [
                    'X-APIKEY: ' . SIMOTEL_API_KEY,
                    'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
            ]);

            $responseTo = curl_exec($curl);
            if ($responseTo === false) {
                $curl_error = curl_error($curl);
                error_log("cURL TO error: $curl_error");
                throw new Exception('cURL TO error: ' . $curl_error);
            }

            $httpCodeTo = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            error_log("HTTP status code TO: $httpCodeTo");
            curl_close($curl);

            if ($httpCodeTo !== 200) {
                throw new Exception('API TO request failed with status code: ' . $httpCodeTo);
            }

            $resultTo = json_decode($responseTo, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode TO error: " . json_last_error_msg());
                throw new Exception('Invalid JSON TO response: ' . json_last_error_msg());
            }

            // ---------- 3) merge FROM + TO (avoid duplicates) ----------
            $mergedCalls = [];
            $seen = [];

            // helper to add calls uniquely
            $addCalls = function ($resultPart) use (&$mergedCalls, &$seen) {
                if (
                    !is_array($resultPart) ||
                    empty($resultPart['data']) ||
                    empty($resultPart['data']['data']) ||
                    !is_array($resultPart['data']['data'])
                ) {
                    return;
                }

                foreach ($resultPart['data']['data'] as $call) {
                    // try to build a unique key for each call
                    $key = '';
                    if (isset($call['uniqueid'])) {
                        $key = 'uid:' . $call['uniqueid'];
                    } elseif (isset($call['record'])) {
                        $key = 'rec:' . $call['record'];
                    } else {
                        $key = 'combo:' .
                            ($call['starttime'] ?? '') . '|' .
                            ($call['src'] ?? '') . '|' .
                            ($call['dst'] ?? '');
                    }

                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $mergedCalls[] = $call;
                    }
                }
            };

            $addCalls($resultFrom);
            $addCalls($resultTo);

            // Put merged data in the same structure your template expects
            $result = [
                'data' => [
                    'data' => $mergedCalls
                ]
            ];

            error_log("API merged response, total calls: " . count($mergedCalls));
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            error_log("Exception: " . $e->getMessage());
        }
    }
}


// the actual download button (had to be like this)
if (isset($_GET['file'])) {
    $fileName = $_GET['file'];

    $downloadPayload = json_encode(["file" => $fileName]);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '/api/v4/reports/audio/download',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $downloadPayload,
        CURLOPT_HTTPHEADER => [
            'X-APIKEY: ' . SIMOTEL_API_KEY,
            'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
            'Content-Type: application/json'
        ],
    ]);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200) {
        die("Download failed. HTTP Code: " . $httpCode);
    }

    header('Content-Type: audio/mpeg');
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Content-Length: ' . strlen($response));
    echo $response;
    exit;
}



include_once '../../temp.html';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>گزارش تماس ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div id="global-loader"><img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری"></div>

<div class="page">
    <?php include_once '../menu.php';
    include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3" style="gap: 12px;">
                                    <h4 class="mb-0">
                                        جستجوی تماس‌ها
                                    </h4>
                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید تماس‌ها را بر اساس بازه زمانی و شماره دانش‌آموز یا اپراتور جستجو کنید. نتایج شامل شماره مبدا، شماره مقصد، زمان شروع، مدت و وضعیت تماس است و در صورت پاسخ داده‌شدن تماس، امکان دانلود فایل صوتی ضبط‌شده نیز وجود دارد."
                                          aria-label="راهنمای جستجوی تماس‌ها">
                                        <i class="fe fe-info fs-4"></i>
                                    </span>
                                </div>
                                <form method="post" class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label>از تاریخ:</label>
                                        <input type="text"
                                               class="form-control fc-datepicker"
                                               name="startdate"
                                               placeholder="1402/09/10"
                                               required
                                               value="<?php echo htmlspecialchars($_POST['startdate'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label>تا تاریخ:</label>
                                        <input type="text"
                                               class="form-control fc-datepicker"
                                               name="enddate"
                                               placeholder="1402/09/11"
                                               required
                                               value="<?php echo htmlspecialchars($_POST['enddate'] ?? ''); ?>">
                                    </div>

                                    <!-- we removed نوع جستجو, no need anymore -->

                                    <div class="col-md-3">
                                        <label>شماره (دانش‌آموز یا اپراتور):</label>
                                        <input type="text"
                                               class="form-control"
                                               name="phone"
                                               placeholder="شماره"
                                               required
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-3 align-self-end">
                                        <button type="submit" class="btn btn-primary w-100">جستجو</button>
                                    </div>
                                </form>

                                <?php if ($result && !empty($result['data']['data'])): ?>
                                    <div class="table-responsive mt-3">
                                        <table id="example1" class="table table-bordered table-hover text-center align-middle">
                                            <thead class="table-light">
                                            <tr>
                                                <th>شماره مبدا (from)</th>
                                                <th>شماره مقصد (to)</th>
                                                <th>زمان شروع</th>
                                                <th>مدت تماس</th>
                                                <th>وضعیت</th>
                                                <th>دانلود</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($result['data']['data'] as $call): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($call['src']); ?></td>
                                                    <td><?php echo htmlspecialchars($call['dst']); ?></td>
                                                    <td><?php echo htmlspecialchars($call['starttime']); ?></td>
                                                    <td><?php echo htmlspecialchars($call['billsec_hhmmss']); ?></td>
                                                    <td><?php echo htmlspecialchars($call['disposition']); ?></td>
                                                    <td>
                                                        <?php if (strtoupper($call['disposition']) === 'ANSWERED'): ?>
                                                            <a href="?file=<?php echo urlencode($call['record']); ?>" class="btn btn-sm btn-success">دانلود</a>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>

                                        </table>
                                    </div>
                                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                                    <p class="text-danger mt-3">هیچ تماسی یافت نشد.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end main-content -->

    <?php include '../../footers.php'; ?>
</div>

<script>
    $(document).ready(function() {
        $('.fc-datepicker').datepicker({ format: 'yyyy/mm/dd' });
    });
</script>
</body>
</html>
