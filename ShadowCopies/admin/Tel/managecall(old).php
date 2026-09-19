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
if (!Permision('permision_managecall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM tel_db WHERE id =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);

            break;
        case '02':
            $swr = 'SELECT * FROM tel_db WHERE dakheli =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
    }
}
if(isset($_GET['delgroup'])){
    $id=intval($_GET['delgroup']);
    $desql='DELETE FROM tel_db WHERE `group`=?';
    $qsel=$conn->prepare($desql);
    $qsel->execute([$id]);
    header('location: managecall.php');
}
if(isset($_GET['deluser'])){
    $id=intval($_GET['deluser']);
    $desql='DELETE FROM tel_db WHERE `id`=?';
    $qsel=$conn->prepare($desql);
    $qsel->execute([$id]);
    header('location: managecall.php');
}
if (isset($_GET['call-mob1'])) {
    $call = retTelDb($_GET['call-mob1']);
    if ($call) {
        call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob1'],
            "box" => 'mob1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-mob2'])) {
    $call = retTelDb($_GET['call-mob2']);
    if ($call) {
        call($call['mob2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob2'],
            "box" => 'mob2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-mob3'])) {
    $call = retTelDb($_GET['call-mob3']);
    if ($call) {
        call($call['mob3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob3'],
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "box" => 'mob3',
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static1'])) {
    $call = retTelDb($_GET['call-static1']);
    if ($call) {
        call($call['tel1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static1'],
            "box" => 'static1',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static2'])) {
    $call = retTelDb($_GET['call-static2']);
    if ($call) {
        call($call['tel2'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static2'],
            "box" => 'static2',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();
    }
}
if (isset($_GET['call-static3'])) {
    $call = retTelDb($_GET['call-static3']);
    if ($call) {
        call($call['tel3'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-static3'],
            "box" => 'static3',
            "group" => $call['group'],
            "state" => $call['state'],
            "city" => $call['city'],
            "act" => $call['act'],
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        header('location: ' . url('admin/Tel/calling.php'));
        die();

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
    <title> مدیریت تماس امن |<?php echo __TITLE ?></title>
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
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">آمار کلی تماس ها</h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>گزارشات اکسل</th>
                                            <th>گروه مخاطبان</th>
                                            <th>کل مخاطبان</th>
                                            <th>گرفته نشده</th>
                                            <th>عدم پاسخ</th>
                                            <th>تماس مجدد</th>
                                            <th>خاتمه یافته</th>
                                            <th>تکراری</th>
                                            <th> اشتباه</th>
                                            <th>حذف </th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM tel_group ';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $res) {


                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown"
                                                                            type="button">
                                                                        انتخاب گزارش <i
                                                                                class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-13">
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?gexportAll=<?php echo $res->id ?>" target="_blank"
                                                                        ><i class="fe fe-phone"></i>
                                                                            کل مخاطبان
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?guncall=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-outgoing"></i>
                                                                            گرفته نشده
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?gbegin=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-call"></i>
                                                                            تماس مجدد
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?gunres=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-missed"></i>
                                                                           عدم پاسخ
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?gfinish=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-missed"></i>
                                                                            خاتمه یافته
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?gdublicate=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-off"></i>
                                                                            مخاطبین تکراری
                                                                        </a>
                                                                        <a class="dropdown-item "
                                                                           href="exportTel.php?gwrong=<?php echo $res->id ?>"
                                                                        ><i class="fe fe-phone-off"></i>
                                                                            شماره اشتباه
                                                                        </a>




                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->namegroup ?></td>
                                                    <td><?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE `group`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([$res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?></td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([0, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([5, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([1, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([2, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `group`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([4, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=?  AND `group`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([3, $res->id]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td><a href="?delgroup=<?php echo $res->id ?>" class="btn btn-sm btn-danger"  onclick="return confirm('درصورت حذف همه اطلاعات و گزارشات این گروه حذف خواهد شد')"><i class="fe fe-x"></i> </a> </td>
                                                </tr>
                                                <?php
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
                                        <h6 class="main-content-label mb-1">آمار انفرادی تماس ها</h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>گزارشات اکسل</th>
                                            <th>نام | داخلی</th>
                                            <th>کل مخاطبان</th>
                                            <th>گرفته نشده</th>
                                            <th> عدم پاسخ</th>
                                            <th>تماس مجدد</th>
                                            <th>خاتمه یافته</th>
                                            <th>مخاطبین تکراری</th>
                                            <th>شماره اشتباه</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbadmin ORDER BY dakheli ASC';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $res) {


                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false"
                                                                            aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown"
                                                                            type="button">
                                                                        انتخاب گزارش <i
                                                                                class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-13">
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?exportAll=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone"></i>
                                                                            کل مخاطبان
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?uncall=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-outgoing"></i>
                                                                            گرفته نشده
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?unres=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-outgoing"></i>
                                                                   عدم پاسخ
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?begin=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-call"></i>
                                                                            تماس مجدد
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?finish=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-missed"></i>
                                                                         خاتمه یافته
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?dublicate=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-off"></i>
                                                                            مخاطبین تکراری
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="exportTel.php?wrong=<?php echo $res->dakheli ?>"
                                                                        ><i class="fe fe-phone-off"></i>
                                                                       شماره اشتباه
                                                                        </a>




                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $res->fname . ' ' . $res->lname .' ( '.$res->dakheli .' )';?></td>
                                                    <td><?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([$res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?></td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([0, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([5, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([1, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([2, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND `dakheli`=? ';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([4, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=?  AND `dakheli`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([3, $res->dakheli]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php
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
                                <div class="col-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">جستجوی تلفن</h6>
                                        <p class="text-muted card-sub-title">یافتن تلفن جهت بررسی های بیشتر
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
                                                        ردیف تلفن
                                                    </option>
                                                    <option value="02">
                                                        داخلی پشتیبان
                                                    </option>


                                                </select>
                                            </div>
                                            <input type="text" name="txt" class="form-control"
                                                   placeholder="جستجوی مخاطب" required>
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


                                <div class="table pd-t-15">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>

                                            <th>ردیف</th>
                                            <th>نام</th>
                                            <th>گروه</th>
                                            <th>وضعیت</th>
                                            <th>پشتیبان</th>
                                            <th>اقدامات</th>
                                            <th>برقراری تماس</th>


                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (isset($recive)) {
                                            if ($wer->rowCount() > 0) {
                                                foreach ($recive as $ret) {
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ret->id ?></td>

                                                        <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                        <td><?php $group = retTelGroup($ret->group);
                                                            echo $group['namegroup']; ?></td>
                                                        <td><?php
                                                            switch ($ret->act) {
                                                                case '0':
                                                                    $act = 'تماس گرفته نشده ';
                                                                    break;
                                                                case '1':
                                                                    $act = 'نیاز به تماس مجدد';
                                                                    break;
                                                                case '2':
                                                                    $act = 'تماس پایان یافته';
                                                                    break;
                                                                case '3':
                                                                    $act = 'شماره اشتباه';
                                                                    break;
                                                                case '4':
                                                                    $act = 'شماره تکراری';
                                                                    break;
                                                                case '5':
                                                                    $act = 'عدم پاسخ';
                                                                    break;
                                                            }
                                                            echo $act;
                                                            ?></td>
                                                        <td><?php echo $ret->dakheli; ?></td>
                                                        <td>
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
                                                                            <div class="dropdown-item text-success "
                                                                                 onclick="window.open('de-view.php?id='+'<?php echo $ret->id ?>','مشاهده جزئیات مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-eye"></i>
                                                                            مشاهده جزئیات مخاطب
                                                                            </div>
                                                                            <div class="dropdown-item  "
                                                                                 onclick="window.open('re-view.php?id='+'<?php echo $ret->id ?>','مشاهده گزارش مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-bar-chart"></i>
                                                                                مشاهده گزارش اقدامات
                                                                            </div>
                                                                            <div class="dropdown-item text-warning "
                                                                                 onclick="window.open('de-edit.php?id='+'<?php echo $ret->id ?>','ویرایش جزئیات مخاطب'
                                                                                         ,'width=1000,height=500,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                              ویرایش جزئیات مخاطب
                                                                            </div>

                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?deluser=<?php echo $ret->id ?>"  onclick="return confirm('آیا با حذف این مخاطب موافقید؟')"
                                                                            ><i
                                                                                        class="fe fe-x-square"></i>
                                                                                حذف مخاطب </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            تماس با <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 1
                                                                            </a>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 2
                                                                            </a>

                                                                            <a class="dropdown-item  "
                                                                               href="?call-mob3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-call"></i>تماس
                                                                                موبایل 3
                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static1=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 1
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static2=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 2
                                                                            </a>
                                                                            <a class="dropdown-item text-info "
                                                                               href="?call-static3=<?php echo $ret->id ?>">
                                                                                <i class="fe fe-phone-outgoing"></i>تماس
                                                                                ثابت 3
                                                                            </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
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
        </div>
    </div>
</div>

<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>
</html>