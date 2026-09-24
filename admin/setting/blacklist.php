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
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
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
if (!Permision('permision_viewsetting')) {
    header('location:' . url("admin/index.php"));
    die();
}
if(isset($_GET['del'])){
    $del=intval($_GET['del']);
    $delet='DELETE FROM blacklist_ip WHERE id_blacklist_ip=?';
    $qdel=$conn->prepare($delet);
    $qdel->execute([$del]);
    header('location: blacklist.php');
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
    <title> لیست سیاه | <?php echo __TITLE ?></title>
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
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                        <div class="card custom-card">
                            <div class="card-body">

                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">لیست سیاه</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش آدرس‌های IP یا مواردی که توسط سامانه مسدود شده‌اند نمایش داده می‌شوند. از طریق این قسمت می‌توانید لیست سیاه را مدیریت کرده، در صورت نیاز آدرس‌های جدید را مسدود یا موارد موجود را از لیست سیاه خارج کنید. کاربران یا آدرس‌های موجود در این لیست امکان دسترسی به بخش‌های محدودشده سامانه را نخواهند داشت."
                                                  aria-label="راهنمای لیست سیاه"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                        </div>

                                        <p class="text-muted card-sub-title">
                                            آدرس‌های قفل‌شده
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>آدرس (IP)</th>
                                            <th>سیستم عامل</th>
                                            <th>حذف</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $bla='SELECT * FROM blacklist_ip ';
                                        $qblack=$conn->prepare($bla);
                                        $qblack->execute();
                                        $result=$qblack->fetchAll(PDO::FETCH_OBJ);
                                        $id=1;
                                        if($qblack->rowCount()>0){
                                            foreach ($result as $res){

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $id ?></td>
                                                    <td class="wd-15"><?php echo $res->ip_blackList_ip ?></td>
                                                    <td class="text-left"><?php echo $res->os_blackList_ip ?></td>
                                                    <td class="wd-15"> <a href="?del=<?php echo $res->id_blackList_ip ?>" class="btn btn-sm btn-danger"  onclick="return confirm('آیا با خروج این آدرس از لیست سیاه موافقید؟')"><i class="fe fe-x"></i> </a> </td>
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
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

