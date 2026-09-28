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
if (!Permision('permision_actfactor')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
// Tree accounts need the «تأیید یا رد فاکتور» operation and the invoice in their own/all scope.
if (!AdminCanUsePermissionScope('finance.pending.verify', ['permision_actfactor'])
    || !AdminCanAccessFinanceFactor((int)($_GET['factor'] ?? 0), 'finance.pending')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!isset($_GET['factor'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!CheckFactorNotSuccess($_GET['factor'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['verify'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $track = $_POST['track'];
        if (CheckTrackId($track)) {
            $factorId = $_POST['numfactor'];
            $fee = intval($_POST['deposit']);
            $factor = queryFactor($factorId);
            if ($fee != $factor->factorfee) {
                date_default_timezone_set("Iran");
                $datenow = date("Y-m-d");
                $timenow = date('H:i:s');
                $ip = GetIp();
                $os = GetOs();
                if ($factor->useradder != NULL) {
                    $user = $factor->useradder;
                } else {
                    $user = $factor->username;
                }
                if (isset($_POST['duedate']) and $_POST['duedate'] != '') {
                    $duedate = $_POST['duedate'];
                    $duedate = explode('/', $duedate);
                    $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
                    $duedate = implode('-', $duedate);
                } else {
                    $duedate = $factor->dateresponse;
                }
                $detail = 'افزایش موجودی از کارت برای فاکتور ' . $factorId;
                $insert = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ref_id,card_pan,ip,os,useractive,dateactive,timeactive,ipactive,osactive)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $query = $conn->prepare($insert);
                $query->execute([$user, $fee, 'موفق', $duedate, $timenow, $detail, 1, $factorId, $track, $factor->card_pan, $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);

                if ($fee < $factor->factorfee) {
                    $fees = $fee * -1;
                    $dtailD = 'کاهش موجودی بواسطه فاکتور ' . $factorId;
                    $insertD = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $queryD = $conn->prepare($insertD);
                    $queryD->execute([$user, $fees, 'موفق', $datenow, $timenow, $dtailD, 0, $factorId, $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
                    $wallet = $factor->wallet + $fee;
                    $total = $factor->factorfee - $fee;
                    $update = 'UPDATE dbfactor SET wallet=?,factorfee=?, status=?,dateresponse=?,timeresponse=? ,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
                    $qupdate = $conn->prepare($update);
                    $qupdate->execute([$wallet, $total, 'منتظر پرداخت', $duedate, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $factorId]);
                    $person=retPerson($user);
                    $value= $factor->factorfee-$fee;
                    $text=$factorId.';'. number_format($value);
                    SendSms($person->tel1, $text, 171705, $user);
                    echo '<script>window.close() </script>';
                    die();
                }
                if ($fee > $factor->factorfee) {
                    $fees = $factor->factorfee * -1;
                    $dtailD = 'کاهش موجودی بواسطه فاکتور ' . $factorId;
                    $insertD = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    $queryD = $conn->prepare($insertD);
                    $queryD->execute([$user, $fees, 'موفق', $datenow, $timenow, $dtailD, 0, $factorId, $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
                    $wallet = $factor->wallet + $factor->factorfee;
                    $total = 0;
                    $update = 'UPDATE dbfactor SET wallet=?,factorfee=?, metod=?,  status=?, dateresponse=? ,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
                    $qupdate = $conn->prepare($update);
                    $qupdate->execute([$wallet, $total, 'کیف پول', 'موفق', $duedate, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $factorId]);
                    $courseA = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
                    $queryA = $conn->prepare($courseA);
                    $queryA->execute([1, $factorId]);
                          // Register Quiz
                    include_once '../../quizconfig.php';
                    include_once '../../inc/function-quiz.php';
                    if (checkUserquiz($factor->username)) {
                        $person = retPerson($factor->username);
                        $login = MD5(passUser($factor->username));
                        $inquiz = 'INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                        $qin = $ponn->prepare($inquiz);
                        $qin->execute([1, $factor->username, $login, 1, '3021-03-21', $person->fname, $person->lname]);
                    }
                    $cou = 'SELECT * FROM dbcourse WHERE numfactor=?';
                    $qpo = $conn->prepare($cou);
                    $qpo->execute([$factorId]);
                    $rew = $qpo->fetchAll(PDO::FETCH_OBJ);
                    foreach ($rew as $tbt) {
                        if (checkGroupAdd($tbt->codecourse, $tbt->username)) {
                            $ugid = retIdGroup($tbt->codecourse);
                            $uid = retIdStudent($tbt->username);
                            $add = 'INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                            $qad = $ponn->prepare($add);
                            $qad->execute([$ugid, $uid]);
                        }
                    }
                    //
                    if (ActivatePublic('actsms_success_card')) {
                        //Start Send SMS
                        $value=$fee-$factor->factorfee;
                        $person=retPerson($user);
                        $text=$factorId.';'. number_format($value);
                        SendSms($person->tel1, $text, 191899, $user);
                    }


                    echo '<script>window.close() </script>';
                    die();
                }

            } else {
                echo '   <div class="alert alert-danger" role="alert">
                      <span class="alert-inner--icon"><i class="fe fe-lock"></i></span>
                          <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                            </button>
                                     خطا! مبلغ متناقض ثبت نشده است
                       </div>';
            }
        } else {
            echo '   <div class="alert alert-danger" role="alert">
                      <span class="alert-inner--icon"><i class="fe fe-lock"></i></span>
                          <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                            </button>
                                     تایید نشد! کد پیگیری تکراری است
                       </div>';
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
    <title> واریزی متناقض <?php echo $_GET['factor'] ?> | <?php echo __TITLE ?></title>
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

    <div class="card-body">
        <form method="post" data-parsley-validate="">
            <div class="form-group">
                <div class="row row-sm">
                    <div class="col-6">
                        <p class="mg-b-10">مبلغ فاکتور:</p>
                        <div class="input-group mb-3 text-success tx-20 text-center">
                            <?php $factor = queryFactor($_GET['factor']); ?>
                            <?php echo number_format($factor->factorfee); ?> تومان
                        </div>
                    </div>
                    <div class="col-6">
                        <p class="mg-b-10">زمان واریز :</p>
                        <div class="input-group mb-3 text-danger tx-20 text-center">
                            <?php
                            $dateno = $factor->dateresponse;
                            $dateno = explode('-', $dateno);
                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                            $dateno = implode('/', $dateno);
                            echo $dateno;
                            ?>
                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <div class="col-4">

                        <p class="mg-b-10">مبلغ واریزی<span class="text-danger">*</span></p>
                        <div class="input-group mb-3">
                            <input class="form-control" name="deposit"
                                   placeholder=" به تومان وارد شود"
                                   required="" type="number">
                        </div>

                    </div>
                    <div class="col-4">

                        <p class="mg-b-10">کد پیگیری<span class="text-danger">*</span></p>
                        <div class="input-group mb-3">
                            <input class="form-control" name="track"
                                   placeholder=" کد پیگیری کارت به کارت"
                                   required="" type="text">
                        </div>

                    </div>
                    <div class="col-4">
                        <p class="mg-b-10">تاریخ واریز وجه(درصورت مغایرت)</p>

                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <i class="fe fe-calendar lh--9 op-6"></i>
                                </div>
                            </div>
                            <input class="form-control fc-datepicker"
                                   placeholder="در صورت صحیح نبودن تاریخ ثبت شود"
                                   type="text" name="duedate">
                        </div>
                    </div>
                </div>
                <?php
                $hash = bin2hex(random_bytes(32));
                $_SESSION['token'] = $hash;
                ?>
                <input type="hidden" name="token" value="<?php echo $hash ?>"
                       required>
                <input type="hidden" name="numfactor" value="<?php echo $_GET['factor'] ?>"
                       required>
                <hr>
                <hr>
                <div class="col-12 pd-t-30 text-center">
                    <button class="btn ripple  btn-success pd-x-20"
                            type="submit"
                            name="verify">ثبت واریزی و اقدامات
                    </button>

                </div>

            </div>
        </form>
    </div>


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>