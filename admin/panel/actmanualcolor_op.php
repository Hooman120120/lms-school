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
//* End Check Session JUST ONE Session

$sql = 'SELECT COUNT(*) as cowait FROM dbcourse WHERE type_process=1 AND suporter=? ';
$query = $conn->prepare($sql);
$query->execute([$_SESSION['admin']['user']]);
$retr = $query->fetch(PDO::FETCH_OBJ);



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
    <title>لیست در انتظار تغییرات دانش آموز | <?php echo __TITLE ?></title>
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
                                        <h6 class="main-content-label mb-1"> تعداد دانش آموزان: <?php echo $retr->cowait ?>
                                            <span
                                                    class="text-primary"></span>
                                        </h6>

                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table table-responsive">
                                        <table class="table text-nowrap text-md-nowrap table-bordered" id="example1">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>ورود</th>
                                                <th>رنگ جدید</th>
                                                <th>کد ملی</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>نام دوره</th>
                                                <th>آزمون</th>
                                                <th>آخرین پشتیبانی</th>
                                                <th>پشتیبانی بعدی</th>
                                                <th>نوع</th>
                                                <th>رنگ</th>
                                                <th>توضیح</th>

                                            </tr>
                                            </thead>
                                            <tbody>

                                            <?php //took active course logic out for more inf  AND codtbl_course.activecourse=1
                                            $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE   dbcourse.activityuser=1  AND dbcourse.type_process=? AND dbcourse.suporter=?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([1,$_SESSION['admin']['user']]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $id = 1;
                                            if ($query->rowCount() > 0) {
                                                foreach ($result as $ret) {
                                                    ?>
                                                    <tr>
                                                        <td class="wd-15"><?php echo $id ?></td>

                                                        <td class="wd-15">
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open('suporting.php?Id='+'<?php echo $ret->username ?>','پشتیبانی'
                                                                         ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                                <i class="fa fa-key"></i></div>

                                                        </td>

                                                        <?php
                                                        switch ($ret->color_wait) {
                                                            case 0:
                                                                $color_wait = 'قرمز';
                                                                break;
                                                            case 1:
                                                                $color_wait = 'نارنجی';
                                                                break;
                                                            case 2:
                                                                $color_wait = 'زرد';
                                                                break;
                                                            case 3:
                                                                $color_wait = 'سبز';
                                                                break;
                                                            case 4:
                                                                $color_wait = 'انصراف';
                                                                break;
                                                        }
                                                        ?>
                                                        <td class="wd-15"><?php echo $color_wait; ?></td>
                                                        <td class="wd-15"><?php echo $ret->username; ?></td>
                                                        <?php
                                                        $personal = retPerson($ret->username);

                                                        ?>
                                                        <td class="wd-15"><?php echo $personal->fname . ' ' . $personal->lname ?></td>
                                                        <?php $namecourse = returnCourse($ret->codecourse); ?>
                                                        <td><?php echo $namecourse->coursename ?></td>
                                                        <?php
                                                        $quiz = getTblQuizColor($ret->codecourse, $ret->username);
                                                        ?>
                                                        <td class="wd-15"><?php echo $quiz[0]; ?></td>


                                                        <?php
                                                        $suport = getsuport_user($ret->username);
                                                        if ($suport) {
                                                            $last = $suport['lastdate'];
                                                            $last = explode('-', $last);
                                                            $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                                                            $last = implode('/', $last);
                                                            //
                                                            $after = $suport['afterdate'];
                                                            $after = explode('-', $after);
                                                            $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                                            $after = implode('/', $after);
                                                            $type = $suport['typesupport'];
                                                            if ($type == 1) {
                                                                $type = '<span class="badge badge-success">ویـژه </span>';
                                                            } else {
                                                                $type = 'عــادی';
                                                            }
                                                        } else {
                                                            $last = 'ندارد';
                                                            $after = 'ندارد';
                                                            $type = 'عادی';
                                                        }
                                                        ?>
                                                        <td><?php echo $last ?> </td>
                                                        <td><?php echo $after ?></td>
                                                        <td><?php echo $type ?></td>
                                                        <?php
                                                        switch ($ret->color) {
                                                            case 0:
                                                                $color = 'قرمز';
                                                                break;
                                                            case 1:
                                                                $color = 'نارنجی';
                                                                break;
                                                            case 2:
                                                                $color = 'زرد';
                                                                break;
                                                            case 3:
                                                                $color = 'سبز';
                                                                break;
                                                        }

                                                        ?>
                                                        <td class="wd-15"><?php echo $color; ?></td>




                                                        <td class="wd-15"><?php echo $ret->color_detail; ?></td>
                                                    </tr>
                                                    <?php
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
