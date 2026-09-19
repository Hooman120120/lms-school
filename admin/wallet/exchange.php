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
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
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
if (!Permision('permision_viewwallet')) {
    header('location:' . url("admin/index.php"));
    die();
}
if(isset($_SESSION['call'])){
    $call=$_SESSION['call'];
    unset($_SESSION['call']);
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM dbpersonal INNER JOIN  dblogin ON dblogin.username=dbpersonal.codemeli WHERE dbpersonal.codemeli=?  ';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '02':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM  dbpersonal  INNER JOIN dblogin ON dblogin.username=dbpersonal.codemeli WHERE   dbpersonal.fname LIKE :name ORDER BY iduser DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = '%' . $txt . '%';

            $swr = 'SELECT * FROM  dbpersonal  INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username WHERE   dbpersonal.lname LIKE :name ORDER BY iduser DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);

            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;


    }
}
if (isset($_GET['call']) and $_GET['call'] != '') {

    $qau = retPerson($_GET['call']);
    $tel = $qau->tel1;
    $name = $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] =  $name;

    header('location: exchange.php');
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
    <title> جستجوی کاربران | <?php echo __TITLE ?></title>
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
                <div class="col-12">
                    <?php if (isset($call)) { ?>
                        <div class="alert alert-outline-success  text-center " role="alert">
                            <div class="mg-t-10">
                                <img style="width: 75px; height: 75px;"
                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                با
                                <?php echo $call;

                                ?>

                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                        </div>
                    <?php } ?>

                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="col-10">
                                <div>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <h6 class="main-content-label mb-1">
                                            جستجوی کاربر برای افزایش یا کاهش موجودی
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید کاربر موردنظر را جستجو کرده و عملیات افزایش یا کاهش موجودی کیف پول او را انجام دهید. پس از انتخاب کاربر، امکان ثبت تغییرات اعتبار و مدیریت گردش مالی کیف پول فراهم می‌شود."
                                              aria-label="راهنمای افزایش یا کاهش موجودی کیف پول"
                                              onclick="event.preventDefault(); event.stopPropagation();">

            <i class="fe fe-info fs-4"></i>

        </span>
                                    </div>
                                    <p class="text-muted card-sub-title">
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
                                                    کد ملی
                                                </option>
                                                <option value="02">
                                                    نام
                                                </option>
                                                <option value="03">
                                                    نام خانوادگی
                                                </option>


                                            </select>
                                        </div>
                                        <input type="text" name="txt" class="form-control"
                                               placeholder="جستجوی کاربر" required>
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
                        <div class="card-body">
                            <div class="table">
                                <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                    <thead>
                                    <tr>
                                        <th>اقدامات</th>
                                        <th>کدملی</th>
                                        <th>نام</th>
                                        <th>استان</th>
                                        <th>شهر</th>
                                        <th>موجودی کیف پول</th>
                                        <th>نوع حساب</th>
                                        <th>تاریخ عضویت</th>


                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    if (isset($recive)) {
                                        if ($wer->rowCount() > 0) {
                                            foreach ($recive as $ret) {
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
                                                                        <div class="dropdown-item text-warning"
                                                                             style="text-align: right"
                                                                             onclick="window.open('withdraw.php?userId='+'<?php echo $ret->username ?>','افزایش یا کاهش'
                                                                                     ,'width=600,height=600,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-dollar-sign"></i>
                                                                            افزودن / کاهش
                                                                        </div>
                                                                        <a class="dropdown-item  "
                                                                           href="?call=<?php echo $ret->username ?>"
                                                                        ><i class="fe fe-phone-forwarded"></i> تماس
                                                                            با
                                                                            کاربر
                                                                        </a>
                                                                        <?php
                                                                        if($ret->role==1){
                                                                            ?>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('<?php echo url("admin/Student/viewProfile.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                         ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-eye"></i>
                                                                                مشاهده اطلاعات
                                                                            </div>
                                                                        <?php } else{?>

                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('<?php echo url("admin/Collague/viewCollague.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                         ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-eye"></i>
                                                                                مشاهده اطلاعات
                                                                            </div>
                                                                        <?php } ?>
                                                                        <div class="dropdown-item  "
                                                                             onclick="window.open('viewlisttransaction.php?id='+'<?php echo $ret->username ?>','مشاهده تراکنش ها'
                                                                                     ,'width=900,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-search"></i> مشاهده
                                                                            تراکنش ها

                                                                        </div>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="wd-10"><?php echo $ret->codemeli ?></td>
                                                    <td><?php echo htmlentities($ret->fname . ' ' . $ret->lname) ?></td>
                                                    <td class="wd-20"><?php echo $ret->state; ?></td>
                                                    <td class="wd-20"><?php echo $ret->city; ?></td>
                                                    <td class="wd-20"><?php echo number_format(totalWallet($ret->codemeli)); ?> تومان </td>
                                                    <td class="wd-20">
                                                        <?php if ($ret->role == 1) { ?>
                                                            دانش آموز
                                                        <?php } else { ?>
                                                            همکار آموزشی
                                                        <?php } ?>
                                                    </td>
                                                    <?php
                                                    $dateno = $ret->datecreated;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td class="wd-20"><?php echo $dateno ?></td>
                                                </tr>
                                            <?php }
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
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
