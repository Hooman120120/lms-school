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
function hasDemo($username)
{
    global $conn;

    $sql = "SELECT * 
            FROM dbcourse_demo 
            INNER JOIN course_demo 
                ON dbcourse_demo.codecourse = course_demo.codecourse 
            WHERE dbcourse_demo.username = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$username]);
    $courses = $stmt->fetchAll(PDO::FETCH_OBJ);

    if ($stmt->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}
function showDemo($username)
{
    global $conn;

    // کوئری دوره‌های دمو
    $sql2 = 'SELECT dbcourse_demo.*, course_demo.coursename
             FROM dbcourse_demo
             INNER JOIN course_demo ON dbcourse_demo.codecourse = course_demo.codecourse
             WHERE dbcourse_demo.username = ?';
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute([$username]);
    $demos = $stmt2->fetchAll(PDO::FETCH_OBJ);

    // شروع کارت
    echo '<div class="card custom-card">
            <div class="card-header border-bottom-0 pb-0">
                <div>
                    <div class="d-flex">
                        <label class="main-content-label my-auto pt-2">دوره های آزمایشی ثبت نام شده</label>
                    </div>
                    <span class="d-block tx-12 mt-2 mb-0 text-muted">دوره های آزمایشی برای شما</span>
                </div>
            </div>
            <div class="card-body">';

    // رندر دوره‌های دمو
    if (!empty($demos)) {
        foreach ($demos as $course) {
            $coursename = htmlspecialchars($course->coursename);
            $codecourse = urlencode($course->codecourse);
            $link = url("Course/mycourse.php?code=$codecourse&demo");
            echo '<div class="row mt-1">
                    <div class="col-8">
                        <span>' . $coursename . '</span>
                    </div>
                    <div class="col-4">
                        <a href="' . $link . '">
                            <div class="d-flex">
                                <button class="btn btn-block btn-outline-secondary">ورود به دوره</button>
                            </div>
                        </a>
                    </div>
                  </div>';
        }
    }


    // پایان کارت
    echo '</div></div>';
}
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
    <title> دوره های من | <?php echo __TITLE ?></title>
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
        <?php if (isset($courseregister) and $courseregister === 1) { ?>
            <script>
                window.location.href = ' ../index.php ';
            </script>
        <?php } ?>
        <!-- Main Content-->
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                            <?php if (isset($_SESSION['forbidden_message'])) { ?>
                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                    <?= $_SESSION['forbidden_message'] ?>
                                </div>
                            <?php unset($_SESSION['forbidden_message']);
                            } ?>
                            <div class="card custom-card">
                                <div class="card-header  border-bottom-0 pb-0">
                                    <div>
                                        <div class="d-flex">
                                            <label class="main-content-label my-auto pt-2">دوره های ثبت نام شده</label>
                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted">دوره های فعال برای شما </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $sql = 'SELECT * FROM `dbcourse` INNER JOIN codtbl_course ON `dbcourse`.`codecourse`=`codtbl_course`.`codecourse` WHERE dbcourse.activityuser=? AND codtbl_course.activecourse=? AND dbcourse.username=?';
                                    $query = $conn->prepare($sql);
                                    $query->execute([1, 1, $_SESSION['auth']['user']]);
                                    $reserved = $query->fetchAll(PDO::FETCH_OBJ);
                                    if ($query->rowCount() > 0) {
                                        foreach ($reserved as $course) {

                                    ?>
                                            <div class="row mt-1">
                                                <div class="col-9">
                                                    <span class=""><?php echo $course->coursename; ?></span>
                                                </div>
                                                <div class="col-3">
                                                    <a href="<?php echo url('Course/mycourse.php?code=') . $course->codecourse ?> ">
                                                        <div class="d-flex">

                                                            <button class="btn btn-block btn-outline-secondary">ورود به
                                                                دوره
                                                            </button>

                                                        </div>
                                                    </a>

                                                </div>
                                            </div>
                                    <?php
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if (hasDemo($_SESSION["auth"]["user"])) {
                                showDemo($_SESSION["auth"]["user"]);
                            } ?>
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