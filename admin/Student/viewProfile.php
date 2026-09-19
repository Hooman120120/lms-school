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
if (!isset($_GET['code'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
if (!Permision('permision_editstudent')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مشاهده اطلاعات کاربری | <?php echo __TITLE ?></title>
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
    <div class="card-body">
        <div class="text-center">

        نوع حساب کاربری:
            <?php if(typeUser($_GET['code'])==1){
                $role=t('student');
            }else{
                $role='همکار آموزشی';
            }
           echo $role;
            ?>
        </div>
        <dl class="dlist-align">
            <dt class="">کد ملی:</dt>
            <dd class="text-right mr-auto">
              <?php echo $_GET['code'] ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <?php $ret=retPerson($_GET['code']); ?>
            <dt class="">نام:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->fname ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">نام خانوادگی:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->lname ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">نام پدر:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->fathername ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class=""><?php echo m('label.student_phone_colon') ?></dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->tel1 ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تلفن مادر:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->tel2 ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تلفن پدر:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->tel3 ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تاریخ تولد:</dt>
            <dd class="text-right mr-auto">
                <?php
               $dateno=$ret->birthdate;
                 $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                ?>
                <?php echo $dateno ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">استان:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->state ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">شهر:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->city ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class=""><?php echo m('label.grade_colon2') ?></dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->paye ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">رشته:</dt>
            <dd class="text-right mr-auto">
                <?php echo $ret->reshtestudent ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">ایجاد کننده:</dt>
            <dd class="text-right mr-auto">
                <?php
                if($ret->useradder != NULL){
                    $useradder=retPerson($ret->useradder);
                    $creator='همکار آموزشی ' . $useradder->fname.' '.$useradder->lname;
                }
                if($ret->creator !=NULL){
                    $useradder=retAdmin($ret->creator);
                    $creator=' کارمند '. $useradder->fname.' '.$useradder->lname;
                }
                if($ret->useradder == NULL AND  $ret->creator ==NULL){
                    $creator='***';
                }
                echo $creator;
                ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تاریخ و ساعت ایجاد:</dt>
            <dd class="text-right mr-auto">
                <?php
                $time=$ret->datecreated;
                $time = explode('-', $time);
                $time = gregorian_to_jalali($time[0], $time[1], $time[2]);
                $time = implode('/', $time);
                $time= $ret->timecreated .' - '. $time;
                ?>
                <?php echo $time  ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تاریخ و ساعت بروزرسانی:</dt>
            <dd class="text-right mr-auto">
                <?php
                $timeup=$ret->dateupdate;
                if($timeup !=NULL) {
                    $timeup = explode('-', $timeup);
                    $timeup = gregorian_to_jalali($timeup[0], $timeup[1], $timeup[2]);
                    $timeup = implode('/', $timeup);
                    $timeup = $ret->timeupdate . ' - ' . $timeup;
                }else{
                    $timeup='***';
                }
                ?>
                <?php echo $timeup  ?>
            </dd>
        </dl>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>