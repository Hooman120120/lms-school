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
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}
//* End Check Session JUST ONE Session

if (isset($_POST['supporter_excel'])) {
    $op = isset($_GET['Op']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['Op']) ? $_GET['Op'] : '';
    $_SESSION['supporter_excel'] = array(
        "Op" => $op
    );
    header('Location: ../Bill/execel.php');
    exit;
}


if (!isset($dateno)) {
    $todayGregorian = date('Y-m-d');
    list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
    $dateno = "$jy/$jm/$jd";
}

if (!function_exists('secToHMS')) {
    function secToHMS(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}


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
    <!-- Title -->
    <title>گزارش نفرات پشتیبان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--    <div id="global-loader">-->
<!--        <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--    </div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <!-- Date filter form -->
                                        <form method="post" id="talktimeFilterForm" class="row g-3 align-items-end" style="margin:0;">
                                            <div class="col-xl-3">
                                                <p class="mb-1">از تاریخ ثبت پایه :<span style="color: red">*</span></p>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6"></i>
                                                        </div>
                                                    </div>
                                                    <input class="form-control fc-datepicker"
                                                           placeholder="1402/09/10"
                                                           required
                                                           type="text"
                                                           name="startdate"
                                                           value="<?php echo htmlspecialchars($_POST['startdate'] ?? ''); ?>">
                                                </div>
                                            </div>

                                            <div class="col-xl-3">
                                                <p class="mb-1">تا تاریخ :<span style="color: red">*</span></p>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6"></i>
                                                        </div>
                                                    </div>
                                                    <input class="form-control fc-datepicker"
                                                           required
                                                           type="text"
                                                           name="enddate"
                                                           value="<?php echo htmlspecialchars($_POST['enddate'] ?? $dateno); ?>">
                                                </div>
                                            </div>

                                            <div class="col-xl-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary">محاسبه زمان مکالمه</button>
                                                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['Op']) ? '?Op=' . urlencode($_GET['Op']) : '')); ?>"
                                                   class="btn btn-outline-secondary ms-2">بازنشانی</a>
                                            </div>
                                        </form>
                                        <!-- end form -->

                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="main-content-label mb-0">
                                                گزارش تعداد دانش آموزان اپراتور
                                                <?php
                                                $admin = retAdmin($_GET['Op']);
                                                echo $admin->dakheli;
                                                ?>
                                                <span class="text-primary"></span>
                                            </h6>

                                            <?php
                                            $currentUser = $_SESSION['admin']['user'];
                                            if (PermisionUser('excel_permission', $currentUser)):
                                                $op = isset($_GET['Op']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['Op']) ? $_GET['Op'] : '';
                                                if (empty($op)) {
                                                    echo '<p class="text-danger mb-0">خطا: مقدار Op نامعتبر یا وجود ندارد.</p>';
                                                } else {
                                                    ?>
                                                    <form action="" method="post" class="mb-0">
                                                        <input type="hidden" name="supporter_excel" value="1">
                                                        <input type="hidden" name="Op" value="<?= htmlspecialchars($op) ?>">
                                                        <button type="submit" class="btn btn-success ms-2">
                                                            <i class="fa fa-file-excel-o"></i> دانلود اکسل
                                                        </button>
                                                    </form>
                                                    <?php
                                                }
                                            endif;
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap text-md-nowrap table-bordered" id="example1">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>ورود</th>
                                                <th>کد ملی</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>نام پایه</th>
                                                <th>زمان مکالمه</th>
                                                <th>آزمون</th>
                                                <th>آخرین پشتیبانی</th>
                                                <th>پشتیبانی بعدی</th>
                                                <th>نوع</th>
                                                <th>رنگ</th>
                                                <th>پرداختی</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // --- Moved date conversion block to the top ---
                                            $startDate = $endDate = null;
                                            if (!empty($_POST['startdate']) && !empty($_POST['enddate'])) {
                                                $startParts = explode('/', $_POST['startdate']);
                                                $endParts   = explode('/', $_POST['enddate']);
                                                if (count($startParts) === 3 && count($endParts) === 3) {
                                                    list($gyStart, $gmStart, $gdStart) = jalali_to_gregorian($startParts[0], $startParts[1], $startParts[2]);
                                                    list($gyEnd, $gmEnd, $gdEnd)       = jalali_to_gregorian($endParts[0], $endParts[1], $endParts[2]);
                                                    $startDate = sprintf('%04d-%02d-%02d 00:00', $gyStart, $gmStart, $gdStart);
                                                    $endDate   = sprintf('%04d-%02d-%02d 23:59', $gyEnd, $gmEnd, $gdEnd);
                                                }
                                            }

                                            $sql = 'SELECT * FROM dbcourse 
                                                    INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse 
                                                    INNER JOIN dbfactor ON dbcourse.numfactor=dbfactor.idfactor 
                                                    WHERE codtbl_course.activecourse=1 
                                                    AND dbcourse.activityuser=1 
                                                    AND dbcourse.suporter=? 
                                                    AND dbfactor.status=? 
                                                    GROUP BY dbcourse.username';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['Op'], 'موفق']);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $id = 1;
                                            if ($query->rowCount() > 0) {
                                                foreach ($result as $ret) {
                                                    $reportQuery = $conn->prepare('
                                                                                            SELECT status 
                                                                                            FROM suport_report 
                                                                                            WHERE username = ? 
                                                                                              AND datenow BETWEEN ? AND ?
                                                                                        ');
                                                    $reportQuery->execute([$ret->username, $startDate, $endDate]);
                                                    $reports = $reportQuery->fetchAll(PDO::FETCH_ASSOC);

                                                    $totalReports = count($reports);
                                                    $noResponseCount = 0;

                                                    foreach ($reports as $r) {
                                                        $s = trim((string)$r['status']);
                                                        if ($s !== '' && mb_stripos($s, 'عدم') !== false) {
                                                            $noResponseCount++;
                                                        }
                                                    }

                                                    $ratioPercent = $totalReports > 0 ? round(($noResponseCount / $totalReports) * 100, 1) : 0.0;

                                                    $colorRow = false;
                                                    if  ($totalReports > 0 && ($noResponseCount / $totalReports) >= 0.7) {
                                                        $colorRow = true;
                                                    }

                                                    $personal = retPerson($ret->username);

                                                    // ----------------- زمان مکالمه -----------------
                                                    $numbers = array_unique(array_filter([
                                                        $personal->tel1 ?? '',
                                                        $personal->tel2 ?? '',
                                                        $personal->tel3 ?? ''
                                                    ]));

                                                    $totalTalkTime = 0; // in seconds
                                                    $talkTimeDisplay = 'لطفاً بازه زمانی را انتخاب کنید'; // default message when no dates
                                                    $lowTalkTime = false;

                                                    if (!empty($_POST['startdate']) && !empty($_POST['enddate'])) {
                                                        if (!empty($numbers) && $startDate && $endDate) {
                                                            $mh = curl_multi_init();
                                                            $chs = [];
                                                            $errors = [];

                                                            $apiUrl = 'http://5.202.253.246:9880//api/v4/reports/quick/search';
                                                            $apiHeaders = [
                                                                'X-APIKEY: D80ozl7VL8cYwNSdT0DoM0BOfSftFhpiROkJRxA7EVzCEuTlHC',
                                                                'Authorization: Basic SG9uYXIxNDA0OlRvb0AxMjUxMjM2NCE=',
                                                                'Content-Type: application/json'
                                                            ];

                                                            foreach ($numbers as $num) {
                                                                $postBody = [
                                                                    "conditions" => ["to" => (string)$num],
                                                                    "date_range" => ["from" => $startDate, "to" => $endDate],
                                                                    "pagination" => ["start" => 0, "count" => 1000, "sorting" => (object)[]],
                                                                    "alike" => "true"
                                                                ];

                                                                $ch = curl_init();
                                                                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postBody));
                                                                curl_setopt($ch, CURLOPT_HTTPHEADER, $apiHeaders);
                                                                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                                                                curl_multi_add_handle($mh, $ch);
                                                                $chs[] = $ch;
                                                            }

                                                            $running = null;
                                                            do {
                                                                $status = curl_multi_exec($mh, $running);
                                                                if ($running) curl_multi_select($mh, 1.0);
                                                            } while ($running > 0 && $status == CURLM_OK);

                                                            foreach ($chs as $ch) {
                                                                $response = curl_multi_getcontent($ch);
                                                                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                                curl_multi_remove_handle($mh, $ch);
                                                                curl_close($ch);

                                                                if ($httpCode >= 200 && $httpCode < 300 && !empty($response)) {
                                                                    $apiData = json_decode($response, true);
                                                                    $rows = $apiData['data']['data'] ?? ($apiData['data'] ?? []);

                                                                    foreach ($rows as $item) {
                                                                        $seconds = 0;
                                                                        if (isset($item['talk_time'])) $seconds = (int)$item['talk_time'];
                                                                        elseif (isset($item['billsec'])) $seconds = (int)$item['billsec'];
                                                                        elseif (isset($item['duration'])) $seconds = (int)$item['duration'];
                                                                        elseif (isset($item['cdr']['billsec'])) $seconds = (int)$item['cdr']['billsec'];

                                                                        $totalTalkTime += max(0, $seconds);
                                                                    }
                                                                } else {
                                                                    $errors[] = "خطا در فراخوانی API (کد: $httpCode)";
                                                                }
                                                            }
                                                            curl_multi_close($mh);

                                                            if (!empty($errors)) {
                                                                $talkTimeDisplay = '<span class="text-danger" title="' . implode(' | ', $errors) . '">خطا در دریافت زمان مکالمه</span>';
                                                            } else {
                                                                $talkTimeDisplay = '<span title="' . number_format($totalTalkTime) . ' ثانیه">' . secToHMS((int)$totalTalkTime) . '</span>';
                                                                $lowTalkTime = ($totalTalkTime < 300);
                                                            }
                                                        }
                                                    }
                                                    //------------------------------------------------------------------------------------
                                                    if ($lowTalkTime) {
                                                        $rowClass = 'bg-danger';
                                                        $rowStyle = 'background-color: #ff9999 !important; color: white;'; // Hard red
                                                    } elseif ($colorRow) {
                                                        $rowClass = 'bg-danger-subtle';
                                                        $rowStyle = 'background-color: #fdecea !important; color: #6b0505;';
                                                    } else {
                                                        $rowClass = '';
                                                        $rowStyle = '';
                                                    }
                                                    ?>

                                                    <tr class="<?php echo $rowClass; ?>" style="<?php echo $rowStyle; ?>">
                                                        <td class="w-5"><?php echo $id ?></td>
                                                        <td class="w-auto">
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open('suporting.php?Id='+'<?php echo $ret->username ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                <i class="fa fa-key"></i>
                                                            </div>
                                                        </td>
                                                        <td class="w-auto"><?php echo $ret->username; ?></td>
                                                        <?php
                                                        $personal = retPerson($ret->username);
                                                        ?>
                                                        <td class="w-auto">
                                                            <?php echo $personal->fname . ' ' . $personal->lname ?>
                                                            <div class="small text-muted">
                                                                گزارش‌ها: <?php echo $totalReports; ?> |
                                                                عدم پاسخ: <?php echo $noResponseCount; ?> |
                                                                نسبت: <?php echo $ratioPercent; ?>%
                                                            </div>
                                                        </td>

                                                        <?php $dore = getCourses($ret->username);
                                                        $dore = implode(' || ', $dore);
                                                        $displayDore = mb_strlen($dore) > 50 ? mb_substr($dore, 0, 50) . '...' : $dore;
                                                        ?>
                                                        <td title="<?= $dore ?>" style="width:50%;"><?php echo $displayDore ?></td>

                                                        <!-- زمان مکالمه column -->
                                                        <td class="w-auto"><?php echo $talkTimeDisplay ?></td>

                                                        <?php
                                                        $quiz = getTblQuizColor($ret->codecourse, $ret->username);
                                                        ?>
                                                        <td class="w-auto"><?= $quiz[0] ?></td>

                                                        <?php
                                                        $suport = getsuport_user($ret->username);
                                                        if ($suport) {
                                                            $last = implode('/', gregorian_to_jalali(...explode('-', $suport['lastdate'])));
                                                            $after = implode('/', gregorian_to_jalali(...explode('-', $suport['afterdate'])));
                                                            $type = $suport['typesupport'] == 1 ? '<span class="badge badge-success">ویـژه </span>' : 'عــادی';
                                                        } else {
                                                            $last = 'ندارد';
                                                            $after = 'ندارد';
                                                            $type = 'عادی';
                                                        }
                                                        ?>
                                                        <td><?php echo $last ?> </td>
                                                        <td><?php echo $after ?></td>
                                                        <td><?php echo $type ?></td>

                                                        <?php
                                                        switch ($ret->color) {
                                                            case 0: $color = 'قرمز'; break;
                                                            case 1: $color = 'نارنجی'; break;
                                                            case 2: $color = 'زرد'; break;
                                                            case 3: $color = 'سبز'; break;
                                                            default: $color = '';
                                                        }
                                                        ?>
                                                        <td class="w-auto"><?php echo $color; ?></td>

                                                        <?php
                                                        $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                                        $wer = $conn->prepare($swr);
                                                        $wer->execute([1, 1, $ret->username]);
                                                        $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                                        $total = [];
                                                        $factorCheck = [];
                                                        if ($wer->rowCount() > 0) {
                                                            foreach ($recive as $rey) {
                                                                if (!in_array($rey->numfactor, $factorCheck)) {
                                                                    $factor = queryFactor($rey->numfactor);
                                                                    $sum = $factor->wallet + $factor->factorfee;
                                                                    $total[] = $sum;
                                                                    $factorCheck[] = $rey->numfactor;
                                                                }
                                                            }
                                                        }
                                                        $sumtoto = array_sum($total);
                                                        ?>
                                                        <td class="w-auto"><?php echo number_format($sumtoto) ?></td>

                                                    </tr>
                                                    <?php
                                                    $id++;
                                                } // end foreach
                                            } // end if rowCount > 0
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>