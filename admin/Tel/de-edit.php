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
if (!AdminCanSee('public-relations.safe-call.manage.contacts.edit')) {
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

if (isset($_POST['edit'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip=GetIp();
        $os=GetOs();
    $uping='UPDATE tel_db SET fname=?,lname=?,guidance=?,`group`=?,mob1=?,mob2=?,mob3=?,tel1=?,tel2=?,tel3=?,act=?,dakheli=?,updator=?,dateupdate=?,timeupdate=?,ip=?,os=? WHERE id=?';
    $qsel=$conn->prepare($uping);
    $qsel->execute([$_POST['fname'],$_POST['lname'],$_POST['guidance'],$_POST['group'],$_POST['mob1'],$_POST['mob2'],$_POST['mob3'],$_POST['tel1'],$_POST['tel2'],$_POST['tel3'],$_POST['status'],$_POST['dakheli'],$_SESSION['admin']['user'],$datenow,$timenow,$ip,$os,$_GET['id']]);
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
    <title> ویرایش جزئیات مخاطب |<?php echo __TITLE ?></title>
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
                                        <h6 class="main-content-label mb-1">ویرایش اطلاعات مخاطب</h6>
                                        <p class="text-muted card-sub-title"> ردیف تلفن : <?php echo $_GET['id']; ?>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <form method="post" data-parsley-validate="">
                                <div class="row">

                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    نام:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="fname"
                                                   value="<?php echo htmlentities($db['fname']) ?>"
                                                   required=""
                                                   type="text"
                                                   data-placement="bottom"
                                                   data-bs-toggle="tooltip"
                                                   title="فارسی بنویسید">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    نام خانوادگی:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="lname"
                                                   value="<?php echo htmlentities($db['lname']) ?>"
                                                   required=""
                                                   type="text"
                                                   data-placement="bottom"
                                                   data-bs-toggle="tooltip"
                                                   title="فارسی بنویسید">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    متن راهنما:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="guidance"
                                                   value="<?php echo htmlentities($db['guidance']) ?>"
                                                   required=""
                                                   type="text"
                                                   data-placement="bottom"
                                                   data-bs-toggle="tooltip"
                                                   title="فارسی بنویسید">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                گروه مخاطبان:<span
                                                        class="tx-danger">*</span>
                                            </div>


                                            <select class="form-control select2 "
                                                    name="group"
                                                    aria-describedby="basic-addon2">
                                                <option label="گروه مخاطبان ">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result
                                                             as $ret) {
                                                        ?>
                                                        <option value="<?php echo $ret->id ?>" <?php if ($db['group'] == $ret->id) {
                                                            echo 'selected';
                                                        } ?>><?php echo htmlentities($ret->namegroup) ?></option>
                                                    <?php }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                وضعیت تماس:<span
                                                        class="tx-danger">*</span>
                                            </div>


                                            <select class="form-control select2 "
                                                    name="status"
                                                    aria-describedby="basic-addon2">
                                                <option label="وضعیت تماس ">
                                                </option>
                                                <option value="0" <?php if ($db['act'] == '0') {
                                                    echo 'selected';
                                                } ?>> تماس گرفته نشده
                                                </option>
                                                <option value="1" <?php if ($db['act'] == '1') {
                                                    echo 'selected';
                                                } ?>>نیاز به تماس مجدد
                                                </option>
                                                <option value="2" <?php if ($db['act'] == '2') {
                                                    echo 'selected';
                                                } ?>>تماس پایان یافته
                                                </option>
                                                <option value="3" <?php if ($db['act'] == '3') {
                                                    echo 'selected';
                                                } ?>>شماره اشتباه است
                                                </option>
                                                <option value="4" <?php if ($db['act'] == '4') {
                                                    echo 'selected';
                                                } ?>>شماره تکراری است
                                                </option>
                                                <option value="5" <?php if ($db['act'] == '5') {
                                                    echo 'selected';
                                                } ?>>عدم پاسخ
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                داخلی تماس گیرنده:<span
                                                        class="tx-danger">*</span>
                                            </div>


                                            <select class="form-control select2 "
                                                    name="dakheli"
                                                    aria-describedby="basic-addon2">
                                                <option label="وضعیت تماس ">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM dbadmin ';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) {
                                                        ?>
                                                        <option value="<?php echo $res->dakheli; ?>" <?php if ($db['dakheli'] == $res->dakheli) {
                                                            echo 'selected';
                                                        } ?>><?php echo $res->fname . ' ' . $res->lname . '(' . $res->dakheli . ')'; ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 pd-t-10">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    موبایل 1:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="mob1"
                                                   value="<?php echo $db['mob1'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <div class="col-lg-6 pd-t-10 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    موبایل 2:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="mob2"
                                                   value="<?php echo $db['mob2'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    موبایل 3:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="mob3"
                                                   value="<?php echo $db['mob2'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    ثابت 1:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="tel1"
                                                   value="<?php echo $db['tel1'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    ثابت 2:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="tel2"
                                                   value="<?php echo $db['tel2'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <div class="col-lg-6 ">
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    ثابت 3:<span
                                                            class="tx-danger">*</span>
                                                </div>
                                            </div>
                                            <input class="form-control"
                                                   name="tel3"
                                                   value="<?php echo $db['tel3'] ?>"
                                                   required=""
                                                   type="text">

                                        </div>
                                    </div>
                                    <?php
                                    $hash = bin2hex(random_bytes(32));
                                    $_SESSION['token'] = $hash;
                                    ?>
                                    <input type="hidden"
                                           name="token"
                                           value="<?php echo $hash ?>">

                                    <div class="mt-3">
                                        <button type="submit"
                                                class="btn btn-primary  mr-1"
                                                name="edit">
                                            بروزرسانی اطلاعات
                                        </button>

                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>


            </div>
        </div>


        <?php include '../../footers.php'; ?>
        <!--End Footer-->


</body>
</html>
