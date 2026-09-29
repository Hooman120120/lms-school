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
$reportScope = AdminScope('finance.advanced-report');
if ($reportScope === null) {
    http_response_code(403); exit('سطح مشاهدهٔ گزارش مالی برای شما فعال نشده است.');
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
date_default_timezone_set("Iran");
$dateno = date("Y-m-d");
$dateno = explode('-', $dateno);
$dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
$dateno = implode('/', $dateno);
if (isset($_POST['search'])) {
    $type = $_POST['typefactor'];
    $course = $_POST['course'];
    $start = $_POST['startdate'];
    $end = $_POST['enddate'];
    switch ($type) {
        case '1':
            $status = 'موفق';
            break;
        case '2':
            $status = 'ناموفق';
            break;
        case '3':
            $status = 'منتظر پرداخت';
            break;
        case '4':
            $status = 'در انتظار بررسی';
            break;
        case '5':
            $status = 'مسترد شده';
            break;

        case '6':
            $status = 'عدم تایید';
            break;
    }
    if ($type == '1') {
        $qdate = 'dateresponse';
    } else {
        $qdate = 'datecreate';
    }
    $startdate = $start;
    $startdate = explode('/', $startdate);
    $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2]);
    $startdate = implode('-', $startdate);
    //
    $enddate = $end;
    $enddate = explode('/', $enddate);
    $enddate = jalali_to_gregorian($enddate[0], $enddate[1], $enddate[2]);
    $enddate = implode('-', $enddate);
    if ($course == 1) {   //همه پایه ها
        $anycourse = 'SELECT * FROM dbfactor WHERE status=? AND ' . $qdate . ' BETWEEN ? AND ? ORDER BY ' . $qdate . ' DESC ';
        $quap = $conn->prepare($anycourse);
        $quap->execute([$status, $startdate, $enddate]);
        $rey = AdminFilterFinanceFactors($quap->fetchAll(PDO::FETCH_OBJ), 'finance.advanced-report');
    } else {
        $anycourse = 'SELECT * FROM dbfactor INNER JOIN dbcourse ON dbcourse.numfactor=dbfactor.idfactor WHERE status=? AND codecourse=? AND ' . $qdate . ' BETWEEN ? AND ? ORDER BY ' . $qdate . ' DESC ';
        $quap = $conn->prepare($anycourse);
        $quap->execute([$status, $course, $startdate, $enddate]);
        $rey = AdminFilterFinanceFactors($quap->fetchAll(PDO::FETCH_OBJ), 'finance.advanced-report');
    }
}
if (isset($_POST['sesave'])) {
    AdminRequire('finance.advanced-report.save-search');
    $type = $_POST['typefactor'];
    $course = $_POST['course'];
    $start = $_POST['startdate'];
    $end = $_POST['enddate'];
    $_SESSION['execel'] = array(
        "type" => $_POST['typefactor'],
        "course" => $_POST['course'],
        "start" => $_POST['startdate'],
        "end" => $_POST['enddate']
    );
    header('location:execel.php');
    exit;


}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
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
    header('location: advanced-report.php');
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
    header('location: advanced-report.php');
    die();
}

if (isset($_GET['unverify']) and $_GET['unverify'] != '') {
    AdminRequire('finance.advanced-report.manage');
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
        header('location: advanced-report.php');
        die();
    }
} // عدم تایید
if (isset($_GET['cancel']) and $_GET['cancel'] != '') {
    AdminRequire('finance.advanced-report.manage');
    $factor = queryFactor($_GET['cancel']);
    if ($factor->status != 'موفق') {
        $_SESSION['cancel'] = 'این صورتحساب پرداخت نشده است و امکان استرداد وجود ندارد';
        header('location: advanced-report.php');

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
        $_SESSION['wallet'] = ' مبلغ ' . number_format($factor_total) . ' تومان با موفقیت به کیف پول ' . $type . ' افزوده شد';
    }
    header('location: advanced-report.php');
} // استرداد فاکتور
if (isset($_GET['delfactor'])) {
    AdminRequire('finance.advanced-report.manage');
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
    <title> گزارش پیشرفته صورتحساب |<?php echo __TITLE ?></title>
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
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
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
                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">گزارش پیشرفته صورتحساب</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید گزارش‌های پیشرفته مربوط به صورتحساب‌ها را بر اساس اطلاعات و فیلترهای موردنظر تهیه کنید. با وارد کردن پارامترهای لازم، سامانه گزارش دقیق‌تری از وضعیت پرداخت‌ها، فاکتورها و تراکنش‌های مالی ارائه می‌دهد."
                                                  aria-label="راهنمای گزارش پیشرفته صورتحساب"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <p class="text-muted card-sub-title">لطفا اطلاعات خواسته شده را جهت تولید گزارش
                                            وارد کنید
                                        </p>
                                    </div>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-xl-3 mb-2">
                                            <label for="sens" class="form-label mg-t-15">وضعیت صورتحساب:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="typefactor"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <option value="1">1- موفق</option>
                                                <option value="2">2- ناموفق</option>
                                                <option value="3">3- منتظر پرداخت</option>
                                                <option value="4">4- در انتظار بررسی</option>
                                                <option value="5">5- مسترد شده</option>
                                                <option value="6">6- عدم تایید</option>
                                            </select>
                                        </div>
                                        <div class="col-xl-3 mb-2">
                                            <label for="sens" class="form-label mg-t-15">پایه مورد نظر:<span
                                                        style="color: red">*</span>
                                            </label>
                                            <select class="form-control select2 "
                                                    name="course"
                                                    aria-describedby="basic-addon2" required>
                                                <option label="اقدام لازم">
                                                </option>
                                                <option value="1">همه پایه ها</option>
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
                                        <div class="col-xl-3">
                                            <p>از تاریخ :<span
                                                        style="color: red">*</span></p>

                                            <div class="input-group ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fe fe-calendar lh--9 op-6"></i>
                                                    </div>
                                                </div>
                                                <input class="form-control fc-datepicker"
                                                       placeholder="1402/09/10" required
                                                       type="text" name="startdate">
                                            </div>
                                        </div>
                                        <div class="col-xl-3 ">
                                            <p>تا تاریخ :<span
                                                        style="color: red">*</span></p>

                                            <div class="input-group ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fe fe-calendar lh--9 op-6"></i>
                                                    </div>
                                                </div>
                                                <input class="form-control fc-datepicker"
                                                       value="<?php echo $dateno ?>" required
                                                       type="text" name="enddate">
                                            </div>
                                        </div>
                                    </div>
                                    <div class=" text-left">
                                        <button type="submit" name="search" class="btn btn-sm btn-primary"><i
                                                    class="fe fe-search"></i> جستجو
                                        </button>

                                        <button type="submit" name="sesave" class="btn btn-sm btn-success"><i
                                                    class="fe fe-save"></i> جستجو و دانلود اکسل
                                        </button>
                                    </div>


                                </form>


                            </div>
                        </div>
                        <?php if (isset($call) and $call === 1) { ?>
                            <div class="alert alert-outline-success  text-center " role="alert">
                                <div class="mg-t-10">
                                    <img style="width: 75px; height: 75px;"
                                         src="../../assets/img/call1.gif">در حال برقراری ارتباط

                                    با
                                    <?php echo $name ?>

                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                            type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>

                            </div>
                        <?php } ?>
                        <?php if (isset($rey)){ ?>
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="table">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>شمارگان</th>
                                            <th>ش.فاکتور</th>
                                            <th>نام</th>
                                            <th>وضعیت</th>
                                            <th>مبلغ</th>
                                            <th>تاریخ</th>
                                            <th>اقدامات</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if ($quap->rowCount() > 0) {
                                            $shomaregan = 1;
                                            foreach ($rey as $exp) { ?>

                                                <tr>
                                                    <?php
                                                    switch ($exp->status) {
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
                                                    }
                                                    ?>
                                                    <td class="wd-10"><?php echo $shomaregan ?></td>
                                                    <th scope="row" class="wd-10"><?php echo $exp->idfactor ?></th>
                                                    <td><?php $fullname = retPerson($exp->username);
                                                        echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                    <td class="wd-15">

                                                        <span class="badge <?php echo $badge ?>"> <?php echo $exp->status ?></span>
                                                    </td>
                                                    <td class="wd-20"> <?php $total = $exp->factorfee + $exp->wallet;
                                                        echo number_format($total) ?> تومان
                                                    </td>

                                                    <?php
                                                    if ($type == '1') {
                                                        $qdate = 'dateresponse';
                                                    } else {
                                                        $qdate = 'datecreate';
                                                    }
                                                    $dateno = $exp->$qdate;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td class="wd-15"><?php echo $dateno ?></td>

                                                    <td class="wd-10">

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
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $exp->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
                                                                            </div>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $exp->idfactor ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $exp->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با دانش آموز
                                                                            </a>
                                                                            <div class="dropdown-item "
                                                                                 onclick="window.open('transfer.php?order='+'<?php echo $exp->idfactor ?>','جابجایی پایه'
                                                                                         ,'width=1000,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-shuffle"></i>جابجایی
                                                                                پایه
                                                                            </div>


                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?cancel=<?php echo $exp->idfactor ?>"
                                                                               onclick="return confirm('آیا با انصراف کلی این فاکتور موافقید؟')"
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
                                                                               href="?call=<?php echo $exp->idfactor ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $exp->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با دانش آموز
                                                                            </a>
                                                                            <div class="dropdown-item text-success "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('include-deposit.php?factor='+'<?php echo $exp->idfactor ?>','ثبت و تایید واریزی'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-check-square"></i>
                                                                                ثبت و
                                                                                تایید واریزی
                                                                            </div>
                                                                            <div class="dropdown-item text-warning "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('editfactor.php?id='+'<?php echo $exp->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                                ویرایش فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $exp->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('listTransaction.php?id='+'<?php echo $exp->idfactor ?>','تراکنش فاکتور'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-list"></i>
                                                                                تراکنش های فاکتور
                                                                            </div>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?delfactor=<?php echo $exp->idfactor ?>"
                                                                            ><i
                                                                                        class="fe fe-x-square"></i>
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
                                                                                 onclick="window.open('ActivationBill.php?factor='+'<?php echo $exp->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-check-square"></i>
                                                                                تایید
                                                                                واریزی
                                                                            </div>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $exp->idfactor ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $exp->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>
                                                                                تماس
                                                                                با دانش آموز
                                                                            </a>

                                                                            <div class="dropdown-item  "
                                                                                 onclick="window.open('Contradictory-deposit.php?factor='+'<?php echo $exp->idfactor ?>',' فاکتور متناقض'
                                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-shuffle"></i>
                                                                                واریزی
                                                                                متناقض
                                                                            </div>

                                                                            <div class="dropdown-item text-warning "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('editfactor.php?id='+'<?php echo $exp->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                                ویرایش فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $exp->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('listTransaction.php?id='+'<?php echo $exp->idfactor ?>','تراکنش فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-list"></i>
                                                                                تراکنش های فاکتور
                                                                            </div>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?unverify=<?php echo $exp->idfactor ?>"
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


                                                </tr>
                                                <?php
                                                $shomaregan++;
                                            }

                                        }

                                        ?>
                                        </tbody>

                                    </table>
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</body>
</html>

