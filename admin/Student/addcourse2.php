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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_SESSION['factorId'])) {
    $idf=$_SESSION['factorId'];
    unset($_SESSION['factorId']);


}
if (isset($_GET['change'])) {
    unset($_SESSION['add-course']);
    header('location: ' . url('admin/Student/addcourse.php'));
    die();
}
if (isset($_POST['search-melli'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['codemeli'] != '') {
            $codemeli = trim($_POST['codemeli']);
            // if (check_national_code($codemeli)) {
                if (checkCodemeli($codemeli)) {
                    if (typeUser($codemeli) == 1) {

                        $_SESSION['add-course'] = array(
                            "codemeli" => $_POST['codemeli']
                        );


                    } else {
                        $erorstudent = 1;
                    }

                } else {
                    $notmelli = 1;
                }

            // } else {
            //     $erormeli = 1;
            // }

        }
    } else {
        $erortoken = 1;
    }
}
if (isset($_POST['sales-course'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (isset($_POST['course']) and $_POST['course'] > 0) {
            $total = 0;
            foreach ($_POST['course'] as $code) {
                $fee = getfee($code);
                if ($fee != 0) {
                    $total = $total + $fee;
                }
            }
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $ip = GetIp();
            $os = GetOs();
            $add = 'INSERT INTO dbfactor (username,sumitem,subtotal,factorfee,wallet,datecreate,timecreate,factoradder,ip ,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
            $inadd = $conn->prepare($add);
            $inadd->execute([$_SESSION['add-course']['codemeli'], $total, $total, $total, 0, $datenow, $timenow, $_SESSION['admin']['user'], $ip, $os]);
            $factorId = $conn->lastInsertId();
            $_SESSION['factorId'] = $factorId;
            $poshtiban=$_POST['suporter'];
            foreach ($_POST['course'] as $code) {
                $inc = 'INSERT INTO dbcourse (username,codecourse,numfactor,suporter) VALUES (?,?,?,?)';
                $inp = $conn->prepare($inc);
                $inp->execute([$_SESSION['add-course']['codemeli'], $code, $factorId,$poshtiban]);
            }
            unset($_SESSION['add-course']);

            header('location: addcourse.php');


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
    <title> <?php echo m('page.create_course_for_student') ?> | <?php echo __TITLE ?></title>
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
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <h6 class="main-content-label mb-1"><?php echo m('page.create_course_for_student') ?> </h6>
                                            <p class="text-muted card-sub-title"><?php echo m('page.new_invoice_for_student') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php if (isset($erormeli) and $erormeli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        کد ملی معتبر نیست

                                    </div>
                                <?php } ?>
                                <?php if (isset($idf)) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                          <?php echo m('invoice.issued_success', [':n' => $idf]) ?>

                                    </div>
                                <?php } ?>
                                <?php if (isset($erorstudent) and $erorstudent == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo m('error.nid_is_collague') ?>

                                    </div>
                                <?php } ?>
                                <?php if (isset($notmelli) and $notmelli == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کدملی در سیستم وجود ندارد

                                    </div>
                                <?php } ?>
                                <?php if (isset($erortoken) and $erortoken == 1) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        خطای غیر منتظره رخ داده است
                                    </div>
                                <?php } ?>
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <?php if (!isset($_SESSION['add-course'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="sens" class="form-label mg-t-15"><?php echo m('form.student_nid_label') ?> <span style="color: red">*</span></label>
                                                            <input class="form-control" name="codemeli"
                                                                   type="text"
                                                                   placeholder="با اعداد انگلیسی وارد شود"
                                                                   required>
                                                        </div>

                                                    </div>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">


                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success "
                                                                name="search-melli"><i
                                                                    class="fa fa-search"></i>
                                                            <?php echo m('action.search_student') ?>
                                                        </button>
                                                    </div>
                                                </form>
                                                <!--                                        end step 1-->
                                            <?php }
                                            if (isset($_SESSION['add-course'])) {
                                                ?>
      <div class="row row-sm">
                                                    <div class="col-xl-8">
                                                <div class="card-header border-bottom-0 pb-1">
                                                    <label class="main-content-label mb-2 pt-1"><?php echo m('label.enrolling_courses') ?></label>
                                                    <p class="tx-12 mb-0 text-muted"><?php echo m('label.select_courses_for_student') ?></p>
                                                </div>
                                                <?php
                                                $rest = retPerson($_SESSION['add-course']['codemeli']);
                                                ?>
                                                <label class="main-content-label mb-2 pt-1"><?php echo m('label.student_name') ?> <span
                                                            class="text-warning">    <?php echo $rest->fname . ' ' . $rest->lname ?> </span></label>
                                                <hr>
                                                <div class="card-body pt-0">
                                                            <form method="post" data-parsley-validate="">
                                                        <ul class="top-selling-products pb-0 mb-0 pr-0">
                                                            <?php
                                                            $sql = 'SELECT * FROM codtbl_course WHERE courseactive=? ';
                                                            $query = $conn->prepare($sql);
                                                            $query->execute([1]);
                                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                            if ($query->rowCount() > 0) {
                                                                foreach ($result as $results) {
                                                                    if (checkactCourse($_SESSION['add-course']['codemeli'], $results->codecourse)) {
                                                                        $registercourse = 1;
                                                                    } else {
                                                                        $registercourse = 0;
                                                                    }
                                                                    ?>
                                                                    <li class="product-item ">
                                                                        <div class="product-img"><input type="checkbox"
                                                                                                        name="course[]"
                                                                                                        value="<?php echo $results->codecourse ?>" <?php if ($registercourse == 1) {
                                                                                echo 'disabled';
                                                                            } ?> ></div>
                                                                        <div class="product-info">
                                                                            <div class="product-name"><?php if ($registercourse == 1) {
                                                                                    echo '<del>' . htmlentities($results->coursename) . '</del>';
                                                                                } else {
                                                                                    echo htmlentities($results->coursename);
                                                                                } ?>  </div>
                                                                            <div class="price"><?php echo htmlentities($results->coursemuted) ?></div>
                                                                        </div>
                                                                        <div class="product-amount">
                                                                            <div class="product-price"><?php echo number_format($results->coursefee) ?>
                                                                                تومان
                                                                            </div>
                                                                            <div class="items-sold"><?php if ($registercourse == 1) {
                                                                                    echo 'غیرفعال';
                                                                                } else {
                                                                                    echo 'فعال';
                                                                                } ?> </div>
                                                                        </div>
                                                                    </li>
                                                                    <hr>
                                                                    <?php

                                                                }
                                                            }
                                                            ?>
                                                        </ul>
                                                        <?php
                                                        $hash = bin2hex(random_bytes(32));
                                                        $_SESSION['token'] = $hash;
                                                        ?>
                                                        <input class="form-control" name="token" type="hidden"
                                                               value="<?php echo $hash ?>">
                                                        <input type="hidden" name="user"
                                                               value="<?php echo $_SESSION['add-course']['codemeli'] ?>">
                                                                <div class="col-lg-8">
                                                                    <p class="mg-b-10 mg-t-10">پشتیبان <span
                                                                                class="tx-danger">*</span></p>
                                                                    <select class="form-control select2" name="suporter"
                                                                            required>
                                                                            <option label="دسته بندی ها">
                                                                        </option>
                                                                        <?php
                                                                        $sql = 'SELECT * FROM dbadmin WHERE permision_suporter=? OR olympiad_support_permission=?';
                                                                        $query = $conn->prepare($sql);
                                                                        $query->execute([1,1]);
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
                                                            <button class="btn btn-success ripple" name="sales-course">
                                                                تایید و صدور فاکتور
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <div class=" mg-t-10">
                                                        <a href="?change">
                                                            <button class="btn btn-warning " name="change">
                                                                تغییر کدملی
                                                            </button>
                                                        </a>
                                                    </div>
                                                </div>
                                                        </div>
                                                                <div class="col-xl-4">
                                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                                            <thead>
                                                            <tr>
                                                                <th>ردیف</th>
                                                                <th>پشتیبان</th>
                                                                <th><?php echo m('label.count_person_course') ?></th>
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            <?php
                                                            $sql='SELECT COUNT(*) as coreg , dbadmin.username FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse RIGHT JOIN dbadmin ON dbadmin.username=dbcourse.suporter  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=? GROUP BY dbcourse.suporter ORDER BY coreg DESC ';
                                                            $query=$conn->prepare($sql);
                                                            $query->execute(['موفق']);
                                                            $refrence=$query->fetchAll(PDO::FETCH_OBJ);
                                                            if($query->rowCount() > 0){
                                                                $count=1;
                                                                foreach ($refrence as $ted){
                                                                                                                   
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
                                            </div>
                                             
                                            <?php } ?>

                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Row -->
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
