<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
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
if(Permision('permision_suporter') AND Permision('permision_viewcourse')==false){
    header('location: panel/desktop.php');
    exit;
}


// username from $re
$username = $re['username'] ?? '';

// default (so code doesn't crash if user not found)
$adminRole = null;

if ($username !== '') {
    $st = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $adminRole = $st->fetchColumn();
}
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
    <title> حساب کاربری | <?php echo __TITLE ?></title>
    <?php include_once 'link.php'; ?>
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once 'menu.php';
    include_once 'header.php';

    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <?php if ((int)$adminRole !== 3) {?>
            <div class="inner-body">
<!--                <div class="example">-->
<!--                    <div class="alert alert-warning mb-0" role="alert">-->
<!--                        <h4 class="alert-heading">پاسخ به تیکت ها!</h4>-->
<!--                        <p> لطفا به تیکت های خود پاسخ دهید-->
<!--                            </p>-->
<!---->
<!---->
<!--                        <hr>-->
<!--                        <div class="text-center">-->
<!--                            <a href="Tickets/inbox.php"-->
<!--                               class="btn ripple btn-outline-warning">مشاهده-->
<!--                                صندوق ورودی</a>-->
<!--                        </div>-->
<!---->
<!--                    </div>-->
<!--                </div>-->
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order ">
                                    <?php
                                    $co_student = 'SELECT COUNT(iduser) as countstudent FROM dblogin WHERE role=? ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1">دانش آموزان</label>
                                    <h2 class="text-left card-item-icon card-icon">
                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countstudent) ?></span>
                                    </h2>
                                    <p class="mb-0 mt-4 text-muted">افرادی که پنل کاربری دارند</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- COL END -->
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <?php
                                    $co_student = 'SELECT COUNT(iduser) as countcollague FROM dblogin WHERE role=? ';
                                    $query_st = $conn->prepare($co_student);
                                    $query_st->execute([2]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1">همکاران</label>
                                    <h2 class="text-left"><i
                                                class="mdi mdi-cube icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($result_student->countcollague) ?> </span>
                                    </h2>
                                    <p class="mb-0 mt-4 text-muted">افرادی که پنل کاربری دارند </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- COL END -->
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <a class="card custom-card pulse-zoom"
                           style="color: #0a0a0a; cursor: pointer; text-decoration: none;"
                           onclick="window.open('<?php echo url('admin/panel/course_detail.php') ?>',
                                   'CourseDetail',
                                   'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                            <div class="card-body">
                                <div class="card-order">
                                    <?php
                                    $co_course = 'SELECT COUNT(idcourse) as course FROM codtbl_course WHERE courseactive=? ';
                                    $query_st = $conn->prepare($co_course);
                                    $query_st->execute([1]);
                                    $result_student = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1"> پایه های درحال ثبت نام</label>
                                    <h2 class="text-left">
                                        <i class="fe fe-activity icon-size float-right text-primary"></i>
                                        <span class="font-weight-bold">
                        <?php echo number_format($result_student->course) ?>
                    </span>
                                    </h2>
                                    <p class="mb-0 mt-4 text-muted">تعداد پایه های درحال ثبت نام </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <?php
                                    $datenow = date("Y-m-d");
                                    $co_course = 'SELECT COUNT(id) as countlogin FROM dbh_login  WHERE datelogin=?  ';
                                    $query_st = $conn->prepare($co_course);
                                    $query_st->execute([$datenow]);
                                    $countlogin = $query_st->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <label class="main-content-label mb-3 pt-1"> بازدید های امروز</label>
                                    <h2 class="text-left"><i
                                                class="fe fe-eye icon-size float-right text-primary"></i><span
                                                class="font-weight-bold"><?php echo number_format($countlogin->countlogin) ?> </span>
                                    </h2>
                                    <p class="mb-0 mt-4 text-muted">تعداد ورود موفق به حساب کاربری </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="row row-sm">
                    <div class="col-xxl-6 col-xl-6 col-md-12 col-lg-6">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0 pb-1">
                                <label class="main-content-label mb-2 pt-1">تعداد ثبت نام </label>
                                <p class="tx-12 mb-0 text-muted">پایه های فعال و درحال اجرا</p>
                            </div>
                            <div class="card-body pt-0">
                                <ul class="top-selling-products pb-0 mb-0 pr-0">
                                    <?php
                                    $course = 'SELECT * FROM codtbl_course WHERE activecourse=?';
                                    $qcourse = $conn->prepare($course);
                                    $qcourse->execute([1]);
                                    $result = $qcourse->fetchAll(PDO::FETCH_OBJ);
                                    if ($qcourse->rowCount() > 0) {
                                        foreach ($result as $res) {


                                            ?>
                                            <li class="product-item">
                                                <div class="product-img"><img src="<?php echo $res->piclink ?>" alt="">
                                                </div>
                                                <div class="product-info">
                                                    <div class="product-name"><?php echo htmlentities($res->coursename) ?></div>
                                                    <div class="price"><?php echo htmlentities($res->coursemuted) ?></div>
                                                </div>
                                                <div class="product-amount">
                                                    <div class="product-price"><?php echo CountRegisterCourse($res->codecourse, 1) ?>
                                                        نفر
                                                    </div>
                                                    <div class="items-sold"><?php echo CountRegisterCourse($res->codecourse, 0) ?>
                                                        نفر در انتظار
                                                    </div>
                                                </div>
                                            </li>
                                            <?php
                                        }
                                    }
                                    ?>


                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-6 col-xl-6 col-md-12 col-lg-6">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0 pb-1">
                                <label class="main-content-label mb-2 pt-1">خلاصه وضعیت مدرسه آنلاین </label>
                                <p class="tx-12 mb-0 text-muted"></p>
                            </div>
                            <div class="card-body pt-0">
                                <ul class="top-selling-products pb-0 mb-0 pr-0">
                                               <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">تعدادافراد ثبت شده</div>
                                            <div class="price">توسط شما</div>
                                        </div>
                                        <?php
                                        $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `factoradder`=?';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold text-danger"><?php echo $rec->coreg ?>  نفر پایه </span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                       <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">پشتیبانی  </div>
                                            <div class="price">توسط شما</div>
                                        </div>
                                        <?php
                                        $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? AND `suporter`=?';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute(['موفق', $_SESSION['admin']['user']]);
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold text-danger"><?php echo $rec->coreg ?>  نفر پایه </span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">فرم ثبت نام دانش آموز</div>
                                            <div class="price"></div>
                                        </div>
                                        <div class="product-amount">
                                            <div class="product-price">
                                                <?php
                                                if (ActivatePublic('act_register_student')) {
                                                    ?>
                                                    <span class="badge badge-pill badge-success-light">فعال</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-pill badge-danger-light">غیر فعال</span>
                                                <?php } ?>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">فرم ثبت نام همکار</div>
                                            <div class="price"></div>
                                        </div>
                                        <div class="product-amount">
                                            <div class="product-price">
                                                <?php
                                                if (ActivatePublic('act_register_collague')) {
                                                    ?>
                                                    <span class="badge badge-pill badge-success-light">فعال</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-pill badge-danger-light">غیر فعال</span>
                                                <?php } ?>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">فرم ورود به حساب کاربری</div>
                                            <div class="price"></div>
                                        </div>
                                        <div class="product-amount">
                                            <div class="product-price">
                                                <?php
                                                if (ActivatePublic('act_login')) {
                                                    ?>
                                                    <span class="badge badge-pill badge-success-light">فعال</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-pill badge-danger-light">غیر فعال</span>
                                                <?php } ?>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">کاربران قفل شده</div>
                                            <div class="price">تکرار رمز نادرست</div>
                                        </div>
                                        <?php
                                        $co_lock = 'SELECT COUNT(iduser) as lockuser FROM dblogin WHERE activity=? ';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute([0]);
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"> <?php echo $rec->lockuser ?> نفر</span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item">

                                        <div class="product-info">
                                            <div class="product-name">دانش آموزان فعال نشده</div>
                                            <div class="price">ثبت شده توسط همکاران</div>
                                        </div>
                                        <?php
                                        $co_lock = 'SELECT COUNT(iduser) as noactive FROM dblogin WHERE `keyactive`IS NOT  NULL AND `true_key`=?';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute([0]);
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"><?php echo $rec->noactive ?>  نفر </span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item ">

                                        <div class="product-info">
                                            <div class="product-name">آی پی های محروم شده</div>
                                            <div class="price">تلاش های مخرب</div>
                                        </div>
                                        <?php
                                        $co_lock = 'SELECT COUNT(*) as balackip FROM blacklist_ip ';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute();
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"><?php echo $rec->balackip ?>    آدرس </span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>

                                    </li>
                                    <li class="product-item pb-0">
                                        <div class="product-info">
                                            <div class="product-name">تعداد صورتحساب ایجاد شده</div>
                                            <div class="price">پرداخت شده و نشده</div>
                                        </div>
                                        <?php
                                        $co_lock = 'SELECT COUNT(idfactor) as factor FROM dbfactor ';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute();
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"> <?php echo number_format($rec->factor) ?></span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>
                                    <li class="product-item pb-0">
                                        <div class="product-info">
                                            <div class="product-name">تعداد پیامک های ارسالی</div>
                                            <div class="price">از مدرسه آنلاین</div>
                                        </div>
                                        <?php
                                        $co_lock = 'SELECT COUNT(id) as smsun FROM db_smsun ';
                                        $qu_lock = $conn->prepare($co_lock);
                                        $qu_lock->execute();
                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                        $co_lock1 = 'SELECT COUNT(id) as telsms FROM tel_sms ';
                                        $qu_lock1 = $conn->prepare($co_lock1);
                                        $qu_lock1->execute();
                                        $rec1 = $qu_lock1->fetch(PDO::FETCH_OBJ);

                                        $countSMS = $rec->smsun + $rec1->telsms;

                                        ?>
                                        <div class="product-amount">
                                            <div class="product-price"><span
                                                        class="font-weight-semibold"> <?php echo number_format($countSMS) ?></span>
                                            </div>
                                            <div class="items-sold"></div>
                                        </div>
                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>

                </div>


            </div>
            <?php }  else { ?>
           <?php } ?>
        </div>
    </div>

    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
