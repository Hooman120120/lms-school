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
if (!Permision('permision_viewcall')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['search'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $_SESSION['uncall'] = $_POST['group'];
        header('location: uncall.php');
    }
}
if (isset($_GET['newgroup'])) {
    unset($_SESSION['uncall']);
    header('location: ' . url('admin/Tel/uncall.php'));
    die();
}
if (isset($_GET['call-mob1'])) {
    $call = retTelDb($_GET['call-mob1']);
    if ($call) {
       call($call['mob1'], $_SESSION['admin']['dakheli'], $call['fname'] . ' ' . $call['lname']);
        $_SESSION['act-call'] = array(
            "id" => $_GET['call-mob1'],
            "box" => 'mob1',
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
//        unset($_SESSION['uncall']);
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
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
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
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "box" => 'mob3',
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
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
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
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
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
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
            "group"=>$call['group'],
            "state"=>$call['state'],
            "city"=>$call['city'],
            "metod" => 0,
            "act" => 0,
            "fullname" => $call['fname'] . ' ' . $call['lname']
        );
        unset($_SESSION['uncall']);
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
    <title> تماس های گرفته نشده |<?php echo __TITLE ?></title>
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
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <h6 class="main-content-label mb-1">تماس های گرفته نشده</h6>
                                            <p class="text-muted card-sub-title">تماس های برقرار نشده تا کنون
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-3 text-left">
                                        <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                                    class="fe fe-refresh-cw"></i>گروه مخاطب جدید</a>
                                    </div>
                                </div>
                                <section class="section">
                                    <div class="row">
                                        <div class="card">
                                            <?php if (!isset($_SESSION['uncall'])) { ?>
                                                <form method="post" data-parsley-validate="">
                                                    <div class="row">
                                                        <div class="col-xl-6 mb-2">
                                                            <label for="sens" class="form-label mg-t-15">انتخاب گروه
                                                                مخاطبان
                                                                <span
                                                                        style="color: red">*</span></label>
                                                            <select class="form-control select2 "
                                                                    name="group"
                                                                    aria-describedby="basic-addon2" required>
                                                                <option label="گروه ">
                                                                </option>
                                                                <?php
                                                                $sql = 'SELECT * FROM tel_group ';
                                                                $query = $conn->prepare($sql);
                                                                $query->execute();
                                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                                if ($query->rowCount() > 0) {
                                                                    foreach ($result as $res) {


                                                                        ?>
                                                                        <option value="<?php echo $res->id ?>"><?php echo $res->namegroup ?></option>
                                                                    <?php }
                                                                }
                                                                ?>

                                                            </select>
                                                        </div>

                                                    </div>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">


                                                    <div class="text-center mg-10">
                                                        <button type="submit" class="btn btn-success "
                                                                name="search"><i
                                                                    class="fa fa-database"></i>
                                                            انتخاب گروه مخاطبین
                                                        </button>
                                                    </div>
                                                </form>
                                                <!--                                        end step 1-->
                                            <?php } else { ?>
                                                <?php $group = retTelGroup($_SESSION['uncall']); ?>
                                                <div class="header-message">نام گروه مخاطبین: <span
                                                            class="text-warning"><?php echo $group['namegroup'] ?></span>
                                                </div>
                                                <div class="header-message">
                                                    تعداد کل این مخاطبین  :
                                                    <span
                                                            class="text-primary"><?php
                                                        $count = 'SELECT COUNT(*) as cou FROM tel_db WHERE act=? AND dakheli=? AND `group`=?';
                                                        $wdr = $conn->prepare($count);
                                                        $wdr->execute([0,$_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                        $recount = $wdr->fetch();
                                                        echo $recount['cou'];
                                                        ?></span>
                                                </div>

                                                <table class="table text-nowrap text-md-nowrap table-bordered"
                                                      >
                                                    <thead>
                                                    <tr>
                                                        <th>ردیف</th>
                                                        <th>نام و نام خانوادگی</th>
                                                        <th>راهنما</th>
                                                        <th>استان</th>
                                                        <th>شهر</th>
                                                        <th>اقدام</th>

                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php
                                                    $sql1 = 'SELECT * FROM tel_db WHERE metod=? AND act=? AND dakheli=? AND `group`=?';
                                                    $query1 = $conn->prepare($sql1);
                                                    $query1->execute([0, 0, $_SESSION['admin']['dakheli'],$_SESSION['uncall']]);
                                                    $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query1->rowCount() > 0) {
                                                        foreach ($resy as $ret) {

                                                            ?>
                                                            <tr>
                                                                <td><?php echo $ret->id ?></td>
                                                                <td><?php echo $ret->fname . ' ' . $ret->lname ?></td>
                                                                <td><?php echo $ret->guidance ?></td>
                                                                <td><?php echo $ret->state ?></td>
                                                                <td><?php echo $ret->city ?></td>
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
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-static1=<?php echo $ret->id ?>">
                                                                                        <i class="fe fe-phone-outgoing"></i>تماس
                                                                                        ثابت 1
                                                                                    </a>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-static2=<?php echo $ret->id ?>">
                                                                                        <i class="fe fe-phone-outgoing"></i>تماس
                                                                                        ثابت 2
                                                                                    </a>
                                                                                    <a class="dropdown-item  "
                                                                                       href="?call-static3=<?php echo $ret->id ?>">
                                                                                        <i class="fe fe-phone-outgoing"></i>تماس
                                                                                        ثابت 3
                                                                                    </a>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>


                                                                </td>

                                                            </tr>
                                                            <?php
                                                        }
                                                    }
                                                    ?>

                                                    </tbody>
                                                </table>


                                            <?php } ?>



                                        </div>
                                    </div>
                                </section>
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


