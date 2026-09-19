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
if (!Permision('permision_viewcollague')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_SESSION['massage'])) {
    $massage = $_SESSION['massage'];
    unset($_SESSION['massage']);
}
if (isset($_POST['txt']) and $_POST['txt'] != '') {
    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case '01':
            $swr = 'SELECT * FROM dbpersonal INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username WHERE username=? AND role =? ';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt, 2]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '02':
            $txt = '%' . $txt . '%';
            $role = 2;
            $swr = 'SELECT * FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli WHERE dblogin.role=:role AND  dbpersonal.fname LIKE :name ORDER BY iduser DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->bindParam(':role', $role, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '03':
            $txt = '%' . $txt . '%';
            $role = 2;
            $swr = 'SELECT * FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli WHERE dblogin.role=:role AND  dbpersonal.lname LIKE :name ORDER BY iduser DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->bindParam(':role', $role, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case '04':
            $txt = '%' . $txt . '%';
            $role = 2;
            $swr = 'SELECT * FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli WHERE dblogin.role=:role AND  dbpersonal.state LIKE :name ORDER BY iduser DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->bindParam(':role', $role, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;

    }
}
if (isset($_GET['call']) and $_GET['call'] != '') {
    $semat = 'همکار';
    $qau = retPerson($_GET['call']);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: sub-collague.php');
    die();
}
if (isset($_GET['activeall'])) {
    if ($_GET['activeall'] != '') {
        $select = 'SELECT codemeli FROM dbpersonal WHERE useradder=?';
        $query = $conn->prepare($select);
        $query->execute([$_GET['activeall']]);
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $ter) {
                $up = 'UPDATE dblogin SET true_key=? WHERE username=? ';
                $qup = $conn->prepare($up);
                $qup->execute([1, $ter->codemeli]);
            }
        }
        header('location: sub-collague.php');
    }
}
if (isset($_GET['refact'])) {
    if ($_GET['refact'] != '') {
        $select = 'SELECT codemeli FROM dbpersonal WHERE useradder=?';
        $query = $conn->prepare($select);
        $query->execute([$_GET['refact']]);
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        if ($query->rowCount() > 0) {
            foreach ($result as $ter) {
                $up = 'UPDATE dbpersonal SET useradder=? WHERE codemeli=? ';
                $qup = $conn->prepare($up);
                $qup->execute([NULL, $ter->codemeli]);
                //
                $tri = 'UPDATE dblogin SET true_key=? WHERE username=?';
                $wer = $conn->prepare($tri);
                $wer->execute([1, $ter->codemeli]);
            }
            $_SESSION['massage'] = 'تمامی زیر مجموعه های همکار آموزشی با موفقیت خارج شدند';
        }
        header('location: sub-collague.php');
    }
}
if (isset($_GET['delcollague'])) {
    if ($_GET['delcollague'] != '') {
        if (totalWallet($_GET['delcollague']) == 0) {
            $select = 'SELECT codemeli FROM dbpersonal WHERE useradder=?';
            $query = $conn->prepare($select);
            $query->execute([$_GET['delcollague']]);
            $result = $query->fetchAll(PDO::FETCH_OBJ);
            if ($query->rowCount() > 0) {
                foreach ($result as $ter) {
                    $up = 'UPDATE dbpersonal SET useradder=? WHERE codemeli=? ';
                    $qup = $conn->prepare($up);
                    $qup->execute([NULL, $ter->codemeli]);
                    //
                    $tri = 'UPDATE dblogin SET true_key=? WHERE username=?';
                    $wer = $conn->prepare($tri);
                    $wer->execute([1, $ter->codemeli]);
                }
            }
            $select = 'SELECT * FROM dbfactor WHERE useradder=?';
            $query = $conn->prepare($select);
            $query->execute([$_GET['delcollague']]);
            $result = $query->fetchAll(PDO::FETCH_OBJ);
            if ($query->rowCount() > 0) {
                foreach ($result as $ter) {
                    $up = 'UPDATE dbfactor SET useradder=? WHERE idfactor=? ';
                    $qup = $conn->prepare($up);
                    $qup->execute([NULL, $ter->idfactor]);
                }
            }

            $del = 'DELETE FROM dbpersonal WHERE codemeli=?';
            $query = $conn->prepare($del);
            $query->execute([$_GET['delcollague']]);
            $_SESSION['massage'] = 'همکار آموزشی با موفقیت حذف شد';
            header('location: sub-collague.php');
        } else {
            $_SESSION['massage'] = 'همکار را نمی توان حذف نمود، دارای بدهکاری و یا بستانکاری است';
            header('location: sub-collague.php');

        }

    }
}
if (isset($_GET['unactive']) and $_GET['unactive'] != '') {
    $unactive='UPDATE dblogin SET activity=? WHERE username=?';
    $exc=$conn->prepare($unactive);
    $exc->execute([0,$_GET['unactive']]);
    $_SESSION['massage']='همکار آموزشی با موفقیت غیرفعال شد';
    header('location: sub-collague.php');
}
if (isset($_GET['activate']) and $_GET['activate'] != '') {
    $unactive='UPDATE dblogin SET activity=? WHERE username=?';
    $exc=$conn->prepare($unactive);
    $exc->execute([1,$_GET['activate']]);
    $_SESSION['massage']='همکار آموزشی با موفقیت فعال شد';
    header('location: sub-collague.php');
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
    <title> زیرمجموعه همکاران | <?php echo __TITLE ?></title>
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
                    <?php if (isset($_SESSION['call'])) { ?>
                        <div class="alert alert-outline-success  text-center " role="alert">
                            <div class="mg-t-10">
                                <img style="width: 75px; height: 75px;"
                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                با
                                <?php echo $_SESSION['call']['name'];
                                unset($_SESSION['call']);
                                ?>

                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                        type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>

                        </div>
                    <?php } ?>
                    <?php if (isset($massage)) { ?>
                        <div class="alert alert-warning" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                    type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $massage ?>

                        </div>
                    <?php } ?>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="col-10">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            جستجوی همکار
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید همکار آموزشی موردنظر را جستجو کرده و اطلاعات حساب کاربری، مشخصات فردی، وضعیت فعالیت و سایر اطلاعات ثبت‌شده او را مشاهده کنید."
                                              aria-label="راهنمای جستجوی همکار آموزشی">

                                            <i class="fe fe-info fs-4"></i>
                                        </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        جستجو و مشاهده اطلاعات همکاران آموزشی
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
                                                    کد ملی همکار
                                                </option>
                                                <option value="02">
                                                    نام
                                                </option>
                                                <option value="03">
                                                    نام خانوادگی
                                                </option>
                                                <option value="04">
                                                    استان
                                                </option>


                                            </select>
                                        </div>
                                        <input type="text" name="txt" class="form-control"
                                               placeholder="جستجوی همکارآموزشی" required>
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
                                        <th>وضعیت</th>
                                        <th>تعداد زیرمجموعه</th>
                                        <th>زیرمجموعه فعال نشده</th>
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
                                                                        جزئیات <i
                                                                                class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <div class="dropdown-item "
                                                                             style="text-align: right"
                                                                             onclick="window.open('subset.php?userId='+'<?php echo $ret->username ?>','لیست زیر مجموعه'
                                                                                     ,'width=1100,height=900,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-search"></i>
                                                                            لیست زیرمجموعه ها
                                                                        </div>
                                                                        <a class="dropdown-item  "
                                                                           href="?call=<?php echo $ret->username ?>"
                                                                        ><i class="fe fe-phone-forwarded"></i> تماس
                                                                            با
                                                                            همکارآموزشی
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="?activeall=<?php echo $ret->username ?>"
                                                                           onclick="return confirm('هشدار !!! زیر مجموعه ها تایید کد شوند؟؟؟')"
                                                                        ><i class="fa fa-dedent"></i> تایید زیرمجموعه ها
                                                                        </a>
                                                                        <a class="dropdown-item  "
                                                                           href="?refact=<?php echo $ret->username ?>"
                                                                           onclick="return confirm('هشدار !!! زیر مجموعه ها رها گردند؟؟؟')"
                                                                        ><i class="fa fa-fast-forward"></i> آزادسازی
                                                                            زیرمجموعه
                                                                        </a>
                                                                        <div class="dropdown-item "
                                                                             style="text-align: right"
                                                                             onclick="window.open('viewCollague.php?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-eye"></i>
                                                                            مشاهده اطلاعات
                                                                        </div>
                                                                        <?php if ($ret->activity == 1) { ?>
                                                                            <a class="dropdown-item text-warning "
                                                                               href="?unactive=<?php echo $ret->username ?>"
                                                                               onclick="return confirm('هشدار !!! همکار غیرفعال گردد؟؟؟')"
                                                                            ><i class="fe fe-eye-off"></i> غیرفعالسازی
                                                                            </a>
                                                                        <?php } else { ?>
                                                                            <a class="dropdown-item text-success "
                                                                               href="?activate=<?php echo $ret->username ?>"
                                                                            ><i class="fe fe-check-circle"></i>       فعالسازی
                                                                            </a>
                                                                        <?php } ?>
                                                                        <hr>
                                                                        <a class="dropdown-item text-danger "
                                                                           href="?delcollague=<?php echo $ret->username ?>"
                                                                           onclick="return confirm('@@@هشدار !!! همکار حذف گردد؟؟؟')"
                                                                        ><i class="fe fe-x-square"></i>     حذف همکار
                                                                        </a>


                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="wd-10"><?php echo $ret->codemeli ?></td>
                                                    <td><?php echo htmlentities($ret->fname . ' ' . $ret->lname) ?></td>
                                                    <td class="wd-20"><?php echo $ret->state; ?></td>
                                                    <td class="wd-20"><?php echo $ret->city; ?></td>
                                                    <td class="wd-20">
                                                        <?php if ($ret->activity == 1) { ?>
                                                            <span class="badge badge-success-light">فعال</span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-warning-light">غیرفعال</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-20">
                                                        <?php
                                                        $vbn = 'SELECT COUNT(idpersonal) as couser FROM dbpersonal WHERE useradder=?';
                                                        $vbs = $conn->prepare($vbn);
                                                        $vbs->execute([$ret->codemeli]);
                                                        $revbs = $vbs->fetch(PDO::FETCH_OBJ);
                                                        echo $revbs->couser;

                                                        ?>
                                                    </td>
                                                    <td class="wd-20">
                                                        <?php
                                                        $co_lock = 'SELECT COUNT(iduser) as noactive FROM dblogin INNER JOIN dbpersonal ON dblogin.username=dbpersonal.codemeli WHERE dbpersonal.useradder=? AND  `keyactive`IS NOT  NULL AND `true_key`=?   ';
                                                        $qu_lock = $conn->prepare($co_lock);
                                                        $qu_lock->execute([$ret->username, 0]);
                                                        $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                                        echo $rec->noactive;
                                                        ?>
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
