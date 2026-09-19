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
$user = retPerson($_GET['user']);

if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}

$sql='SELECT * FROM dbcourse WHERE username=? AND codecourse=?';
$query=$conn->prepare($sql);
$query->execute([$_GET['user'],$_GET['course']]);
if($query->rowCount() > 0){
    $dbcourse=$query->fetch(PDO::FETCH_OBJ);
}else{
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if(isset($_POST['changecolor'])){
    $color=$_POST['color'];
    $detail=$_POST['detail'];
    $update='UPDATE dbcourse SET type_process=?,color_wait=?,color_detail=? WHERE username=? AND codecourse=?';
    $combine=$conn->prepare($update);
    $combine->execute([1,$color,$detail,$_GET['user'],$_GET['course']]);
    echo '<script>window.close() </script>';
    die();
}

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
    <title>درخواست تغییر رنگ بندی دانش آموز |<?php echo __TITLE ?></title>
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
    <div class="alert alert-info" role="alert">
        <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

        پس از تایید این صفحه بسته شده و صفحه قبلی را بارگذاری مجدد (Refresh /F5) نمایید
    </div>
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="table-responsive">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>نوع</th>
                                            <th>وضعیت</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <th>نام و نام خانوادگی</th>
                                                <td class="text-success tx-18"><?php echo $user->fname.' '.$user->lname   ?></td>
                                        </tr>
                                        <tr>
                                            <th>نام پایه</th>
                                            <?php
                                         $name=Getcourse($dbcourse->codecourse,0 ,2);
                                          $name=$name['coursename'];
                                            ?>
                                                <td class="text-primary"><?php echo $name ?></td>
                                        </tr>
                                        <tr>
                                            <th>رنگ دانش آموز</th>
                                            <?php
                                            switch ($dbcourse->color){
                                                case 0:
                                                    $ccc = '<div class="rounded-circle bg-danger" data-placement="top"
                                                         data-bs-toggle="tooltip" title="  حضور زیر 25% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div';
                                                    $color = '#e86464';
                                                    break;
                                                case 1:
                                                    $ccc = '<div class="rounded-circle bg-warning" data-placement="top"
                                                                                              data-bs-toggle="tooltip"
                                                                                              title="  حضور بین 25% تا 50% در کلاس آنلاین"
                                                                                              style="width: 30px; height: 30px;"></div>';
                                                    $color = 'orange';
                                                    break;
                                                case 2:
                                                    $ccc = '<div class="rounded-circle " data-placement="top"
                                                         data-bs-toggle="tooltip"
                                                         title=" حضور بین 50% تا 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px; background-color: #fffb02;"  ></div>';
                                                    $color = 'yellow';
                                                    break;
                                                case 3:
                                                    $ccc = '<div class="rounded-circle bg-success" data-placement="top"
                                                         data-bs-toggle="tooltip" title=" حضور بالای 75% در کلاس آنلاین"
                                                         style="width: 30px; height: 30px;"></div>';
                                                    $color = '#8de360';
                                                    break;
                                            }
                                            ?>
                                            <td class="text-primary"><?php echo $ccc   ?>  </td>
                                        </tr>
                                        <tr>
                                            <th>وضعیت محاسبه</th>
                                            <?php
                                            switch ($dbcourse->type_process){
                                                case 0:
                                                    $typestatus='اتوماتیک';
                                                    break;
                                                case 1:
                                                    $typestatus='منتظر بررسی';
                                                    break;
                                                case 2:
                                                    $typestatus='دستی';
                                                    break;
                                            }
                                            ?>
                                            <td class="text-primary"><?php echo $typestatus ?></td>
                                        </tr>
                                        <tr>
                                            <th>پشتیبان</th>
                                            <td class="text-primary"><?php echo $dbcourse->suporter ?></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                    <form method="post" data-parsley-validate="">
                        <div class="row">
                            <div class="col-xl-4 mb-2">
                                <label for="sens" class="form-label">رنگ پیشنهادی<span
                                        style="color: red">*</span>
                                </label>
                                <select class="form-control select2 "
                                        name="color"
                                        aria-describedby="basic-addon2" required>
                                    <option label="اقدام لازم">
                                    </option>
                                    <option value="3">سبز</option>
                                    <option value="2">زرد</option>
                                    <option value="1">نارنجی</option>
                                    <option value="0">قرمز</option>
                                    <option value="4">انصرافی</option>



                                </select>
                            </div>
                            <div class="col-xl-12 mb-2">
                                <p class="mg-b-10 "> توضیحات پشتیبانی: <span
                                        style="color: red">*</span></p>
                                <textarea class="form-control" rows="5" name="detail" required></textarea>
                            </div>
                            <div class=" mg-10">
                                <button type="submit" class="btn btn-warning "
                                        name="changecolor">درخواست بررسی

                                </button>
                            </div>

                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

