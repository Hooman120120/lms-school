<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

if (isset($_POST['send-message'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $message_text = $_POST['detail'];
        $codeCourse = $_POST['codecourse'];
        if (isset($_POST['sendAll'])) {
            date_default_timezone_set('Asia/Tehran');
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $sql = "INSERT INTO suport_message (sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time) 
            SELECT 'admin', ?, ?, 'student', `username`, ?, 'sent', ?, ? FROM dbcourse WHERE codecourse = ? AND activityuser = 1";
            $params = [$_SESSION['admin']['user'], GetIp(), $message_text, $datenow, $timenow, $codeCourse];
        } else {
            date_default_timezone_set('Asia/Tehran');
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $sql = "INSERT INTO suport_message (sender_type, sender_username, sender_ip, receiver_type, receiver_username, message_text, status, message_date, message_time)
            SELECT 'admin', ?, ?, 'student', `username`, ?, 'sent', ?, ? FROM dbcourse WHERE codecourse = ? AND suporter = ? AND activityuser = 1";
            $params = [$_SESSION['admin']['user'], GetIp(), $message_text, $datenow, $timenow, $codeCourse, $_SESSION['admin']['user']];
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            $_SESSION['message'] = 'پیام ها با موفقیت صادر شدند';
        } else {
            $_SESSION['message'] = 'عملیات با شکست مواجه شد . دانش آموزی در این پایه برای شما ثبت نشده است';
        }

        header('Location: desktop.php');
        die();
    } else {
        $_SESSION['message'] = 'خطای غیرمنتظره در انجام عملیات';
        header('Location: desktop.php');
        die();
    }
}

function timeToSeconds($time)
{
    if (!$time || !strpos($time, ':')) return 0;
    list($h, $m, $s) = explode(':', $time);
    return ($h * 3600) + ($m * 60) + $s;
}

function secondsToTime($seconds)
{
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}


function reportIncomingSimotel($start, $end, $op, $count = 100000)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://' . SIMOTEL_SERVER . '/api/v4/reports/quick/search',
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
            'X-APIKEY: ' . SIMOTEL_API_KEY,
            'Authorization: Basic ' . SIMOTEL_BASIC_AUTH,
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
        foreach ($response->data->data as $call) {
            if ($call->disposition == 'ANSWERED') {
                $answered++;
                $time_answered += $call->billsec;
            }
            if ($call->disposition == 'NO ANSWER' || $call->disposition == 'BUSY') {
                $noanswered++;
                $time_noanswered += $call->duration;
            }
        }
    }

    $convertTime = function ($seconds) {
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

$groupSql = 'SELECT * FROM handhug_groups WHERE id = ?';
$groupStmt = $conn->prepare($groupSql);
$groupStmt->execute([$_GET['group']]);
$group = $groupStmt->fetch(PDO::FETCH_OBJ);

$countMembersSql = 'SELECT COUNT(*) AS countMembers FROM dbcourse WHERE codecourse = ? AND group_id = ?';
$countMembersStmt = $conn->prepare($countMembersSql);
$countMembersStmt->execute([$group->codecourse, $_GET['group']]);
$countMembers = $countMembersStmt->fetch(PDO::FETCH_OBJ);


//* End Check Session JUST ONE Session
include_once '../temp.html';
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
    <title> سامانه من | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
</head>

<body class="main-body leftmenu">
    <!-- Loader -->
    <!--<div id="global-loader">-->
    <!--    <img src="--><?php //echo url('assets/img/media/loader.gif') 
                            ?><!--" class="loader-img" alt="درحال بارگذاری">-->
    <!--</div>-->
    <!-- End Loader -->
    <!-- Page -->
    <div class="page">
        <?php
        include_once '../menu.php';
        include_once '../header.php';
        ?>

        <!-- Main Content-->
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="card mb-4 mt-4 mt-md-0">
                        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                            <div>
                                <h5 class="mb-1">گروه <?= $group->group_name ?></h5>
                                <small class="text-muted"><?= $countMembers->countMembers ?> عضو</small>
                            </div>
                        </div>
                    </div>
                    <div class="row row-sm">
                        <div class="col-sm-12 col-lg-12 col-xl-12">
                            <div class="row row-sm">
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3 mb-3 mb-md-0 mt-3 mt-md-0">
                                    <a href="<?= url('handhug/see-members.php?group=') . $_GET['group'] ?>" class="d-block h-100">
                                        <div class="card custom-card bg-warning h-100" style="background-image: url('<?= url('../assets/img/handhug/orange.jpg') ?>');">
                                            <div class="card-body">
                                                <div class="card-order ">
                                                    <label class="main-content-label mb-3 pt-1 text-black">اعضای گروه</label>
                                                    <h2 class="text-left card-item-icon card-icon text-black">
                                                        <i class="fa fa-calendar-times-o icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold">🫂👥👨‍👩‍👧‍</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black">مشاهده اعضای گروه شما</p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 mb-3 mb-md-0">
                                    <a href="<?= url('handhug/send-ticket-members.php?group=') . $_GET['group'] ?>" class="d-block h-100">
                                        <div class="card custom-card bg-success h-100" style="background-image: url('<?= url('../assets/img/handhug/green.jpg') ?>');">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <label class="main-content-label mb-3 pt-1 text-black">ارسال پیام کلی</label>
                                                    <h2 class="text-left text-black"><i
                                                            class="fa fa-calendar-check-o icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold">💬👄👀</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black">ارسال تیکت به اعضای گروه شما</p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 mb-3 mb-md-0">
                                    <a href="<?= url('handhug/transfer-files.php?group=') . $_GET['group'] ?>" class="d-block h-100">
                                        <div class="card custom-card bg-info h-100" style="background-image: url('<?= url('../assets/img/handhug/blue.jpg') ?>');">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <label class="main-content-label mb-3 pt-1 text-black">ارسال و دریافت فایل</label>
                                                    <h2 class="text-left text-black">
                                                        <i class="fe fe-loader icon-size float-right text-primary"></i>
                                                        <span class="font-weight-bold">📁📂🗃️</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black">مشاهده فایل های ارسالی از اعضای گروه</p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 mb-3 mb-md-0">
                                    <a href="<?= url('handhug/see-attendance-members.php?group=') . $_GET['group'] ?>" class="d-block h-100">
                                        <div class="card custom-card bg-info-gradient h-100" style="background-image: url('<?= url('../assets/img/handhug/red-blue.jpg') ?>');">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <label class="main-content-label mb-3 pt-1 text-black">
                                                        مشاهده حضور و غیاب و تراز آزمون
                                                    </label>
                                                    <h2 class="text-left text-black">
                                                        <i class="si si-list icon-size float-right text-primary"></i>
                                                        <span class="font-weight-bold">✒️✒️✒️</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black">مشاهده حضور و غیاب و تراز آزمون</p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../footers.php'; ?>
        <!--End Footer-->
</body>

</html>