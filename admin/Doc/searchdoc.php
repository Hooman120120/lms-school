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
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM documention INNER JOIN dblogin ON documention.username=dblogin.username WHERE documention.username =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM documention INNER JOIN dbpersonal ON documention.username=dbpersonal.codemeli INNER JOIN dblogin ON documention.username=dblogin.username WHERE dbpersonal.fname LIKE :name ORDER BY id DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '02':
            $txt = explode('/', $txt);
            $txt = jalali_to_gregorian($txt[0], $txt[1], $txt[2]);
            $txt = implode('-', $txt);
            $swr = 'SELECT * FROM documention INNER JOIN dblogin ON documention.username=dblogin.username WHERE documention.datecreate =? ORDER BY id DESC';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '04':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM documention INNER JOIN dbpersonal ON documention.username=dbpersonal.codemeli INNER JOIN dblogin ON documention.username=dblogin.username WHERE dbpersonal.lname LIKE :name ORDER BY id DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;

    }
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $ret = retPerson($_GET['call']);
    $tel = $ret->tel1;
    $name = $ret->fname . ' ' . $ret->lname;
    Call($tel, intval($_SESSION['admin']['dakheli']), $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: searchdoc.php');
    die();
}
if (isset($_GET['view'])) {
    $setview = 'UPDATE documention SET view=? WHERE id=?';
    $qsell = $conn->prepare($setview);
    $qsell->execute([1, $_GET['view']]);
    header('location: searchdoc.php');
    die();
}
if (isset($_GET['unview'])) {
    $setview = 'UPDATE documention SET view=? WHERE id=?';
    $qsell = $conn->prepare($setview);
    $qsell->execute([0, $_GET['unview']]);
    header('location: searchdoc.php');
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

    header('location: searchdoc.php');
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
                                        <h6 class="main-content-label mb-1 d-flex align-items-center" style="gap:10px;">

                                            جستجوی مستندات

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید مستندات و مدارک ثبت‌شده دانش آموزان را بر اساس اطلاعات مختلف جستجو و مشاهده کنید. این امکان به شما کمک می‌کند تا در کوتاه‌ترین زمان به مستندات مورد نظر دسترسی پیدا کرده و وضعیت آن‌ها را بررسی نمایید."
                                                  aria-label="راهنمای جستجوی مستندات">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </h6>
                                        <p class="text-muted card-sub-title">یافتن اسناد الکترونیکی
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
                                                        تاریخ ایجاد
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
                                                   placeholder="عبارت برای جستجو..." required>
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
                                            <th>کدملی</th>
                                            <th>نام</th>
                                            <th>دانلود</th>
                                            <th>وضعیت</th>
                                            <th>کاربری</th>
                                            <th>تاریخ</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if (isset($recive)) {
                                            if ($wer->rowCount() > 0) {

                                                foreach ($recive as $ret) {
                                                    ?>
                                                    <tr>
                                                        <td class="wd-15">
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
                                                                            <?php }
                                                                            if ($ret->meta != '') {
                                                                                ?>
                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('<?php echo url("admin/Doc/meta.php") ?>'+'?id='+'<?php echo $ret->id ?>','مشاهده اطلاعات'
                                                                                             ,'width=600,height=600,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-database"></i>
                                                                                    توضیحات بیشتر
                                                                                </div>
                                                                            <?php }
                                                                            if ($ret->view == 0) { ?>
                                                                                <a class="dropdown-item  "
                                                                                   href="?view=<?php echo $ret->id ?>">

                                                                                    <i class="fe fe-eye"></i> دیدم
                                                                                </>
                                                                            <?php }else{ ?>
                                                                                <a class="dropdown-item  "
                                                                                   href="?unview=<?php echo $ret->id ?>"
                                                                                >
                                                                                    <i class="fe fe-eye-off"></i>         ندیدم
                                                                                </a>
                                                                            <?php } ?>
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $ret->username ?>"
                                                                            ><i class="fe fe-phone-forwarded"></i>  تماس

                                                                            </a>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?del=<?php echo $ret->id ?>"
                                                                            ><i class="fe fe-x"></i>  حذف
                                                                            </a>

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>


                                                        </td>
                                                        <td class="wd-15"><?php echo $ret->username ?></td>
                                                        <?php
                                                        $add = retPerson($ret->username);
                                                        ?>
                                                        <td><?php echo htmlentities($add->fname . ' ' . $add->lname) ?></td>
                                                        <td class="wd-20">
                                                            <a href="<?php echo $ret->url ?>" target="_blank"
                                                               class="btn btn-sm ripple btn-light btn-icon">
                                                                <i class="fe fe-download"></i>
                                                            </a>
                                                        </td>
                                                        <td class="wd-15">
                                                            <?php if ($ret->view == 0) {
                                                                echo '<span class=" badge badge-warning  "> مشاهده نشده</span>';
                                                            } else {
                                                                echo '<span class=" badge badge-info  "> مشاهده شده</span>';
                                                            } ?>
                                                        </td>
                                                        <td class="wd-15">
                                                            <?php if ($ret->role == 1) {
                                                                echo 'دانش آموز';
                                                            } else {
                                                                echo 'همکار آموزشی';
                                                            } ?>
                                                        </td>
                                                        <?php
                                                        $dateno = $ret->datecreate;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>
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
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

