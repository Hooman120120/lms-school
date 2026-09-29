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
if (!isset($_GET['user']) and !isset($_GET['course'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!isset($_GET['demo'])) {
    $user = retPerson($_GET['user']);
    $course = returnCourse($_GET['course']);
    if (!$user) {
        echo '<strong>404</strong>';
        echo '<script>window.close() </script>';
        die();
    }
    if (!$course) {
        echo '<strong>404</strong>';
        echo '<script>window.close() </script>';
        die();
    }
} else {
    $user = retPerson($_GET['user']);
    $sql = 'SELECT * FROM course_demo WHERE codecourse = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['course']]);
$course = $stmt->fetch(PDO::FETCH_OBJ);
}

$sql = 'SELECT class_id FROM dbcourse WHERE username = ? AND codecourse = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$_GET['user'], $_GET['course']]);
$classId = $stmt->fetchColumn();

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
    <title> <?php echo m('page.attendance_report') ?> | <?php echo __TITLE ?></title>
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
                                        <h6 class="main-content-label mb-1">گزارش حضورغیاب : <span
                                                    class="text-primary"><?php echo $user->fname . ' ' . $user->lname ?></span>
                                        </h6>
                                        <p class="text-muted card-sub-title"> <?php echo m('label.course_name_colon') ?><span
                                                    class="text-primary"><?php echo $course->coursename ?></span>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <table class="table text-nowrap text-md-nowrap table-bordered">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>نام درس و جلسه</th>
                                            <th>تاریخ برگزاری</th>
                                            <th>زمان حضور</th>
                                            <th>وضعیت</th>

                                        </tr>
                                        </thead>
                                        <tbody>

                                        <?php
                                        $sql = 'SELECT * FROM tbl_attendanced WHERE codecourse=? AND class_id=? ORDER BY datestart DESC ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['course'], $classId]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($query->rowCount() > 0) {

                                            foreach ($result as $ret) {
                                                $tbl = retattendance($_GET['user'], $ret->id);
                                                if ($tbl) {

                                                    ?>
                                                    <tr <?php if ($tbl->status == 'غایب') {
                                                        echo 'class="bg-danger" ';
                                                    } ?>>
                                                        <td><?php echo $id ?></td>
                                                        <td><?php echo $ret->classname; ?></td>
                                                        <?php
                                                        $dateno = $ret->datestart;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);

                                                        ?>
                                                        <td><?php echo $dateno ?></td>
                                                        <td><?php echo $tbl->timehozor ?></td>
                                                        <td><?php if ($tbl->status == 'غایب') {
                                                                echo 'غایب';
                                                            }
                                                            elseif ($tbl->status =='problematic'){
                                                                echo 'problematic';
                                                            }
                                                            else {
                                                                echo 'حاضر';
                                                            } ?></td>
                                                    </tr>
                                                    <?php
                                                    $id++;
                                                }
                                            }
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>