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
if (!Permision('permision_allticket')) {
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
    $name = $ret->fname . ' ' . $ret->lname;
    Call($tel, intval($_SESSION['admin']['dakheli']), $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: allticket.php');
    die();
}
if (isset($_GET['close']) and $_GET['close'] != '') {
    $ticketid = intval($_GET['close']);
    $update = 'UPDATE ticket_topic SET ticket_status=?,metod=? WHERE ticket_num=?';
    $act = $conn->prepare($update);
    $act->execute(['بسته شده', 3, $ticketid]);
    header('location: allticket.php');
    die();
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM ticket_topic WHERE ticket_num =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '02':
            $swr = 'SELECT * FROM ticket_topic WHERE username =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM ticket_topic INNER JOIN dbpersonal ON ticket_topic.username=dbpersonal.codemeli WHERE dbpersonal.fname LIKE :name ORDER BY ticket_num DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '04':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM ticket_topic INNER JOIN dbpersonal ON ticket_topic.username=dbpersonal.codemeli WHERE dbpersonal.lname LIKE :name ORDER BY ticket_num DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;

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
    <title> کارتابل همه تیکت | <?php echo __TITLE ?></title>
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
                <!--Row-->
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>
                                    </div>
                                    <div class="card-item-title mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1">تعداد کل تیکت
                                            ها </label>

                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as countin FROM ticket_topic ';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([]);
                                            $refer = $ququ->fetch();

                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['countin'] ?></h4>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body" style="border-radius: 10px;">
                                <div class="card-item">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>

                                    </div>
                                    <div class="card-item-title mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1"> منتظر
                                            پاسخ </label>

                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as count1 FROM ticket_topic WHERE metod=?';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([1]);
                                            $refer = $ququ->fetch();

                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['count1'] ?></h4>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body bg-warning" style="border-radius: 10px;">
                                <div class="card-item ">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>

                                    </div>
                                    <div class="card-item-title   mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1">منتظر
                                            ارجاع</label>

                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as count0 FROM ticket_topic WHERE metod=?';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([0]);
                                            $refer = $ququ->fetch();

                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['count0'] ?> </h4>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>

                                    </div>
                                    <div class="card-item-title  mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1"> اورژانسی</label>

                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as count4 FROM ticket_topic WHERE metod=?';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([4]);
                                            $refer = $ququ->fetch();

                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['count4'] ?></h4>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body bg-info" style="border-radius: 10px;">
                                <div class="card-item">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>

                                    </div>
                                    <div class="card-item-title  mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1">تیکت های
                                            باز</label>
                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as count2 FROM ticket_topic WHERE metod=?';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([2]);
                                            $refer = $ququ->fetch();

                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['count2'] ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-2">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-item">
                                    <div class="card-item-icon card-icon">
                                        <svg class="text-primary" xmlns="http://www.w3.org/2000/svg"
                                             enable-background="new 0 0 24 24" height="24" viewBox="0 0 24 24"
                                             width="24">
                                            <g>
                                                <rect height="14" opacity=".3" width="14" x="5" y="5"></rect>
                                                <g>
                                                    <rect fill="none" height="24" width="24"></rect>
                                                    <g>
                                                        <path d="M19,3H5C3.9,3,3,3.9,3,5v14c0,1.1,0.9,2,2,2h14c1.1,0,2-0.9,2-2V5C21,3.9,20.1,3,19,3z M19,19H5V5h14V19z"></path>
                                                        <rect height="5" width="2" x="7" y="12"></rect>
                                                        <rect height="10" width="2" x="15" y="7"></rect>
                                                        <rect height="3" width="2" x="11" y="14"></rect>
                                                        <rect height="2" width="2" x="11" y="10"></rect>
                                                    </g>
                                                </g>
                                            </g>
                                        </svg>

                                    </div>
                                    <div class="card-item-title  mb-2">
                                        <label class="main-content-label tx-13 font-weight-bold mb-1"> خاتمه
                                            یافته</label>

                                    </div>
                                    <div class="card-item-body">
                                        <div class="card-item-stat">
                                            <?php
                                            $sql = 'SELECT COUNT(*) as count3 FROM ticket_topic WHERE metod=?';
                                            $ququ = $conn->prepare($sql);
                                            $ququ->execute([3]);
                                            $refer = $ququ->fetch();
                                            ?>
                                            <h4 class="font-weight-bold"> <?php echo $refer['count3'] ?></h4>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--End row-->
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
                                            <h6 class="main-content-label mb-1">جستجوی تیکت</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید تیکت‌های ثبت‌شده را بر اساس معیارهای مختلف مانند شماره تیکت، دانش آموز، فرستنده، گیرنده، وضعیت یا سایر اطلاعات موجود جستجو کنید. این امکان به شما کمک می‌کند تا در کوتاه‌ترین زمان، تیکت موردنظر را پیدا کرده و روند رسیدگی یا پیگیری آن را ادامه دهید."
                                                  aria-label="راهنمای جستجوی تیکت"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>

                                        <p class="text-muted card-sub-title"></p>
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
                                                        شماره تیکت
                                                    </option>
                                                    <option value="02">
                                                        کد ملی
                                                    </option>
                                                    <option value="03">
                                                        نام
                                                    </option>
                                                    <option value="04">
                                                        نام خانوادگی
                                                    </option>


                                                </select>
                                            </div>
                                            <input type="text" name="txt" class="form-control"
                                                   placeholder="جستجوی تیکت" required>
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
                                            <th>ش.تیکت</th>
                                            <th>نام</th>
                                            <th>وضعیت</th>
                                            <th>پاسخ دهنده</th>
                                            <th>موضوع</th>

                                        </tr>
                                        </thead>

                                        <tbody>

                                        <?php
                                        if (isset($recive)) {
                                            if ($wer->rowCount() > 0) {
                                                foreach ($recive as $ret) {
                                                    ?>
                                                    <tr>

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
                                                                                 onclick="window.open('response.php?ticket='+'<?php echo $ret->ticket_num ?>','مشاهده و ارجاع'
                                                                                         ,'width=1300,height=800,menubar=0,toolbar=0,resizable=0,left=150,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit-2"></i> مشاهده و
                                                                                پاسخ
                                                                            </div>
                                                                            <a class="dropdown-item"
                                                                               href="?call=<?php echo $ret->username ?>"
                                                                            ><i class="fe fe-phone-outgoing"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>

                                                                            <div class="dropdown-item "
                                                                                 onclick="window.open('faq.php','سوالات متداول'
                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-help-circle"></i> مشاهده
                                                                                سوالات
                                                                                متداول
                                                                            </div>
                                                                            <?php
                                                                            if (Permision('permision_refer')) { ?>
                                                                            <div class="dropdown-item "
                                                                                 onclick="window.open('Refer-forward.php?ticket='+'<?php echo $ret->ticket_num ?>','سوالات متداول'
                                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                            ><i class="fe fe-corner-up-right"></i> ارجاع
                                                                                    به
                                                                                    فردی دیگر
                                                                                </div><?php } ?>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?close=<?php echo $ret->ticket_num ?>"
                                                                               onclick="return confirm('آیا با بستن این تیکت موافقید؟')"
                                                                            ><i class="fe fe-x-square"></i> بستن تیکت
                                                                            </a>

                                                        </td>
                                                        <td><?php echo $ret->ticket_num ?></td>
                                                        <td><?php $person = retPerson($ret->username);
                                                            echo $person->fname . ' ' . $person->lname; ?></td>
                                                        <td><?php echo $ret->ticket_status ?></td>
                                                        <td><?php if ($ret->responser != NULL) {
                                                                $ga = retAdmin($ret->responser);
                                                                echo $ga->fname . ' ' . $ga->lname;
                                                            } ?></td>
                                                        <td><?php echo htmlentities($ret->ticket_title) ?></td>

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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">آخرین تیکت های ثبت شده</h6>
                                        <p class="text-muted card-sub-title">30 تیکت آخر
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>اقدامات</th>
                                            <th>ش.تیکت</th>
                                            <th>نام</th>
                                            <th>وضعیت</th>
                                            <th>پاسخ دهنده</th>
                                            <th>ارتباط با</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM ticket_topic  ORDER BY `ticket_num`  DESC LIMIT 30 ';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result
                                                     as $res) {
                                                switch ($res->ticket_section) {
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
                                                                             onclick="window.open('response.php?ticket='+'<?php echo $res->ticket_num ?>','مشاهده و ارجاع'
                                                                                     ,'width=1300,height=800,menubar=0,toolbar=0,resizable=0,left=150,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-edit-2"></i> مشاهده و پاسخ
                                                                        </div>
                                                                        <a class="dropdown-item"
                                                                           href="?call=<?php echo $res->username ?>"
                                                                        ><i class="fe fe-phone-outgoing"></i> تماس
                                                                            با
                                                                            صادرکننده
                                                                        </a>

                                                                        <div class="dropdown-item "
                                                                             onclick="window.open('faq.php','سوالات متداول'
                                                                         ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                        ><i class="fe fe-help-circle"></i> مشاهده سوالات
                                                                            متداول
                                                                        </div>
                                                                        <?php
                                                                        if (Permision('permision_refer')) { ?>
                                                                        <div class="dropdown-item "
                                                                             onclick="window.open('Refer-forward.php?ticket='+'<?php echo $res->ticket_num ?>','سوالات متداول'
                                                                                     ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                                        ><i class="fe fe-corner-up-right"></i> ارجاع به
                                                                                فردی دیگر
                                                                            </div><?php } ?>
                                                                        <hr>
                                                                        <a class="dropdown-item text-danger "
                                                                           href="?close=<?php echo $res->ticket_num ?>"
                                                                           onclick="return confirm('آیا با بستن این تیکت موافقید؟')"
                                                                        ><i class="fe fe-x-square"></i> بستن تیکت
                                                                        </a>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <th scope="row"><?php echo $res->ticket_num ?></th>
                                                    <td><?php $fullname = retPerson($res->username);
                                                        echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                    <td> <?php echo $res->ticket_status ?>
                                                    </td>

                                                    <td>
                                                        <?php
                                                        if ($res->responser != NULL) {
                                                            $na = retAdmin($res->responser);

                                                            $fa = $na->fname . ' ' . $na->lname;
                                                        } else {
                                                            $fa = '';
                                                        }
                                                        echo $fa;
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlentities($res->ticket_title) ?></td>


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