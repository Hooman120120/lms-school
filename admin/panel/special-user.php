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


if (isset($_POST['special_user_excel'])) {
    AdminRequire('support.reports.special-users.excel');
    $_SESSION['special_user_excel'] = ['export' => true];
    error_log("Session set for special_user_excel");
    header('Location: ../Bill/execel.php');
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title>گزارش نفرات ویژه | <?php echo __TITLE ?></title>
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
                                <div class="col-12 d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="main-content-label mb-1">گزارش تعداد افراد ویژه
                                            <span class="text-primary"></span>
                                        </h6>
                                    </div>
                                    <?php
                                    $currentUser = $_SESSION['admin']['user'] ?? '';
                                    if (AdminCan('support.reports.special-users.excel')):
                                        ?>
                                        <form action="" method="post" style="display: inline;">
                                            <input type="hidden" name="special_user_excel" value="1">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa fa-file-excel-o"></i> دانلود اکسل
                                            </button>
                                        </form>
                                    <?php
                                    endif;
                                    ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <table class="table text-nowrap text-md-nowrap table-bordered" id="">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>کد ملی</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>پایه</th>
                                            <th>استان</th>
                                            <th>شهر</th>
                                            <th>تلفن1</th>
                                            <th>تلفن2</th>
                                            <th>تلفن3</th>


                                        </tr>
                                        </thead>
                                        <tbody>

                                        <?php
                                        $sql = 'SELECT * FROM suport_user  WHERE  typesupport=1';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($query->rowCount() > 0) {
                                        foreach ($result as $ret) {
                                        ?>
                                        <tr>
                                            <td><?php echo $id ?></td>
                                            <td><?php echo $ret->username; ?></td>
                                            <?php
                                           $personal=retPerson($ret->username);
                                            ?>
                                            <td><?php echo $personal->fname.' '.$personal->lname ?></td>
                                            <td><?php echo $personal->paye ?></td>
                                            <td><?php echo $personal->state ?></td>
                                            <td><?php echo $personal->city ?></td>
                                            <td><?php echo $personal->tel1 ?></td>
                                            <td><?php echo $personal->tel2 ?></td>
                                            <td><?php echo $personal->tel3 ?></td>

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
