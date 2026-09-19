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
if (!Permision('permision_viewdocument')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_GET['view'])) {
    $setview = 'UPDATE documention SET view=? WHERE id=?';
    $qsell = $conn->prepare($setview);
    $qsell->execute([1, $_GET['view']]);
    header('location: newdoc.php');
    die();
}

if (isset($_GET['call']) and $_GET['call'] != '') {
    $ret = retPerson($_GET['call']);
    $tel = $ret->tel1;
    $name = $ret->fname . ' ' . $ret->lname;
    Call($tel, intval($_SESSION['admin']['dakheli']), $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: newdoc.php');
    die();
}
if(isset($_GET['del'])){
    $scan='SELECT * FROM documention WHERE id=?';
    $qscan=$conn->prepare($scan);
    $qscan->execute([$_GET['del']]);
    $rey=$qscan->fetch(PDO::FETCH_OBJ);
    $url=explode(CURRENT_DOMAIN,$rey->url);
    $src =  trim($url[1], '/');
    unlink('../../'.$src);

    $delsql='DELETE FROM documention WHERE id=?';
    $del=$conn->prepare($delsql);
    $del->execute([$_GET['del']]);

    header('location: newdoc.php');
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
    <title> مستندات مشاهده نشده | <?php echo __TITLE ?></title>
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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-9">
                                        <div>
                                            <h6 class="main-content-label mb-1 d-flex align-items-center" style="gap:10px;">

                                                تازه ها

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش مستندات و مدارک جدیدی که توسط دانش آموزان ارسال شده و هنوز مشاهده یا بررسی نشده‌اند نمایش داده می‌شود. این قسمت به شما کمک می‌کند تا سریع‌تر مدارک جدید را شناسایی و پیگیری کنید."
                                                      aria-label="راهنمای مستندات جدید">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </h6>
                                            <p class="text-muted card-sub-title"> مستندات جدید و مشاهده نشده
                                            </p>
                                        </div>
                                    </div>

                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>تاریخ</th>
                                            <th>کدملی</th>
                                            <th>نام</th>
                                            <th>نوع کاربری</th>
                                            <th>توضیحات</th>
                                            <th> دانلود</th>
                                            <th>دیدم</th>
                                            <th>تماس</th>
                                            <th>حذف</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM documention  WHERE view=?  ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([0]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result

                                                     as $res) { ?>
                                                <tr>
                                                    <?php
                                                    $dateno = $res->datecreate;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td class="wd-20"><?php echo $dateno ?></td>
                                                    <td class="wd-20"><?php echo $res->username ?></td>
                                                    <?php
                                                    $add = retPerson($res->username);
                                                    ?>
                                                    <td><?php echo htmlentities($add->fname . ' ' . $add->lname) ?></td>
                                                    <td class="wd-20"><?php if (typeUser($res->username) == 1) {
                                                            echo 'دانش آموز';
                                                        } else {
                                                            echo 'همکار آموزشی';
                                                        } ?></td>
                                                    <td class="wd-20">
                                                        <?php
                                                        if ($res->meta != ''){
                                                        ?>
                                                        <div onclick="window.open('meta.php?id='+'<?php echo $res->id ?>','توضیحات بیشتر'
                                                                ,'width=800,height=500,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )"
                                                             class="btn btn-sm ripple btn-primary btn-icon">
                                                            <i class="fe fe-database"></i>
                                                            <?php } ?>
                                                        </div>
                                                    </td>
                                                    <td class="wd-20">
                                                        <a href="<?php echo $res->url ?>" target="_blank"
                                                           class="btn btn-sm ripple btn-info btn-icon">
                                                            <i class="fe fe-download"></i>
                                                        </a>
                                                    </td>
                                                    <td class="wd-20">
                                                        <a href="?view=<?php echo $res->id ?>"
                                                           class="btn btn-sm ripple btn-success btn-icon">
                                                            <i class="fe fe-eye"></i>
                                                        </a>
                                                    </td>
                                                    <td class="wd-20">
                                                        <a href="?call=<?php echo $res->username ?>"
                                                           class="btn btn-sm ripple btn-light btn-icon">
                                                            <i class="fe fe-phone-call"></i>
                                                        </a>
                                                    </td>
                                                    <td class="wd-20">
                                                        <a href="?del=<?php echo $res->id ?>"
                                                           onclick="return confirm('آیا با حذف این سند موافقید؟؟')"
                                                           class="btn btn-sm ripple btn-danger btn-icon">
                                                            <i class="fe fe-x-circle"></i>
                                                        </a>
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
                </div>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>