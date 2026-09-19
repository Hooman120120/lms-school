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
// if (!Permision('permision_actfactor')) {
//     echo '<strong>403</strong>';
//     echo '<script>window.close() </script>';
//     die();
// }
// if (!isset($_GET['id'])) {
//     echo '<strong>403</strong>';
//     echo '<script>window.close() </script>';
//     die();
// }
// if (!CheckFactorNotSuccess($_GET['id'])) {
//     echo '<strong>403</strong>';
//     echo '<script>window.close() </script>';
//     die();
// }
if (queryFactor($_GET['id'])) {
    $factor = queryFactor($_GET['id']);
} else {
    echo '<script>window.close() </script>';
    echo '<strong>404</strong>';
    exit;
}
if (isset($_POST['save'])) {
    if ($factor->useradder != NULL) {
        $user = $factor->useradder;
    } else {
        $user = $factor->username;
    }
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    $discount_new = intval($_POST['discount']);
    $wage_new = intval($_POST['wage']);
    $subtotal_new = $discount_new + $wage_new;
    if ($factor->sumitem < $subtotal_new) {  //مبلغ دوره کمتر از تخفیف است
        $eror = 'خطا ! تخفیف و کارمزد نمی تواند بیشتر از هزینه دوره باشد';
    } else {
        if ($factor->sumitem == $subtotal_new) {  // دوره رایگان شده است
            if ($factor->wallet > 0) {
                $detail = 'افزایش موجودی بخاطر اصلاح فاکتور : ' . $_GET['id'];
                $inwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $ext = $conn->prepare($inwallet);
                $ext->execute([$user, $factor->wallet, 'موفق', $datenow, $timenow, $detail, 1, $_GET['id'], $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
                //
                $upw = 'UPDATE dbfactor SET wallet=? WHERE idfactor=?';
                $exup = $conn->prepare($upw);
                $exup->execute([0, $_GET['id']]);
            }
            $upm = 'UPDATE dbfactor SET discountfee=?, wage=?,subtotal=?,factorfee=?,status=?,metod=?,dateresponse=?,timeresponse=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
            $extry = $conn->prepare($upm);
            $extry->execute([$discount_new, $wage_new, 0, 0, 'موفق', 'رایگان', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $_GET['id']]);
            //
            $coup = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
            $exco = $conn->prepare($coup);
            $exco->execute([1, $_GET['id']]);
            // Register Quiz
            include_once '../../quizconfig.php';
            include_once '../../inc/function-quiz.php';
            $existingUid = checkUserquizByApi($factor->username);
            if (!$existingUid) {
                $person = retPerson($factor->username);
                $login = MD5(passUser($factor->username));
                CreateUserByApi(['aid' => 1, 'username' => $factor->username, 'password' => $login, 'active' => 1, 'expiration_date' => '3021-03-21', 'fname' => $person->fname, 'lname' => $person->lname, 'pass' => passUser($factor->username)]);
                // $inquiz = 'INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                // $qin = $ponn->prepare($inquiz);
                // $qin->execute([1, $factor->username, $login, 1, '3021-03-21', $person->fname, $person->lname]);
            }
            $cou = 'SELECT * FROM dbcourse WHERE numfactor=?';
            $qpo = $conn->prepare($cou);
            $qpo->execute([$_GET['id']]);
            $rew = $qpo->fetchAll(PDO::FETCH_OBJ);
            foreach ($rew as $tbt) {
                // ۱. دریافت آیدی کلاسی که کاربر انتخاب کرده است
                $class_id = $tbt->class_id;

                // ۲. دریافت ugid (آیدی گروه در سامانه آزمون) از جدول classes
                // فرض می‌کنیم شما از PDO با متغیر $conn استفاده می‌کنید
                $stmt = $conn->prepare("SELECT ugid FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                $class_info = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($class_info && !empty($class_info['ugid'])) {
                    $ugid = $class_info['ugid']; // این همان آیدی زیرگروه در سامانه آزمون است

                    // ۳. بررسی اینکه آیا کاربر از قبل در این گروه عضو هست یا خیر
                    // دقت کنید: اگر تابع checkGroupAddByApi بر اساس codecourse کار می‌کند،
                    // بهتر است یک تابع جدید بسازید که بر اساس ugid چک کند، یا اینکه به سادگی خطاها را در سامانه آزمون مدیریت کنید.
                    // اگر سامانه آزمون در صورت تکراری بودن کاربر ارور نمی‌دهد، می‌توانید مستقیماً کاربر را اضافه کنید:

                    $uid = GetUserIdByApi($tbt->username); // گرفتن آیدی کاربر در سامانه آزمون

                    if ($uid) {
                        // ۴. افزودن کاربر به زیرگروه (کلاس)
                        AddUserToGroupByApi($ugid, $uid);
                    }
                }
            }
//            foreach ($rew as $tbt) {
//                $existingUgumid = checkGroupAddByApi($tbt->codecourse, $tbt->username);
//                if (!$existingUgumid) {
//                    $ugid = retIdGroupByApi($tbt->codecourse);
//                    $uid = GetUserIdByApi($tbt->username);
//                    AddUserToGroupByApi($ugid, $uid);
//                    // $add = 'INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
//                    // $qad = $ponn->prepare($add);
//                    // $qad->execute([$ugid, $uid]);
//                }
//            }
            echo '<script>window.close() </script>';
            die();
        }
        if ($factor->sumitem > $subtotal_new) {
            if ($factor->wallet > 0) {
                $detail = 'افزایش موجودی بخاطر اصلاح فاکتور : ' . $_GET['id'];
                $inwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $ext = $conn->prepare($inwallet);
                $ext->execute([$user, $factor->wallet, 'موفق', $datenow, $timenow, $detail, 1, $_GET['id'], $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
                //
                $upw = 'UPDATE dbfactor SET wallet=? WHERE idfactor=?';
                $exup = $conn->prepare($upw);
                $exup->execute([0, $_GET['id']]);
            }
            $total = $factor->sumitem - $subtotal_new;
            $upm = 'UPDATE dbfactor SET discountfee=?, wage=?,subtotal=?,factorfee=?,status=?,metod=?,dateresponse=?,timeresponse=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
            $extry = $conn->prepare($upm);
            $extry->execute([$discount_new, $wage_new, $total, $total, 'منتظر پرداخت', 'کارت به کارت', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $_GET['id']]);
            echo '<script>window.close() </script>';
            die();
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
    <title> ویرایش فاکتور : <?php echo $_GET['id'] ?> | <?php echo __TITLE ?></title>
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
    <?php if (isset($eror)) { ?>
        <div class="alert alert-warning" role="alert">
            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                    type="button">
                <span aria-hidden="true">×</span>
            </button>
            <?php echo $eror ?>
        </div>
    <?php } ?>
    <div class="card-body">
        <dl class="dlist-align">
            <dt class=""> <?php
                if ($factor->status == 'موفق') {
                    echo 'شماره فاکتور:';
                } else {
                    echo 'شماره پیش فاکتور:';
                } ?></dt>
            <dd class="text-right mr-auto">
                <?php echo '#' . $_GET['id'] ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تاریخ صدور صورتحساب</dt>
            <?php
            $dateno = $factor->datecreate;
            if ($dateno != NULL) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                $dateno = $factor->timecreate . ' - ' . $dateno;
            }
            ?>


            <dd class="text-right mr-auto"> <?php echo $dateno ?>

            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">نام دانش آموز:</dt>
            <dd class="text-right mr-auto">
                <?php $fullname = retPerson($factor->username);
                echo $fullname->fname . ' ' . $fullname->lname ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">کد ملی دانش آموز:</dt>
            <dd class="text-right mr-auto">
                <?php echo $factor->username; ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">ایجاد کننده:</dt>
            <dd class="text-right mr-auto">
                <?php $fullname = retPerson($factor->username);
                if ($factor->useradder != NULL) {
                    $useradder = retPerson($factor->useradder);
                    echo 'همکار آموزشی:' . $useradder->fname . ' ' . $useradder->lname;
                }
                if ($factor->factoradder != NULL) {
                    $factoradder = retAdmin($factor->factoradder);
                    echo 'کارمند ' . $factoradder->fname . ' ' . $factoradder->lname;
                }
                ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">کد ملی ایجاد کننده:</dt>
            <dd class="text-right mr-auto">
                <?php echo $factor->useradder; ?>
            </dd>
        </dl>
        <div class="table-responsive border">
            <table class="table  text-nowrap text-md-nowrap table-striped mg-b-0">
                <thead>
                <tr>
                    <th>ردیف</th>
                    <th>دوره</th>
                    <th>مبلغ</th>
                    <th>خدمات پشتیبانی</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $sqt = 'SELECT * FROM dbcourse WHERE numfactor=?';
                $qut = $conn->prepare($sqt);
                $qut->execute([$factor->idfactor]);
                $rest = $qut->fetchAll();
                if ($qut->rowCount() > 0) {
                    $id = 1;
                    foreach ($rest as $erp) {

                        ?>
                        <tr>
                            <th><?php echo $id ?></th>
                            <td><?php $db = returnCourse($erp['codecourse']);
                                echo $db->coursename; ?></td>
                            <?php
                            if ((int)$erp['has_operator'] === 0) {
                                $price = $db->coursefee_without;
                            } else {
                                $price = $db->coursefee;
                            }
                            ?>
                            <td><?php echo number_format($price ?? 0) ?>
                                تومان
                            </td>
                            <td><?php echo $erp['has_operator'] == 0 ? 'ندارد' : 'دارد' ?>
                            </td>
                        </tr>

                        <?php
                        $id++;
                    }
                } ?>
                </tbody>
            </table>
        </div>
        <dl class="dlist-align">
            <dt class="">جمع موارد:</dt>
            <dd class="text-right mr-auto"> <?php echo number_format($factor->sumitem) ?>
                تومان
            </dd>
        </dl>

        <div class="card-body">
            <div class="row">
                <form method="post" data-parsley-validate="">
                    <dl class="dlist-align">
                        <dt class="">مبلغ تخفیف:<span class="text-danger">*</span></dt>
                        <dd class="text-right mr-auto">
                            <div class="input-group-prepend">
                                <input type="number"
                                       class="form-control text-danger text-left"
                                       name="discount"
                                       value="<?php echo $factor->discountfee ?>"
                                       required="">
                                <div class="input-group-text">
                                    تومان
                                </div>
                            </div>
                        </dd>
                    </dl>

                    <dl class="dlist-align">
                        <dt class="">کارمزد همکار:<span class="text-danger">*</span></dt>
                        <dd class="text-right mr-auto">
                            <div class="input-group-prepend">
                                <input type="number"
                                       class="form-control text-danger text-left"
                                       name="wage"
                                       value="<?php echo $factor->wage ?>"
                                       required="">
                                <div class="input-group-text">
                                    تومان
                                </div>
                            </div>
                        </dd>
                    </dl>
                    <div class="text-center mg-t-20">
                        <button type="submit" class="btn btn-success "
                                name="save">
                            محاسبه و صدور فاکتور جدید
                        </button>
                    </div>

                </form>
            </div>

        </div>


    </div>


    <?php include '../../footers.php'; ?>
</body>

</html>