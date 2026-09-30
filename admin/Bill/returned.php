<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
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
//* End Check Session JUST ONE Session
$username = (string)($_SESSION['admin']['user'] ?? '');
$returnedScope = AdminScope('finance.returned');
if ($returnedScope === null) {
    http_response_code(403); exit('سطح مشاهدهٔ فاکتورهای مستردشده برای شما فعال نشده است.');
}
$canManageReturned = AdminCan('finance.returned.manage');
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_SESSION['message'])) {
    $id = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['fee'])) {
    $fee = $_SESSION['fee']['fee'];
    $name = $_SESSION['fee']['name'];
    unset($_SESSION['fee']);
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
    $ret = retPerson($user);
    $tel = $ret->tel1;
    $name = $semat . ' ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: returned.php');
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
    header('location: returned.php');
    die();
}
if (isset($_GET['delfactor'])) {
    $factorId = intval($_GET['delfactor']);
    if (!$canManageReturned || !AdminCanAccessFinanceFactor($factorId, 'finance.returned')) {
        http_response_code(403); exit('مجوز مدیریت این فاکتور فعال نشده است.');
    }
    $factor = queryFactor($factorId);
    if ($factor->status != 'موفق') {

        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        //
        $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
        $query_del = $conn->prepare($delfactor);
        $query_del->execute([$factorId]);
        $_SESSION['message'] = $factorId;
        header('location: returned.php');
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> فاکتورهای مسترد شده | <?php echo __TITLE ?></title>
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
                if (AdminCanSee('finance.returned')) { ?>


                    <!-- Row -->
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
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
                                    <?php if (isset($fee)) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            مبلغ <?php echo number_format($fee); ?> تومان با موفقیت به کیف پول
                                            " <?php echo $name ?> " افزوده شد .
                                        </div>
                                    <?php } ?>
                                    <div class="row">
                                        <div class="col-10">
                                            <div>
                                                <div style="display:flex; align-items:center; gap:12px;">
                                                    <h6 class="main-content-label mb-1">فاکتورهای مسترد شده</h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش فاکتورهایی نمایش داده می‌شوند که به دلیل انصراف دانش آموز از پایه، فرآیند استرداد برای آن‌ها انجام شده است. این لیست امکان بررسی سوابق فاکتورهای برگشت داده‌شده و پیگیری وضعیت انصراف از پایه را فراهم می‌کند."
                                                          aria-label="راهنمای فاکتورهای مسترد شده"
                                                          onclick="event.preventDefault(); event.stopPropagation();">

                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>
                                                </div>
                                                <p class="text-muted card-sub-title">فاکتورهای انصراف از پایه</p>
                                            </div>
                                        </div>
                                        <div class="col-2 text-left">
                                            <button class="btn btn-sm btn-primary" onclick="window.location.reload()"><i
                                                        class="fe fe-refresh-cw"></i> بارگذاری مجدد
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>ش.فاکتور</th>
                                                <th>نام دانش آموز</th>
                                                <th>مبلغ فاکتور</th>
                                                <th>زمان ایجاد</th>
                                                <th>اقدامات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            if ($adminRole == 2 && $returnedScope === 'all') {
                                                $sql = 'SELECT DISTINCT dbfactor.* FROM dbfactor INNER JOIN dbcourse ON dbfactor.idfactor = dbcourse.numfactor INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse WHERE codtbl_course.course_type = 2 AND dbfactor.status = ? ORDER BY dbfactor.idfactor DESC';
                                                $query = $conn->prepare($sql);
                                                $query->execute(['مسترد شده']);
                                            } else {
                                                $sql = 'SELECT * FROM dbfactor WHERE `status`=?';
                                                $params = ['مسترد شده'];
                                                if ($returnedScope === 'own') {
                                                    $sql .= ' AND (factoradder = ? OR useradder = ?)';
                                                    $params[] = $username;
                                                    $params[] = $username;
                                                }
                                                $sql .= ' ORDER BY `datecreate` DESC ';
                                                $query = $conn->prepare($sql);
                                                $query->execute($params);
                                            }
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $result = AdminFilterFinanceFactors($result, 'finance.returned');
                                            if ($query->rowCount() > 0) {
                                                foreach (
                                                    $result

                                                    as $res
                                                ) {

                                                    ?>
                                                    <tr>
                                                        <td class="wd-20"><?php echo $res->idfactor ?></td>
                                                        <td><?php $fullname = retPerson($res->username);
                                                            echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                        <td class="wd-20"> <?php echo number_format($res->factorfee) ?>
                                                            تومان
                                                        </td>

                                                        <?php
                                                        $dateno = $res->datecreate;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>
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
                                                                        <div class="dropdown-menu tx-13">
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->idfactor ?>"><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $res->username ?>"><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با دانش آموز
                                                                            </a>
                                                                            <?php if ($canManageReturned): ?>
                                                                            <div class="dropdown-item text-success "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('include-deposit.php?factor='+'<?php echo $res->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )"><i class="fe fe-check-square"></i> ثبت و
                                                                                تایید واریزی
                                                                            </div>
                                                                            <div class="dropdown-item text-warning "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('editfactor.php?id='+'<?php echo $res->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                                ویرایش فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $res->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
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
                                                                               href="?delfactor=<?php echo $res->idfactor ?>"
                                                                               onclick="return confirm('آیا با حذف این فاکتور موافقید؟')"><i
                                                                                        class="fe fe-x-square"></i>
                                                                                حذف فاکتور </a>
                                                                            <?php endif; ?>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </td>

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
