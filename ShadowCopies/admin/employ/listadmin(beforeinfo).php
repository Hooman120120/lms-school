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
if (!Permision('permision_viewemploy')) {
    header('location:' . url("admin/index.php"));
    die();
}
if (isset($_GET['disable'])) {
    if($_GET['disable']!=$_SESSION['admin']['user']) {
        $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
        $query = $conn->prepare($sql);
        $query->execute([0, $_GET['disable']]);
        header('location: listadmin.php');
    }
}
if (isset($_GET['enable'])) {
    $sql = 'UPDATE dbadmin SET adminactive =? WHERE username=?';
    $query = $conn->prepare($sql);
    $query->execute([1, $_GET['enable']]);
    header('location: listadmin.php');
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
    <title> لیست کارمندان | <?php echo __TITLE ?></title>
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
                                <div class="row row-sm">
                                    <div class="col-8">
                                        <div>
                                            <h6 class="main-content-label mb-1">کارمندان</h6>
                                            <p class="text-muted card-sub-title">لیست همه کارمندان موجود
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-left">
                                        <a href="<?php echo url('admin/employ/addemploy.php') ?> "
                                           target="_blank"
                                           class="btn btn-sm btn-primary"><i class="fe fe-download"></i> افزودن کارمند جدید </a>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>اقدامات</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>نام کاربری</th>
                                            <th>پشتیبان</th>
                                            <th>داخلی</th>
                                            <th>وضعیت</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $bla = 'SELECT * FROM dbadmin ORDER BY dakheli ASC ';
                                        $qblack = $conn->prepare($bla);
                                        $qblack->execute();
                                        $result = $qblack->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($qblack->rowCount() > 0) {
                                            foreach ($result as $res) {

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $id ?></td>
                                                    <td class="wd-15">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-sm btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i
                                                                                class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <?php if ($res->adminactive == 1) { ?>
                                                                            <a class="dropdown-item text-warning "
                                                                               href="?disable=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-eye-off"></i> غیرفعال کردن
                                                                            </a>
                                                                        <?php } else { ?>
                                                                            <a class="dropdown-item text-success "
                                                                               href="?enable=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-eye"></i> فعال کردن
                                                                            </a>

                                                                        <?php } ?>
                                                                        <a class="dropdown-item text-success "
                                                                           href="<?php echo url('admin/employ/addemploy.php') ?>?edit=<?php echo $res->username ?>"
                                                                        ><i class="fe fe-edit"></i> ویرایش کردن

                                                                        </a>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->fname . ' ' . $res->lname ?></td>
                                                    <td class="wd-15"><?php echo $res->username ?></td>
                                                    <td class="wd-15"><?php if(PermisionUser('permision_suporter',$res->username) ){echo '   <span class="badge badge-success-light">می باشد</span> ';} else{echo '  <span class="badge badge-warning-light">نیست</span> ';} ?></td>
                                                    <td class="wd-15"><?php echo $res->dakheli ?></td>
                                                    <td class="wd-20">
                                                        <?php if ($res->adminactive == 1) { ?>
                                                            <span class="badge badge-success-light">فعال</span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-warning-light">غیرفعال</span>
                                                        <?php } ?>
                                                    </td>

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

