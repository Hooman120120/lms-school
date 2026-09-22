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
if (!Permision('permision_bill')) {
    http_response_code(403); exit('مجوز مشاهده صورتحساب‌ها برای شما تعریف نشده است.');
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_SESSION['message'])) {
    $id = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['cancel'])) {
    $cancel = $_SESSION['cancel'];
    unset($_SESSION['cancel']);
}
if (isset($_SESSION['wallet'])) {
    $wal = $_SESSION['wallet'];
    unset($_SESSION['wallet']);
}
if (isset($_SESSION['fee'])) {
    $fee = $_SESSION['fee']['fee'];
    $name = $_SESSION['fee']['name'];
    unset($_SESSION['fee']);
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM dbfactor WHERE username =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);

            break;
        case '02':
            $swr = 'SELECT * FROM dbfactor WHERE idfactor =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = explode('/', $txt);
            $txt = jalali_to_gregorian($txt[0], $txt[1], $txt[2]);
            $txt = implode('-', $txt);
            $swr = 'SELECT * FROM dbfactor WHERE dateresponse =? ORDER BY idfactor DESC';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '04':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbfactor INNER JOIN dbpersonal ON dbfactor.username=dbpersonal.codemeli WHERE dbpersonal.fname LIKE :name ORDER BY idfactor DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '05':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbfactor INNER JOIN dbpersonal ON dbfactor.username=dbpersonal.codemeli WHERE  dbpersonal.lname LIKE :famili ORDER BY idfactor DESC';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':famili', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '06':
            $swr = 'SELECT * FROM dbfactor WHERE useradder =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
    }


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
    header('location: SearchBillView.php');
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
    header('location: SearchBillView.php');
    die();
}
if (isset($_GET['delfactor'])) {
    $factorId = intval($_GET['delfactor']);
    $factor = queryFactor($factorId);
    if ($factor->status != 'موفق' and $factor->status != 'مسترد شده') {
        $wallet = $factor->wallet;
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if ($wallet > 0) {
            $detail = 'افزایش کیف پول بواسطه حذف فاکتور:' . $factorId;
            if ($factor->useradder != NULL) {
                $username = $factor->useradder;
            } else {
                $username = $factor->username;
            }
            $indb = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $exindb = $conn->prepare($indb);
            $exindb->execute([$username, $wallet, 'موفق', $datenow, $timenow, $detail, 1, $factorId, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //
            $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
            $query_del = $conn->prepare($delfactor);
            $query_del->execute([$factorId]);
            $_SESSION['message'] = $factorId;
            $fname = retPerson($username);
            $_SESSION['fee'] = array(
                "fee" => $wallet,
                "name" => $fname->fname . ' ' . $fname->lname
            );
            header('location: advanced-report.php');
        } else {
            $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
            $query_del = $conn->prepare($delfactor);
            $query_del->execute([$factorId]);
            $_SESSION['message'] = $factorId;
            header('location: advanced-report.php');
        }

    }
    if ($factor->status == 'مسترد شده') {
        $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
        $query_del = $conn->prepare($delfactor);
        $query_del->execute([$factorId]);
        $_SESSION['message'] = $factorId;
        header('location: advanced-report.php');

    }

} //حذف فاکتور
if (isset($_GET['unverify']) and $_GET['unverify'] != '') {
    if (CheckFactorNotSuccess($_GET['unverify'])) {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $update = 'UPDATE dbfactor SET status=?,dateresponse=?,timeresponse=?,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
        $qupdate = $conn->prepare($update);
        $qupdate->execute(['عدم تایید', $datenow, $timenow, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $_GET['unverify']]);
        if (ActivatePublic('actsms_success_card')) {
            //Start send sms
            $sel = 'SELECT * FROM dbfactor WHERE idfactor=?';
            $qsel = $conn->prepare($sel);
            $qsel->execute([$_GET['unverify']]);
            $ret = $qsel->fetch();
            if ($ret['useradder'] != NULL) {
                $user = $ret['useradder'];
                $person = retPerson($user);
                $tel = $person->tel1;
            } else {
                $user = $ret['username'];
                $person = retPerson($user);
                $tel = $person->tel1;
            }
            $text = number_format($ret['factorfee']) . ';' . $_GET['unverify'];
            SendSms($tel, $text, 171703, $user);
            // end send SMS
        }
        header('location: SearchBillView.php');
        die();
    }
}
if (isset($_GET['cancel']) and $_GET['cancel'] != '') {
    $factor = queryFactor($_GET['cancel']);
    if ($factor->status != 'موفق') {
        $_SESSION['cancel'] = 'این صورتحساب پرداخت نشده است و امکان استرداد وجود ندارد';
        header('location: SearchBillView.php');
    } else {
        $factor_total = $factor->wallet + $factor->factorfee;
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        $upfac = 'UPDATE dbfactor SET status=? ,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=?';
        $qfact = $conn->prepare($upfac);
        $qfact->execute(['مسترد شده', $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os, $_GET['cancel']]);
        //
        $delcourse = 'UPDATE  dbcourse SET activityuser=? WHERE numfactor=?';
        $qdc = $conn->prepare($delcourse);
        $qdc->execute([0, $_GET['cancel']]);
        //Quiz cancel
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        $tyu = 'SELECT * FROM dbcourse WHERE numfactor=?';
        $swe = $conn->prepare($tyu);
        $swe->execute([$_GET['cancel']]);
        $rev = $swe->fetchAll(PDO::FETCH_OBJ);
        foreach ($rev as $track) {
            $ugid_parent = retIdGroup($track->codecourse);
            $uid = retIdStudent($track->username);
            $delquiz = 'DELETE FROM `_ugroups_umembers` WHERE ugid_parent=? AND uid=? ';
            $qaz = $ponn->prepare($delquiz);
            $qaz->execute([$ugid_parent, $uid]);
        }


        //
        if ($factor->useradder != NULL) {
            $user = $factor->useradder;
            $type = 'همکار آموزشی';
        } else {
            $user = $factor->username;
            $type = 'دانش آموز';
        }
        $detail = 'افزایش موجودی بواسطه استرداد فاکتور: ' . $_GET['cancel'];
        $charge = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $qch = $conn->prepare($charge);
        $qch->execute([$user, $factor_total, 'موفق', $datenow, $timenow, $detail, 1, $_GET['cancel'], $ip, $os, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
        //
        $_SESSION['cancel'] = 'فاکتور ' . $_GET['cancel'] . ' با موفقیت مسترد شد ';
        $_SESSION['wallet'] = ' مبلغ ' . number_format($factor_total) . ' تومان با موفقیت به کیف پول ' . $type . 'افزوده شد';
    }
    header('location: SearchBillView.php');
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
                <?php
                if (Permision('permision_bill')) { ?>

                    <!-- Row -->
                    <div class="row row-sm">
                        <?php if (isset($call) and $call === 1) { ?>
                            <div class="alert alert-outline-success  text-center " role="alert">
                                <div class="mg-t-10">
                                    <img style="width: 75px; height: 75px;"
                                         src="../../assets/img/call1.gif">در حال برقراری ارتباط

                                    با <?php echo $name ?>

                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                            type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>

                            </div>
                        <?php } ?>
                        <?php if (isset($id)) { ?>
                            <div class="alert alert-warning" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                فاکتور شماره : <?php echo $id ?> با موفقیت حذف شد
                            </div>
                        <?php } ?>
                        <?php if (isset($cancel)) { ?>
                            <div class="alert alert-warning" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?php echo $cancel ?>
                            </div>
                        <?php } ?>
                        <?php if (isset($wal)) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?php echo $wal ?>
                            </div>
                        <?php } ?>
                        <?php if (isset($fee)) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                مبلغ <?php echo number_format($fee); ?> تومان با موفقیت به کیف پول " <?php echo $name ?>
                                " افزوده شد .
                            </div>
                        <?php } ?>
                        <?php if (Permision('permision_viewwallet')) { ?>
                            <div class="col-12">
                                <div class="card custom-card">
                                    <div class="card-body py-3">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                                            <div>
                                                <h6 class="main-content-label mb-1">مدیریت کیف پول</h6>
                                                <p class="text-muted mb-0">برای نمایش ابزارهای کیف پول، دکمه را انتخاب کنید.</p>
                                            </div>
                                            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#wallet-management-actions" aria-expanded="false"
                                                    aria-controls="wallet-management-actions">
                                                <i class="si si-wallet ml-1"></i> مدیریت کیف پول
                                                <i class="fe fe-chevron-down mr-1"></i>
                                            </button>
                                        </div>
                                        <div class="collapse mt-3" id="wallet-management-actions">
                                            <div class="d-flex flex-wrap" style="gap: 10px;">
                                                <a class="btn btn-primary" href="<?php echo url('admin/wallet/dashboard.php') ?>">
                                                    <i class="fe fe-grid ml-1"></i> داشبورد کیف پول
                                                </a>
                                                <a class="btn btn-outline-primary" href="<?php echo url('admin/wallet/exchange.php') ?>">
                                                    <i class="fe fe-plus-circle ml-1"></i> افزودن / کاهش اعتبار
                                                </a>
                                                <a class="btn btn-outline-primary" href="<?php echo url('admin/wallet/searchwalletview.php') ?>">
                                                    <i class="fe fe-list ml-1"></i> گزارش تراکنش‌ها
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">آخرین صورتحساب‌های موفق</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش آخرین صورتحساب‌های موفق و تأییدشده نمایش داده می‌شوند. این لیست برای بررسی تراکنش‌های مالی نهایی‌شده و مشاهده سوابق پرداخت‌های موفق کاربران مورد استفاده قرار می‌گیرد."
                                                      aria-label="راهنمای آخرین صورتحساب‌های موفق"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <p class="text-muted card-sub-title">
                                                ۱۵ صورتحساب جدید تأییدشده

                                            </p>
                                        </div>
                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>اقدامات</th>
                                                <th>ش.فاکتور</th>
                                                <th>نام</th>
                                                <th>مبلغ</th>
                                                <th>تاریخ تایید</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sql = 'SELECT * FROM dbfactor WHERE status=?  ORDER BY dateresponse DESC ,`idfactor` DESC LIMIT 15 ';
                                            $query = $conn->prepare($sql);
                                            $query->execute(['موفق']);
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
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $res->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
                                                                            </div>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->idfactor ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با دانش آموز
                                                                            </a>
                                                                            <div class="dropdown-item "
                                                                                 onclick="window.open('transfer.php?order='+'<?php echo $res->idfactor ?>','جابجایی پایه'
                                                                                         ,'width=1000,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-shuffle"></i>جابجایی پایه
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('listTransaction.php?id='+'<?php echo $res->idfactor ?>','تراکنش فاکتور'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-list"></i>
                                                                                تراکنش های فاکتور
                                                                            </div>


                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?cancel=<?php echo $res->idfactor ?>"
                                                                               onclick="return confirm('آیا با استرداد این فاکتور موافقید؟')"
                                                                            ><i class="fe fe-x-square"></i> انصراف از
                                                                                پایه ها
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="wd-20">
                                                        <?php echo $res->idfactor ?></th>
                                                        <td><?php $fullname = retPerson($res->username);
                                                            echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                        <td class="wd-20"> <?php $total = $res->factorfee + $res->wallet;
                                                            echo number_format($total) ?> تومان
                                                        </td>

                                                        <?php
                                                        $dateno = $res->dateresponse;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>


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
                        <div class="col-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">جستجوی صورتحساب</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید صورتحساب‌های ثبت‌شده در سامانه را جستجو و بررسی کنید. با وارد کردن اطلاعات موردنظر، امکان یافتن صورتحساب مرتبط و مشاهده جزئیات تراکنش و وضعیت پرداخت آن فراهم می‌شود."
                                                      aria-label="راهنمای جستجوی صورتحساب"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <p class="text-muted card-sub-title">یافتن صورتحساب
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="main-header-center">

                                            <div class="input-group">
                                                <div class="input-group-btn search-panel">
                                                    <select class="form-control select2" name="category" required>
                                                        <option label="دسته بندی ها">
                                                        </option>
                                                        <option value="01">
                                                            کد ملی دانش آموز
                                                        </option>
                                                        <option value="02">
                                                            شماره صورتحساب
                                                        </option>
                                                        <option value="03">
                                                            تاریخ تایید صورتحساب
                                                        </option>
                                                        <option value="04">
                                                            نام
                                                        </option>
                                                        <option value="05">
                                                            نام خانوادگی
                                                        </option>
                                                        <option value="06">
                                                            کد ملی همکار
                                                        </option>

                                                    </select>
                                                </div>
                                                <input type="text" name="txt" class="form-control"
                                                       placeholder="جستجوی صورتحساب" required>
                                                <button class="btn search-btn"><i class="fe fe-search"></i></button>
                                            </div>
                                            <div class="main-header-right">
                                                <div class="dropdown header-search">
                                                    <button type="submit" name="search"
                                                            class="nav-link icon header-search">
                                                        <i class="fe fe-search header-icons"></i>
                                                    </button>
                                                </div>
                                            </div>

                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>اقدامات</th>
                                                <th>ش.فاکتور</th>
                                                <th>نام</th>
                                                <th>مبلغ</th>
                                                <th>وضعیت</th>

                                            </tr>
                                            </thead>

                                            <tbody>
                                            <?php
                                            if (isset($recive)) {
                                                if ($wer->rowCount() > 0) {

                                                    foreach ($recive as $ret) {
                                                        ?>
                                                        <tr>
                                                            <?php
                                                            switch ($ret->status) {
                                                                case 'موفق':
                                                                    $badge = 'badge-success';
                                                                    $str = 1;
                                                                    break;
                                                                case 'عدم تایید':
                                                                    $badge = 'badge-danger';
                                                                    $str = 2;
                                                                    break;
                                                                case 'در انتظار بررسی':
                                                                    $badge = 'badge-warning';
                                                                    $str = 3;
                                                                    break;
                                                                case 'ناموفق':
                                                                    $badge = 'badge-warning';
                                                                    $str = 2;
                                                                    break;
                                                                case 'منتظر پرداخت':
                                                                    $badge = 'badge-warning';
                                                                    $str = 2;
                                                                    break;
                                                                case 'مسترد شده':
                                                                    $badge = 'badge-warning';
                                                                    $str = 2;
                                                                    break;
                                                                case 'نیازمند تایید مدیر':
                                                                    $badge = 'badge-secondary';
                                                                    $str = 5;
                                                                    break;
                                                            }
                                                            ?>
                                                            <td class="wd-20">
                                                                <?php if ($str == 1) { ?>
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false"
                                                                                        aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown"
                                                                                        type="button">
                                                                                    اقدام <i
                                                                                            class="fas fa-caret-down mr-1"></i>
                                                                                </button>
                                                                                <div class="dropdown-menu tx-8">
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('viewfactor.php?id='+'<?php echo $ret->idfactor ?>','مشاهده فاکتور'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-search"></i>
                                                                                        مشاهده فاکتور
                                                                                    </div>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call=<?php echo $ret->idfactor ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با
                                                                                        صادرکننده
                                                                                    </a>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-student=<?php echo $ret->username ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با دانش آموز
                                                                                    </a>
                                                                                    <div class="dropdown-item "
                                                                                         onclick="window.open('transfer.php?order='+'<?php echo $ret->idfactor ?>','جابجایی پایه'
                                                                                                 ,'width=1000,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                                    ><i class="fe fe-shuffle"></i>جابجایی
                                                                                        پایه
                                                                                    </div>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('listTransaction.php?id='+'<?php echo $ret->idfactor ?>','تراکنش فاکتور'
                                                                                                 ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-list"></i>
                                                                                        تراکنش های فاکتور
                                                                                    </div>


                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                       href="?cancel=<?php echo $ret->idfactor ?>"
                                                                                       onclick="return confirm('آیا با استرداد این فاکتور موافقید؟')"
                                                                                    ><i class="fe fe-x-square"></i>
                                                                                        انصراف از
                                                                                        پایه ها
                                                                                    </a>


                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php }
                                                                if ($str == 2) { ?>
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false"
                                                                                        aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown"
                                                                                        type="button">
                                                                                    اقدام <i
                                                                                            class="fas fa-caret-down mr-1"></i>
                                                                                </button>
                                                                                <div class="dropdown-menu tx-13">
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call=<?php echo $ret->idfactor ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با
                                                                                        صادرکننده
                                                                                    </a>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-student=<?php echo $ret->username ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با دانش آموز
                                                                                    </a>
                                                                                    <div class="dropdown-item text-success "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('include-deposit.php?factor='+'<?php echo $ret->idfactor ?>','ثبت و تایید واریزی'
                                                                                                 ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )"
                                                                                    ><i class="fe fe-check-square"></i>
                                                                                        ثبت و
                                                                                        تایید واریزی
                                                                                    </div>
                                                                                    <div class="dropdown-item text-warning "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('editfactor.php?id='+'<?php echo $ret->idfactor ?>','تغییر وضعیت'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-edit"></i>
                                                                                        ویرایش فاکتور
                                                                                    </div>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('viewfactor.php?id='+'<?php echo $ret->idfactor ?>','مشاهده فاکتور'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-search"></i>
                                                                                        مشاهده فاکتور
                                                                                    </div>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('listTransaction.php?id='+'<?php echo $ret->idfactor ?>','تراکنش فاکتور'
                                                                                                 ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-list"></i>
                                                                                        تراکنش های فاکتور
                                                                                    </div>
                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                       href="?delfactor=<?php echo $ret->idfactor ?>"
                                                                                       onclick="return confirm('آیا با حذف این فاکتور موافقید؟')"
                                                                                    ><i class="fe fe-x-square"></i>
                                                                                        حذف فاکتور </a>


                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php }
                                                                if ($str == 3) { ?>
                                                                    <div class="text-wrap">
                                                                        <div class="text-center">
                                                                            <div class="dropdown">
                                                                                <button aria-expanded="false"
                                                                                        aria-haspopup="true"
                                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                        data-bs-toggle="dropdown"
                                                                                        type="button">
                                                                                    اقدام <i
                                                                                            class="fas fa-caret-down mr-1"></i>
                                                                                </button>
                                                                                <div class="dropdown-menu tx-8">
                                                                                    <div class="dropdown-item text-success "
                                                                                         onclick="window.open('ActivationBill.php?factor='+'<?php echo $ret->idfactor ?>','تغییر وضعیت'
                                                                                                 ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-check-square"></i>
                                                                                        تایید
                                                                                        واریزی
                                                                                    </div>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call=<?php echo $ret->idfactor ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با
                                                                                        صادرکننده
                                                                                    </a>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-student=<?php echo $ret->username ?>"
                                                                                    ><i class="fe fe-phone-forwarded"></i>
                                                                                        تماس
                                                                                        با دانش آموز
                                                                                    </a>

                                                                                    <div class="dropdown-item  "
                                                                                         onclick="window.open('Contradictory-deposit.php?factor='+'<?php echo $ret->idfactor ?>',' فاکتور متناقض'
                                                                                                 ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-shuffle"></i>
                                                                                        واریزی
                                                                                        متناقض
                                                                                    </div>

                                                                                    <div class="dropdown-item text-warning "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('editfactor.php?id='+'<?php echo $ret->idfactor ?>','تغییر وضعیت'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-edit"></i>
                                                                                        ویرایش فاکتور
                                                                                    </div>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('viewfactor.php?id='+'<?php echo $ret->idfactor ?>','مشاهده فاکتور'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-search"></i>
                                                                                        مشاهده فاکتور
                                                                                    </div>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('listTransaction.php?id='+'<?php echo $ret->idfactor ?>','تراکنش فاکتور'
                                                                                                 ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-list"></i>
                                                                                        تراکنش های فاکتور
                                                                                    </div>
                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                       href="?unverify=<?php echo $ret->idfactor ?>"
                                                                                       onclick="return confirm('آیا با عدم تایید این فاکتور موافقید؟')"
                                                                                    ><i class="fe fe-x-square"></i> عدم
                                                                                        تایید
                                                                                    </a>


                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php } ?>


                                                            </td>
                                                            <td class="wd-20"><?php echo $ret->idfactor ?></td>
                                                            <td><?php $fullname = retPerson($ret->username);
                                                                echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                            <td class="wd-20"> <?php $total = $ret->factorfee + $ret->wallet;
                                                                echo number_format($total) ?> تومان
                                                            </td>
                                                            <td class="wd-20">

                                                                <span class="badge <?php echo $badge ?>"> <?php echo $ret->status ?></span>
                                                            </td>

                                                        </tr>
                                                        <?php
                                                    }
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
                    <!-- End Row -->
                <?php } else { ?>
                    <div class="card-body">

                        <div class="alert alert-danger" role="alert">
                            <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                            شما مجوز لازم برای مشاهده این بخش را ندارید، با مدیر سامانه تماس بگیرید
                        </div>
                        <div style="text-align: center; padding-top: 20px">
                            <img src="../../assets/img/media/alert.png">

                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
