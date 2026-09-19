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
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $newgroup = intval($_POST['group-new']);
        $oldgroup = intval($_POST['group-old']);
        $actold = intval($_POST['act-old']);
        $actnew = intval($_POST['act-new']);
        $fer = intval($_POST['ferestande']);
        $recive = intval($_POST['recive']);

        if ($actnew != 6 and $actold != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,dakheli=?,act=? WHERE dakheli=? AND `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$newgroup, $recive, $actnew, $fer, $oldgroup, $actold]);
            $verify = 100;
        }
        if ($actnew != 6 and $actold == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,dakheli=?,act=? WHERE dakheli=? AND `group`=?  ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$newgroup, $recive, $actnew, $fer, $oldgroup]);
            $verify = 100;
        }

        if ($actnew == 6 and $actold == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,dakheli=? WHERE dakheli=? AND `group`=?  ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$newgroup, $recive, $fer, $oldgroup]);
            $verify = 100;
        }

        if ($actnew == 6 and $actold != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,dakheli=? WHERE dakheli=? AND `group`=? AND act=?   ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$newgroup, $recive, $fer, $oldgroup, $actold]);
            $verify = 100;
        }


    }
}
if (isset($_POST['group-to-operator'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ferestande=intval($_POST['group-fer']);
        $actfer=intval($_POST['act-fer']);
        $oprec=intval($_POST['operator-rec']);
        $actrec=intval($_POST['act-rec']);
        if ($actfer != 6 and $actrec != 6) {
            $sql1 = 'UPDATE tel_db SET dakheli=?,act=? WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$oprec, $actrec, $ferestande, $actfer]);
            $verify = 100;
        }
        if ($actfer != 6 and $actrec == 6) {
            $sql1 = 'UPDATE tel_db SET dakheli=? WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$oprec, $ferestande, $actfer]);
            $verify = 100;
        }
        if ($actfer == 6 and $actrec != 6) {
            $sql1 = 'UPDATE tel_db SET dakheli=?,act=? WHERE  `group`=?  ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$oprec, $actrec, $ferestande]);
            $verify = 100;
        }
        if ($actfer == 6 and $actrec == 6) {
            $sql1 = 'UPDATE tel_db SET dakheli=? WHERE  `group`=?  ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$oprec, $ferestande]);
            $verify = 100;
        }

    }
}
if (isset($_POST['group-to-group'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $ggroup = intval($_POST['ggroup']);
        $gact = intval($_POST['gact']);
        $rgroup = intval($_POST['rgroup']);
        $ract = intval($_POST['ract']);
        if ($gact != 6 and $ract != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?,act=? WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ract, $ggroup, $gact]);
            $verify = 100;
        }
        if ($gact != 6 and $ract == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?  WHERE  `group`=? AND act=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ggroup, $gact]);
            $verify = 100;
        }
        if ($gact == 6 and $ract != 6) {
            $sql1 = 'UPDATE tel_db SET `group`=? ,act=? WHERE  `group`=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup, $ract,$ggroup]);
            $verify = 100;
        }
        if ($gact == 6 and $ract == 6) {
            $sql1 = 'UPDATE tel_db SET `group`=?  WHERE  `group`=? ';
            $query1 = $conn->prepare($sql1);
            $query1->execute([$rgroup,$ggroup]);
            $verify = 100;
        }

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
    <title> مرکز تبادل تلفن |<?php echo __TITLE ?></title>
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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">جابجایی خطوط اپراتور به اپراتور </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                                <?php if (isset($verify) and $verify == 100) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        انتقال با موفقیت انجام شد

                                    </div>
                                <?php } ?>
                                <?php if (isset($verify) and $verify == 0) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        خطا ! انتقال انجام نشد

                                    </div>
                                <?php } ?>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">


                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتقالی از<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ferestande"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM dbadmin ';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) {

                                                        ?>
                                                        <option value="<?php echo $res->dakheli ?>"><?php echo $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')' ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> وضعیت تماس فرستنده<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-old"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه تماس ها</option>

                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب گروه تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-old"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {

                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <hr>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتقالی به<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="recive"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM dbadmin ';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) {

                                                        ?>
                                                        <option value="<?php echo $res->dakheli ?>"><?php echo $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')' ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15">وضعیت تماس گیرنده<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-new"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق وضعیت فرستنده</option>

                                            </select>
                                        </div>
                                        <div class="col-xl-4 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب گروه تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-new"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {

                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-success "
                                                    name="save"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">تخصیص کل گروه یه یک نفر (درون گروهی) </h6>
                                        <p class="text-muted card-sub-title">کل مخاطبان یک گروه از اپراتور های قبلی جدا
                                            و به اپراتور جدید متصل می شوند
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">


                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه فرستنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="group-fer"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {

                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-fer"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه مخاطبین</option>

                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <hr>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب اپراتور گیرنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="operator-rec"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM dbadmin ';
                                                $query = $conn->prepare($sql);
                                                $query->execute();
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) {

                                                        ?>
                                                        <option value="<?php echo $res->dakheli ?>"><?php echo $res->fname . ' ' . $res->lname . ' (' . $res->dakheli . ')' ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت تماس<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="act-rec"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق فرستنده</option>

                                            </select>
                                        </div>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-outline-info "
                                                    name="group-to-operator"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">ادغام یک گروه با گروهی دیگر</h6>
                                        <p class="text-muted card-sub-title"> مخاطبان از گروه حذف و به گروهی دیگر متصل
                                            می
                                            شوند
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه فرستنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ggroup"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {

                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> انتخاب وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="gact"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">همه مخاطبین</option>

                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <hr>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه گیرنده:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="rgroup"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <?php
                                                $sql = 'SELECT * FROM tel_group ';
                                                $querys = $conn->prepare($sql);
                                                $querys->execute();
                                                $results = $querys->fetchAll(PDO::FETCH_OBJ);
                                                if ($querys->rowCount() > 0) {
                                                    foreach ($results as $tel) {

                                                        ?>
                                                        <option value="<?php echo $tel->id ?>"><?php echo $tel->namegroup ?></option>
                                                        <?php
                                                    }
                                                }
                                                ?>

                                            </select>
                                        </div>
                                        <div class="col-xl-6 mb-2">
                                            <label for="sens" class="form-label mg-t-15"> تعیین وضعیت گروه<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="ract"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="وضعیت تماس">
                                                </option>
                                                <option value="0">گرفته نشده</option>
                                                <option value="1">نیاز به تماس مجدد</option>
                                                <option value="2">تماس پایان یافته</option>
                                                <option value="3">شماره/اطلاعات هویتی اشتباه است</option>
                                                <option value="4">شماره تکراری است</option>
                                                <option value="5">عدم پاسخ</option>
                                                <option value="6">مطابق فرستنده</option>

                                            </select>
                                        </div>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">

                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-outline-primary "
                                                    name="group-to-group"><i
                                                        class="fa fa-save"></i>
                                                تایید و انتقال خطوط
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
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>