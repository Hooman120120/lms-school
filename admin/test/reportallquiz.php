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
if (!Permision('permision_viewtest')) {
    header('location: ' . url('admin/index.php'));
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
    <title> کارنامه تجمیعی |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<div class="main-body leftmenu">
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
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <div class="col-xl-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">گزارش نتایج بر اساس درس و دوره</h6>
                                            <p class="text-muted card-sub-title">لطفا اطلاعات خواسته شده را جهت تولید
                                                گزارش
                                                وارد کنید
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-xl-6 mb-2">
                                                <label for="sens" class="form-label mg-t-15">دوره مورد نظر:<span
                                                            style="color: red">*</span>
                                                </label>
                                                <select class="form-control select2 "
                                                        name="course"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="اقدام لازم">
                                                    </option>

                                                    <?php
                                                    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result
                                                                 as $res) { ?>
                                                            <option value="<?php echo $res->codecourse ?>"> <?php echo $res->coursename ?></option>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-xl-6 mb-2">
                                                <label for="sens" class="form-label mg-t-15">درس مورد نظر:<span
                                                            style="color: red">*</span>
                                                </label>
                                                <select class="form-control select2 "
                                                        name="typefactor"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="اقدام لازم">
                                                    </option>
                                                    <?php
                                                    $sql = 'SELECT * FROM dbdars ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute();
                                                    $results = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($results
                                                                 as $ress) { ?>
                                                            <option value="<?php echo $ress->id ?>"> <?php echo $ress->dars ?> </option>
                                                        <?php }
                                                    }
                                                    ?>

                                                </select>
                                            </div>
                                        </div>
                                            <div class=" text-left">
                                                <button type="submit" name="coreport" class="btn btn-sm btn-success"><i
                                                            class="fe fe-save"></i> دانلود اکسل
                                                </button>
                                            </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">گزارش نتایج مدیریتی بر اساس دوره</h6>
                                            <p class="text-muted card-sub-title">لطفا اطلاعات خواسته شده را جهت تولید
                                                گزارش
                                                وارد کنید
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-xl-6 mb-2">
                                                <label for="sens" class="form-label mg-t-15">دوره مورد نظر:<span
                                                            style="color: red">*</span>
                                                </label>
                                                <select class="form-control select2 "
                                                        name="course"
                                                        aria-describedby="basic-addon2" required>
                                                    <option label="اقدام لازم">
                                                    </option>
                                                 
                                                    <?php
                                                    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result
                                                                 as $res) { ?>
                                                            <option value="<?php echo $res->codecourse ?>"> <?php echo $res->coursename ?></option>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                  
                                            <div class=" text-left">
                                                <button type="submit" name="moreport" class="btn btn-sm btn-success"><i
                                                            class="fe fe-save"></i> دریافت اکسل گزارش
                                                </button>
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



<?php include '../../footers.php'; ?>
</body>
</html>
