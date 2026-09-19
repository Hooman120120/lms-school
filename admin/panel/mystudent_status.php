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

if (!isset($_SESSION['adminrole'])) {

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
    <title> خلاصه وضعیت دانش آموزان | <?php echo __TITLE ?></title>
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
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                <div class="card custom-card overflow-hidden">
                    <div class="card-header  border-bottom-0 pb-0">
                        <div>
                            <div class="d-flex">
                                <label class="main-content-label my-auto pt-2">خلاصه وضعیت
                                    دانش آموزان </label>

                            </div>
                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> وضعیت دانش آموزان من (نفر دوره)  </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <table id="example1" class="table table-bordered">
                                <thead>
                                <tr class="bg-light">
                                    <th>عنوان</th>
                                    <th>دوره</th>
                                    <th class="wd-15">تعداد</th>
                                    <th class="wd-15"></th>

                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <?php
                                    $sql = 'SELECT COUNT(*) as cowait FROM dbcourse WHERE type_process=1 AND suporter=? ';
                                    $query = $conn->prepare($sql);
                                    $query->execute([$_SESSION['admin']['user']]);
                                    $ret = $query->fetch(PDO::FETCH_OBJ);

                                    ?>
                                    <td>در انتظار تایید رنگبندی دستی</td>
                                    <td>#</td>
                                    <td><?php echo $ret->cowait ?></td>
                                    <td>
                                        <div class="btn  ripple btn-light "
                                             onclick="window.open('actmanualcolor_op.php?','درخواست های تغییر رنگ'
                                                                 ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                            مشاهده
                                        </div>
                                    </td>

                                </tr>


                                <?php
                                $sql = 'SELECT `color`,`codecourse`, COUNT(*) as cocolor FROM `dbcourse` WHERE `suporter`=? AND `activityuser`=1 GROUP BY `color`,`codecourse` ORDER BY color DESC ';
                                $query = $conn->prepare($sql);
                                $query->execute([$_SESSION['admin']['user']]);
                                $results = $query->fetchAll(PDO::FETCH_OBJ);
                                if ($query->rowCount() > 0) {
                                    foreach ($results as $res) {
                                        if (iscourseActivate($res->codecourse)) {
                                            switch ($res->color) {
                                                case 0:
                                                    $ccolor = ' <div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    $style = 'color:#090909;background-color: #e35b5b;border-color: #000000';
                                                    break;
                                                case 1:
                                                    $ccolor = '<div class="rounded-circle bg-warning" data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title="  حضور بین 25% تا 50% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    $style = 'color:#090909;background-color: #f88b51;border-color: #000000';
                                                    break;
                                                case 2:
                                                    $ccolor = '<div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px; background-color: #fffb02"></div>';
                                                    $style = 'color:#090909;background-color: #fffb02;border-color: #000000';
                                                    break;
                                                case 3:
                                                    $ccolor = '<div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    $style = 'color:#090909;background-color: #3bd006;border-color: #000000';
                                                    break;
                                            }
                                            ?>

                                            <tr>
                                                <td>
                                                    <?php echo $ccolor; ?>
                                                </td>
                                                <?php $namecourse = returnCourse($res->codecourse); ?>
                                                <td><?php echo $namecourse->coursename; ?></td>
                                                <td><?php echo $res->cocolor ?></td>
                                                <td>
                                                    <div class="btn  ripple "
                                                         style="<?php echo $style ?>"
                                                         onclick="window.open('viewlistcolor_admin.php?Id='+'<?php echo $res->codecourse ?>'+'&color='+'<?php echo $res->color ?>','مشاهده کاربران'
                                                                 ,'width=1500,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                        مشاهده
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
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
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>