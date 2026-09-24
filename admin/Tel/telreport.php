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
if (!Permision('permision_viewcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
$db=retTelDb($_GET['id']);

if($db['dakheli'] != $_SESSION['admin']['dakheli']){
    echo '<strong>403</strong>';
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
    <title> گزارش تماس |<?php echo __TITLE ?></title>
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
    <h6 class="main-content-label mb-1">گزارش سوابق تماس</h6>
    <table class="table text-nowrap text-md-nowrap table-bordered">
        <thead>
        <tr>
            <th>تاریخ</th>
            <th>ثبت کننده</th>
            <th>تماس با</th>
            <th>توضیحات</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $pri = 'SELECT * FROM tel_detail WHERE dbtel=?';
        $wer = $conn->prepare($pri);
        $wer->execute([$_GET['id']]);
        $result = $wer->fetchAll(PDO::FETCH_OBJ);
        if ($wer->rowCount() > 0) {
            foreach ($result as $ret) {

                ?>
                <tr>
                    <?php
                    $dateno = $ret->datecreated;
                    $dateno = explode('-', $dateno);
                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                    $dateno = implode('/', $dateno);
                    $dateno = $ret->timecreated . '-' . $dateno;
                    ?>
                    <td><?php echo $dateno ?></td>
                    <td><?php $admin = retAdmin($ret->useradder);
                        echo $admin->fname . ' ' . $admin->lname; ?></td>
                    <td>
                        <?php
                        switch ($ret->box) {
                            case 'mob1':
                                $boox = 'موبایل 1';
                                break;
                            case 'mob2':
                                $boox = 'موبایل 2';
                                break;
                            case 'mob3':
                                $boox = 'موبایل 3';
                                break;
                            case 'static1':
                                $boox = 'تلفن ثابت 1';
                                break;
                            case 'static2':
                                $boox = 'تلفن ثابت 2';
                                break;
                            case 'static3':
                                $boox = 'تلفن ثابت 3';
                                break;

                        }
                        echo $boox;
                        ?>

                    </td>
                    <td><?php echo htmlentities($ret->txt) ?></td>
                </tr>
            <?php }
        }
        ?>
        </tbody>
    </table>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>


