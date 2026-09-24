<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
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
if ($adminRole != 0 and $adminRole != 1  and $adminRole != 51 and $adminRole != 52) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!checkvalidcourse(intval($_GET['code']))) {
    header('location: ' . url('admin/index.php'));
    die();
}

// after: $re = sessionAdmin(...); and after you validate session_id
$adminUsername = $re['username'];   // e.g. "thisisit"

$stmtRole = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
$stmtRole->execute([$adminUsername]);

$adminrole = (int)($stmtRole->fetchColumn() ?? 0);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست دانش آموزان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
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
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row row-sm">
                                    <div class="col-9">
                                        <?php $course = returnCourse($_GET['code']); ?>

                                        <h6 class="main-content-label mb-1">لیست دانش آموزان پایه <span
                                                class="text-warning"> <?php echo $course->coursename ?> (<?php echo $_GET['code'] ?>)</span>
                                        </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>

                                    </div>
                                    <?php if ((int)$adminRole == 0) { ?>
                                        <div class="col-3 text-left">
                                            <a href="export-student.php?code=<?php echo $_GET['code'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fe fe-download"></i> خروجی لیست دانش آموزان
                                            </a>
                                        </div>
                                    <?php } ?>

                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>#</th>
                                            <th>نام دانش آموز</th>
                                                <th>کدملی</th>
                                                <th>شماره فاکتور</th>
                                                <th>وضعیت</th>
                                                <th>ایجاد کننده</th>
                                                <th>پشتیبان</th>
                                                <th>اولین ورود</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (in_array($_SESSION['admin']['adminrole'], [0, 6, 51, 52])) {
                                            $sql = 'SELECT * FROM dbcourse WHERE codecourse=? AND activityuser = 1';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['code']]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        }
                                        elseif ($_SESSION['admin']['adminrole'] == 1 ){
                                            $sql = 'SELECT * FROM dbcourse WHERE codecourse=? AND activityuser = 1 AND suporter = ?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['code'],$_SESSION['admin']['user'] ]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);

                                        }
                                        else{ echo"دسترسی ندارید";
                                        return;}

                                        if ($query->rowCount() > 0) {
                                            $counter=1;
                                            foreach ($result as $ret) {
                                                $fullname = retPerson($ret->username);
                                                $factor=queryFactor($ret->numfactor);
                                                $firstlogin=retdblogin($ret->username);

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $counter ?></td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                                            onclick="window.open('../panel/suporting.php?Id='+'<?php echo $ret->username ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                    <?php if ((int)$adminRole !== 3) { ?>

                                                        <td class="wd-100"><?php echo $ret->username ?></td>
                                                        <td class="wd-100"><?php echo $ret->numfactor ?></td>
                                                        <td class="wd-100"><?php if ($ret->activityuser == 1) {
                                                                echo '<span class="badge badge-success-light">فعال</span>';
                                                            } else {
                                                                echo '<span class="badge badge-warning-light">غیرفعال</span>';
                                                            } ?>
                                                        </td>
                                                        <td class="wd-100"><?php
                                                            if ($factor->useradder != NULL) {
                                                                $useradder = retPerson($factor->useradder);
                                                                echo 'همکار آموزشی:' . $useradder->fname . ' ' . $useradder->lname;
                                                            }else{
                                                                if ($factor->factoradder != NULL) {
                                                                    echo $factor->factoradder;
                                                                }else{
                                                                    echo 'خودش';
                                                                }
                                                            }
                                                            ?>
                                                        </td>

                                                        <td class="wd-100">   <?php if($ret->suporter==NULL){ ?>
                                                                <button  onclick="window.open('select-suporter.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                    ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )" > ندارد</button>
                                                            <?php } else{ echo $ret->suporter; } ?>

                                                        </td>
                                                        <td class="wd-15"><?php if($firstlogin->firstlogin==1 ){
                                                                echo '<span class="badge badge-success-light">بله</span>';
                                                            } else {
                                                                echo '<span class="badge badge-warning-light">خیــر</span>';
                                                            } ?></td>

                                                    <?php } ?>

                                                </tr>

                                                <?php
                                                $counter++;
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