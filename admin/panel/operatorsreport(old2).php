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
date_default_timezone_set("Iran");
// Handle date filter form
if(isset($_POST['filter'])){
    $startJ = explode('/', $_POST['startdate']); // e.g., 1404/02/15
    $endJ   = explode('/', $_POST['enddate']);   // e.g., 1404/02/20

    $startG = jalali_to_gregorian($startJ[0], $startJ[1], $startJ[2]);
    $endG   = jalali_to_gregorian($endJ[0], $endJ[1], $endJ[2]);

    $startDate = sprintf('%04d-%02d-%02d', $startG[0], $startG[1], $startG[2]);
    $endDate   = sprintf('%04d-%02d-%02d', $endG[0], $endG[1], $endG[2]);
} else {
    $startDate = date('Y-m-d'); // today
    $endDate = date('Y-m-d');   // today

}
$startDateTime = $startDate . 'T00:00:00';
$endDateTime   = $endDate   . 'T23:59:59';

//var_dump($startDate, $endDate);

// 🔁 Helper: Convert HH:MM:SS string to seconds
function timeToSeconds($time) {
    if (!$time || !strpos($time, ':')) return 0;
    list($h, $m, $s) = explode(':', $time);
    return ($h * 3600) + ($m * 60) + $s;
}

// 🔁 Helper: Convert seconds back to HH:MM:SS
function secondsToTime($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}


function reportCallLMS2($startDate, $endDate, $op)
{
    global $conn;
    $sql = 'SELECT * FROM suport_report WHERE poshtiban=? AND datenow BETWEEN ? AND ?';
    $query = $conn->prepare($sql);
    $query->execute([$op, $startDate, $endDate]);
    $result = $query->fetchAll(PDO::FETCH_OBJ);

    $answered = $noanswered = $verifytext = $notverifytext = 0;

    foreach($result as $res){
        if($res->type == 'تماس تلفنی' && $res->status == 'موفقیت آمیز') $answered++;
        if($res->type == 'تماس تلفنی' && $res->status == 'عدم پاسخگویی') $noanswered++;
        if($res->type == 'پشتیبانی متنی' && $res->status == 'موفقیت آمیز') $verifytext++;
        if($res->type == 'پشتیبانی متنی' && $res->status == 'عدم پاسخگویی') $notverifytext++;
    }

    return [
        'LMS TOTAL VoiceCall' => $answered + $noanswered,
        'LMS VoiceCall Successful' => $answered,
        'LMS VoiceCall NOT' => $noanswered,
        'LMS TOTAL TEXTSUPPORT' => $verifytext + $notverifytext,
        'LMS TEXTSUPPORT Successful' => $verifytext,
        'LMS TEXTSUPPORT NOT' => $notverifytext
    ];
}
//convert second to time changed here (thats why im not reusing the already created function in the function.php file)
function reportCallSimotelNew($start, $end, $op, $count = 100000)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://5.202.253.246:9880/api/v4/reports/quick/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
            "conditions":{"from": "' . $op . '", "to": "", "cuid":"","type":"outgoing"},
            "date_range":{"from":"' . $start . '","to":"' . $end . '"},
            "pagination":{"start":0,"count":' . $count . ',"sorting":{}},
            "alike":"true"
        }',
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
            'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);

    $answered = 0;
    $time_answered = 0;
    $noanswered = 0;
    $time_noanswered = 0;

    for ($num = 0; $num < sizeof($response->data->data); $num++) {
        $call = $response->data->data[$num];
        if ($call->disposition == 'ANSWERED') {
            $answered++;
            $time_answered += $call->billsec;
        }
        if ($call->disposition == 'NO ANSWER' || $call->disposition == 'BUSY') {
            $noanswered++;
            $time_noanswered += $call->duration;
        }
    }

    // Unique internal helper
    $convertTime = function($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $remainingSeconds);
    };

    $time_total = $time_answered ;

    return [
        'ANSWERED' => $answered,
        'NO ANSWERED' => $noanswered,
        'TIME ANSWERED' => $convertTime($time_answered),
        'TIME NO ANSWERED' => $convertTime($time_noanswered),
        'TIME TOTAL' => $convertTime($time_total),
    ];
}

function reportIncomingSimotel($start, $end, $op, $count = 100000)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://5.202.253.246:9880/api/v4/reports/quick/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
            "conditions":{"to": "' . $op . '", "cuid":"","type":"incoming"},
            "date_range":{"from":"' . $start . '","to":"' . $end . '"},
            "pagination":{"start":0,"count":' . $count . ',"sorting":{}} ,
            "alike":"true"
        }',
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
            'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);

    $answered = 0;
    $time_answered = 0;
    $noanswered = 0;
    $time_noanswered = 0;

    if (isset($response->data->data)) {
        for ($i = 0; $i < sizeof($response->data->data); $i++) {
            $call = $response->data->data[$i];

            if ($call->disposition == 'ANSWERED') {
                $answered++;
                $time_answered += $call->billsec; // ✅ Only talk time
            }
            if ($call->disposition == 'NO ANSWER' || $call->disposition == 'BUSY') {
                $noanswered++;
                $time_noanswered += $call->duration; // Missed calls still use total duration
            }
        }
    }

    $convertTime = function($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $remainingSeconds);
    };

    $time_total = $time_answered + $time_noanswered;

    return [
        'ANSWERED' => $answered,
        'NO ANSWERED' => $noanswered,
        'TIME ANSWERED' => $convertTime($time_answered),
        'TIME NO ANSWERED' => $convertTime($time_noanswered),
        'TIME TOTAL' => $convertTime($time_total),
    ];
}


function reportCallSimotelTotal($start, $end, $op, $count = 100000)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://5.202.253.246:9880/api/v4/reports/quick/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{
		"conditions":{"from": "' . $op . '", "to": "", "cuid":"","type":"outgoing"},
		"date_range":{"from":"' . $start . '","to":"' . $end . '"},
		"pagination":{"start":0,"count":' . $count . ',"sorting":{}} ,
		"alike":"true"
	}',
        CURLOPT_HTTPHEADER => array(
            'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
            'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
            'Content-Type: application/json'
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);

    $answered = 0;
    $time_answered = 0;
    $noanswered = 0;
    $time_noanswered = 0;

    for ($num = 0; $num < sizeof($response->data->data); $num++) {
        if ($response->data->data[$num]->disposition == 'ANSWERED') {
            $answered++;
            $time_answered = $time_answered + $response->data->data[$num]->duration;
        }
        if ($response->data->data[$num]->disposition == 'NO ANSWER' || $response->data->data[$num]->disposition == 'BUSY') {
            $noanswered++;
            $time_noanswered = $time_noanswered + $response->data->data[$num]->duration;
        }
    }

    $time_total = $time_answered + $time_noanswered;

    // ✅ Use the global helper functions you defined earlier
    $hds = secondsToTime($time_answered);
    $nohds = secondsToTime($time_noanswered);
    $totalHds = secondsToTime($time_total);

    $results = array(
        'ANSWERED' => $answered,
        'NO ANSWERED' => $noanswered,
        'TIME ANSWERED' => $hds,
        'TIME NO ANSWERED' => $nohds,
        'TIME TOTAL' => $totalHds,
    );

    return $results;
}



// Fetch all operators
$sql = 'SELECT * FROM dbadmin WHERE permision_suporter = 1';
$exe = $conn->query($sql);
$operators = $exe->fetchAll(PDO::FETCH_ASSOC);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> گزارش دانش آموزان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <!-- Main Content-->
    <div class="main-content pt-0"><style>/* smooth shuffle when cards removed */  #support-cards .col-12, #support-cards .col-sm-6, #support-cards .col-lg-4 {transition: all 0.28s ease-in-out;}</style>
        <div class="container-fluid">
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                <div class="card custom-card overflow-hidden">

                    <div class="container">

                        <!-- Date Filter Header -->
                        <div class="card mb-3 p-3">
                            <form method="post" class="row g-3 align-items-end">
                                <div class="col-xl-3">
                                    <label>از تاریخ ثبت دوره: <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                        </div>
                                        <input class="form-control fc-datepicker" type="text" name="startdate"
                                               placeholder="1402/09/10" value="<?php echo isset($startJalali) ? $startJalali : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-xl-3">
                                    <label>تا تاریخ: <span style="color:red">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text"><i class="fe fe-calendar"></i></div>
                                        </div>
                                        <input class="form-control fc-datepicker" type="text" name="enddate"
                                               placeholder="1402/09/10" value="<?php echo isset($endJalali) ? $endJalali : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-xl-3">
                                    <button class="btn btn-primary" type="submit" name="filter">اعمال فیلتر</button>
                                </div>
                            </form>
                        </div>

                        <!-- Operator Cards -->
                        <div class="row">
                            <?php foreach ($operators as $op):
                                $report = reportCallLMS2($startDate, $endDate, $op['username']);
                                ?>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card custom-card bg-light shadow-sm border-0 mt-3 position-relative">
                                        <!-- Remove operator card -->
                                        <button type="button" class="btn-close position-absolute top-0 end-0 m-2"
                                                aria-label="Close" onclick="this.closest('.col-12').remove();"></button>

                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-6 my-auto">
                                                    <h6 class="mb-3 font-weight-normal"><?php echo $op['username']; ?> - گزارش پشتیبانی</h6>
                                                    <div class="text-right">
                                                        <h3 class="font-weight-bold mb-2 text-primary">گزارش</h3>
                                                        <p class="tx-13 my-auto text-muted">
                                                            <?php echo jdate('l Y/m/d'); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="col-6 my-auto">
                                                    <div class="forth circle">
                                                        <div class="chart-circle-value circle-style">
                                                            <div class="tx-16 font-weight-bold"><?php echo $report['LMS TOTAL VoiceCall']; ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- LMS Report -->
                                            <div class="row mt-3">
                                                <div class="col-12 col-md-6">
                                                    <ul class="crypto-transcation list-unstyled mg-b-0">
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body">
                                                                <p class="tx-medium mg-b-3 tx-13">تماس موفق</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall Successful']; ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body">
                                                                <p class="tx-medium mg-b-3 tx-13">عدم پاسخگویی تماس</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall NOT']; ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body">
                                                                <p class="tx-medium mg-b-3 tx-13">متنی موفق</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT Successful']; ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body">
                                                                <p class="tx-medium mg-b-3 tx-13">عدم پاسخگویی متنی</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT NOT']; ?></h5>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <!-- SIMOTEL Report  -->
                                                <?php
                                                $simotelOp = str_replace('op', '', $op['username']);

                                                // ✅ Use the new "reportCallSimotelTotal" for full outgoing time calculation
                                                $simotelReport = reportCallSimotelTotal($startDateTime, $endDateTime, $simotelOp);

                                                // ✅ Incoming report stays the same
                                                $simotelIncomingReport = reportIncomingSimotel($startDateTime, $endDateTime, $simotelOp);

                                                // 1️⃣ Convert total outgoing time (TIME TOTAL) into seconds
                                                $outgoingSeconds = timeToSeconds($simotelReport['TIME TOTAL']);

                                                // 2️⃣ Convert total incoming answered time into seconds
                                                $incomingSeconds = timeToSeconds($simotelIncomingReport['TIME ANSWERED']);

                                                // 3️⃣ Add them together
                                                $mainTotalTalkSeconds = $outgoingSeconds + $incomingSeconds;

                                                // 4️⃣ Convert back to HH:MM:SS
                                                $mainTotalTalkTime = secondsToTime($mainTotalTalkSeconds);
                                                ?>
                                                <div class="col-12 col-md-6">
                                                    <div class="card custom-card overflow-hidden shadow-sm">
                                                        <ul class="crypto-transcation list-unstyled mg-b-0 mb-0">
                                                            <li class="list-item d-flex justify-content-between align-items-center mb-0 pl-3 pr-3 mt-0 pb-3">
                                                                <div>
                                                                    <p class="tx-medium mg-b-3 tx-13 mb-0">پاسخ داده شده</p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-900 mb-0">تعداد: <?php echo $simotelReport['ANSWERED']; ?></p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-500 mb-0">زمان: <?php echo $simotelReport['TIME ANSWERED']; ?></p>
                                                                </div>
                                                            </li>

                                                            <li class="list-item d-flex justify-content-between align-items-center mb-0 pl-3 pr-3 mt-0 pb-3">
                                                                <div>
                                                                    <p class="tx-medium mg-b-3 tx-13 mb-0">عدم پاسخ</p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-900 mb-0">تعداد: <?php echo $simotelReport['NO ANSWERED']; ?></p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-500 mb-0">زمان: <?php echo $simotelReport['TIME NO ANSWERED']; ?></p>
                                                                </div>
                                                            </li>

                                                            <li class="list-item d-flex justify-content-between align-items-center mb-0 pl-3 pr-3 mt-0 pb-3">
                                                                <div>
                                                                    <p class="tx-medium mg-b-3 tx-13 mb-0"> تماس های ورودی پاسخ داده شده</p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-900 mb-0">تعداد: <?php echo $simotelIncomingReport['ANSWERED']; ?></p>
                                                                    <p class="tx-11 mg-b-0 tx-gray-500 mb-0">زمان: <?php echo $simotelIncomingReport['TIME ANSWERED']; ?></p>
                                                                </div>
                                                            </li>
                                                            <li class="list-item d-flex justify-content-between align-items-center mb-0 pl-3 pr-3 mt-0 pb-3">
                                                                <div>
                                                                    <p class="tx-medium mg-b-3 tx-13 mb-0">کل تماس ها</p>
                                                                    <?php
                                                                    $totalAnsweredSeconds = timeToSeconds($simotelReport['TIME ANSWERED']) + timeToSeconds($simotelIncomingReport['TIME ANSWERED']);
                                                                    $totalAnsweredTime = secondsToTime($totalAnsweredSeconds);
                                                                    ?>
                                                                    <p class="tx-11 mg-b-0 tx-gray-900 mb-0">زمان: <?php echo $totalAnsweredTime; ?></p>
                                                                </div>
                                                            </li>
                                                            <li class="list-item d-flex justify-content-between align-items-center mb-0 pl-3 pr-3 mt-0 pb-3">
                                                                <div>
                                                                    <p class="tx-medium mg-b-3 tx-13 mb-0">کل تماس های اصلی</p>
                                                                    <?php
                                                                    $totalMainSeconds = timeToSeconds($simotelReport['TIME TOTAL']) + timeToSeconds($simotelIncomingReport['TIME ANSWERED']);
                                                                    $totalMainTime = secondsToTime($totalMainSeconds);
                                                                    ?>
                                                                    <p class="tx-11 mg-b-0 tx-gray-900 mb-0">زمان: <?php echo $totalMainTime; ?></p>
                                                                </div>
                                                            </li>


                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- end LMS + SIMOTEL row -->
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                                    </div> <!-- card -->
                                </div> <!-- col -->
                        </div> <!-- row -->

                    </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include '../../footers.php'; ?>
        <!--End Footer-->


</body>

</html>