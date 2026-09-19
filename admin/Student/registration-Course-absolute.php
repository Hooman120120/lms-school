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
if (!Permision('permision_addcourse_student')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['search'])) {

    $category = $_POST['category'];
    $txt = $_POST['txt'];
    switch ($category) {
        case 'one':
            $swr = 'SELECT * FROM dbcourse WHERE username =?';
            $wer = $conn->prepare($swr);
            $wer->execute([$txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case 'two':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbcourse INNER JOIN dbpersonal ON dbcourse.username=dbpersonal.codemeli WHERE dbpersonal.fname LIKE :name ORDER BY idcourse DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        case 'three':
            $txt = '%' . $txt . '%';
            $swr = 'SELECT * FROM dbcourse INNER JOIN dbpersonal ON dbcourse.username=dbpersonal.codemeli WHERE dbpersonal.lname LIKE :name ORDER BY idcourse DESC ';
            $wer = $conn->prepare($swr);
            $wer->bindParam(':name', $txt, PDO::PARAM_STR);
            $wer->execute();
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
        default:
            $sql = "SELECT * FROM dbcourse INNER JOIN (SELECT * FROM dbpersonal INNER JOIN dbpersonal_type_vip ON dbpersonal_type_vip.dbpersonal_id = dbpersonal.idpersonal WHERE dbpersonal_type_vip.type_vip_id = ?) AS vip ON dbcourse.username = vip.codemeli WHERE vip.shift = ?";
            $wer = $conn->prepare($sql);
            $wer->execute([$category, $txt]);
            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
            break;
    }
}
if (isset($_POST['search-course'])) {
    $course = $_POST['course'];
    $vip = $_POST['vip'];
    if ($vip == 'all') {
        $sql = "SELECT * FROM dbcourse INNER JOIN dbpersonal ON dbcourse.username = dbpersonal.codemeli WHERE dbcourse.codecourse = ? GROUP BY dbcourse.username";
        $wer = $conn->prepare($sql);
        $wer->execute([$course]);
        $recive = $wer->fetchAll(PDO::FETCH_OBJ);
    } else {
        $sql = "SELECT * FROM dbcourse INNER JOIN (SELECT * FROM dbpersonal INNER JOIN dbpersonal_type_vip ON dbpersonal_type_vip.dbpersonal_id = dbpersonal.idpersonal WHERE dbpersonal_type_vip.type_vip_id = ?) AS vip ON dbcourse.username = vip.codemeli WHERE dbcourse.codecourse = ? GROUP BY dbcourse.username";
        $wer = $conn->prepare($sql);
        $wer->execute([$vip, $course]);
        $recive = $wer->fetchAll(PDO::FETCH_OBJ);
    }
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_GET['disable'])) {
    $disable = intval($_GET['disable']);
    $update = 'UPDATE dbcourse SET activityuser=? WHERE idcourse=?';
    $qu = $conn->prepare($update);
    $qu->execute([0, $disable]);
    $_SESSION['massege'] = 'دسترسی به دوره برای دانش آموز غیرفعال شد';
    header('location: registration-Course-absolute.php');
    die();
}
if (isset($_GET['enable'])) {
    $disable = intval($_GET['enable']);

    $check = 'SELECT * FROM dbcourse WHERE idcourse=?';
    $ch = $conn->prepare($check);
    $ch->execute([$disable]);
    $result = $ch->fetch();
    $act = queryFactor($result['numfactor']);
    if ($act->status == 'موفق') {
        $update = 'UPDATE dbcourse SET activityuser=? WHERE idcourse=?';
        $qu = $conn->prepare($update);
        $qu->execute([1, $disable]);
        $_SESSION['massege'] = 'دسترسی به دوره برای دانش آموز فعال شد';
        header('location: registration-Course-absolute.php');
        die();
    } else {
        $_SESSION['massege'] = 'فاکتور هزینه دانش آموز برای این دوره هنوز پرداخت / تایید نشده است';
        header('location: registration-Course-absolute.php');
        die();
    }
}
if (isset($_GET['call-student'])) {
    $ret = retPerson($_GET['call-student']);
    $tel = $ret->tel1;
    $name = ' دانش آموز ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: registration-Course-absolute.php');
    die();
}
if (isset($_GET['call-mother'])) {
    $ret = retPerson($_GET['call-mother']);
    $tel = $ret->tel2;
    $name = '  مادر دانش آموز   ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: registration-Course-absolute.php');
    die();
}
if (isset($_GET['call-father'])) {
    $ret = retPerson($_GET['call-father']);
    $tel = $ret->tel3;
    $name = ' پدر دانش آموز  ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: registration-Course-absolute.php');
    die();
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
    <title> مشاهده دوره های دانش آموز | <?php echo __TITLE ?></title>
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
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-body d-flex ">
                            <div class="col-6">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            جستجوی دانش آموز
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید دانش‌آموز موردنظر را جستجو کرده و وضعیت ثبت‌نام فعال یا غیرفعال او در دوره‌ها را بررسی کنید."
                                              aria-label="راهنمای جستجوی دانش‌آموز">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        بررسی ثبت‌نام فعال یا غیرفعال دانش آموز در دوره‌ها
                                    </p>
                                </div>

                                <form method="post" data-parsley-validate="">
                                    <div class="main-header-center">

                                        <div class="input-group">
                                            <div class="input-group-btn search-panel">
                                                <select class="form-control select2" name="category" required>
                                                    <option label="دسته بندی ها">
                                                    </option>
                                                    <option value="one">
                                                        کد ملی دانش آموز
                                                    </option>
                                                    <option value="two">
                                                        نام
                                                    </option>
                                                    <option value="three">
                                                        نام خانوادگی
                                                    </option>
                                                    <?php
                                                    $sql = "SELECT * FROM type_vip";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute();
                                                    $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                    foreach ($vips as $vip) {
                                                        ?>
                                                        <option value="<?= $vip->id ?>">
                                                            دانش آموزان <?= $vip->name ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <?php
                                            // $hash = bin2hex(random_bytes(32));
                                            // $_SESSION['token'] = $hash;
                                            ?>
                                            <!-- <input class="form-control" name="token" type="hidden"
                                                value="<//?php echo $hash ?>"> -->
                                            <input type="text" name="txt" class="form-control"
                                                   placeholder="جستجوی دانش آموز" required>
                                            <button type="submit" name="search" class="btn search-btn"><i
                                                        class="fe fe-search"></i></button>
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
                            <div class="col-6 my-auto mx-auto">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            گزارش کل
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip="در این بخش می‌توانید گزارش کلی دانش آموزان را بر اساس دوره‌های فعال دریافت کنید. ابتدا دوره موردنظر و سپس بازه یا نوع دانش آموزان را انتخاب کرده و برای مشاهده گزارش، دکمه جستجو را بزنید."
                                              aria-label="راهنمای گزارش کل دانش آموزان">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        بر اساس دوره‌های فعال
                                    </p>
                                </div>
                                <form method="post" data-parsley-validate="">
                                    <div class="d-flex align-items-center flex-nowrap" style="gap: 10px;">
                                        <!-- دسته‌بندی -->
                                        <div style="width: 200px;">
                                            <select class="form-control select2" name="course" required>
                                                <option value="">یکی را انتخاب کن</option>
                                                <?php
                                                $sql = "SELECT * FROM codtbl_course WHERE activecourse = 1";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute();
                                                $courses = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                foreach ($courses as $course):
                                                    ?>
                                                    <option value="<?= $course->codecourse ?>">دوره
                                                        <?= $course->coursename ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- زمان -->
                                        <div style="width: 150px;">
                                            <select name="vip" class="form-control select2" required>
                                                <option value="" disabled selected hidden>انتخاب زمان</option>
                                                <?php
                                                $sql = "SELECT * FROM type_vip";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute();
                                                $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                foreach ($vips as $vip) {
                                                    ?>
                                                    <option value="<?= $vip->id ?>">
                                                        دانش آموزان <?= $vip->name ?>
                                                    </option>
                                                <?php } ?>
                                                <option value="all">همه دانش آموزان</option>
                                            </select>
                                        </div>

                                        <!-- توکن -->
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input type="hidden" name="token" value="<?= $hash ?>">

                                        <!-- دکمه -->
                                        <button type="submit" name="search-course" class="btn btn-primary">
                                            <i class="fe fe-search"></i>
                                        </button>
                                    </div>
                                </form>


                            </div>
                        </div>


                        <div class="card-body">
                            <?php if (isset($_SESSION['massege'])) { ?>
                                <div class="alert alert-info" role="alert">
                                    <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                    <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                    <?php echo $_SESSION['massege'];
                                    unset($_SESSION['massege']); ?>
                                </div>
                            <?php } ?>
                            <?php if (isset($call) and $call === 1) { ?>
                                <div class="alert alert-outline-success  text-center " role="alert">
                                    <div class="mg-t-10">
                                        <img style="width: 75px; height: 75px;" src="../../assets/img/call1.gif">در حال
                                        برقراری ارتباط
                                        با
                                        <?php echo $name ?>

                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </div>

                                </div>
                            <?php } ?>
                            <div class="table">
                                <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                    <thead>
                                    <tr>
                                        <th class="wd-30">اقدامات</th>
                                        <th class="wd-100">نام و نام خانوادگی</th>
                                        <th>دوره</th>
                                        <th class="wd-30">وضعیت فردی</th>
                                        <th class="wd-30">وضعیت دوره</th>
                                        <th class="wd-30">پشتیبان</th>
                                        <th class="wd-30">اولین ورود</th>
                                        <th class="wd-30">همکارآموزشی</th>
                                        <th class="wd-30">نوع دانش آموز</th>
                                        <th class="wd-30">شیفت دانش آموز</th>


                                    </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    if (isset($recive)) {
                                        if ($wer->rowCount() > 0) {
                                            foreach ($recive as $ret) {
                                                ?>
                                                <tr>
                                                    <td class="wd-30">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false" aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <div class="dropdown-item  "
                                                                             onclick="window.open('viewProfile.php?code='+'<?php echo $ret->username ?>','مشاهده اطلاعات کاربری'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fa fa-address-card"></i> مشاهده
                                                                            اطلاعات
                                                                            کاربری

                                                                        </div>
                                                                        <a class="dropdown-item"
                                                                           href="?call-student=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i> تماس
                                                                            با
                                                                            دانش آموز
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="?call-mother=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i> تماس
                                                                            با
                                                                            مادر
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="?call-father=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-phone-outgoing"></i> تماس
                                                                            با
                                                                            پدر
                                                                        </a>
                                                                        <div class="dropdown-item"
                                                                             onclick="window.open('log-viewFilm.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-video"></i> فیلم های
                                                                            مشاهده نموده

                                                                        </div>
                                                                        <div class="dropdown-item"
                                                                             onclick="window.open('log-viewdoc.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                     ,'width=600,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-book-open"></i> جزوات
                                                                            دانلودی

                                                                        </div>
                                                                        <a class="dropdown-item text-info"
                                                                           onclick="window.open('log-viewquiz.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                   ,'width=1450,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )"><i
                                                                                    class="fa fa-question"></i> مشاهده آزمون ها

                                                                        </a>
                                                                        <a class="dropdown-item disabled"
                                                                           href="?takalif=<?php echo $ret->username ?>"><i
                                                                                    class="fe fe-book"></i> گزارش تکالیف ارسالی

                                                                        </a>
                                                                        <a class="dropdown-item text-secondary"
                                                                           onclick="window.open('log-viewattendance.php?user='+'<?php echo $ret->username ?>'+'&course='+'<?php echo $ret->codecourse ?>' ,'گزارش'
                                                                                   ,'width=800,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )"><i
                                                                                    class="fe fe-check-square"></i> مشاهده حضور
                                                                            غیاب

                                                                        </a>

                                                                        <hr>
                                                                        <?php if ($ret->activityuser == 1) { ?>
                                                                            <a class="dropdown-item text-danger"
                                                                               href="?disable=<?php echo $ret->idcourse ?>"><i
                                                                                        class="fe fe-eye-off"></i>غیرفعال سازی
                                                                                فردی
                                                                            </a>
                                                                        <?php } ?>
                                                                        <?php if ($ret->activityuser == 0) { ?>
                                                                            <a class="dropdown-item text-success"
                                                                               href="?enable=<?php echo $ret->idcourse ?>"><i
                                                                                        class="fe fe-eye"></i>فعال سازی فردی
                                                                            </a>
                                                                        <?php } ?>

                                                    </td>
                                                    <td class="wd-30"><?php $person = retPerson($ret->username);
                                                        echo $person->fname . ' ' . $person->lname; ?></td>
                                                    <td><?php $course = returnCourse($ret->codecourse);
                                                        echo $course->coursename; ?></td>
                                                    <td class="wd-30"><?php if ($ret->activityuser == 1) {
                                                            echo '<span class="badge badge-success-light">فعال</span>';
                                                        } else {
                                                            echo '<span class="badge badge-warning-light">غیرفعال</span>';
                                                        } ?></td>
                                                    <td class="wd-30"><?php if ($course->activecourse == 1) {
                                                            echo '<span class="badge badge-success-light">فعال</span>';
                                                        } else {
                                                            echo '<span class="badge badge-warning-light">غیرفعال</span>';
                                                        }
                                                        ?></td>
                                                    <td class="wd-30"><?php if ($ret->suporter != NULL) {
                                                            echo $ret->suporter;
                                                        } ?> </td>

                                                    <td class="wd-30">
                                                        <?php
                                                        $first = retdblogin($ret->username);
                                                        if ($first->firstlogin == 1) {
                                                            echo '<span class="badge badge-success-light">بـله</span>';
                                                        } else {
                                                            echo '<span class="badge badge-warning-light">خــیر</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-30">
                                                        <?php
                                                        if ($person->useradder != NULL) {
                                                            $adder = retPerson($person->useradder);
                                                            echo $adder->fname . ' ' . $adder->lname;
                                                        } else {
                                                            echo 'ندارد';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-30">
                                                        <?php
                                                        $sql = "SELECT * FROM dbpersonal_type_vip INNER JOIN dbpersonal ON dbpersonal_type_vip.dbpersonal_id = dbpersonal.idpersonal WHERE dbpersonal.codemeli = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$person->codemeli]);
                                                        $vips = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                        $typeVips = array();
                                                        if ($stmt->rowCount() > 0) {
                                                            foreach ($vips as $vip) {
                                                                $sql = "SELECT * FROM type_vip WHERE id = ?";
                                                                $stmt = $conn->prepare($sql);
                                                                $stmt->execute([$vip->type_vip_id]);
                                                                $type = $stmt->fetch(PDO::FETCH_OBJ);
                                                                array_push($typeVips, $type->name);
                                                            }
                                                            echo implode('، ', $typeVips);
                                                        } else {
                                                            echo 'تعریف نشده';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!isset($person->shift)) {
                                                            echo 'تعریف نشده';
                                                        } else {
                                                            switch ($person->shift) {
                                                                case '0':
                                                                    echo 'صبح';
                                                                    break;
                                                                case '1':
                                                                    echo 'بعدازظهر';
                                                                    break;
                                                                case '2':
                                                                    echo 'گردشی';
                                                                    break;
                                                            }
                                                        }
                                                        ?>
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
    <!-- End Row -->



    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        $(document).ready(function () {
            const inputHtml = '<input type="text" name="txt" class="form-control" placeholder="جستجوی دانش آموز" required>';
            const selectHtml = `
        <select name="txt" class="form-control select2" required>
            <option value="" disabled selected hidden>انتخاب زمان</option>
            <option value="0">صبح</option>
            <option value="1">بعدازظهر</option>
            <option value="2">گردشی</option>
        </select>`;

            $('select[name="category"]').on('change', function () {
                const selectedValue = this.value;
                if ($.isNumeric(selectedValue)) {
                    $('input[name="txt"]').replaceWith(selectHtml);
                } else {
                    if ($('select[name="txt"]').length) {
                        $('select[name="txt"]').replaceWith(inputHtml);
                    }
                }
            });
        });
    </script>


</body>

</html>