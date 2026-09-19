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
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_managecall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!retTelDb($_GET['id'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
$db = retTelDb($_GET['id']);
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
    <title> مشاهده جزئیات مخاطب |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- Page -->
<div class="page">
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">مشاهده اطلاعات مخاطب</h6>
                                        <p class="text-muted card-sub-title"> ردیف تلفن : <?php echo $_GET['id']; ?>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-6">

                                    <dl class="dlist-align">
                                        <dt>نام:</dt>
                                        <dd class="text-primary">
                                            <?php echo htmlentities($db['fname']); ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>نام خانوادگی:</dt>
                                        <dd class="text-primary">
                                            <?php echo htmlentities($db['lname']); ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>متن راهنما:</dt>
                                        <dd class="text-primary">
                                            <?php echo htmlentities($db['guidance']); ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>گروه مخاطبان:</dt>
                                        <dd class="text-primary">
                                            <?php $gr=retTelGroup($db['group']);
                                            echo $gr['namegroup']; ?>

                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>وضعیت تماس:</dt>
                                        <dd class="text-primary">
                                            <?php
                                            switch ($db['act']) {
                                                case '0':
                                                    $act = 'تماس گرفته نشده ';
                                                    break;
                                                case '1':
                                                    $act = 'نیاز به تماس مجدد';
                                                    break;
                                                case '2':
                                                    $act = 'تماس پایان یافته';
                                                    break;
                                                case '3':
                                                    $act = 'شماره اشتباه';
                                                    break;
                                                case '4':
                                                    $act = 'شماره تکراری';
                                                    break;
                                                case '5':
                                                    $act = 'عدم پاسخ';
                                                    break;
                                            }

                                            echo $act ?>


                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>داخلی تماس گیرنده:</dt>
                                        <dd class="text-primary">
<?php echo $db['dakheli']; ?>
                                        </dd>

                                    </dl>
                                           <dl class="dlist-align">
                                        <dt>استان:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['state']; ?>
                                        </dd>

                                    </dl>

                                </div>
                                <div class="col-6">
                                    <dl class="dlist-align">
                                        <dt>موبایل 1:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['mob1']; ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>موبایل 2:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['mob2']; ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>موبایل 3:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['mob3']; ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>ثابت 1:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['tel1']; ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>ثابت 2:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['tel2']; ?>
                                        </dd>

                                    </dl>
                                    <dl class="dlist-align">
                                        <dt>ثابت 3:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['tel3']; ?>
                                        </dd>

                                    </dl>
                                               <dl class="dlist-align">
                                        <dt>شهر:</dt>
                                        <dd class="text-primary">
                                            <?php echo $db['city']; ?>
                                        </dd>

                                    </dl>
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

