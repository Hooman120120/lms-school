<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}
if (isset($_GET['code'])) {
    $course = returnCourse($_GET['code']);
}
include_once '../temp.html';
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
    <title> گروه نهم | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
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
                <?php
                if (isset($_GET['code'])) {
                $groupSql = 'SELECT group_id FROM dbcourse WHERE username = ? AND codecourse = ?';
                $groupStmt = $conn->prepare($groupSql);
                $groupStmt->execute([$_SESSION['auth']['user'], $_GET['code']]);
                $groupId = $groupStmt->fetchColumn();

                $leaderSql = 'SELECT leader_username FROM handhug_groups WHERE id = ?';
                $leaderStmt = $conn->prepare($leaderSql);
                $leaderStmt->execute([$groupId]);
                $leader = $leaderStmt->fetchColumn();
                ?>
                <div class="row row-sm">
                    <div class="col-xl-12 ">
                        <div class="card custom-card">
                            <div class="card-header p-3 tx-medium my-auto tx-white " style="background-color: yellow;">
                                <?php if (isset($_GET['code'])) {
                                    $course = returnCourse($_GET['code']); ?>
                                    <p class="text-black fs-6 "><?= $course->coursename ?><br><br>
                                        <?= $course->coursemuted ?> </p>
                                <?php } else { ?>
                                    <p class="text-black fs-6"> به نسخه آزمایشی بغل دستی خودش آمدید.<br>
                                        <?php echo m('msg.handhug_complete_registration') ?> </p>
                                <?php } ?>
                                <br><br>
                            </div>
                            <div class="card-body">
                                <p class="mg-b-0">
                                </p>
                                <p class="mt-3"> جهت ارتباط با پشتیبان خود ، شماره<span class="text-danger tx-18"
                                                                                        style="text-align: left;direction: ltr">91098185(021)</span>
                                    بعد داخلی
                                    :
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">

                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card"
                             style="background-image: url('<?= url('../assets/img/handhug/vorod.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کلاس
                                            آنلاین</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 fs-8">برنامه کلاس های آنلاین و لینک
                                            ورود </span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php // if (!isset($_GET['demo'])) {
                                ?>
                                <?php // if (act_classSkyroomForThisClass($class->id) == 0) {
                                ?>
                                <button class="btn btn-primary disabled">کلاس فعال نیست</button>
                                <?php // } else {
                                ?>
                                <!--                                        <a href="?code=-->
                                <?php //// echo $course->codecourse
                                ?><!-- &login=true" target="_blank"-->
                                <!--                                           class="btn btn-primary">ورود به کلاس-->
                                <!--                                            آنلاین</a>-->
                                <?php // }
                                //   } else {
                                ?>
                                <!--                                    <button class="btn btn-primary disabled">برای دسترسی به خدمات کلاس آنلاین، پایه موردنظر را خریداری نمایید</button>-->
                                <?php // }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card"
                             style="background-image: url('<?= url('../assets/img/handhug/hoozoor.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">حضورغیاب </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 fs-8">گزارش حضورغیاب شما در کلاس های
                                            آنلاین</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php // if (!isset($_GET['demo'])) {
                                ?>
                                <a href="attendance.php?code=<?php // echo $course->codecourse
                                ?>" target="_blank"
                                   class="btn btn-secondary"><?php echo m('action.attendance_report_course') ?></a>
                                <?php // } else {
                                ?>
                                <?php // }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card"
                             style="background-image: url('<?= url('../assets/img/handhug/taklif.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">جزوات و تکلیف ارائه شده</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 fs-8">جزوات ، متون و تکالیف ارائه شده جهت
                                            دانلود برای این <?php echo t('grade') ?> </span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php // if (!isset($_GET['demo'])) {
                                ?>
                                <?php // } else {
                                ?>
                                <a href="addon.php?code=<?php // echo $course->codecourse
                                ?>&demo"
                                   target="_blank"
                                   class="btn btn-success">مشاهده جزوه</a>
                                <?php // }
                                ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="row row-sm justify-content-center align-content-center">

                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                    <div class="card custom-card"
                         style="background-image: url('<?= url('../assets/img/handhug/gorohhandhug.jpg') ?>');">
                        <div class="card-header  border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">گروه بغل دستی</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 fs-8">مشاهده اعضای بغل دستی</span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <a href="handhug-members.php?code=<?php echo (int)$course->codecourse
                            ?>&group=<?= $groupId ?>" target="_blank"
                               class="btn position-relative" style="background-color: #0c5460; color: white;">
                                مشاهده
                                <span class="position-absolute top-0 start-100 translate-middle badge ">
                                    </span>
                            </a>
                        </div>
                    </div>
                </div>

                <?php

                if ($_SESSION['auth']['user'] == $leader) {

                    ?>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card"
                             style="background-image: url('<?= url('../assets/img/handhug/samane.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">سامانه من سرگروه</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 fs-8">سامانه من سرگروه</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <a href="head-group.php?code=<?php echo (int)$course->codecourse
                                ?>&group=<?= $groupId ?>" target="_blank"
                                   class="btn position-relative" style="background-color: orange; color: white;">
                                    مشاهده
                                    <span class="position-absolute top-0 start-100 translate-middle badge ">
                                            </span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php }
                } ?>
            </div>


        </div>
    </div>
</div>


<!-- Main Footer-->
<?php include '../footers.php'; ?>
<!--End Footer-->


</body>

</html>