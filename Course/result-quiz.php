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
    <title> آزمون های برگزار شده <?php echo m('label.for_course_colon') ?> <?php echo $course1->coursename ?> | <?php echo __TITLE ?></title>
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
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header  border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">آزمون های برگزار شده </label>
                                    </div>
                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> آزمون های  <?php echo $course1->coursename ?>   </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                $sql = 'SELECT * FROM `dbquiz` WHERE codecourse=? ORDER BY idquiz DESC ';
                                $query = $conn->prepare($sql);
                                $query->execute([$_GET['code']]);
                                $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                if ($query->rowCount() > 0) {
                                    foreach ($reserved as $course) {

                                        ?>
                                        <div class="row mt-1">
                                            <div class="col-6">
                                                <span class=""><?php echo $course->quizname; ?></span>
                                            </div>
                                            <div class="col-3">
                                                <?php
                                                $requiz = 'SELECT * FROM result_quiz WHERE quizid=? AND username=?';
                                                $qres = $conn->prepare($requiz);
                                                $qres->execute([$course->idquiz, $_SESSION['auth']['user']]);
                                                $req = $qres->fetchAll(PDO::FETCH_OBJ);

                                                $sql = "SELECT * FROM result_quizzes_jame WHERE quiz_id = ? AND username = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$course->idquiz, $_SESSION['auth']['user']]);
                                                $resultQuizJame = $stmt->fetchAll(PDO::FETCH_OBJ);

                                                //
                                                if ($qres->rowCount() == 0 and $stmt->rowCount() == 0){ ?>
                                                <span class="badge badge-danger tx-15">غایب</span>
                                                <?php
                                                }else { ?>
                                                <a href="<?php echo url('Course/print-quiz.php?quiz=') . $course->idquiz ?> ">
                                                    <div class="d-flex">

                                                        <button class="btn btn-sm btn-block btn-success">دانلود کارنامه
                                                        </button>

                                                    </div>
                                                </a>
                                                <?php }?>

                                            </div>
                                            <?php if($course->resultpdf !=NULL){ ?>
                                            <div class="col-3">
                                                <a href="<?php echo  $course->resultpdf ?> ">
                                                    <div class="d-flex">

                                                        <button class="btn btn-sm btn-block btn-info">دانلود پاسخنامه
                                                        </button>

                                                    </div>
                                                </a>
                                            </div>
                                            <?php } ?>
                                        </div>
                                        <hr>
                                        <?php
                                    }
                                }else{
                                    echo m('msg.no_quiz_for_course');
                                }
                                ?>
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