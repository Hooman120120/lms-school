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
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
$ids = intval($_GET['code']);


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Produce">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست حضورغیاب دانش آموزان | <?php echo __TITLE ?></title>
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
                                        <?php $general = rettblattendance($ids); ?>
                                        <h6 class="main-content-label mb-1">گرارش حضورغیاب پایه : <span
                                                    class="text-primary"><?php echo $general->classname ?></span></h6>
                                        <p class="text-muted card-sub-title"> کد پایه :<span
                                                    class="text-primary"><?php echo $general->codecourse ?></span>
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
                                            <th>کد ملی</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th> زمان حضور</th>
                                            <th>وضعیت</th>
                                            <th>پشتیبان</th>

                                        </tr>
                                        </thead>
                                        <tbody>

                                        <?php
                                        $sql = 'SELECT * FROM dbattendance WHERE coding=? ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$ids]);
                                        $list = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($query->rowCount() > 0) {
                                            foreach ($list as $ret) {
                                                ?>
                                                <tr <?php if($ret->status == 'غایب'){ echo ' class="bg-danger" '; } else{echo ' class="bg-success" '; }    ?> >
                                                    <td class="wd-30"><?php echo $id ?></td>
                                                    <td class="wd-30"><?php echo $ret->username ?></td>

                                                    <td><?php $person=retPerson($ret->username); echo $person->fname.' '.$person->lname ?></td>
                                                    <td class="wd-30"><?php echo $ret->timehozor  ?></td>
                                                    <td class="wd-30"><?php if($ret->status == 'غایب'){ echo 'غایب'; } else {echo 'حاضر'; }?></td>
                                                    <?php
                                                    $sql='SELECT * FROM dbcourse WHERE codecourse=? AND username=? ';
                                                    $query1=$conn->prepare($sql);
                                                    $query1->execute([$general->codecourse,$ret->username]);
                                                    $sup=$query1->fetch();

                                                    ?>
                                                    <td class="wd-30"><?php if($sup['suporter']== NULL){echo 'ندارد'; } else { echo $sup['suporter']; }   ?></td>
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>