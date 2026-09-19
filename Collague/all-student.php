<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
//Protecting against SESSION HIJACKING
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}


//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}
include_once '../temp.html';
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
    <title> لیست دانش آموزان من | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
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
    include_once '../inc/menu-collague.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div>
                                    <h6 class="main-content-label mb-1">لیست دانش آموزان ثبت نامی</h6>
                                    <p class="text-muted card-sub-title">جهت سهولت مشاهده لیست دانش آموزان از رایانه
                                        استفاده نمایید</p>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>

                                        <tr>
                                            <th>ردیف</th>
                                            <th>نام</th>
                                            <th>پایه</th>
                                            <th>وضعیت</th>
                                            <th>اقدامات</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbpersonal WHERE useradder=?';
                                        $ququ = $conn->prepare($sql);
                                        $ququ->execute([$_SESSION['auth']['user']]);
                                        $retur = $ququ->fetchAll(PDO::FETCH_OBJ);
                                        if ($ququ->rowCount() > 0) {
                                            $id = 1;
                                            foreach ($retur

                                                     as $gmc) {
                                                ?>
                                                <tr>
                                                    <th scope="row" class="wd-10"><?php echo $id; ?> </th>
                                                    <td><?php echo htmlentities($gmc->fname . ' ' . $gmc->lname) ?> </td>
                                                    <td class="wd-10"><?php echo $gmc->paye ?></td>
                                                    <?php
                                                    if (keylogin($gmc->codemeli)) { ?>
                                                        <td><span class="badge badge-danger">منتظر تایید کد</span></td>
                                                        <td>
                                                            <a href="activate-student.php?id=<?php echo $gmc->codemeli ?>"
                                                               class="btn btn-outline-primary btn-block">ثبت کد
                                                                تایید</a></td>
                                                    <?php } ?>
                                                    <?php if (keyactcourse($gmc->codemeli) == 100) { ?>
                                                        <td class="wd-20"><span class="badge badge-success">فعال</span></td>
                                                        <td>
                                                            <a href="sales-course.php?sales=<?php echo $gmc->codemeli ?>"
                                                               class="btn btn-outline-primary btn-block">افزودن پایه</a>
                                                        </td>
                                                    <?php } ?>
                                                    <?php if (keyactcourse($gmc->codemeli) == 400) { ?>
                                                        <td><span class="badge badge-warning">منتظر خرید پایه</span>
                                                        </td>
                                                        <td>
                                                            <a href="sales-course.php?sales=<?php echo $gmc->codemeli ?>"
                                                               class="btn btn-outline-primary btn-block">خرید پایه</a>
                                                        </td>
                                                    <?php } ?>
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
                <!-- End Row -->


            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
