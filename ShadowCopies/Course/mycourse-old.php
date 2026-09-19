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
if (!isset($_GET['code']) and $_GET['code'] = '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}


$sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND `codtbl_course`.`codecourse`=?';
$query = $conn->prepare($sql);
$query->execute([1, 1, $_SESSION['auth']['user'], $_GET['code']]);
if (!$query->rowCount() > 0) {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
$course = returnCourse($_GET['code']);
$dars = 'SELECT * FROM `dbcourse` WHERE username=? AND codecourse=?';
$qdars = $conn->prepare($dars);
$qdars->execute([$_SESSION['auth']['user'], $_GET['code']]);
if ($qdars->rowCount() > 0) {
    $suporter = $qdars->fetch();
    $suporter = $suporter['suporter'];
}
if (isset($_GET['login'])) {
    $student = retPerson($_SESSION['auth']['user']);
    require_once '../api/Skyroom.php';
    $apiUrl = __SKY;
    $api = new Skyroom($apiUrl);
    $action = 'createLoginUrl';
    $params = array(
        'room_id' => intval($course->link_class),
        'user_id' => $_SESSION['auth']['user'],
        'language' => 'fa',
        'nickname' => $student->fname . ' ' . $student->lname,
        'access' => 1,
        'concurrent' => 1,
        'ttl' => 30,
    );

    $result10 = $api->call($action, $params);

    if (Skyroom\HttpError::IsError($result10)) {
        printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
    } else {
        $sky = output($result10);
        $skytoken = json_decode($sky);
        $skytoken = $skytoken->{'result'};
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
        $ver = $conn->prepare($token);
        $ver->execute([$_SESSION['auth']['user'], $_GET['code'], $datenow, $timenow, $skytoken, $ip, $os]);
        header('location:' . $skytoken);

    }
}
if (isset($_GET['general'])) {
    $student = retPerson($_SESSION['auth']['user']);
    require_once '../api/Skyroom.php';
    $apiUrl = __SKY;
    $api = new Skyroom($apiUrl);
    $action = 'createLoginUrl';
    $params = array(
        'room_id' => intval($course->link_general),
        'user_id' => $_SESSION['auth']['user'],
        'language' => 'fa',
        'nickname' => $student->fname . ' ' . $student->lname,
        'access' => 1,
        'concurrent' => 1,
        'ttl' => 30,
    );

    $result10 = $api->call($action, $params);

    if (Skyroom\HttpError::IsError($result10)) {
        printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
    } else {
        $sky = output($result10);
        $skytoken = json_decode($sky);
        $skytoken = $skytoken->{'result'};
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
        $ver = $conn->prepare($token);
        $ver->execute([$_SESSION['auth']['user'], $_GET['code'], $datenow, $timenow, $skytoken, $ip, $os]);
        header('location:' . $skytoken);

    }
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
    <title> <?php echo $course->coursename ?> | <?php echo __TITLE ?></title>
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

                <div class="row row-sm">
                    <div class="col-xl-12 ">
                        <div class="card custom-card">
                            <div class="card-header p-3 tx-medium my-auto tx-white bg-secondary">
                                <?php echo $course->coursename ?>
                            </div>
                            <div class="card-body">
                                <p class="mg-b-0"> <?php echo $course->coursemuted ?></p>
                                <p class="mt-3"> جهت ارتباط با پشتیبان خود ، شماره<span class="text-danger tx-18" style="text-align: left;direction: ltr">91098185(021)</span> بعد داخلی
                                    : <?php if (isset($suporter) and $suporter != NULL) {
                                        $suporter = retAdmin($suporter);
                                        echo'<span class="text-danger tx-18">'. $suporter->dakheli.'</span>';
                                    } ?> را شماره گیری کنید</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">

                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کلاس آنلاین</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">برنامه کلاس های آنلاین و لینک ورود </span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (act_classSkyroom($course->codecourse) == 0) { ?>
                                    <button class="btn btn-primary disabled">کلاس فعال نیست</button>
                                <?php } else { ?>
                                    <a href="?code=<?php echo $course->codecourse ?> &login=true" target="_blank"
                                       class="btn btn-primary">ورود به کلاس
                                        آنلاین</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به آزمون آنلاین</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">آزمون های آنلاین دروس شما</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (act_quiz($course->codecourse) == 0) { ?>
                                    <button class="btn btn-secondary disabled">آزمون فعال نیست</button>
                                <?php } else { ?>
                                    <form method="post" action="<?php echo url('quiz/login.php') ?>">
                                        <input type="hidden" value="<?php echo $_SESSION['auth']['user'] ?>"
                                               name="username" required>
                                        <?php $user = passUser($_SESSION['auth']['user']); ?>
                                        <input type="hidden" value="<?php echo $user ?>" name="password" required>
                                        <button class="btn btn-secondary " type="submit" name="user_login">ورود به
                                            سامانه آزمون
                                        </button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>
                    </div>


                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کلاس مشاوره</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">کلاس های مشاوره و عمومی</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (act_generalSkyroom($course->codecourse) == 0) { ?>
                                    <button class="btn btn-info disabled">کلاس مشاوره فعال نیست</button>
                                <?php } else { ?>
                                    <a href="?code=<?php echo $course->codecourse ?> &general=true" target="_blank"
                                       class="btn btn-info">ورود به کلاس مشاوره</a>
                                <?php } ?>
                                <a href="<?php echo url('Support/SendTicket.php') ?>" class="btn btn-outline-light">
                                    پیام به پشتیبان خود</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">کارنامه آزمون ها</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"></span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <a href="result-quiz.php?code=<?php echo $course->codecourse ?> " target="_blank"
                                   class="btn btn-info">مشاهده کارنامه</a>

                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کانال ایتا</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">کانال اختصاصی ایتا</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <a href="<?php echo $course->link_eita ?>" target="_blank" class="btn btn-warning">ورود
                                    به کانال ایتا
                                    پایه</a>

                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">حضورغیاب </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">گزارش حضورغیاب شما در کلاس های آنلاین</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <a href="attendance.php?code=<?php echo $course->codecourse   ?>" target="_blank" class="btn btn-secondary">دریافت گزارش حضور غیاب
                                    پایه</a>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="row row-sm">

                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header  border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">فیلم کلاس های برگزار شده</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 text-muted"></span>
                            </div>
                        </div>
                        <?php
                        $film = retmovie($_GET['code']);
                        ?>
                        <div class="product-timeline card-body pt-3 mt-1">
                            <ul class="timeline-1 mb-0">
                                <?php
                                if ($film) {
                                    foreach ($film as $ter) {
                                        ?>
                                        <li class="mt-0"><i class="fe fe-film product-icon"></i> <span
                                                    class="font-weight-semibold mb-4 tx-14 "><?php echo $ter->filmname ?> </span>
                                            <a href="play.php?id=<?php echo $ter->id ?>" target="_blank"
                                               class="btn btn-sm btn-success  float-left  ">مشاهده</a>
                                            <p class="mb-0 text-muted tx-12"><?php echo $ter->host ?></p>
                                        </li>

                                    <?php }
                                } ?>

                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-6">
                    <div class="card custom-card">
                        <div class="card-header  border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">جزوات و تکلیف ارائه شده</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 text-muted">جزوات ، متون و تکالیف ارائه شده جهت دانلود برای این پایه </span>
                            </div>
                        </div>
                        <?php
                        $addon = retaddon($_GET['code']);
                        ?>
                        <div class="card-body">
                            <div class="product-timeline card-body pt-3 mt-1">
                                <ul class="timeline-1 mb-0">
                                    <?php
                                    if ($addon) {
                                        foreach ($addon as $ert) {
                                            ?>
                                            <li class="mt-0"><i class="fe fe-file product-icon"></i> <span
                                                        class="font-weight-semibold mb-4 tx-14 "><?php echo $ert->docname ?> </span>
                                                <a href="attachment.php?id=<?php echo $ert->id ?>" target="_blank"
                                                   class="btn btn-sm btn-success  float-left  ">دانلود</a>
                                                <p class="mb-0 text-muted tx-12"><?php echo $ert->host ?></p>

                                            </li>

                                        <?php }
                                    } ?>

                                </ul>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>


<!-- Main Footer-->
<?php include '../footers.php'; ?>
<!--End Footer-->


</body>
</html>