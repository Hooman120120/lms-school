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
if (!Permision('permision_refer')) {
    header('location:' . url("admin/index.php"));
    die();
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $ret = retPerson($_GET['call']);
    $tel = $ret->tel1;
    $name =  $ret->fname . ' ' . $ret->lname;
    Call($tel, intval($_SESSION['admin']['dakheli']), $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: Refer.php');
    die();
}
if (isset($_GET['close']) and $_GET['close'] != '') {
    $ticketid = intval($_GET['close']);
    $update = 'UPDATE ticket_topic SET ticket_status=?,metod=? WHERE ticket_num=?';
    $act = $conn->prepare($update);
    $act->execute(['بسته شده',3, $ticketid]);
    header('location: Refer.php');
    die();
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> منتظر ارجاع | <?php echo __TITLE ?></title>
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
                                <div class="row">
                                    <div class="col-10">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">لیست تیکت‌های منتظر ارجاع</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تیکت‌هایی نمایش داده می‌شوند که هنوز به هیچ کاربری ارجاع داده نشده‌اند. برای تسریع در روند رسیدگی، لازم است شخص یا واحد مناسب را برای پاسخگویی انتخاب و تیکت را به او ارجاع دهید. پس از ارجاع، مسئولیت بررسی و پاسخگویی به تیکت بر عهده فرد انتخاب‌شده خواهد بود."
                                                      aria-label="راهنمای لیست تیکت‌های منتظر ارجاع"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                لطفاً جهت تسریع در روند پاسخگویی، فرد مورد نظر برای پاسخگویی را انتخاب کنید.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-2 text-left">
                                        <button class="btn btn-sm btn-primary" onclick="window.location.reload()"><i
                                                    class="fe fe-refresh-cw"></i> بارگذاری مجدد
                                        </button>
                                    </div>

                                </div>

                            <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                <thead>

                                <tr>
                                    <th>شماره تیکت</th>
                                    <th>نام</th>
                                    <th>ارتباط با</th>
                                    <th>موضوع</th>
                                    <th>اولویت</th>
                                    <th>اقدامات</th>

                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sql = 'SELECT * FROM ticket_topic WHERE metod=?';
                                $ququ = $conn->prepare($sql);
                                $ququ->execute([0]);
                                $retur = $ququ->fetchAll(PDO::FETCH_OBJ);
                                if ($ququ->rowCount() > 0) {

                                    foreach ($retur

                                             as $gmc) {
                                        $ret = retPerson($gmc->username);
                                        switch ($gmc->ticket_section) {
                                            case '1':
                                                $section = __MOSHAVER;
                                                break;
                                            case '2':
                                                $section = __LOGINROOM;
                                                break;
                                            case '3':
                                                $section = __OFFICIAL;
                                                break;
                                            case '4':
                                                $section = __TECHER;
                                                break;
                                            case '5':
                                                $section = __QUIZ;
                                                break;
                                            case '6':
                                                $section = __MAINTENANCE;
                                                break;
                                            case '7':
                                                $section = __BAZRESI;
                                                break;
                                            case '8':
                                                $section = __BOSS;
                                                break;
                                        }

                                        ?>
                                        <tr>
                                            <td><?php echo $gmc->ticket_num ?></td>
                                            <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                            <td><?php echo $section ?></td>
                                            <td><?php echo $gmc->ticket_title ?></td>
                                            <td><?php echo $gmc->ticket_pririoty ?></td>
                                            <td>
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
                                                                <div class="dropdown-item text-success "
                                                                     onclick="window.open('Refer-forward.php?ticket='+'<?php echo $gmc->ticket_num ?>','مشاهده و ارجاع'
                                                                             ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                    <i class="fe fe-corner-up-right"></i> مشاهده و ارجاع
                                                                </div>
                                                                <a class="dropdown-item"
                                                                   href="?call=<?php echo $gmc->username ?>"
                                                                ><i class="fe fe-phone-outgoing"></i> تماس
                                                                    با
                                                                    صادرکننده
                                                                </a>
                                                                <div class="dropdown-item "
                                                                     onclick="window.open('Contradictory-wallet.php?order='+'<?php echo $gmc->ticket_num ?>','تایید واریزی'
                                                                             ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                ><i class="fe fe-italic"></i> پاسخ به تیکت
                                                                </div>
                                                                <div class="dropdown-item "
                                                                     onclick="window.open('faq.php','سوالات متداول'
                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                ><i class="fe fe-help-circle"></i>     مشاهده سوالات متداول
                                                                </div>
                                                                <hr>
                                                                <a class="dropdown-item text-danger "
                                                                   href="?close=<?php echo $gmc->ticket_num ?>"
                                                                   onclick="return confirm('آیا با بستن این تیکت موافقید؟')"
                                                                ><i class="fe fe-x-square"></i> بستن تیکت
                                                                </a>


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
    </div>
</div>
<!-- End Row -->


<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>
</html>