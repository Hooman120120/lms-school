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
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مشاهده توضیحات  شماره : <?php echo $_GET['id'] ?> |<?php echo __TITLE ?></title>
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

            <div class="form-group">
                <div class="row row-sm">
                    <?php
                    $sql = 'SELECT * FROM documention WHERE id=?  ';
                    $query = $conn->prepare($sql);
                    $query->execute([$_GET['id']]);
                    $res = $query->fetch();
                    ?>
                    <div class="text-muted mb-2">
                        تاریخ ارسال:
                        <?php echo $res['timecreate']; ?>
                        -
                        <?php
                        $convert = $res['datecreate'];
                        $convert = explode('-', $convert);
                        $convert = gregorian_to_jalali($convert[0], $convert[1], $convert[2]);
                        $convert = implode('/', $convert);
                        echo $convert; ?>
                    </div>
                    <textarea rows="8" disabled><?php echo $res['meta'] ?></textarea>

                </div>
            </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</body>
</html>