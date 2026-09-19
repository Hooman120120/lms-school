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

if (!isset($_GET['demo'])) {
    $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=? AND `codtbl_course`.`codecourse`=?';
    $query = $conn->prepare($sql);
    $query->execute([1, 1, $_SESSION['auth']['user'], $_GET['code']]);
    if (!$query->rowCount() > 0) {
        header('location: ' . url('Course/allcourse.php'));
        die();
    }


    $checkClass = 'SELECT * FROM dbcourse WHERE username = ? AND codecourse = ? AND class_id IS NOT NULL';
    $stmtt = $conn->prepare($checkClass);
    $stmtt->execute([$_SESSION['auth']['user'], $_GET['code']]);
    $thisCourse = $stmtt->fetch(PDO::FETCH_OBJ);
    if (!$stmtt->rowCount() > 0) {
        $_SESSION['forbidden_message'] = 'شما هنوز در هیچ کلاسی عضو نشده‌اید. لطفاً منتظر تایید مدیریت باشید.';
        header('location: ' . url('Course/allcourse.php'));
        die();
    }

    $getClassName = 'SELECT * FROM classes WHERE id = ?';
    $classStmt = $conn->prepare($getClassName);
    $classStmt->execute([$thisCourse->class_id]);
    $class = $classStmt->fetch(PDO::FETCH_OBJ);
    $course = returnCourse($_GET['code']);
    $dars = 'SELECT * FROM `dbcourse` WHERE username=? AND codecourse=?';
    $qdars = $conn->prepare($dars);
    $qdars->execute([$_SESSION['auth']['user'], $_GET['code']]);
    if ($qdars->rowCount() > 0) {
        $suporter = $qdars->fetch();
        $suporter = $suporter['suporter'];
    }
} else {
    $sql = "SELECT * FROM course_demo WHERE codecourse = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['code']]);
    $course = $stmt->fetch(PDO::FETCH_OBJ);
}
if (isset($_GET['login'])) {
//    $student = retPerson($_SESSION['auth']['user']);
//    require_once '../api/Skyroom.php';
//    $apiUrl = __SKY;
//    $api = new Skyroom($apiUrl);
//    $action = 'createLoginUrl';
//    $params = array(
//        'room_id' => intval($course->link_class),
//        'user_id' => $_SESSION['auth']['user'],
//        'language' => 'fa',
//        'nickname' => $student->fname . ' ' . $student->lname,
//        'access' => 1,
//        'concurrent' => 1,
//        'ttl' => 3600,
//    );
//
//    $result10 = $api->call($action, $params);
//
//    if (Skyroom\HttpError::IsError($result10)) {
//        printf('<pre>%s (%d)</pre>', $result10->getMessage(), $result10->getCode());
//    } else {
//        $sky = output($result10);
//        $skytoken = json_decode($sky);
//        $skytoken = $skytoken->{'result'};
//        date_default_timezone_set("Iran");
//        $datenow = date("Y-m-d");
//        $timenow = date('H:i:s');
//        $ip = GetIp();
//        $os = GetOs();
//        $token = 'INSERT INTO log_skyroom(username,codecourse,datelogin,timelogin,url,ip,os) VALUES (?,?,?,?,?,?,?)';
//        $ver = $conn->prepare($token);
//        $ver->execute([$_SESSION['auth']['user'], $_GET['code'], $datenow, $timenow, $skytoken, $ip, $os]);
//        header('location:' . $skytoken);
//    }

         $student = retPerson($_SESSION['auth']['user']);
    
         $apiUrl = 'https://room.honaramoozesh.ir/api/enroll';
         $params = array(
             'roomId'   => trim($course->link_class),
             'username' => $_SESSION['auth']['user'],
             'password' => $_SESSION['auth']['user'],
             'name'     => $student->fname,
             'lastName' => $student->lname,
             'role'     => 'student'
         );
    
         $ch = curl_init($apiUrl);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
         curl_setopt($ch, CURLOPT_HTTPHEADER, [
             'Content-Type: application/json',
             'X-API-Key: -iQUqvEIIrq3DlkiHrt0K0ipUODyd62kJ3TP2Cxky50'
         ]);
    
         $response = curl_exec($ch);
         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
    
         $result = json_decode($response, true);
     //     dd($result);
         if ($httpCode == 200 && isset($result['success']) && $result['success'] === true) {
    
             $joinLink = $result['data']['joinUrl'];
    
             // 🔴 خیلی مهم: قبلش هیچ echo / print نداشته باشی
             header("Location: " . $joinLink);
             exit;
         } else {
             die("خطا در اتصال به کلاس آنلاین");
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


$student_username = $_SESSION['auth']['user'] ?? ($_SESSION['student']['user'] ?? ''); // adjust if your session key differs
$codecourse = (int)($course->codecourse ?? 0);

$hwTodoCount = 0;

if ($student_username !== '' && $codecourse > 0) {
$q = $conn->prepare("
    SELECT COUNT(DISTINCT h.id)
    FROM dbcourse d
    INNER JOIN classes c ON c.id = d.class_id
    INNER JOIN homework_admin h ON h.class_id = c.id
    LEFT JOIN homework_student s ON s.homework_id = h.id AND s.student_username = d.username
    LEFT JOIN private_homework_students phs ON phs.homework_id = h.id AND phs.username = d.username
    WHERE d.username = ?
      AND c.codecourse = ?
      AND h.status = 'approved'
      AND s.id IS NULL
      AND (h.deadline > NOW() OR h.deadline IS NULL)   -- <-- این خط اضافه شده
      AND (
            h.homework_type = 0
            OR phs.username IS NOT NULL
          )
");
    $q->execute([$student_username, $codecourse]);
    $hwTodoCount = (int)$q->fetchColumn();

}
$sqll = 'SELECT * FROM dbcourse WHERE username = ? AND codecourse = ?';
$stmtt = $conn->prepare($sqll);
$stmtt->execute([$_SESSION['auth']['user'], $_GET['code']]);
$thisUser = $stmtt->fetch(PDO::FETCH_OBJ);



include_once '../temp.html';
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
    $studentCourseHeaderColor = $studentMenuColor ?? null;
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm">
                    <div class="col-xl-12 ">
                        <div class="card custom-card">
                            <div class="card-header p-3 tx-medium my-auto tx-white <?= $studentCourseHeaderColor === null ? 'bg-secondary' : '' ?>"
                                 <?= $studentCourseHeaderColor !== null ? 'style="background-color: ' . htmlspecialchars($studentCourseHeaderColor, ENT_QUOTES, 'UTF-8') . ' !important;"' : '' ?>>
                                <div><?php echo htmlspecialchars((string)$course->coursename, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!isset($_GET['demo'])): ?>
                                    <div class="mt-2"><?php echo htmlspecialchars((string)$class->name, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="mg-b-0"> <?php echo $course->coursemuted ?></p>
                                <?php if (!isset($_GET['demo']) and $thisUser->has_operator != 0) { ?>
                                    <p class="mt-3"> جهت ارتباط با پشتیبان خود ، شماره<span class="text-danger tx-18"
                                                                                            style="text-align: left;direction: ltr">91098185(021)</span>
                                        بعد داخلی
                                        : <?php if (isset($suporter) and $suporter != NULL) {
                                            $suporter = retAdmin($suporter);
                                            echo '<span class="text-danger tx-18">' . $suporter->dakheli . '</span>';
                                        } ?> را شماره گیری کنید</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <?php $faragir = retPerson($_SESSION['auth']['user']) ?>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= $faragir->gender == 'خانم' ? url('../assets/img/mycourse/online-class-girl.jpg') : url('../assets/img/mycourse/online-class-boy.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کلاس
                                            آنلاین</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">برنامه کلاس های آنلاین و لینک
                                            ورود </span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!isset($_GET['demo'])) { ?>
                                    <?php if (act_classSkyroomForThisClass($class->id) == 0) { ?>
                                        <button class="btn btn-primary disabled">کلاس فعال نیست</button>
                                    <?php } else { ?>
                                        <a href="?code=<?php echo $course->codecourse ?>&login=true" target="_blank"
                                           class="btn btn-primary">ورود به کلاس
                                            آنلاین</a>
                                    <?php }
                                } else { ?>
                                    <?php if (act_classSkyroomDemo($_GET['code']) == 0) { ?>
                                        <button class="btn btn-primary disabled">کلاس فعال نیست</button>
                                    <?php } else { ?>
                                        <a href="?code=<?php echo $course->codecourse ?>&login=true&demo" target="_blank"
                                           class="btn btn-primary">ورود به کلاس
                                            آنلاین</a>
                                    <?php }
                                } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= url('../assets/img/mycourse/test.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">ورود به سامانه آزمون
                                        </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"></span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!isset($_GET['demo'])) { ?>
                                    <? //php echo url('quiz/login.php')
                                    ?>
                                    <form method="post" action="https://my.dast-garmi.ir/login.php">
                                        <input type="hidden" value="<?php echo $_SESSION['auth']['user'] ?>"
                                               name="username" required>
                                        <?php $user = passUser($_SESSION['auth']['user']); ?>
                                        <input type="hidden" value="<?php echo $user ?>" name="password" required>
                                        <button class="btn btn-secondary " type="submit" name="user_login">ورود به
                                            سامانه آزمون
                                        </button>
                                    </form>
                                <?php  } else { ?>
                                    <button class="btn btn-secondary disabled">برای دسترسی به خدمات آزمون، پایه موردنظر را خریداری نمایید</button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>


                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= $faragir->gender == 'خانم' ? url('../assets/img/mycourse/council-girl.jpg') : url('../assets/img/mycourse/council-boy.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کلاس مشاوره</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">کلاس های مشاوره و عمومی</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!isset($_GET['demo'])) { ?>
                                    <?php if (act_generalSkyroom($course->codecourse) == 0) { ?>
                                        <button class="btn btn-info disabled">کلاس مشاوره فعال نیست</button>
                                    <?php } else { ?>
                                        <a href="?code=<?php echo $course->codecourse ?> &general=true" target="_blank"
                                           class="btn btn-info">ورود به کلاس مشاوره </a>
                                    <?php } ?>

                                    <a href="<?php echo url('Support/SendTicket.php') ?>" class="btn btn-outline-light">
                                        پیام به پشتیبان خود</a>
                                <?php } else { ?>
                                    <button class="btn btn-info disabled">برای دسترسی به خدمات کلاس مشاوره، پایه موردنظر را خریداری نمایید</button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= url('../assets/img/mycourse/addon.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">جزوات و تکلیف ارائه شده</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">جزوات ، متون و تکالیف ارائه شده جهت
                                            دانلود برای این پایه </span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!isset($_GET['demo'])) { ?>
                                    <a href="select_lesson.php?code=<?php echo $course->codecourse ?>&type=addon"
                                       target="_blank"
                                       class="btn btn-success">مشاهده جزوه</a>
                                <?php } else { ?>
                                    <a href="addon.php?code=<?php echo $course->codecourse ?>&demo"
                                       target="_blank"
                                       class="btn btn-success">مشاهده جزوه</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!--                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">-->
                    <!--                        <div class="card custom-card" style="background-image: url('--><? //= url('../assets/img/mycourse/exam-result.jpg') 
                    ?>
                    <!--                            <div class="card-header  border-bottom-0 pb-0">-->
                    <!--                                <div>-->
                    <!--                                    <div class="d-flex">-->
                    <!--                                        <label class="main-content-label my-auto pt-2">کارنامه آزمون ها</label>-->
                    <!--                                    </div>-->
                    <!--                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">گزارش عملکرد شما از طریق امتحانات</span>-->
                    <!--                                </div>-->
                    <!--                            </div>-->
                    <!--                            <div class="card-body text-center">-->
                    <!--                                --><?php //if (!isset($_GET['demo'])) { 
                    ?>
                    <!--                                    <a href="result-quiz.php?code=--><?php //echo $course->codecourse 
                    ?><!-- " target="_blank"-->
                    <!--                                       class="btn btn-info">مشاهده کارنامه</a>-->
                    <!--                                --><?php //} else { 
                    ?>
                    <!--                                    <button class="btn btn-info disabled">برای دسترسی به خدمات آزمون، پایه موردنظر را خریداری نمایید</button>-->
                    <!--                                --><?php //} 
                    ?>
                    <!--                            </div>-->
                    <!--                        </div>-->
                    <!--                    </div>-->
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= url('../assets/img/mycourse/eitaa.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">لینک ورود به کانال ایتا</label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">کانال اختصاصی ایتا</span>
                                </div>
                            </div>

                            <div class="card-body text-center flex-row d-flex justify-content-between">
                                <?php if (!isset($_GET['demo'])) {
                                    if (!empty($class->link_eita)) {
                                        ?>
                                        <a href="<?php echo $class->link_eita; ?>" target="_blank" class="btn btn-warning">
                                            ورود به کانال ایتا پایه
                                        </a>
                                    <?php } else { ?>
                                        <!-- OLD BUTTON (unchanged) -->
                                        <a href="<?php echo $course->link_eita; ?>" target="_blank"
                                           class="btn btn-warning">
                                            ورود به کانال ایتا پایه
                                        </a>
                                    <?php } ?>

                                    <?php
                                    // show new button only if the proper group link exists (not null/empty)
                                    $username = $re['username']; // keep your real key if different

                                    $stmt = $conn->prepare("SELECT gender FROM dbpersonal WHERE codemeli = ? LIMIT 1");
                                    $stmt->execute([$username]);
                                    $genderDb = trim((string)$stmt->fetchColumn());

                                    $gender = ($genderDb === 'خانم') ? 'female' : (($genderDb === 'آقا') ? 'male' : null);

                                    $groupLink = null;
                                    if ($gender === 'female') $groupLink = $course->girls_group ?? null;
                                    if ($gender === 'male')   $groupLink = $course->guys_group ?? null;

                                    $groupLink = is_string($groupLink) ? trim($groupLink) : '';
                                    ?>

                                    <?php if ($groupLink !== '') { ?>
                                        <div style="margin-top:10px;"></div>
                                        <a href="<?= htmlspecialchars($groupLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-warning">
                                            گروه گفتگو ایتا
                                        </a>
                                    <?php } ?>

                                <?php } else { ?>

                                    <button class="btn btn-warning disabled">
                                        برای دسترسی به خدمات کانال اطلاع رسانی، پایه موردنظر را خریداری نمایید
                                    </button>

                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card" style="background-image: url('<?= $faragir->gender == 'خانم' ? url('../assets/img/mycourse/attendance-girl.jpg') : url('../assets/img/mycourse/attendance-boy.jpg') ?>');">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">حضورغیاب </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted">گزارش حضورغیاب شما در کلاس های
                                            آنلاین</span>
                                </div>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!isset($_GET['demo'])) { ?>
                                    <a href="attendance.php?code=<?php echo $course->codecourse ?>&class=<?= $class->id ?>" target="_blank"
                                       class="btn btn-secondary">دریافت گزارش حضور غیاب
                                        پایه</a>
                                <?php } else { ?>
                                    <button class="btn btn-secondary disabled">برای دسترسی به خدمات کلاس آنلاین، پایه موردنظر را خریداری نمایید</button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="row row-sm justify-content-center align-content-center">

                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                    <div class="card custom-card" style="background-image: url('<?= url('../assets/img/mycourse/videos.jpg') ?>');">
                        <div class="card-header  border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">فیلم کلاس های برگزار شده</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 text-muted">فیلم جلسات گذشته جهت مرور مطالب
                                        درسی</span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <?php if (!isset($_GET['demo'])) { ?>
                                <a href="select_lesson.php?code=<?php echo $course->codecourse ?>&type=movie"
                                   target="_blank"
                                   class="btn btn-danger">مشاهده فیلم</a>
                            <?php } else { ?>
                                <a href="movie.php?code=<?php echo $course->codecourse ?>&demo"
                                   target="_blank"
                                   class="btn btn-danger">مشاهده فیلم</a>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                    <div class="card custom-card" style="background-image: url('<?= url('../assets/img/mycourse/homework.jpg') ?>');">
                        <div class="card-header  border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">تکالیف</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 text-muted">گزارش تکالیف شما در کلاس های
                                        آنلاین</span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <?php if (!isset($_GET['demo'])) { ?>
                                <a href="homework.php?code=<?php echo (int)$course->codecourse ?>" target="_blank"
                                   class="btn btn-secondary position-relative">
                                    دریافت گزارش تکالیف پایه

                                    <?php if ($hwTodoCount > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                <?= (int)$hwTodoCount ?>
                                            </span>
                                    <?php endif; ?>
                                </a>
                            <?php } else { ?>
                                <button class="btn btn-secondary disabled">برای دسترسی به خدمات کلاس آنلاین، پایه موردنظر را خریداری نمایید</button>
                            <?php } ?>
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
