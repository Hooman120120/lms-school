<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/function-quiz.php';
include_once '../../inc/jdf.php'; 
displayError(1);
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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['user'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
$user = retPerson($_GET['user']);
if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}

$apiResult = GetQuizSummaryByApi($user->codemeli);
$quizData = [];
if ($apiResult && $apiResult['success']) {
    $quizData = $apiResult['data'];
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> گزارش آزمون ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
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
                                <div class="col-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">گزارش آزمون های : <span
                                                    class="text-primary"><?php echo $user->fname . ' ' . $user->lname ?></span>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap text-md-nowrap table-bordered">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>نام آزمون</th>
                                                <th>تاریخ برگزاری</th>
                                                <th>تعداد سوالات</th>
                                                <th>پاسخ صحیح</th>
                                                <th>پاسخ غلط</th>
                                                <th>بی پاسخ</th>
                                                <th>درصد</th>
                                                <th>تراز</th>
                                                <th>رتبه</th>
                                                <th>شرکت کنندگان</th>
                                                <th>طول آزمون (دقیقه)</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            if (!empty($quizData)) {
                                                $id = 1;
                                                foreach ($quizData as $quiz) {
                                                    // تبدیل تاریخ میلادی به شمسی
                                                    $dateparts = explode(' ', $quiz['test_date']);
                                                    $dateonly = explode('-', $dateparts[0]);
                                                    $jalaliDate = gregorian_to_jalali($dateonly[0], $dateonly[1], $dateonly[2]);
                                                    $jalaliDateStr = implode('/', $jalaliDate);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $id ?></td>
                                                        <td><?php echo $quiz['test_name'] ?></td>
                                                        <td><?php echo $jalaliDateStr ?></td>
                                                        <td><?php echo $quiz['total_questions'] ?></td>
                                                        <td><?php echo $quiz['correct_answers'] ?></td>
                                                        <td><?php echo $quiz['incorrect_answers'] ?></td>
                                                        <td><?php echo $quiz['blank_answers'] ?></td>
                                                        <td class="bg-light"><?php echo $quiz['percent'] ?>%</td>
                                                        <td class="bg-success"><?php echo number_format($quiz['taraz']) ?></td>
                                                        <td class="bg-info"><?php echo $quiz['rank'] ?></td>
                                                        <td><?php echo $quiz['total_participants'] ?></td>
                                                        <td><?php echo $quiz['test_duration'] ?></td>
                                                    </tr>
                                                    <?php
                                                    $id++;
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="12" class="text-center">هیچ آزمونی یافت نشد</td>
                                                </tr>
                                                <?php
                                            }
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
</div>
</body>
</html>
