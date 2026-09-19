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
$course1 = returnCourse($_GET['code']);

$sql = 'SELECT class_id FROM dbcourse WHERE username = ? AND codecourse = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$_SESSION['auth']['user'], $_GET['code']]);
$class = $stmt->fetchColumn();
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar Produce">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> کلاسهای آنلاین برگزار شده <?php echo m('label.for_course_colon') ?> <?php echo $course1->coursename ?> | <?php echo __TITLE ?></title>
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
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-12 col-lg-2 col-xl-2">
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-6 col-xl-6">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">حضور و غیاب شما در جلسات آنلاین  برگزار شده </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> جلسات درسی  <?php echo $course1->coursename ?>   </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="product-timeline card-body pt-3 mt-1">
                                    <ul class="timeline-1 mb-0">
                                        <?php
                                        $sql = 'SELECT * FROM `tbl_attendanced` WHERE codecourse=? AND class_id=? ORDER BY datestart DESC ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['code'], $class]);
                                        $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                        foreach ($reserved as $course) {
                                            $dateno = $course->datestart;
                                            $dateno = explode('-', $dateno);
                                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                            $dateno = implode('/', $dateno);

                                            ?>
                                            <li class="mt-0"><i class="fe fe-tv product-icon"></i> <span
                                                        class="font-weight-semibold mb-4 tx-14 "><?php echo $course->classname; ?></span>
                                                <?php
                                                $requiz = 'SELECT * FROM dbattendance WHERE coding=? AND username=?';
                                                $qres = $conn->prepare($requiz);
                                                $qres->execute([$course->id, $_SESSION['auth']['user']]);
                                                $req = $qres->fetch(PDO::FETCH_OBJ);
                                                //
                                                if ($req->status=='غایب'){ ?>
                                                    <span class="badge badge-danger float-left  tx-15">غایب</span>
                                                    <p class="mb-0 text-muted tx-12 "><?php echo $dateno ?></p>
                                                    <?php
                                                }else { ?>
                                                    <span class="badge badge-success float-left  tx-15"><?php echo $req->status  ?></span>
                                                    <p class="mb-0 text-muted tx-12 "><?php echo $dateno ?></p>

                                                <?php }?>
                                            </li>

                                            <?php
                                        } ?>
                                    </ul>

                                    <?php
                                    }else{
                                        echo 'گزارش حضور و غیابی برای شما بارگزاری نشده است';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- col end -->
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
