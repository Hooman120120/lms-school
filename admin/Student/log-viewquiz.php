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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['user']) and !isset($_GET['course'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
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
    <title> گزارش آزمون های پایه | <?php echo __TITLE ?></title>
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
                                        <p class="text-muted card-sub-title"> نام پایه :<span
                                                    class="text-primary"><?php echo $course->coursename ?></span>
                                        </p>
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
                                            <th>نام آزمون و جلسه</th>
                                            <th>نام درس</th>
                                            <th>توالی</th>
                                            <th>تاریخ برگزاری</th>
                                            <th>تعداد سوالات</th>
                                            <th>پاسخ صحیح</th>
                                            <th>پاسخ غلط</th>
                                            <th>بی پاسخ</th>
                                            <th>نمره</th>
                                            <th>تراز</th>
                                            <th>رتبه</th>
                                            <th>شرکت کنندگان</th>
                                            <th>طول آزمون</th>

                                        </tr>
                                        </thead>
                                        <tbody>

                                        <?php
                                        $sql = 'SELECT * FROM dbquiz WHERE codecourse=? ORDER BY datestart DESC ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['course']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($query->rowCount() > 0) {

                                            foreach ($result as $ret) {
                                                $tbl = returnquiz($_GET['user'], $ret->idquiz);
                                                if ($tbl) {

                                                    ?>
                                                    <tr>
                                                        <td><?php echo $id ?></td>
                                                        <td><?php echo $ret->quizname; ?></td>
                                                        <td><?php $dars = getdars($ret->dars);
                                                            echo $dars ?></td>
                                                        <td><?php echo $ret->tasalsol; ?></td>
                                                        <?php
                                                        $dateno = $ret->datestart;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td><?php echo $dateno ?></td>
                                                        <td><?php echo $tbl->allquestion ?></td>
                                                        <td><?php echo $tbl->trueresponse ?></td>
                                                        <td><?php echo $tbl->falseresponse ?></td>
                                                        <td><?php echo $tbl->unresponse ?></td>
                                                        <td class="bg-light"><?php echo $tbl->nomre ?></td>
                                                        <td class="bg-success"><?php echo number_format($tbl->taraz) ?></td>
                                                        <td class="bg-info"><?php echo $tbl->rotbe ?></td>
                                                        <td><?php echo countresultquiz($ret->idquiz) ?></td>
                                                        <td><?php echo $tbl->duration ?></td>

                                                    </tr>
                                                    <?php

                                                } else { ?>
                                                    <tr class="bg-danger">
                                                        <td><?php echo $id ?></td>
                                                        <td><?php echo $ret->quizname; ?></td>
                                                        <td><?php $dars = getdars($ret->dars);
                                                            echo $dars ?></td>
                                                        <td><?php echo $ret->tasalsol; ?></td>
                                                        <?php
                                                        $dateno = $ret->datestart;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);

                                                        ?>
                                                        <td><?php echo $dateno ?></td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                        <td>غایب</td>
                                                    </tr>
                                                    <?php
                                                }
                                                $id++;
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
    </div>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>