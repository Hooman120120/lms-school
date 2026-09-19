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
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['user']) and !isset($_GET['course'])) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
$user = retPerson($_GET['user']);
$course = returnCourse($_GET['course']);
if (!$user) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!$course) {
    echo '<strong>404</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['select-suporter'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $suporter=$_POST['suporter'];
        $sql='UPDATE dbcourse SET suporter=? WHERE username=? AND codecourse=?';
        $query=$conn->prepare($sql);
        $query->execute([$suporter,$_GET['user'],$_GET['course']]);
        echo '<script>window.close() </script>';
    }
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
    <title>انتخاب پشتیبان | <?php echo __TITLE ?></title>
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
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">انتخاب پشتیبان برای : <span
                                                    class="text-primary"><?php echo $user->fname . ' ' . $user->lname ?></span>
                                        </h6>
                                        <p class="text-muted card-sub-title"> نام دوره :<span
                                                    class="text-primary"><?php echo $course->coursename ?></span>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>پشتیبان</th>
                                                <th>تعداد ( نفر دوره )</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql = 'SELECT COUNT(*) as coreg , dbadmin.username FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse RIGHT JOIN dbadmin ON dbadmin.username=dbcourse.suporter  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? GROUP BY dbcourse.suporter ORDER BY coreg DESC ';
                                            $query = $conn->prepare($sql);
                                            $query->execute(['موفق']);
                                            $refrence = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {
                                                $count = 1;
                                                foreach ($refrence as $ted) {

                                                    ?>
                                                    <tr>
                                                        <td class="wd-15"><?php echo $count ?></td>
                                                        <td><?php echo $ted->username ?></td>
                                                        <td class="wd-100"><?php echo $ted->coreg ?></td>
                                                    </tr>
                                                    <?php
                                                    $count++;
                                                }
                                            }
                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">
                                        <div class="col-lg-8">
                                            <p class="mg-b-10 mg-t-10">پشتیبان <span
                                                        class="tx-danger">*</span></p>
                                            <select class="form-control select2" name="suporter"
                                                    required>
                                                <option label="دسته بندی ها">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM dbadmin WHERE permision_suporter=? OR adminrole = 2 ORDER BY dakheli ASC ';
                                                $query = $conn->prepare($sql);
                                                $query->execute([1]);
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $admin) { ?>

                                                        <option>
                                                            <?php echo $admin->username ?>
                                                        </option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="text-center mt-3">
                                            <button class="btn btn-success ripple"
                                                    name="select-suporter">
                                                تخصیص پشتیبان
                                            </button>
                                        </div>


                                    </div>
                                </form>
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