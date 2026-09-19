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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!Permision('permision_reportstudent')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $factorId = queryFactor($_GET['call']);
    if ($factorId->useradder != NULL) {
        $user = $factorId->useradder;
        $semat = 'همکار آموزشی';
    } else {
        $user = $factorId->username;
        $semat = 'دانش آموز';
    }
    $qau = retPerson($user);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: report-student.php');
    die();
}
if (isset($_GET['call-student']) and $_GET['call-student'] != '') {
    $semat = 'دانش آموز';
    $qau = retPerson($_GET['call-student']);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: report-student.php');
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
    <title> گزارش آماری دانش آموزان | <?php echo __TITLE ?></title>
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
                            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                    $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin WHERE role=? ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1">همه دانش آموزان</label>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left"><a href="export-student.php?all" class="btn btn-sm btn-primary">دریافت لیست</a> </p>
                                        <p class="mb-0 mt-4 text-muted">لیست همه دانش آموزان</p>
                                </div>
                            </div>
                        </div>
                    </div>
              
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                              $co_student = ' SELECT COUNT(iduser) as countstudent FROM dblogin LEFT JOIN dbcourse ON dblogin.username=dbcourse.username RIGHT JOIN dbpersonal ON dbpersonal.codemeli=dblogin.username WHERE dblogin.role=? AND dbcourse.username IS NULL  ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1"> بدون دوره</label>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left"><a href="export-student.php?uncourse" class="btn btn-sm btn-primary">دریافت لیست</a> </p>
                                             <p class="mb-0 mt-4 text-muted">تا کنون هیچ دوره ای ثبت نام نکرده</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                 $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username  INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE dblogin.role=? AND dbcourse.activityuser=? AND codtbl_course.courseactive=?; ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1,0,1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);;
                                    ?>
                                    <label class="main-content-label mb-3 pt-1"> منتظر پرداخت فاکتور</label>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="text-left"><a href="export-student.php?away" class="btn btn-sm btn-primary">دریافت لیست</a> </p>
                                         <p class="mb-0 mt-4 text-muted">تعداد نفر منتظر ورود به کلاس</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                      <div class="row row-sm">
                    <?php if (isset($_SESSION['call']) ) { ?>
                        <div class="alert alert-outline-success  text-center " role="alert">
                            <div class="mg-t-10">
                                <img style="width: 75px; height: 75px;"
                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                با
                                <?php echo $_SESSION['call']['name'];
                                unset($_SESSION['call']);
                                ?>

                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                        </div>
                    <?php } ?>
                    <div class="col-lg-8">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1"> آخرین حساب کاربری ایجاد شده دانش آموز</h6>
                                        <p class="text-muted card-sub-title">30 حساب کاربری اخیر
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>اقدامات</th>
                                            <th>کدملی</th>
                                            <th>نام</th>
                                            <th>تاریخ ثبت</th>
                                            <th>ایجاد کننده</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbpersonal INNER JOIN dblogin ON `dbpersonal`.`codemeli`= `dblogin`.`username` WHERE `dblogin`.`role`=?  ORDER BY datecreated DESC ,`idpersonal` DESC LIMIT 30 ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([1]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                        foreach ($result
                                        as $res) {
                                        ?>
                                        <tr>
                                            <td class="wd-20">
                                                <div class="text-wrap">
                                                    <div class="text-center">
                                                        <div class="dropdown">
                                                            <button aria-expanded="false"
                                                                    aria-haspopup="true"
                                                                    class="btn ripple btn-outline-primary dropdown-toggle"
                                                                    data-bs-toggle="dropdown" type="button">
                                                                اقدام <i
                                                                    class="fas fa-caret-down mr-1"></i>
                                                            </button>
                                                            <div class="dropdown-menu tx-8">
                                                                     <div class="dropdown-item  "
                                                                     onclick="window.open('viewProfile.php?code='+'<?php echo $res->username ?>','مشاهده اطلاعات کاربری'
                                                                             ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                    <i class="fa fa-address-card"></i> مشاهده اطلاعات
                                                                    کاربری

                                                                </div>
                                                                <a class="dropdown-item  "
                                                                   href="?call=<?php echo $res->username ?>"
                                                                ><i class="fe fe-phone-forwarded"></i> تماس
                                                                    با
                                                                    ایجاد کننده
                                                                </a>
                                                                <a class="dropdown-item  "
                                                                   href="?call-student=<?php echo $res->username ?>"
                                                                ><i class="fe fe-phone-forwarded"></i> تماس
                                                                    با دانش آموز
                                                                </a>



                                                                <hr>
                                                                <a class="dropdown-item text-danger "
                                                                   href="?unverify=<?php echo ''; ?>"
                                                                   onclick="return confirm('هشدار !!! پاک کنید همه چیزش پاک میشه!!!')"
                                                                ><i class="fe fe-x-square"></i> حذف حساب کاربری
                                                                </a>


                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="wd-20"><?php echo $res->codemeli ?></td>
                                            <td><?php echo htmlentities($res->fname.' '.$res->lname)  ?></td>
                                            <?php
                                            $dateno = $res->datecreated;
                                            $dateno = explode('-', $dateno);
                                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                            $dateno = implode('/', $dateno);
                                            ?>
                                            <td class="wd-20"><?php echo $dateno ?></td>
                                            <td class="wd-20">
                                                <?php
                                                if ($res->creator == NULL AND $res->useradder == NULL){ echo 'خودش'; }
                                                if ($res->creator != NULL ){ echo 'اپراتور پشتیبانی'; }
                                                if ($res->useradder != NULL ){ echo 'همکار آموزشی'; }

                                                ?>
                                            </td>
                                        </tr>
                                        <?php
                                        }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">گزارش تعداد استانی دانش آموزان</h6>
                                        <p class="text-muted card-sub-title">آمار تعداد دانش آموزان به تفکیک استان
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>نام استان</th>
                                            <th>تعداد</th>
                                            <th>خروجی</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT `state`,COUNT(state) as costate FROM `dbpersonal` INNER JOIN `dblogin` ON `dbpersonal`.`codemeli`=`dblogin`.`username` WHERE `dblogin`.`role`=?  GROUP BY `dbpersonal`.`state`';
                                        $query = $conn->prepare($sql);
                                        $query->execute([1]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                        foreach ($result
                                        as $ret) {
                                        ?>
                                        <tr>
                                            <td><?php echo $ret->state ?></td>
                                            <td class="wd-20"><?php echo number_format($ret->costate) ?></td>
                                            <td class="wd-20"><a href="export-student.php?state=<?php echo $ret->state ?>" class="btn btn-sm btn-outline-success" target="_blank"  ><i class="fe fe-download"></i> </a> </td>

                                        </tr>
                                        <?php
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


