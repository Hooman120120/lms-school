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

// Protecting against SESSION Hijacking
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

// --- API INTEGRATION LOGIC START ---

// 1. Get the username (codemeli) from the URL
$student_username = $_GET['username'] ?? '';

$api_results = null;
$error_message = '';

if (!empty($student_username)) {
    // 2. Define the Website A API Endpoint
    // Replace 'https://website-a.com' with the actual domain of Website A
    $api_url = "https://www.dast-garmi.ir/api/get-results.php?codemeli=" . urlencode($student_username);

    // 3. Call the API using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $api_results = json_decode($response, true);
    } elseif ($http_code == 404) {
        $error_message = "هیچ سابقه آزمونی برای این کد ملی در سامانه آزمون یافت نشد.";
    } else {
        $error_message = "خطا در برقراری ارتباط با سرور آزمون (کد خطا: $http_code)";
    }
} else {
    $error_message = "کد ملی در آدرس بار (URL) یافت نشد.";
}

// --- API INTEGRATION LOGIC END ---
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
    <title>کارنامه دانش‌آموز | <?php echo __TITLE; ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <div class="container-fluid">
        <div class="row row-sm">
            <div class="col-lg-12">
                <div class="card custom-card mt-4">
                    <div class="card-body">
                        <div class="main-content-label mg-b-5">
                            نتایج آزمون‌های دانش‌آموز: <?php echo htmlspecialchars($student_username); ?>
                        </div>
                        <p class="mg-b-20">لیست تمامی آزمون‌های شرکت شده در سامانه آزمون آنلاین.</p>

                        <?php if ($api_results && $api_results['success']): ?>
                            <div class="alert alert-info">
                                نام دانش‌آموز: <strong><?php echo $api_results['user_info']['full_name']; ?></strong> |
                                تعداد کل آزمون‌ها: <strong><?php echo $api_results['total_exams_found']; ?></strong>
                            </div>

                            <div class="table-responsive">
                                <table id="example1" class="table table-bordered table-striped mg-b-0 text-center">
                                    <thead class="thead-dark">
                                    <tr>
                                        <th>ردیف</th>
                                        <th>نام آزمون</th>
                                        <th>تاریخ شروع</th>
                                        <th>وضعیت</th>
<!--                                        <th>تراز / نمره</th>-->
<!--                                        <th>عملیات</th>-->
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $counter = 1;
                                    foreach ($api_results['exams'] as $exam):
                                        // Convert UTC to Jalali if necessary using your jdf.php
                                        // $date = jdate('Y/m/d H:i', strtotime($exam['utstart_datetime']));
                                        $date = $exam['utstart_datetime'];
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                            <td dir="ltr"><?php echo htmlspecialchars($date); ?></td>
                                            <td>
                                                <?php if ($exam['utfinish'] == 1): ?>
                                                    <span class="badge badge-success">کامل شده</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">ناتمام</span>
                                                <?php endif; ?>
                                            </td>
<!--                                            <td class="tx-bold tx-primary">--><?php //echo ($exam['utbalance'] ?: '0'); ?><!--</td>-->
<!--                                            <td>-->
<!--                                                <a href="https://www.website-a.com/results.php?utid=--><?php //echo $exam['utid']; ?><!--" target="_blank" class="btn btn-sm btn-info">-->
<!--                                                    مشاهده جزئیات-->
<!--                                                </a>-->
<!--                                            </td>-->
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>

</body>
</html>
