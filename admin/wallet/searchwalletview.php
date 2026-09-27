<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (!inst_feature('wallet')) { http_response_code(404); exit('کیف پول در نسخه مدرسه‌ای فعال نیست.'); }
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
if (!Permision('permision_viewwallet')) {
    header('location:' . url("admin/index.php"));
    die();
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM db_wallet INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE db_wallet.username =? ORDER BY order_id DESC';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '02':
            $swr = 'SELECT * FROM db_wallet INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE order_id =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = explode('/', $txt);
            $txt = jalali_to_gregorian($txt[0], $txt[1], $txt[2]);
            $txt = implode('-', $txt);
            $swr = 'SELECT * FROM db_wallet INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE datecreted =? ORDER BY order_id DESC';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '04':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM db_wallet INNER JOIN dbpersonal ON db_wallet.username=dbpersonal.codemeli INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE dbpersonal.fname LIKE :name ORDER BY order_id DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '05':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM db_wallet INNER JOIN dbpersonal ON db_wallet.username=dbpersonal.codemeli INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE  dbpersonal.lname LIKE :famili ORDER BY order_id DESC';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':famili', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '06':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM db_wallet INNER JOIN dbpersonal ON db_wallet.username=dbpersonal.codemeli INNER JOIN dblogin ON db_wallet.username=dblogin.username WHERE  db_wallet.status LIKE :status ORDER BY order_id DESC';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':status', $txt, PDO::PARAM_STR);
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
    $_SESSION['call'] = $name;

    header('location: searchwalletview.php');
    die();
}
if (isset($_GET['changeverify'])) {
    $order = intval($_GET['changeverify']);
    $change = 'UPDATE db_wallet SET status=? WHERE order_id=?';
    $qchange = $conn->prepare($change);
    $qchange->execute(['موفق',$order]);
    header('location: searchwalletview.php');
    die();
}
if (isset($_GET['cancel'])) {
    $order = intval($_GET['cancel']);
    $del='DELETE FROM db_wallet WHERE order_id=?';
    $qdel=$conn->prepare($del);
    $qdel->execute([$order]);
    header('location: searchwalletview.php');
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
    <title>گزارش شارژحساب | <?php echo __TITLE ?></title>
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
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">
                                                جستجوی شارژ حساب
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید شارژهای ثبت‌شده حساب کاربران را جستجو و بررسی کنید. با وارد کردن اطلاعات موردنظر، امکان مشاهده جزئیات شارژ، وضعیت ثبت و سوابق مربوط به افزایش اعتبار حساب فراهم می‌شود."
                                                  aria-label="راهنمای جستجوی شارژ حساب"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>

                                            </span>
                                        </div>
                                        <p class="text-muted card-sub-title">یافتن شارژ
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
                                                        شماره سفارش
                                                    </option>
                                                    <option value="03">
                                                        تاریخ سفارش
                                                    </option>
                                                    <option value="04">
                                                        نام
                                                    </option>
                                                    <option value="05">
                                                        نام خانوادگی
                                                    </option>
                                                    <option value="06">
                                                        وضعیت
                                                    </option>


                                                </select>
                                            </div>
                                            <input type="text" name="txt" class="form-control"
                                                   placeholder="جستجوی شارژ" required>
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
                                            <th>ش.سفارش</th>
                                            <th>نام</th>
                                            <th>مبلغ</th>
                                            <th>تراکنش</th>
                                            <th>وضعیت</th>
                                            <th>نوع</th>
                                            <th>تاریخ</th>
                                            <th>توضیحات</th>

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
                                                        if ($ret->status == 'موفق') {
                                                            $badge = 'badge-success';
                                                            $text = 'موفق';
                                                            $sta = 1;
                                                        }
                                                        if ($ret->status == 'ناموفق') {
                                                            $badge = 'badge-danger';
                                                            $text = 'ناموفق';
                                                            $sta = 2;
                                                        }
                                                        if ($ret->status == 'رد شده') {
                                                            $badge = 'badge-danger';
                                                            $text = 'ناموفق';
                                                            $sta = 2;
                                                        }
                                                        if ($ret->status == 'در انتظار بررسی') {
                                                            $badge = 'badge-warning';
                                                            $text = 'در انتظار بررسی';
                                                            $sta = 3;
                                                        }
                                                        if ($ret->status == 'منتظر پرداخت') {
                                                            $badge = 'badge-warning';
                                                            $text = 'منتظر پرداخت';
                                                            $sta = 4;
                                                        }
                                                        ?>
                                                        <td class="wd-20">
                                                            <?php if ($sta != 1) { ?>
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
                                                                                <?php
                                                                                if ($ret->role == 1) {
                                                                                    ?>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('<?php echo url("admin/Student/viewProfile.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                                 ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-eye"></i>
                                                                                        مشاهده اطلاعات
                                                                                    </div>
                                                                                <?php } else { ?>

                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('<?php echo url("admin/Collague/viewCollague.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                                 ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-eye"></i>
                                                                                        مشاهده اطلاعات
                                                                                    </div>
                                                                                <?php } ?>
                                                                                <a class="dropdown-item  "
                                                                                   href="?call=<?php echo $ret->username ?>"
                                                                                ><i class="fe fe-phone-forwarded"></i>
                                                                                    تماس
                                                                                    با
                                                                                    صادرکننده
                                                                                </a>
                                                                                <a class="dropdown-item text-success  "
                                                                                   href="?changeverify=<?php echo $ret->order_id ?>"
                                                                                ><i class="fe fe-check"></i>تبدیل به
                                                                                    موفق
                                                                                </a>


                                                                                <hr>
                                                                                <a class="dropdown-item text-danger "
                                                                                   href="?cancel=<?php echo $ret->order_id ?>"
                                                                                   onclick="return confirm('آیا با حذف این تراکنش موافقید؟')"
                                                                                ><i class="fe fe-x-square"></i> حذف
                                                                                    تراکنش
                                                                                </a>


                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php } else { ?>
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
                                                                                <?php
                                                                                if ($ret->role == 1) {
                                                                                    ?>
                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('<?php echo url("admin/Student/viewProfile.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                                 ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-eye"></i>
                                                                                        مشاهده اطلاعات
                                                                                    </div>
                                                                                <?php } else { ?>

                                                                                    <div class="dropdown-item "
                                                                                         style="text-align: right"
                                                                                         onclick="window.open('<?php echo url("admin/Collague/viewCollague.php") ?>'+'?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                                 ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                        <i class="fe fe-eye"></i>
                                                                                        مشاهده اطلاعات
                                                                                    </div>
                                                                                <?php } ?>
                                                                                <a class="dropdown-item  "
                                                                                   href="?call=<?php echo $ret->username ?>"
                                                                                ><i class="fe fe-phone-forwarded"></i>
                                                                                    تماس
                                                                                    با
                                                                                    صادرکننده
                                                                                </a>


                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="wd-15"><?php echo $ret->order_id ?></td>
                                                        <td><?php $fullname = retPerson($ret->username);
                                                            echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                        <td class="wd-20"> <?php $total = $ret->fee;
                                                            echo number_format($total) ?> تومان
                                                        </td>
                                                        <td class="wd-20"><?php echo $ret->ref_id ?></td>

                                                        <td class="wd-15">
                                                            <span class="badge <?php echo $badge ?>"><?php echo $text ?></span>
                                                        </td>
                                                        <td class="wd-20"><?php if ($ret->mode == 1) {
                                                                echo '<i class="text-success fas fa-level-up-alt "></i>';
                                                            }
                                                            if ($ret->mode == 0) {
                                                                echo '<i class="text-danger fas fa-level-down-alt "></i>';
                                                            }

                                                            ?></td>
                                                        <?php
                                                        $dateno = $ret->datecreted;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>
                                                        <td class="wd-20"><?php echo $ret->detail_wallet ?></td>


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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">
                                                آخرین درخواست‌های شارژ
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش آخرین درخواست‌های شارژ حساب ثبت‌شده توسط کاربران نمایش داده می‌شود. این لیست برای بررسی درخواست‌های افزایش موجودی، مشاهده جزئیات شارژ و پیگیری وضعیت درخواست‌ها مورد استفاده قرار می‌گیرد."
                                                  aria-label="راهنمای آخرین درخواست‌های شارژ"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <p class="text-muted card-sub-title">
                                            ۵۰ درخواست شارژ حساب
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>اقدامات</th>
                                            <th>ش.سفارش</th>
                                            <th>نام</th>
                                            <th>مبلغ</th>
                                            <th>تراکنش</th>
                                            <th>وضعیت</th>
                                            <th>نوع</th>
                                            <th>تاریخ</th>
                                            <th>توضیحات</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM db_wallet INNER JOIN  dblogin ON db_wallet.username=dblogin.username ORDER BY datecreted DESC ,`order_id` DESC LIMIT 50 ';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result
                                                     as $res) {
                                                ?>
                                                <tr>
                                                    <?php
                                                    if ($res->status == 'موفق') {
                                                        $badge = 'badge-success';
                                                        $text = 'موفق';
                                                        $sta = 1;
                                                    }
                                                    if ($res->status == 'ناموفق') {
                                                        $badge = 'badge-danger';
                                                        $text = 'ناموفق';
                                                        $sta = 2;
                                                    }
                                                    if ($res->status == 'در انتظار بررسی') {
                                                        $badge = 'badge-warning';
                                                        $text = 'در انتظار بررسی';
                                                        $sta = 3;
                                                    }
                                                    if ($res->status == 'منتظر پرداخت') {
                                                        $badge = 'badge-warning';
                                                        $text = 'منتظر پرداخت';
                                                        $sta = 4;
                                                    }
                                                    if ($res->status == 'رد شده') {
                                                        $badge = 'badge-danger';
                                                        $text = 'ناموفق';
                                                        $sta = 2;
                                                    }
                                                    ?>
                                                    <td class="wd-20">
                                                        <?php if ($sta != 1) { ?>
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
                                                                            <?php
                                                                            if ($res->role == 1) {
                                                                                ?>
                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('<?php echo url("admin/Student/viewProfile.php") ?>'+'?code='+'<?php echo $res->username ?>','مشاهده اطلاعات'
                                                                                             ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-eye"></i>
                                                                                    مشاهده اطلاعات
                                                                                </div>
                                                                            <?php } else { ?>

                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('<?php echo url("admin/Collague/viewCollague.php") ?>'+'?code='+'<?php echo $res->username ?>','مشاهده اطلاعات'
                                                                                             ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-eye"></i>
                                                                                    مشاهده اطلاعات
                                                                                </div>
                                                                            <?php } ?>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <a class="dropdown-item text-success  "
                                                                               href="?changeverify=<?php echo $res->order_id ?>"
                                                                            ><i class="fe fe-check"></i>تبدیل به موفق
                                                                            </a>

                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?cancel=<?php echo $res->order_id ?>"
                                                                               onclick="return confirm('آیا با حذف این تراکنش موافقید؟')"
                                                                            ><i class="fe fe-x-square"></i> حذف تراکنش
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php } else { ?>
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
                                                                            <?php
                                                                            if ($res->role == 1) {
                                                                                ?>
                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('<?php echo url("admin/Student/viewProfile.php") ?>'+'?code='+'<?php echo $res->username ?>','مشاهده اطلاعات'
                                                                                             ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-eye"></i>
                                                                                    مشاهده اطلاعات
                                                                                </div>
                                                                            <?php } else { ?>

                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('<?php echo url("admin/Collague/viewCollague.php") ?>'+'?code='+'<?php echo $res->username ?>','مشاهده اطلاعات'
                                                                                             ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-eye"></i>
                                                                                    مشاهده اطلاعات
                                                                                </div>
                                                                            <?php } ?>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-20">
                                                        <?php echo $res->order_id ?></td>
                                                    <td><?php $fullname = retPerson($res->username);
                                                        echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                    <td class="wd-20"> <?php $total = $res->fee;
                                                        echo number_format($total) ?> تومان
                                                    </td>
                                                    <td class="wd-20"><?php echo $res->ref_id ?></td>

                                                    <td class="wd-20">
                                                        <span class="badge <?php echo $badge ?>"><?php echo $text ?></span>


                                                    </td>
                                                    <td class="wd-20"><?php if ($res->mode == 1) {
                                                            echo '<i class="text-success fas fa-level-up-alt "></i>';
                                                        }
                                                        if ($res->mode == 0) {
                                                            echo '<i class="text-danger fas fa-level-down-alt "></i>';
                                                        }

                                                        ?></td>


                                                    <?php
                                                    $dateno = $res->datecreted;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td class="wd-20"><?php echo $dateno ?></td>
                                                    <td class="wd-20"><?php echo $res->detail_wallet ?></td>


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
            </div>
        </div>
    </div>
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>


