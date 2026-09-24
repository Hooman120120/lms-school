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
if (!Permision('permision_addcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $in = 'INSERT INTO tel_group (namegroup) VALUES (?)';
        $qin = $conn->prepare($in);
        $qin->execute([$_POST['namegroup']]);

    }
}
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $del = 'DELETE FROM tel_group WHERE id=?';
    $qdel = $conn->prepare($del);
    $qdel->execute([$id]);
    header('location: add-group.php');
    die();
}
if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $code=intval($_POST['id']);
    $upd='UPDATE tel_group SET namegroup=? WHERE id=?';
    $actupd=$conn->prepare($upd);
    $actupd->execute([$_POST['namegroup'],$code]);
        header('location: add-group.php');
        die();
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
    <title>مشاهده صورتحساب | <?php echo __TITLE ?></title>
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
                    <?php if (!isset($_GET['edit'])) { ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">ایجاد گروه تماس جدید </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">

                                        <div class="col-xl-12">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام گروه تماس:
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="namegroup"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="">
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save"
                                                    class="btn btn-success  mr-1">
                                                ثبت و ایجاد گروه تماس
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">ویرایش گروه تماس </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <?php
                                        $irancel = 'SELECT * FROM tel_group WHERE id=?';
                                        $rightel = $conn->prepare($irancel);
                                        $rightel->execute([$_GET['edit']]);
                                        if ($rightel->rowCount() > 0) {
                                            $talia = $rightel->fetch();

                                            ?>
                                            <div class="col-xl-12">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام گروه تماس:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="namegroup"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="" value="<?php echo $talia['namegroup'] ?>">
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                    <input class="form-control" name="id" type="hidden"
                                                           value="<?php echo $_GET['edit'] ?>">
                                                </div>
                                            </div>
                                            <div class="mt-3 text-center">
                                                <button type="submit"
                                                        name="save-update"
                                                        class="btn btn-warning  mr-1">
                                                    ثبت و ویرایش گروه تماس
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php }

                    ?>
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">گروه های ایجاد شده </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>کد گروه</th>
                                            <th>نام گروه</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM tel_group ';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result
                                                     as $ret) {

                                                ?>
                                                <tr>
                                                    <td><?php echo $ret->id ?></td>
                                                    <td><?php echo htmlentities($ret->namegroup) ?></td>
                                                    <td>
                                                        <a href="?del=<?php echo $ret->id ?> " onclick="return confirm('با حذف گروه تمام مخاطبین گروه هم حذف میشوند!!')" type="button"
                                                           class="btn ripple btn-danger btn-sm">حذف</a>
                                                        <a href="?edit=<?php echo $ret->id ?> " type="button"
                                                           class="btn ripple btn-warning btn-sm">ویرایش</a>

                                                    </td>
                                                </tr>
                                            <?php }
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


