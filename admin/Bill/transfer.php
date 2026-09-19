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
//*
if (!Permision('permision_bill')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!isset($_GET['order'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (queryFactor($_GET['order'])) {
    $factor = queryFactor($_GET['order']);
} else {
    echo '<script>window.close()</script>';
    echo '<strong>404</strong>';
    exit;
}
if ($factor->status != 'موفق') {
    echo '<script>window.close()</script>';
    echo '<strong>403</strong>';
    die();
}
if (isset($_GET['reset'])) {
    unset($_SESSION['newFactor']);
    unset($_SESSION['newcourse']);
    echo '<script>window.close()</script>';
    die();
}
if (isset($_POST['save'])) {

    $sub = 0;
    $is = 0;
    foreach ($_POST['doure'] as $code) {
        if (getfee($code)) {
            $sub = getfee($code) + $sub;
            $_SESSION['newcourse'][$is] = $code;
            $is++;
        }
    }
    $sumitem = $factor->sumitem;  // جمع موارد قبل
    $dis = $factor->discountfee;
    $wage = $factor->wage;
    $reminder = $sumitem - $sub;    // مقدار باقیمانده را محاسبه می کند
    $_SESSION['newFactor'] = array(
        "subtotal" => $sub,
        "reminder" => $reminder,
    );
}
if (isset($_POST['verifychange'])) {
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    if ($factor->useradder != NULL) {
        $username = $factor->useradder;
    } else {
        $username = $factor->username;
    }
    $wage = totalWallet($username);
    $number = intval($_GET['order']);
    if ($_SESSION['newFactor']['reminder'] > 0) {
        $fee = $_SESSION['newFactor']['reminder'];
        $detail = 'افزایش موجودی حساب بخاطر اصلاح فاکتور : ' . $_GET['order'];
        $chwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $charge = $conn->prepare($chwallet);
        $charge->execute([$username, $fee, 'موفق', $datenow, $timenow, $detail, 1, $number, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
        //
        $newsubtotal = $factor->discountfee + $factor->wage;
        $newsubtotal = $_SESSION['newFactor']['subtotal'] - $newsubtotal;
        $upfactor = 'UPDATE dbfactor SET sumitem=?,subtotal=?,wallet=?,factorfee=?,useractive=?, dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
        $exe = $conn->prepare($upfactor);
        $exe->execute([$_SESSION['newFactor']['subtotal'], $newsubtotal, $newsubtotal, 0, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $number]);

        //Quiz transfer
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        $tyu = 'SELECT * FROM dbcourse WHERE numfactor=?';
        $swe = $conn->prepare($tyu);
        $swe->execute([$number]);
        $rev = $swe->fetchAll(PDO::FETCH_OBJ);
        foreach ($rev as $track) {
            $ugid_parent = retIdGroup($track->codecourse);
            $uid = retIdStudent($track->username);
            $delquiz = 'DELETE FROM `_ugroups_umembers` WHERE ugid_parent=? AND uid=? ';
            $qaz = $ponn->prepare($delquiz);
            $qaz->execute([$ugid_parent, $uid]);
        }
        //
        $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
        $exold = $conn->prepare($delold);
        $exold->execute([$number]);
        //
        foreach ($_SESSION['newcourse'] as $newcode) {
            $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
            $excourse = $conn->prepare($incourse);
            $excourse->execute([$factor->username, $newcode, 1, $number]);
            //
            if(checkGroupAdd($newcode,$factor->username)){
                $ugid=retIdGroup($newcode);
                $uid=retIdStudent($factor->username);
                $add='INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                $qad=$ponn->prepare($add);
                $qad->execute([$ugid,$uid]);
            }
        }
        //
        unset($_SESSION['newcourse']);
        unset($_SESSION['newFactor']);
        echo '<script>window.close()</script>';
        die();
    } //فاکتور کاهشی است و پول باید به کیف زده شود
    if ($_SESSION['newFactor']['reminder'] == 0) {
        //Quiz transfer
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        $tyu = 'SELECT * FROM dbcourse WHERE numfactor=?';
        $swe = $conn->prepare($tyu);
        $swe->execute([$number]);
        $rev = $swe->fetchAll(PDO::FETCH_OBJ);
        foreach ($rev as $track) {
            $ugid_parent = retIdGroup($track->codecourse);
            $uid = retIdStudent($track->username);
            $delquiz = 'DELETE FROM `_ugroups_umembers` WHERE ugid_parent=? AND uid=? ';
            $qaz = $ponn->prepare($delquiz);
            $qaz->execute([$ugid_parent, $uid]);
        }

        $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
        $exold = $conn->prepare($delold);
        $exold->execute([$number]);
        //
        foreach ($_SESSION['newcourse'] as $newcode) {
            $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
            $excourse = $conn->prepare($incourse);
            $excourse->execute([$factor->username, $newcode, 1, $number]);
            //
            if(checkGroupAdd($newcode,$factor->username)){
                $ugid=retIdGroup($newcode);
                $uid=retIdStudent($factor->username);
                $add='INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                $qad=$ponn->prepare($add);
                $qad->execute([$ugid,$uid]);
            }
        }


        //
        unset($_SESSION['newcourse']);
        unset($_SESSION['newFactor']);
        echo '<script>window.close()</script>';
        die();
    } // تغییری در فاکتور نداریم و فقط جابجایی پایه داریم
    if ($_SESSION['newFactor']['reminder'] < 0) {
        $baghimande = $_SESSION['newFactor']['reminder'] * -1;
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if ($wage > 0 and $baghimande < $wage) {
            $feewage = $baghimande * -1;

            $detail = 'کاهش موجودی حساب بخاطر اصلاح فاکتور :' . $number;
            $chwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $charge = $conn->prepare($chwallet);
            $charge->execute([$username, $feewage, 'موفق', $datenow, $timenow, $detail, 0, $number, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //
            $bardashtwage = $feewage * -1;
            $newsubtotal = $factor->discountfee + $factor->wage;
            $newsubtotal = $_SESSION['newFactor']['subtotal'] - $newsubtotal;
            $walletnew = $factor->wallet + $factor->factorfee + $bardashtwage;
            $upfactor = 'UPDATE dbfactor SET sumitem=?,subtotal=?,wallet=?,factorfee=?,useractive=?, dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
            $exe = $conn->prepare($upfactor);
            $exe->execute([$_SESSION['newFactor']['subtotal'], $newsubtotal, $walletnew, 0, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $number]);
            //
            //Quiz transfer
            include_once '../../quizconfig.php';
            include_once '../../inc/function-quiz.php';
            $tyu = 'SELECT * FROM dbcourse WHERE numfactor=?';
            $swe = $conn->prepare($tyu);
            $swe->execute([$number]);
            $rev = $swe->fetchAll(PDO::FETCH_OBJ);
            foreach ($rev as $track) {
                $ugid_parent = retIdGroup($track->codecourse);
                $uid = retIdStudent($track->username);
                $delquiz = 'DELETE FROM `_ugroups_umembers` WHERE ugid_parent=? AND uid=? ';
                $qaz = $ponn->prepare($delquiz);
                $qaz->execute([$ugid_parent, $uid]);
            }
            //
            $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
            $exold = $conn->prepare($delold);
            $exold->execute([$number]);
            //
            foreach ($_SESSION['newcourse'] as $newcode) {
                $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
                $excourse = $conn->prepare($incourse);
                $excourse->execute([$factor->username, $newcode, 1, $number]);

                if(checkGroupAdd($newcode,$factor->username)){
                    $ugid=retIdGroup($newcode);
                    $uid=retIdStudent($factor->username);
                    $add='INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                    $qad=$ponn->prepare($add);
                    $qad->execute([$ugid,$uid]);
                }
            }
            //
            unset($_SESSION['newcourse']);
            unset($_SESSION['newFactor']);
            echo '<script>window.close()</script>';
            die();

        }//مبلغ کیف پول بیشتر از مبلغ فاکتور جدید است
        if ($wage > 0 and $baghimande > $wage) {
            $newfee = $baghimande - $wage;
            $feewage = $wage * -1;
            $detail = 'کاهش موجودی حساب بخاطر اصلاح فاکتور :' . $number;
            $chwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $charge = $conn->prepare($chwallet);
            $charge->execute([$username, $feewage, 'موفق', $datenow, $timenow, $detail, 0, $number, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //
            $bardashtwage = $feewage * -1;
            $newsubtotal = $factor->discountfee + $factor->wage;
            $newsubtotal = $_SESSION['newFactor']['subtotal'] - $newsubtotal;
            $walletnew = $factor->wallet + $factor->factorfee + $bardashtwage;
            $upfactor = 'UPDATE dbfactor SET sumitem=?,subtotal=?,wallet=?,factorfee=?,status=?,dateresponse=?,timeresponse=?,ref_id=?, card_pan=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
            $exe = $conn->prepare($upfactor);
            $exe->execute([$_SESSION['newFactor']['subtotal'], $newsubtotal, $walletnew, $newfee, 'منتظر پرداخت', NULL, NULL, NULL, NULL, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $number]);
            //
            $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
            $exold = $conn->prepare($delold);
            $exold->execute([$number]);
            //
            foreach ($_SESSION['newcourse'] as $newcode) {
                $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
                $excourse = $conn->prepare($incourse);
                $excourse->execute([$factor->username, $newcode, 0, $number]);
            }
            //
            unset($_SESSION['newcourse']);
            unset($_SESSION['newFactor']);
            echo '<script>window.close()</script>';
            die();

        } //مبلغ کیف پول کمتر از مبلغ فاکتور جدید است
        if ($wage > 0 and $baghimande == $wage) {
            $feewage = $wage * -1;
            $newfee = 0;
            $detail = 'کاهش موجودی حساب بخاطر اصلاح فاکتور :' . $number;
            $chwallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $charge = $conn->prepare($chwallet);
            $charge->execute([$username, $feewage, 'موفق', $datenow, $timenow, $detail, 0, $number, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //
            $bardashtwage = $feewage * -1;
            $newsubtotal = $factor->discountfee + $factor->wage;
            $newsubtotal = $_SESSION['newFactor']['subtotal'] - $newsubtotal;
            $upfactor = 'UPDATE dbfactor SET sumitem=?,subtotal=?,wallet=?,factorfee=?,useractive=?, dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
            $exe = $conn->prepare($upfactor);
            $exe->execute([$_SESSION['newFactor']['subtotal'], $newsubtotal, $newsubtotal, 0, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $number]);

            //Quiz transfer
            include_once '../../quizconfig.php';
            include_once '../../inc/function-quiz.php';
            $tyu = 'SELECT * FROM dbcourse WHERE numfactor=?';
            $swe = $conn->prepare($tyu);
            $swe->execute([$number]);
            $rev = $swe->fetchAll(PDO::FETCH_OBJ);
            foreach ($rev as $track) {
                $ugid_parent = retIdGroup($track->codecourse);
                $uid = retIdStudent($track->username);
                $delquiz = 'DELETE FROM `_ugroups_umembers` WHERE ugid_parent=? AND uid=? ';
                $qaz = $ponn->prepare($delquiz);
                $qaz->execute([$ugid_parent, $uid]);
            }
            //
            $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
            $exold = $conn->prepare($delold);
            $exold->execute([$number]);
            //
            foreach ($_SESSION['newcourse'] as $newcode) {
                $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
                $excourse = $conn->prepare($incourse);
                $excourse->execute([$factor->username, $newcode, 1, $number]);
                //
                if(checkGroupAdd($newcode,$factor->username)){
                    $ugid=retIdGroup($newcode);
                    $uid=retIdStudent($factor->username);
                    $add='INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                    $qad=$ponn->prepare($add);
                    $qad->execute([$ugid,$uid]);
                }
            }
            //
            unset($_SESSION['newcourse']);
            unset($_SESSION['newFactor']);
            echo '<script>window.close()</script>';
            die();
        } //مبلغ کیف پول برابر با مبلغ فاکتور جدید است
        if ($wage == 0) {

            $newsubtotal = $factor->discountfee + $factor->wage;
            $newsubtotal = $_SESSION['newFactor']['subtotal'] - $newsubtotal;
            $wa = $newsubtotal - $baghimande;

            $upfactor = 'UPDATE dbfactor SET sumitem=?,subtotal=?,wallet=?,factorfee=?,status=?,dateresponse=?,timeresponse=?,ref_id=?,card_pan=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
            $exe = $conn->prepare($upfactor);
            $exe->execute([$_SESSION['newFactor']['subtotal'], $newsubtotal, $wa, $baghimande, 'منتظر پرداخت', NULL, NULL, NULL, NULL, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $number]);
            //
            $delold = 'DELETE FROM dbcourse WHERE numfactor=?';
            $exold = $conn->prepare($delold);
            $exold->execute([$number]);
            //
            foreach ($_SESSION['newcourse'] as $newcode) {
                $incourse = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
                $excourse = $conn->prepare($incourse);
                $excourse->execute([$factor->username, $newcode, 0, $number]);
            }
            //
            unset($_SESSION['newcourse']);
            unset($_SESSION['newFactor']);
            echo '<script>window.close()</script>';
            die();

        } //کیف پول خالی است


    } //فاکتور افزایشی است و می بایست پرداخت انجام شود
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> جابجابی پایه : <?php echo $_GET['order'] ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<div class="page">
    <div class="alert alert-info" role="alert">
        <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>
        پس از تایید این صفحه بسته شده و صفحه قبلی را بارگذاری مجدد (Refresh /F5) نمایید
    </div>
    <?php if (!isset($_SESSION['newFactor'])) { ?>
        <div class="card-body">
            <div class="row row-sm">
                <div class="col-2">
                    <p class="mg-b-10">مبلغ تخفیف:</p>
                    <div class="input-group mb-3 text-danger tx-10 text-center">
                        <?php echo number_format($factor->discountfee); ?> تومان
                    </div>
                </div>
                <div class="col-2">
                    <p class="mg-b-10">کارمزد همکار:</p>
                    <div class="input-group mb-3 text-danger tx-10 text-center">
                        <?php echo number_format($factor->wage); ?> تومان
                    </div>
                </div>
                <div class="col-2">
                    <p class="mg-b-10">مبلغ صورتحساب</p>
                    <div class="input-group mb-3 text-warning tx-15 text-center">
                        <?php echo number_format($factor->subtotal); ?> تومان
                    </div>
                </div>
                <div class="col-3">
                    <p class="mg-b-10"> پرداختی کیف پول </p>
                    <div class="input-group mb-3 text-success tx-15 text-center">
                        <?php echo number_format($factor->wallet); ?> تومان
                    </div>
                </div>
                <div class="col-3">
                    <p class="mg-b-10">پرداخت وجه</p>
                    <div class="input-group mb-3 text-success tx-15 text-center">
                        <?php echo number_format($factor->factorfee); ?> تومان
                    </div>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-9">
                                <div>
                                    <h6 class="main-content-label mb-1">جابجایی پایه </h6>
                                    <p class="text-muted card-sub-title"> مشاهده پایه ها و انتخاب جایگزین</p>
                                </div>
                            </div>
                        </div>
                        <form method="post" data-parsley-validate="">
                            <?php
                            $sqt = 'SELECT * FROM dbcourse WHERE numfactor=?';
                            $qut = $conn->prepare($sqt);
                            $qut->execute([$factor->idfactor]);
                            $rest = $qut->fetchAll();
                            if ($qut->rowCount() > 0) {
                                $id = 1;
                                foreach ($rest as $erp) {
                                    ?>
                                    <div class="row">
                                        <div class="col-6 mg-t-20 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        پایه قبلی:
                                                    </div>
                                                </div>
                                                <input class="form-control"
                                                       value="<?php $db = returnCourse($erp['codecourse']);
                                                       echo $db->coursename; ?>"
                                                       aria-describedby="basic-addon2"
                                                       type="text" disabled>
                                            </div>
                                        </div>
                                        <div class="col-6 mg-t-20">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    پایه جایگزین<span
                                                            class="tx-danger">*</span>
                                                </div>
                                                <select class="form-control text-danger select2 "
                                                        aria-describedby="basic-addon2"
                                                        required="" name="doure[]">
                                                    <option label="پایه ">
                                                    </option>
                                                    <?php
                                                    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result
                                                                 as $res) { ?>
                                                            <option value="<?php echo $res->codecourse ?>">
                                                                <?php echo $res->coursename ?></option>
                                                            <?php
                                                        }
                                                    }
                                                    ?>

                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                            <div class="text-right mg-t-20">
                                <button type="submit" class="btn btn-success "
                                        name="save"><i
                                            class="fa fa-save"></i>
                                    جابجایی پایه
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="alert alert-success" role="alert">
            <span class="alert-inner--icon"><i class="fa fa-info"></i></span>
            در جابجایی پایه ، مبلغ تخفیف و کارمزد همکار همانند فاکتور اولیه محاسبه و از فاکتور جدید کم میگردد!
        </div>
        <div class="row">
            <div class="col-6">
                <div class="card custom-card cart-details">
                    <div class="card-body">
                        <h5 class="mb-3 font-weight-bold tx-14"> صورتحساب قبلی برای فاکتور
                            : <?php echo $_GET['order'] ?></h5>
                        <dl class="dlist-align">
                            <dt class="">جمع موارد :</dt>
                            <dd class="text-right mr-auto">
                                <?php echo number_format($factor->sumitem); ?>
                                تومان
                            </dd>
                        </dl>
                        <dl class="dlist-align">
                            <dt>مقدار تخفیف:</dt>
                            <dd class="text-right text-danger mr-auto">
                                <?php echo number_format($factor->discountfee); ?>
                                تومان
                            </dd>
                        </dl>
                        <dl class="dlist-align">
                            <dt>کارمزدهمکار:</dt>
                            <dd class="text-right text-danger mr-auto">
                                <?php echo number_format($factor->wage); ?>
                                تومان
                            </dd>
                        </dl>
                        <dl class="dlist-align">
                            <dt>مبلغ صورتحساب:</dt>
                            <dd class="text-right mr-auto">
                                <?php echo number_format($factor->subtotal); ?>
                                تومان
                            </dd>
                        </dl>
                        <dl class="dlist-align">
                            <dt>پرداختی از کیف پول:</dt>
                            <dd class="text-right mr-auto">
                                <?php echo number_format($factor->wallet); ?>
                                تومان
                            </dd>
                        </dl>
                        <hr>
                        <dl class="dlist-align">
                            <dt>مبلغ پرداخت شده:</dt>
                            <dd class="text-right text-success tx-18  mr-auto">
                                <strong> <?php echo number_format($factor->factorfee); ?> تومان</strong></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card custom-card cart-details">
                    <div class="card-body">
                        <h5 class="mb-3 font-weight-bold tx-14">اصلاحیه صورتحساب جدید برای فاکتور
                            : <?php echo $_GET['order'] ?></h5>
                        <dl class="dlist-align">
                            <dt class="">جمع موارد جدید:</dt>
                            <dd class="text-right mr-auto">
                                <?php echo number_format($_SESSION['newFactor']['subtotal']); ?>
                                تومان
                            </dd>
                        </dl>
                        <?php
                        if ($_SESSION['newFactor']['reminder'] > 0) {
                            ?>
                            <dl class="dlist-align">
                                <dt>مقدار اختلاف:</dt>

                                <dd class="text-right text-danger mr-auto">
                                    <?php echo number_format($_SESSION['newFactor']['reminder']); ?>
                                    -
                                </dd>
                            </dl>
                            <dl class="dlist-align">
                                <dt>افزایش کیف پول:</dt>
                                <dd class="text-right text-success mr-auto">
                                    <?php echo number_format($_SESSION['newFactor']['reminder']); ?>
                                    +
                                </dd>
                            </dl>
                            <?php
                        } else {
                            ?>
                            <dl class="dlist-align">
                                <dt>مقدار اختلاف:</dt>

                                <dd class="text-right text-success mr-auto">
                                    <?php echo number_format($_SESSION['newFactor']['reminder'] * -1); ?>
                                    +
                                </dd>
                            </dl>
                            <?php
                        }
                        ?>

                        <dl class="dlist-align">
                            <dt>موجودی کیف پول</dt>
                            <?php
                            $wallet = NULL;
                            if ($factor->useradder != NULL) {
                                $wallet = totalWallet($factor->useradder);
                                if ($wallet == NULL) {
                                    $wallet = 0;
                                }
                            }
                            if ($factor->useradder == NULL) {
                                $wallet = totalWallet($factor->username);
                                if ($wallet == NULL) {
                                    $wallet = 0;
                                }
                            }

                            ?>

                            <dd class="text-right mr-auto"><?php echo number_format($wallet); ?>
                                تومان
                            </dd>
                        </dl>

                        <hr>
                        <dl class="dlist-align">
                            <?php
                            $newtotal = $_SESSION['newFactor']['subtotal'] - $factor->sumitem;
                            if ($newtotal < 0) {
                                $newtotal = 0;
                            }
                            if ($newtotal > 0 and $wallet > 0) {
                                $newtotal = $newtotal - $wallet;
                                if ($newtotal < 0) {
                                    $newtotal = 0;
                                }

                            }
                            ?>

                            <dt>مبلغ قابل پرداخت:</dt>
                            <dd class="text-right text-success tx-18  mr-auto">
                                <strong><?php echo number_format($newtotal) ?> تومان</strong></dd>
                        </dl>
                        <form method="post">
                            <div class="text-center">

                                <button data-direction="next" class="step-btn btn btn-success"
                                        type="submit" name="verifychange">ثبت صورتحساب
                                </button>

                                <a data-direction="next" class="step-btn btn btn-light"
                                   href="?order=<?php echo $_GET['order'] ?>&reset">لغو جابجایی

                                </a>

                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>