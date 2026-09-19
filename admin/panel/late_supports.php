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

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> تاخیر در پشتیبانی | <?php echo __TITLE ?></title>
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
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h3>لیست تاخیر در پشتیبانی </h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $today = date('Y-m-d');
                            $sql = "SELECT DISTINCT 
                                        dbcourse.username,
                                        dbpersonal.fname,
                                        dbpersonal.lname,
                                        dbpersonal.datecreated
                                    FROM dbcourse
                                    INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse
                                    INNER JOIN suport_user    ON dbcourse.username = suport_user.username
                                    INNER JOIN dbpersonal     ON dbcourse.username = dbpersonal.codemeli
                                    WHERE dbcourse.suporter = :supporter
                                      AND dbcourse.activityuser = 1
                                      AND codtbl_course.activecourse = 1
                                      AND suport_user.afterdate < :today
                                    ORDER BY dbpersonal.lname, dbpersonal.fname";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([
                                ':supporter' => $_SESSION['admin']['user'],
                                ':today'     => $today,
                            ]);
                            $students = $stmt->fetchAll(PDO::FETCH_OBJ);

                            if (count($students) > 0): ?>
                                <table id="example1" class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام</th>
                                        <th>نام خانوادگی</th>
                                        <th>کدملی</th>
                                        <th>تاریخ ثبت نام</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($students as $stu):
                                        $jalaliDate = '';
                                        if (!empty($stu->datecreated)) {
                                            $parts = explode('-', $stu->datecreated);
                                            if (count($parts) === 3) {
                                                $jalali = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
                                                $jalaliDate = implode('/', $jalali);
                                            } else {
                                                $jalaliDate = $stu->datecreated;
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="wd-15">
                                                <div class="position-relative d-inline-block">
                                                    <div class="btn btn-sm ripple btn-info btn-icon"
                                                         onclick="window.open('../panel/suporting.php?Id='+'<?php echo $stu->username ?>','پشتیبانی'
                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                        <i class="fa fa-key"></i>
                                                    </div>
                                                </div>
                                            </td>

                                            <td><?= htmlspecialchars($stu->fname) ?></td>
                                            <td><?= htmlspecialchars($stu->lname) ?></td>
                                            <td><?= htmlspecialchars($stu->username) ?></td>
                                            <td><?= htmlspecialchars($jalaliDate) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info">تاخیر در پشتیبانی وجود ندارد.</div>
                            <?php endif; ?>
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