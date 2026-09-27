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
$dateconfig = delaySupportAdmin();

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

// 🔁 Convert HH:MM:SS string to seconds
function timeToSeconds($time)
{
    if (!$time || !strpos($time, ':')) return 0;
    list($h, $m, $s) = explode(':', $time);
    return ($h * 3600) + ($m * 60) + $s;
}

// 🔁 Convert seconds to HH:MM:SS string
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
        CURLOPT_URL => 'http://'.SIMOTEL_SERVER.'/api/v4/reports/quick/search',
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
            'X-APIKEY: '.SIMOTEL_API_KEY,
            'Authorization: Basic '.SIMOTEL_BASIC_AUTH,
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

$sales_deputy = [0, 51, 52, 53];

//* End Check Session JUST ONE Session
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> میزکار من | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<style>
    .op-section:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .card-link-wrapper {
        display: block;
        text-decoration: none;
        color: inherit;
    }

    .card-link-wrapper .card {
        transition: transform 0.2s ease-in-out;
    }

    .card-link-wrapper:hover .card {
        transform: scale(0.98);
    }
</style>
<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
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
                <div class="row row-sm">
                    <div class="col-sm-12 col-lg-12 col-xl-8">
                        <div class="row row-sm">
                            <?php if (in_array($_SESSION['admin']['adminrole'], $sales_deputy)) { ?>
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3 ">
                                    <a class="clickable-card2"
                                       href="<?= url('admin/panel/all_users_management.php') ?>">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <label class="main-content-label mb-3 pt-1">کل دانش آموزان</label>
                                                    <h2 class="text-left card-item-icon card-icon">
                                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold text-black "> <?php echo allUsersCount(); ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-muted"> فرد </p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                <!--                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">-->
                                <!--                                    <div class="card custom-card">-->
                                <!--                                        <div class="card-body">-->
                                <!--                                            <div class="card-order">-->
                                <!--                                                --><?php
//                                                $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `factoradder`=?';
//                                                $qu_lock = $conn->prepare($co_lock);
//                                                $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
//                                                $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
//                                                ?>
                                <!--                                                <label class="main-content-label mb-3 pt-1"> ثبت نامی های من </label>-->
                                <!--                                                <h2 class="text-left"><i-->
                                <!--                                                            class="fe fe-database icon-size float-right text-primary"></i><span-->
                                <!--                                                            class="font-weight-bold">--><?php

                                //echo $rec->coreg ?><!--  </span>-->
                                <!--                                                </h2>-->
                                <!--                                                <p class="mb-0 mt-4 text-muted">نفر پایه </p>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                    </div>-->
                                <!--                                </div>-->


                            <?php } else { ?>
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                    <a href="<?= url('admin/panel/alltypeusers.php') ?>" class="card-link-wrapper">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <?php
                                                    $exclude_sql = "
                                                    SELECT codemeli AS username 
                                                    FROM dbpersonal 
                                                    WHERE suspension_mode = 1 AND codemeli IS NOT NULL
                                                    UNION
                                                    SELECT username 
                                                    FROM dbcourse 
                                                    GROUP BY username 
                                                    HAVING SUM(COALESCE(has_operator, 0)) = 0
                                                ";
                                                    $exclude_stmt = $conn->prepare($exclude_sql);
                                                    $exclude_stmt->execute();
                                                    $excluded_users = $exclude_stmt->fetchAll(PDO::FETCH_COLUMN);


                                                    $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `suporter`=?';
                                                    $qu_lock = $conn->prepare($co_lock);
                                                    $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
                                                    $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                                    $total_course_regs = $rec->coreg; // This is the original, unfiltered count

                                                    $excluded_course_regs = 0;
                                                    if (!empty($excluded_users)) {
                                                        $placeholders = implode(',', array_fill(0, count($excluded_users), '?'));

                                                        $co_lock_exclude = "SELECT COUNT(*) as excluded_count FROM `dbcourse` INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse WHERE dbcourse.activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `suporter`=? AND dbcourse.username IN ($placeholders)";

                                                        $qu_lock_exclude = $conn->prepare($co_lock_exclude);
                                                        $params = array_merge(['موفق', $_SESSION['admin']['user']], $excluded_users);
                                                        $qu_lock_exclude->execute($params);
                                                        $rec_exclude = $qu_lock_exclude->fetch(PDO::FETCH_OBJ);
                                                        $excluded_course_regs = $rec_exclude->excluded_count;
                                                    }
                                                    $final_course_regs = $total_course_regs - $excluded_course_regs;


                                                    $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.suporter=?';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1, 1, $_SESSION['admin']['user']]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);

                                                    $user = array();
                                                    $id = 0;
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result as $res) {
                                                            if (in_array($res->username, $excluded_users)) {
                                                                continue;
                                                            }

                                                            if (!in_array($res->username, $user)) {
                                                                array_push($user, $res->username);
                                                                $id++;
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                        <label class="main-content-label mb-3 pt-1">
                                                            دانش آموزان من
                                                        </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد دانش آموزان تحت مدیریت شما نمایش داده می‌شود. عدد اول تعداد دانش آموزان یکتا و عدد داخل پرانتز تعداد کل ثبت‌نام‌های قطعی آن‌ها در پایه‌ها (نفر-پایه) را نشان می‌دهد. اگر یک دانش آموز در چند پایه ثبت‌نام کرده باشد، در آمار نفر-پایه به ازای هر پایه یک بار محاسبه می‌شود."
                                                              aria-label="راهنمای دانش آموزان من">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <h2 class="text-left card-item-icon card-icon">
                                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold">(<?php echo $final_course_regs ?>)<?php echo $id ?> </span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-muted">فرد (نفرپایه)</p>
                                                </div>
                                                <!--                                            <div class="card-order">-->
                                                <!--                                                --><?php
                                                //                                                $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `suporter`=?';
                                                //                                                $qu_lock = $conn->prepare($co_lock);
                                                //                                                $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
                                                //                                                $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                                //                                                $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.suporter=?';
                                                //                                                $query = $conn->prepare($sql);
                                                //                                                $query->execute([1, 1, $_SESSION['admin']['user']]);
                                                //                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                //                                                $user = array();
                                                //                                                $id = 0;
                                                //                                                if ($query->rowCount() > 0) {
                                                //
                                                //                                                    foreach ($result as $res) {
                                                //                                                        if (!in_array($res->username, $user)) {
                                                //                                                            array_push($user, $res->username);
                                                //                                                            $id++;
                                                //
                                                //                                                        }
                                                //                                                    }
                                                //                                                }
                                                //                                                ?>
                                                <!--                                                <label class="main-content-label mb-3 pt-1">دانش آموزان من </label>-->
                                                <!--                                                <h2 class="text-left card-item-icon card-icon">-->
                                                <!--                                                    <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span-->
                                                <!--                                                            class="font-weight-bold">(-->
                                                <?php //echo $rec->coreg ?><!--)--><?php //echo $id ?><!-- </span>-->
                                                <!--                                                </h2>-->
                                                <!--                                                <p class="mb-0 mt-4 text-muted">فرد (نفرپایه)</p>-->
                                                <!--                                            </div>-->
                                            </div>
                                        </div>
                                    </a>
                                </div>

                            <?php } ?>
                            <?php if ($_SESSION['admin']['adminrole'] == 6) { ?>
                                <!--                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3 d-flex flex-row flex-wrap ">-->
                                <!--                                    --><?php
//                                    $co_lock2 = 'SELECT COUNT(*) AS user_count FROM dbpersonal WHERE creator = ? AND char_length (codemeli) = 11;';
//                                    $qu_lock2 = $conn->prepare($co_lock2);
//                                    $qu_lock2->execute([$_SESSION['admin']['user']]);
//                                    $rec2 = $qu_lock2->fetch(PDO::FETCH_OBJ);
//
//                                    ?>
                                <!--                                    <a class="custom-card d-flex col-6 op-section"-->
                                <!--                                       href="--><?php //= url('admin/panel/myusers-seller.php') . '?user=' . urlencode($_SESSION['admin']['user']). '&type=2'  ?><!--"-->
                                <!--                                       style="color:#0a0a0a; cursor:pointer; border-top-right-radius:10px; background-color:white; border-bottom:solid 1px lightgrey; border-left:solid 1px lightgrey;">-->
                                <!--                                        <div class="">-->
                                <!--                                            <div class="card-order">-->
                                <!--                                                <label class="main-content-label" style="margin-top: 6px;">ثبت موقت</label>-->
                                <!--                                                <p class="mb-0 text-muted" >نفر : <span style="color: black;">--><?php //echo $rec2->user_count ?><!--</span></p>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                    </a>-->
                                <!--                                    --><?php
//
//                                    $co_lock1 = 'SELECT COUNT(*) AS user_count FROM dbpersonal WHERE creator = ? AND char_length (codemeli) = 10;';
//                                    $qu_lock1 = $conn->prepare($co_lock1);
//                                    $qu_lock1->execute([$_SESSION['admin']['user']]);
//                                    $rec1 = $qu_lock1->fetch(PDO::FETCH_OBJ);
//
//                                    ?>
                                <!--                                    <a class=" custom-card  d-flex  col-6 op-section" style="color: #0a0a0a; cursor: pointer; border-top-left-radius: 10px; background-color: white; border-bottom: solid 1px lightgrey;"-->
                                <!--                                       href="--><?php //= url('admin/panel/myusers-seller.php') . '?user=' . urlencode($_SESSION['admin']['user']). '&type=1'  ?><!--" onclick="">-->
                                <!--                                        <div class="">-->
                                <!--                                            <div class="card-order">-->
                                <!--                                                <label class="main-content-label " style="margin-top: 6px;">ثبت قطعی</label>-->
                                <!--                                                <p class="mb-0 text-muted" >نفر : <span style="color: black;">--><?php //echo $rec1->user_count;?><!--</span></p>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                    </a>-->
                                <!--                                    --><?php
//
//                                    $co_lock = 'SELECT COUNT(*) AS user_count FROM dbpersonal WHERE creator = ?';
//                                    $qu_lock = $conn->prepare($co_lock);
//                                    $qu_lock->execute([$_SESSION['admin']['user']]);
//                                    $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
//
//                                    $sql4="SELECT COUNT(*) AS corem
//                                            FROM dbcourse
//                                            INNER JOIN dbfactor
//                                              ON dbcourse.numfactor = dbfactor.idfactor
//                                            INNER JOIN codtbl_course
//                                              ON codtbl_course.codecourse = dbcourse.codecourse
//                                            INNER JOIN dbpersonal
//                                              ON dbpersonal.codemeli = dbfactor.username
//                                            WHERE dbcourse.activityuser = 1
//                                              AND codtbl_course.activecourse = 1
//                                              AND dbfactor.status = ?
//                                              AND dbfactor.factoradder = ?
//                                              AND dbpersonal.creator = dbfactor.factoradder;";
//                                    $sql4 = $conn->prepare($sql4);
//                                    $sql4->execute(['موفق', $_SESSION['admin']['user']]);
//                                    $db_fac_adder = $sql4->fetch(PDO::FETCH_OBJ);
//
//                                    ?>
                                <!---->
                                <!--                                    <a class=" custom-card  d-flex col-12 op-section align-item-center justify-item-center flex-column " style="color: #0a0a0a; cursor: pointer;  margin-top:-20px; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; background-color: white; "-->
                                <!--                                       href="--><?php //= url('admin/panel/myusers-seller.php') . '?user=' . urlencode($_SESSION['admin']['user']). '&type=3'  ?><!--" onclick="">-->
                                <!--                                        <div class="  d-flex align-item-center justify-item-center flex-column">-->
                                <!--                                            <div class="card-order  ">-->
                                <!--                                                <p class="main-content-label text-center" style="margin-top: 10px;">دانش آموزان ثبت نامی شما</p>-->
                                <!--                                                <p class="mb-0 text-muted text-center" > <span style="color: black;"><span class="text-muted"> نفر پایه : </span>--><?php //echo $db_fac_adder->corem; ?><!--</span>-->
                                <!--                                                <p class="mb-0 text-muted text-center" > <span style="color: black;"><span class="text-muted"> نفر : </span>--><?php //echo $rec->user_count; ?><!--</span>-->
                                <!--                                            </div>-->
                                <!--                                        </div>-->
                                <!--                                    </a>-->
                                <!--                                </div>-->
                            <?php } ?>
                            <!-- COL END -->
                            <?php $allowed_users_administrators = [6];
                            if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators)
                                && (AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.today', false) !== null
                                    || AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.period', false) !== null)) { ?>
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                    <div class="card custom-card overflow-hidden" style="height:164px;">


                                        <div class="card-body p-0 h-100 d-flex flex-column">

                                            <!-- Top half -->
                                            <?php if (AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.today', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) !== null): ?>
                                            <a href="<?= url('admin/panel/temp-users-sales.php?type=2') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                        <div>
                                                            <?php
                                                            if (AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.today', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                                $totalToday = countTodayUsers();
                                                            } else {
                                                                $totalToday = countTodayUsers($_SESSION['admin']['user']);
                                                            }
                                                            ?>
                                                            <label class="main-content-label mb-2 pt-1">
                                                                ثبت موقت فعال امروز
                                                            </label>
                                                        </div>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که امروز ثبت‌نام موقت آن‌ها انجام شده و هنوز به ثبت‌نام قطعی تبدیل نشده است. این آمار برای پیگیری ثبت‌نام‌های ناتمام و تکمیل فرآیند ثبت‌نام دانش آموزان کاربرد دارد."
                                                              aria-label="راهنمای ثبت موقت فعال امروز">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <i class="fe fe-database icon-size text-primary"></i>
                                                </div>
                                                <div class="text-center font-weight-bold fs-6"><?= $totalToday ?></div>
                                            </a>
                                            <?php endif; ?>
                                            <div class="border-top"></div>

                                            <!-- Bottom half -->
                                            <?php if (AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.period', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) !== null): ?>
                                            <a href="<?= url('admin/panel/temp-users-sales.php?type=1') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <?php
                                                if (AdminRecordVisibility('dashboard.summary.overview.manager.page-b9857fa208.period', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                    $totaltemp = countUsers();
                                                } else {
                                                    $totaltemp = countUsers($_SESSION['admin']['user']);
                                                } ?>
                                                <div class="d-flex align-items-start justify-content-between">

                                                    <div class="d-flex align-items-center" style="gap:12px;">

                                                        <label class="main-content-label mb-2 pt-1">
                                                            ثبت موقت‌های فعال
                                                        </label>

                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد کل دانش آموزانی نمایش داده می‌شود که ثبت‌نام آن‌ها به‌صورت موقت در سامانه ثبت شده و هنوز به ثبت‌نام قطعی تبدیل نشده است. این آمار به شما کمک می‌کند تا ثبت‌نام‌های در انتظار تکمیل یا تأیید را پیگیری و مدیریت کنید."
                                                              aria-label="راهنمای ثبت موقت‌های فعال">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>

                                                    <i class="fe fe-database icon-size text-primary"></i>

                                                </div>
                                                <div class="text-center"><?= $totaltemp ?></div>
                                            </a>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                </div>
                            <?php } ?>


                            <style>
                                .split-btn:hover {
                                    box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .12);
                                    position: relative;
                                    z-index: 1;
                                }
                            </style>
                            <?php $allowed_users_administrators = [6];
                            if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators)
                                && (AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.today', false) !== null
                                    || AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.period', false) !== null)) { ?>
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                    <div class="card custom-card overflow-hidden" style="height:164px;">
                                        <div class="card-body p-0 h-100 d-flex flex-column">

                                            <!-- Top half -->
                                            <?php if (AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.today', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) !== null): ?>
                                            <a href="<?= url('admin/panel/permanet-users-sales.php?type=2') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div>
                                                        <?php if (AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.today', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                            $totalpermToday = countTodayPermanetUsers();
                                                        } else {
                                                            $totalpermToday = countTodayPermanetUsers($_SESSION['admin']['user']);
                                                        } ?>
                                                        <label class="main-content-label mb-2 pt-1">ثبت قطعی
                                                            امروز</label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که ثبت‌نام آن‌ها امروز به‌صورت قطعی نهایی شده است. این آمار شامل ثبت‌نام‌هایی است که فرآیند ثبت‌نام آن‌ها تکمیل شده و پایه به‌صورت قطعی برای دانش آموز ثبت شده است."
                                                              aria-label="راهنمای ثبت قطعی امروز">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <i class="fe fe-database icon-size text-primary"></i>
                                                </div>
                                                <div class="text-center font-weight-bold fs-6"><?= $totalpermToday ?></div>
                                            </a>
                                            <?php endif; ?>

                                            <div class="border-top"></div>

                                            <!-- Bottom half -->
                                            <?php if (AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.period', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) !== null): ?>
                                            <a href="<?= url('admin/panel/permanet-users-sales.php?type=1') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <?php
                                                if (AdminRecordVisibility('dashboard.summary.overview.manager.page-25d387505a.period', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                    $totalperm = countPermanetUsers();
                                                } else {
                                                    $totalperm = countPermanetUsers($_SESSION['admin']['user']);
                                                } ?>
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div>
                                                        <label class="main-content-label mb-2 pt-1">ثبت قطعی </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد کل دانش آموزانی نمایش داده می‌شود که ثبت‌نام آن‌ها به‌صورت قطعی در سامانه نهایی شده است. این آمار شامل تمامی ثبت‌نام‌های تکمیل‌شده بوده و دانش آموزان پس از ثبت قطعی به پایه‌های مربوطه دسترسی خواهند داشت."
                                                              aria-label="راهنمای ثبت قطعی">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <i class="fe fe-database icon-size text-primary"> </i>
                                                </div>
                                                <div class="text-center"><?= $totalperm ?></div>
                                            </a>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                </div>
                            <?php }
                            if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators) && AdminRecordVisibility('dashboard.summary.overview.desktop.page-8477ff94be', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) !== null) { ?>
                                <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                    <div class="card custom-card overflow-hidden" style="height:164px;">
                                        <div class="card-body p-0 h-100 d-flex flex-column">

                                            <!-- Top half -->
                                            <a href="<?= url('admin/panel/resell-students.php?status=all') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <div>
                                                        <?php if (AdminRecordVisibility('dashboard.summary.overview.desktop.page-8477ff94be', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                            $sql = 'SELECT COUNT(*) FROM sales_followup WHERE status = ?';
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute(['در انتظار تماس']);
                                                            $countResell = $stmt->fetchColumn();
                                                        } else {
                                                            $sql = 'SELECT COUNT(*) FROM sales_followup WHERE status = ? AND sales_admin = ?';
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute(['در انتظار تماس', $_SESSION['admin']['user']]);
                                                            $countResell = $stmt->fetchColumn();
                                                        } ?>
                                                        <label class="main-content-label mb-2 pt-1">ثبت فروش
                                                            مجدد</label>
                                                    </div>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که برای فروش مجدد یا پیگیری تمدید ثبت‌نام در انتظار تماس هستند. این آمار به کارشناسان فروش کمک می‌کند تا فرصت‌های فروش مجدد را مدیریت کرده و پیگیری‌های لازم را در زمان مناسب انجام دهند."
                                                          aria-label="راهنمای ثبت فروش مجدد">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                    </div>
                                                    <i class="fe fe-database icon-size text-primary"></i>
                                                </div>
                                                <div class="text-center font-weight-bold fs-6"><?= $countResell ?></div>
                                            </a>

                                            <div class="border-top"></div>

                                            <a href="<?= url('admin/panel/resell-students.php?status=success') ?>"
                                               class="d-flex flex-column justify-content-center text-decoration-none text-dark px-3 split-btn"
                                               style="flex:1; transition: box-shadow .2s ease;">
                                                <?php
                                                if (AdminRecordVisibility('dashboard.summary.overview.desktop.page-8477ff94be', in_array($_SESSION['admin']['adminrole'], $sales_deputy)) === 'all') {
                                                    $sql = 'SELECT COUNT(*) FROM sales_followup WHERE status = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute(['فروش موفق']);
                                                    $countSucessResell = $stmt->fetchColumn();
                                                } else {
                                                    $sql = 'SELECT COUNT(*) FROM sales_followup WHERE status = ? AND sales_admin = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute(['فروش موفق', $_SESSION['admin']['user']]);
                                                    $countSucessResell = $stmt->fetchColumn();
                                                } ?>
                                                <div class="d-flex align-items-start justify-content-between">
                                                    <div>
                                                        <label class="main-content-label mb-2 pt-1">فروش مجدد های ثبت
                                                            شده</label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد کل درخواست‌های فروش مجدد ثبت‌شده در سامانه نمایش داده می‌شود. این آمار شامل تمامی مواردی است که برای پیگیری، تمدید ثبت‌نام یا ارائه مجدد خدمات آموزشی ثبت شده‌اند و به کارشناسان فروش در مدیریت فرآیند فروش مجدد کمک می‌کند."
                                                              aria-label="راهنمای فروش مجدد های ثبت شده">
                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <i class="fe fe-database icon-size text-primary"> </i>
                                                </div>
                                                <div class="text-center"><?= $countSucessResell ?></div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <!--                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">-->
                            <!--                                <div class="card custom-card">-->
                            <!--                                    <div class="card-body">-->
                            <!--                                        <div class="card-order">-->
                            <!---->
                            <!--                                            --><?php
                            //                                            $managers = ['op201', 'keyvan', 'kazemi', 'kianoosh'];
                            //                                            $admin = $_SESSION['admin']['user'];
                            //                                            if (in_array($admin, $managers)) {
                            //                                                $sql = "SELECT COUNT(*) AS unseen_messages FROM (SELECT * FROM suport_message WHERE receiver_username = ? AND status = ? GROUP BY sender_username) AS senders";
                            //                                                $stmt = $conn->prepare($sql);
                            //                                                $stmt->execute([$_SESSION['admin']['user'], 'sent']);
                            //                                                $unseen = $stmt->fetch(PDO::FETCH_OBJ);
                            //                                            } else {
                            //                                                $sql = "SELECT COUNT(*) AS unseen_messages FROM (SELECT * FROM suport_message WHERE receiver_username = ? AND status = ? GROUP BY sender_username) AS senders";
                            //                                                $stmt = $conn->prepare($sql);
                            //                                                $stmt->execute(['managers', 'sent']);
                            //                                                $unseen = $stmt->fetch(PDO::FETCH_OBJ);
                            //                                            }
                            //                                            ?>
                            <!--                                            <label class="main-content-label mb-3 pt-1">منتظر پاسخ من</label>-->
                            <!--                                            <h2 class="text-left"><i-->
                            <!--                                                        class="fe fe-edit icon-size float-right text-primary"></i><span-->
                            <!--                                                        class="font-weight-bold">-->
                            <?php // $unseen->unseen_messages ?><!--</span>-->
                            <!--                                            </h2>-->
                            <!--                                            <p class="mb-0 mt-4 text-muted">دانش آموز منتظر پاسخ پیام</p>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!--                            <div class="col-sm-12 col-md-4 col-lg-3 col-xl-3">-->
                            <!--                                <div class="clickable-card">-->
                            <!--                                    <a href="javascript:void(0)"-->
                            <!--                                       onclick="openNotificationWindow()"-->
                            <!--                                       class="text-decoration-none text-dark">-->
                            <!--                                        <div class="card custom-card card-order blinking-bg shadow-sm border-2 border-danger text-dark">-->
                            <!--                                            <div class="card-body text-center">-->
                            <!--                                                <label class="main-content-label mb-3 pt-1 d-block fs-5 fw-bold">-->
                            <!--                                                    اعلان‌ها-->
                            <!--                                                </label>-->
                            <!--                                                <h2 class="text-left mb-3 d-flex justify-content-between align-items-center">-->
                            <!--                                                    <i class="fe fe-bell icon-size text-danger"></i>-->
                            <!--                                                </h2>-->
                            <!---->
                            <!--                                                <p class="mb-0 text-muted">-->
                            <!--                                                    نفر دارای اعلان فعال-->
                            <!--                                                </p>-->
                            <!--                                            </div>-->
                            <!--                                        </div>-->
                            <!--                                    </a>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!---->
                            <!--                            <script>-->
                            <!--                                function openNotificationWindow() {-->
                            <!--                                    const w = screen.availWidth || screen.width;-->
                            <!--                                    const h = screen.availHeight || screen.height;-->
                            <!---->
                            <!--                                    const specs = [-->
                            <!--                                        `width=${w}`,-->
                            <!--                                        `height=${h}`,-->
                            <!--                                        'left=0',-->
                            <!--                                        'top=0',-->
                            <!--                                        'menubar=0',-->
                            <!--                                        'toolbar=0',-->
                            <!--                                        'status=0',-->
                            <!--                                        'location=0',-->
                            <!--                                        'scrollbars=1',-->
                            <!--                                        'resizable=1'-->
                            <!--                                    ].join(',');-->
                            <!---->
                            <!--                                    const win = window.open('../notifications/notifications.php', 'اعلان‌ها', specs);-->
                            <!---->
                            <!--                                    if (win) {-->
                            <!--                                        try {-->
                            <!--                                            win.moveTo(0, 0);-->
                            <!--                                            win.resizeTo(w, h);-->
                            <!--                                            win.focus();-->
                            <!--                                        } catch (_) {}-->
                            <!--                                    } else {-->
                            <!--                                        // popup blocked → fallback-->
                            <!--                                        window.location.href = 'notifications.php';-->
                            <!--                                    }-->
                            <!--                                }-->
                            <!--                            </script>-->
                            <style>
                                .blinking-bg {
                                    animation: blinkBackground 1.2s infinite;
                                }

                                @keyframes blinkBackground {
                                    0%, 100% {
                                        background-color: #ffe5e5;
                                    }
                                    50% {
                                        background-color: #ff0000;
                                    }
                                }

                                /* 🔴 Clickable behavior */
                                .clickable-card {
                                    text-decoration: none !important;
                                    color: inherit !important;
                                    display: block;
                                }

                                .clickable-card .card {
                                    transition: all 0.2s ease-in-out;
                                }

                                .clickable-card:hover .card {
                                    transform: scale(1.02);
                                    box-shadow: 0 0 15px rgba(255, 0, 0, 0.6);
                                }

                                .clickable-card2 .card {
                                    transition: all 0.2s ease-in-out;
                                }

                                .clickable-card2:hover .card {
                                    transform: scale(1.02);
                                    box-shadow: 0 0 15px rgba(0, 0, 0, 0);
                                }
                            </style>

                            <!-- ✅ Wrap inside proper Bootstrap column nigga -->
                            <?php $allowed_users = [0, 1, 2, 51, 52, 54];
                            if (in_array($_SESSION['admin']['adminrole'], $allowed_users)) { ?>
                                <div class="col-sm-12 col-md-4 col-lg-3 col-xl-3">
                                    <div class="clickable-card">
                                        <a href="<?php echo url('admin/notifications/operator_notif.php') ?>"
                                           class="text-decoration-none text-dark">
                                            <div class="card custom-card card-order blinking-bg shadow-sm border-2 border-danger text-dark">
                                                <div class="card-body text-center">
                                                    <div class="d-flex justify-content-center align-items-center mb-3" style="gap:10px;">
                                                        <label class="main-content-label mb-0 pt-1 d-block fs-5 fw-bold">
                                                            اعلان‌ها
                                                        </label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که در حال حاضر دارای اعلان فعال هستند. این اعلان‌ها می‌توانند شامل یادآوری‌ها، اطلاعیه‌ها یا پیام‌هایی باشند که نیاز به مشاهده یا پیگیری توسط پشتیبان یا کارشناس فروش دارند."
                                                              aria-label="راهنمای اعلان‌ها">
                                <i class="fe fe-info fs-4"></i>
                            </span>
                                                    </div>
                                                    <h2 class="text-left mb-3 d-flex justify-content-between align-items-center">
                                                        <i class="fe fe-bell icon-size text-danger"></i>
                                                    </h2>
                                                    <p class="mb-0 text-muted">
                                                        نفر دارای اعلان فعال
                                                    </p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if ($_SESSION['admin']['adminrole'] !== 6) {
                                ?>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 clickable-card2">
                                    <a href=" <?php echo url('admin/panel/tomorrow_supports.php') ?>">
                                        <div class="card custom-card bg-info">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <div class="d-flex align-items-center mb-3" style="gap:10px;">
                                                        <label class="main-content-label mb-0 pt-1">پشتیبانی فردا</label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش تعداد پشتیبانی‌ها و برنامه‌های پیگیری تنظیم‌شده برای روز آینده نمایش داده می‌شود. این اطلاعات به شما کمک می‌کند تا برنامه‌ریزی بهتری برای تماس‌ها و اقدامات فردای خود داشته باشید."
                                                              aria-label="راهنمای پشتیبانی فردا">
                                <i class="fe fe-info fs-4"></i>
                            </span>
                                                    </div>
                                                    <h2 class="text-left"><i
                                                                class="fe fe-loader icon-size float-right text-primary"></i><span
                                                                class="font-weight-bold text-black"> <?php echo $dateconfig['Tomarow']; ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black"> برنامه ریزی شده فردا </p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 clickable-card2">
                                    <a href=" <?php echo url('admin/panel/mystudent_status.php') ?>">
                                        <div class="card custom-card bg-primary-gradient">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <div class="d-flex align-items-center mb-3" style="gap:10px;">
                                                        <label class="main-content-label mb-0 pt-1">خلاصه وضعیت دانش آموزان</label>
                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش نمای کلی و آمار خلاصه از آخرین وضعیت دانش آموزان تحت مدیریت شما ارائه می‌شود تا بتوانید روند پیشرفت، فعال بودن یا نیاز به پیگیری آن‌ها را به سرعت بررسی کنید."
                                                              aria-label="راهنمای وضعیت دانش آموزان">
                                <i class="fe fe-info fs-4"></i>
                            </span>
                                                    </div>
                                                    <h2 class="text-left"><i
                                                                class="fe fe-airplay icon-size float-right text-primary"></i><span
                                                                class="font-weight-bold"> -</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-black">وضعیت دانش آموزان من</p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php } ?>
                            <!-- COL END -->
                            <!--                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">-->
                            <!--                                <div class="card custom-card">-->
                            <!--                                    <div class="card-body">-->
                            <!--                                        <div class="card-order">-->
                            <!--                                            --><?php
                            //                                            $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `factoradder`=?';
                            //                                            $qu_lock = $conn->prepare($co_lock);
                            //                                            $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
                            //                                            $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                            //                                            ?>
                            <!--                                            <label class="main-content-label mb-3 pt-1"> ثبت نامی های من </label>-->
                            <!--                                            <h2 class="text-left"><i-->
                            <!--                                                        class="fe fe-database icon-size float-right text-primary"></i><span-->
                            <!--                                                        class="font-weight-bold">-->
                            <?php //echo $rec->coreg ?><!--  </span>-->
                            <!--                                            </h2>-->
                            <!--                                            <p class="mb-0 mt-4 text-muted">نفر پایه </p>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->


                            <!-- COL END -->
                        </div>

                        <div class="row row-sm">
                            <?php
                            if ($_SESSION['admin']['adminrole'] !== 1) {
                                ?>
                                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                    <a class="clickable-card2"
                                       href="<?php echo url('admin/panel/reactivate_experimental.php?type=1') ?>">
                                        <div class="card custom-card bg-secondary ">
                                            <div class="card-body">
                                                <div class="card-order">
                                                    <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1"> اکانت های رد
                                                        شده</label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش فهرست اکانت‌هایی نمایش داده می‌شود که پس از بررسی، تأیید نشده یا درخواست آن‌ها رد شده است. از طریق این قسمت می‌توانید علت رد شدن هر اکانت را بررسی کرده و در صورت نیاز اقدامات لازم برای پیگیری یا اصلاح اطلاعات را انجام دهید."
                                                          aria-label="راهنمای اکانت‌های رد شده">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                    <h2 class="text-left"><i
                                                            class="fe fe-loader icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"> *</span>
                                                    </h2>
                                                    <p class="mb-0 mt-4"> لیست اکانت های رد شده </p>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php } ?>
                            <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3 clickable-card2">
                                <a href=" <?php echo url('admin/panel/late_supports.php') ?>">
                                    <div class="card custom-card bg-warning">
                                        <div class="card-body">
                                            <div class="card-order ">
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">تاخیر در پشتیبانی</label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد دانش آموزانی نمایش داده می‌شود که تاریخ پشتیبانی آن‌ها گذشته و هنوز پیگیری یا پشتیبانی مورد نیاز برای آن‌ها انجام نشده است. این آمار به شناسایی موارد معوق و اولویت‌بندی پیگیری‌های پشتیبانی کمک می‌کند."
                                                      aria-label="راهنمای تأخیر در پشتیبانی">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                                <h2 class="text-left card-item-icon card-icon">
                                                    <i class="fa fa-calendar-times-o icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold text-black"><?php echo $dateconfig['Last']; ?></span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-black">تاریخ پشتیبانی گذشته</p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- COL END -->
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 clickable-card2">
                                <a href=" <?php echo url('admin/panel/today_supports.php') ?>">
                                    <div class="card custom-card bg-success">
                                        <div class="card-body">
                                            <div class="card-order">
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                <label class="main-content-label mb-3 pt-1">پشتیبانی های امروز</label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تعداد پشتیبانی‌هایی نمایش داده می‌شود که برای امروز برنامه‌ریزی شده‌اند. این آمار به پشتیبانان کمک می‌کند تا برنامه روزانه خود را مدیریت کرده و تمامی پیگیری‌ها و ارتباطات تعیین‌شده را در زمان مقرر انجام دهند."
                                                      aria-label="راهنمای پشتیبانی‌های امروز">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                                </div>
                                                <h2 class="text-left"><i
                                                        class="fa fa-calendar-check-o icon-size float-right text-primary"></i><span
                                                        class="font-weight-bold text-black"><?php echo $dateconfig['Today']; ?></span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-black">برنامه ریزی شده امروز</p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <!-- COL END -->

                            <!--                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">-->
                            <!--                                <div class="card custom-card bg-teal">-->
                            <!--                                    <div class="card-body">-->
                            <!--                                        <div class="card-order">-->
                            <!--                                            --><?php
                            //                              $sql = 'SELECT COUNT(*) as counReport FROM suport_report WHERE poshtiban=?';
                            //                                  $exe = $conn->prepare($sql);
                            //                                  $exe->execute([$_SESSION['admin']['user']]);
                            //                                  $natije = $exe->fetch();
                            //                            ?>
                            <!--                                        <label class="main-content-label mb-3 pt-1"> تعداد پشتیبانی</label>-->
                            <!--                                            <h2 class="text-left"><i-->
                            <!--                                                        class="si si-list icon-size float-right text-primary"></i><span-->
                            <!--                                                        class="font-weight-bold">-->
                            <?php // echo $natije['counReport']; ?><!-- </span>-->
                            <!--                                            </h2>-->
                            <!--                                            <p class="mb-0 mt-4 "> گزارش ثبت شده </p>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 handhug clickable-card2">
                                <a href="<?php echo url('admin/handhug/handhug-management.php') ?>">
                                    <div class="card custom-card bg-info-gradient">
                                        <div class="card-body">
                                            <div class="card-order">
                                                <div class="d-flex align-items-center mb-2" style="gap:12px;">
                                                    <label class="main-content-label mb-3 pt-1 text-black">
                                                        بغل دستی
                                                    </label>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش تعداد گروه‌های هم‌سطح با گروه فعلی نمایش داده می‌شود. این آمار به شما کمک می‌کند تا تعداد گروه‌های مشابه را مشاهده کرده و در صورت نیاز بین آن‌ها جابه‌جا شده یا وضعیت آن‌ها را مقایسه کنید."
                                                          aria-label="راهنمای گروه‌های بغل دستی">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                                <h2 class="text-left text-black">
                                                    <i class="si si-list icon-size float-right text-primary"></i>
                                                    <span class="font-weight-bold">10</span>
                                                </h2>
                                                <p class="mb-0 mt-4 text-black">
                                                    تعداد گروه بغل دستی
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- COL END -->
                        </div>
                        <div class="row row-sm">
                            <!--                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">-->
                            <!--                                <div class="card custom-card overflow-hidden">-->
                            <!--                                    <div class="card-header  border-bottom-0 pb-0">-->
                            <!--                                        <div>-->
                            <!--                                            <div class="d-flex">-->
                            <!--                                                <label class="main-content-label my-auto pt-2">زمانبندی دانش آموزان-->
                            <!--                                                </label>-->
<!--                                                                                <span class="menu-info-trigger"-->
<!--                                                                                      data-tooltip="در این بخش می‌توانید زمان تماس و پیگیری هر دانش آموز را مدیریت کنید. با تعیین تاریخ و ساعت تماس، برنامه پشتیبانی دانش‌آموزان منظم شده و زمان‌های تعیین‌شده در لیست زمان‌بندی نمایش داده می‌شوند."-->
<!--                                                                                      aria-label="راهنمای زمان‌بندی پشتیبانی دانش آموزان"-->
<!--                                                                                      onclick="event.preventDefault(); event.stopPropagation();">-->
<!--                                                    -->
<!--                                                        <i class="fe fe-info fs-4"></i>-->
<!--                                                    </span>-->
                            <!--                                            </div>-->
                            <!--                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> تاریخ های برنامه ریزی شده آتی- محاسبه بصورت نفر پایه </span>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                    <div class="card-body">-->
                            <!--                                        <div class="row">-->
                            <!--                                            <table class="table table-bordered">-->
                            <!--                                                <thead>-->
                            <!--                                                <tr class="bg-light">-->
                            <!--                                                    <th>تاریخ</th>-->
                            <!--                                                    <th class="wd-25">تعداد</th>-->
                            <!--                                                </tr>-->
                            <!--                                                </thead>-->
                            <!--                                                <tbody>-->
                            <!--                                                --><?php
                            //                                                $sql = 'SELECT COUNT(*) as countdate,`afterdate` FROM `suport_user` LEFT JOIN dbcourse ON suport_user.username=dbcourse.username LEFT JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse  WHERE dbcourse.suporter=? AND dbcourse.activityuser=1 AND codtbl_course.activecourse=1 GROUP BY `afterdate` ORDER BY afterdate ASC ';
                            //                                                $wqu = $conn->prepare($sql);
                            //                                                $wqu->execute([$_SESSION['admin']['user']]);
                            //                                                $username = array();
                            //                                                $require = $wqu->fetchAll(PDO::FETCH_OBJ);
                            //                                                if ($wqu->rowCount() > 0) {
                            //                                                    foreach ($require as $res) {
                            //                                                        ?>
                            <!--                                                        <tr>-->
                            <!--                                                            <td>-->
                            <!--                                                                --><?php
                            //                                                                $last = $res->afterdate;
                            //                                                                $last = explode('-', $last);
                            //                                                                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                            //                                                                $last = implode('/', $last);
                            //                                                                echo $last;
                            //                                                                ?>
                            <!--                                                            </td>-->
                            <!--                                                            <td>--><?php //echo $res->countdate ?><!--</td>-->
                            <!--                                                        </tr>-->
                            <!--                                                        --><?php
                            //                                                    }
                            //                                                }
                            //                                                ?>
                            <!--                                                </tbody>-->
                            <!--                                            </table>-->
                            <!--                                        </div>-->
                            <!--                                    </div>-->
                            <!--                                </div>-->
                            <!--                            </div>-->
                            <!-- COL END -->
                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                <?php if (isset($_SESSION['message'])) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?= $_SESSION['message'] ?>
                                    </div>
                                    <?php unset($_SESSION['message']);
                                } ?>
                                <div class="card custom-card overflow-hidden">
                                    <div class="card-header  border-bottom-0 pb-0">
                                        <div>
                                            <div class="d-flex">
                                                <label class="main-content-label my-auto pt-2">ارسال پیام یکسان به
                                                    دانش آموزان </label>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید یک پیام یکسان را به‌صورت همزمان برای چند دانش آموز از طریق چت پشتیبانی ارسال کنید. این قابلیت برای اطلاع‌رسانی، اعلام برنامه‌ها، یادآوری‌ها و ارسال پیام‌های عمومی به گروهی از دانش آموزان کاربرد دارد."
                                                      aria-label="راهنمای ارسال پیام یکسان به دانش آموزان"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                            </div>
                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> ارسال پیام به  دانش آموزان من یا همه دانش آموزان یک پایه </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <form method="post" data-parsley-validate="">
                                                <div class="col-lg-12 mg-t-auto">

                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            ارسال به پایه<span
                                                                class="tx-danger">*</span>
                                                        </div>


                                                        <select class="form-control select2 "
                                                                name="codecourse"
                                                                aria-describedby="basic-addon2" required>
                                                            <option label="پایه ">
                                                            </option>
                                                            <?php
                                                            $course = retCourse();
                                                            foreach ($course as $day) {
                                                                ?>
                                                                <option value="<?php echo $day->codecourse ?>"><?php echo $day->coursename ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mg-t-20">
                                                    <label class="custom-switch">
                                                        <input type="checkbox" name="sendAll"
                                                               class="custom-switch-input" checked="">
                                                        <span class="custom-switch-indicator"></span>
                                                        <span class="custom-switch-description">ارسال پیام به همه دانش آموزان در پایه</span>
                                                    </label>
                                                </div>
                                                <div class="col-xl-12 mt-2">
                                                    <p class="mg-b-10 "> متن پیام <span
                                                            style="color: red">*</span></p>
                                                    <textarea class="form-control" rows="5" name="detail"
                                                              required></textarea>
                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                                <div class="text-left mg-10">
                                                    <button type="submit" class="btn btn-primary "
                                                            name="send-message">ارسال پیام یکسان <i
                                                            class="fe fe-send"></i>

                                                    </button>
                                                </div>

                                            </form>


                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- COL END -->
                        </div>
                    </div>
                    <div class="col-sm-12 col-lg-12 col-xl-4">
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                            <div class="card custom-card overflow-hidden">
                                <div class="card-header  border-bottom-0 pb-0">
                                    <div>
                                        <div class="d-flex">
                                            <label class="main-content-label my-auto pt-2">فعالیت امروز </label>

                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted"> تعداد اقدامات پشتیبانی ثبت شده امروز </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-6 my-auto">
                                            <h6 class="mb-3 font-weight-normal">گزارش پشتیبانی </h6>
                                            <div class="text-right">
                                                <?php
                                                $datenow = date('Y-m-d');

                                                $sql = 'SELECT COUNT(*) as counReportdate FROM suport_report WHERE poshtiban=? AND datenow=?';
                                                $exe1 = $conn->prepare($sql);
                                                $exe1->execute([$_SESSION['admin']['user'], $datenow]);
                                                $natije1 = $exe1->fetch();

                                                ?>
                                                <h3 class="font-weight-bold ml-3 mb-2 text-primary"><?php echo $natije1['counReportdate']; ?>
                                                    گزارش </h3>
                                                <p class="tx-13 my-auto text-muted">  <?php
                                                    echo jdate('l Y/m/d ');
                                                    ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 my-auto">
                                            <div class="forth circle">
                                                <div class="chart-circle-value circle-style">
                                                    <div class="tx-16 font-weight-bold"><?php echo $natije1['counReportdate']; ?>   </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--                        Start 2.7.3-->
                                        <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                            <div class="card custom-card overflow-hidden">
                                                <?php
//                                                $report = reportCallLMS($datenow, $datenow, $_SESSION['admin']['user']);
                                                ?>
                                                <ul class="crypto-transcation list-unstyled mg-b-0">
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> تماس موفق</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط شما</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo 1 ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی تماس</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط شما</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo 1 ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> متنی موفق</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط شما</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo 1 ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی متنی</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات توسط شما</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo 1 ?></h5>
                                                        </div>
                                                    </li>

                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                            <div class="card custom-card overflow-hidden">
                                                <?php

                                                $start = $datenow . ' 00:01';
                                                $end = $datenow . ' 23:59';
                                                $op = retAdmin($_SESSION['admin']['user']);
                                                $op = $op->dakheli ?? '';
                                                $simotel = reportCallSimotel($start, $end, $op);
                                                $simotelIncoming = reportIncomingSimotel($start, $end, $op);
                                                $totalMainSeconds = timeToSeconds($simotel['TIME TOTAL']) + timeToSeconds($simotelIncoming['TIME ANSWERED']);
                                                $totalMainTime = secondsToTime($totalMainSeconds);
                                                ?>
                                                <ul class="crypto-transcation list-unstyled mg-b-0">
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13">پاسخ داده شده</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $simotel['TIME ANSWERED'] ?> </p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div> 
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13">عدم پاسخ</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $simotel['TIME NO ANSWERED'] ?> </p>
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['NO ANSWERED'] ?></h5>
                                                        </div>
                                                    </li>
                                                    <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                        <div class="media-body ">
                                                            <p class="tx-medium mg-b-3 tx-13"> کل تماس شما</p>
                                                            <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                : <?php echo $totalMainTime; ?> </p>
                                                            <!--<p class="tx-11 mg-b-0 tx-gray-900"> زمان : -->
                                                            <?php // echo $simotel['TIME TOTAL'] ?><!-- </p>-->
                                                            <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                        </div>
                                                        <div class="text-right mr-auto my-auto">
                                                            <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] + $simotel['NO ANSWERED'] + $simotelIncoming['ANSWERED']; ?></h5>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- COL END -->
                        <!--                        --><?php //if ($_SESSION['admin']['adminrole'] !== 6) {
                        //                            ?>
                        <!--
                    <div class="row row-sm">
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                            <div class="col-lg-12 mg-b-25 ">
                                <a href="--><?php //echo url('admin/panel/mypersonalcourse.php') ?><!--  "
                                           class="btn btn-block btn-outline-info"><i
                                                    class="fe fe-list"></i> دانش آموزان نفر پایه </a>
                                    </div>
                                </div>
                                <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                    <div class="col-lg-12 mg-b-25 ">
                                        <a href="--><?php //echo url('admin/panel/mystudent.php') ?><!--  "
                                           class="btn btn-block btn-outline-warning"><i
                                                    class="fe fe-list"></i> دانش آموزان فردی </a>
                                    </div>
                                </div>
                            </div>
                            -->
                        <!--                        --><?php //} ?>

                        <!-- COL END -->
                    </div>

                </div>


            </div>

        </div>

    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
