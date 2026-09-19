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
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['changesuporter'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $agent = $_SERVER["HTTP_USER_AGENT"];
        $mor = NULL;
        if (preg_match('/MSIE (\d+\.\d+);/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Chrome[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Edge\/\d+/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Firefox[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'application/vnd.ms-excel';
        } else if (preg_match('/OPR[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        } else if (preg_match('/Safari[\/\s](\d+\.\d+)/', $agent)) {
            $mor = 'text/csv';
        }
        if ($_FILES['fileToUpload']['type'] != $mor) {
            echo "<script>alert('   فایل  مجاز برای  بارگذاری CSV ');</script>";
        } else {
            $target_dir = '../../assets/doc/temp';
            $hashFile = bin2hex(random_bytes(8));
            $target_file = $target_dir . basename($hashFile . $_FILES["fileToUpload"]["name"]);

            if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
                $CSVfp = fopen($target_file, "r");
                if ($CSVfp !== FALSE) {
                    $icc = 0;
                    $erorUser = array();

                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 1000000, ",");
                        if ($data) {
                            if ($icc != 0) {
                                if (checkactCourse($data[0], $data[1])) {

                                    // get current suporter
                                    $st_old = $conn->prepare('SELECT suporter FROM dbcourse WHERE username=? AND codecourse=? LIMIT 1');
                                    $st_old->execute([$data[0], $data[1]]);
                                    $oldSup = $st_old->fetchColumn();

                                    $newSup = $data[2];

                                    // update only if changed (also handles NULL)
                                    if ((string)$oldSup !== (string)$newSup) {
                                        $sql = 'UPDATE dbcourse SET suporter=?, suporter_updated_at=NOW() WHERE username=? AND codecourse=?';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$newSup, $data[0], $data[1]]);
                                    }
                                } else {
                                    array_push($erorUser, $data[0]);
                                }
                            }

                            $icc++;
                        }
                    }
                }
                fclose($CSVfp);
                unlink($target_file);
            }
        }
    }
}
//role check
$sq = 'SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1';
$st = $conn->prepare($sq);
$st->execute([$_SESSION['admin']['user']]);
$admin = $st->fetch(PDO::FETCH_OBJ);
$adminRole = isset($admin->adminrole) ? (int)$admin->adminrole : 0;


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مدیریت پشتیبان ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        @keyframes pulseZoom {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .pulse-zoom {
            animation: pulseZoom 2s ease-in-out infinite;
            transition: transform 0.3s ease-in-out;
        }

        .pulse-zoom:hover {
            animation-play-state: paused;
            transform: scale(1.07);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .op-section:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
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
                <?php if (!in_array($adminRole, [3], true)): ?>
                    <?php if (isset($icc) and $icc > 0) { ?>
                        <div class="alert alert-solid-success" role="alert">
                            <button aria-label="Close" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">&times;</span></button>
                            <strong>توجه کنید !</strong> فایل جابجایی پشتیبان ها با موفقیت بارگزاری شد
                        </div>
                    <?php } ?>
                    <?php if (isset($erorUser) and sizeof($erorUser) > 0) { ?>
                        <div class="alert alert-solid-danger" role="alert">
                            <button aria-label="Close" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">&times;</span></button>
                            <strong>خطـــا !</strong> تعدادی از (کدملی ، کد دوره) را در سیستم پیدا نکردیم بنا براین برای افراد
                            زیر هیچ اتفاقی نیفتاد
                            <div class="col-3">
                                <table class="table table-bordered">
                                    <thead>
                                        <th>#</th>
                                        <th>کدملی</th>
                                    </thead>
                                    <tbody>
                                        <?php $id = 1;
                                        foreach ($erorUser as $res) { ?>
                                            <tr>
                                                <td class="wd-15"><?php echo $id ?></td>
                                                <td><?php echo $res ?></td>

                                            </tr>
                                        <?php
                                            $id++;
                                        }
                                        ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="inner-body">
                        <div class="row row-sm">
                            <div class="col-sm-12 col-lg-12 col-xl-8">
                                <div class="row row-sm">
                                    <!-- <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                        <a class="card custom-card pulse-zoom" style="color: #0a0a0a; cursor: pointer;"
                                            onclick="window.open('<//?php echo url('admin/panel/studentsummary.php/') ?>',
                                           'StudentSummary',
                                           'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                            <div class="card-body"> -->
                                    <!-- <div class="card-order">
                                                    <//?php
                                                    $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=?';
                                                    $qu_lock = $conn->prepare($co_lock);
                                                    $qu_lock->execute(['موفق']);
                                                    $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                                    $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1, 1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    $user = array();
                                                    $id = 0;
                                                    if ($query->rowCount() > 0) {

                                                        foreach ($result as $res) {
                                                            if (!in_array($res->username, $user)) {
                                                                array_push($user, $res->username);
                                                                $id++;
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <label class="main-content-label mb-3 pt-1">دانش آموزان</label>
                                                    <h2 class="text-left card-item-icon card-icon">
                                                        <i class="mdi mdi-account-multiple icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold">(<//?php echo $rec->coreg ?>)<//?php echo $id ?> </span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-muted">فرد (نفردوره)</p>
                                                </div> -->
                                    <!-- </div>
                                        </a>
                                    </div> -->
                                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3 d-flex flex-row flex-wrap">
                                        <a class=" custom-card  d-flex  col-6 op-section" style="color: #0a0a0a; cursor: pointer; border-top-right-radius: 10px; background-color: white; border-bottom: solid 1px lightgrey; border-left: solid 1px lightgrey;"
                                            onclick="">
                                            <div class="">
                                                <div class="card-order">
                                                    <label class="main-content-label" style="margin-top: 6px;"> با پشتیبان</label>
                                                    <p class="mb-0 text-muted">نفر دوره : <span style="color: black;">1222</span></p>
                                                    <p class="mb-0 text-muted">فرد : <span style="color: black;">1222</span></p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class=" custom-card  d-flex  col-6 op-section" style="color: #0a0a0a; cursor: pointer; border-top-left-radius: 10px; background-color: white; border-bottom: solid 1px lightgrey;"
                                            onclick="">
                                            <?php 
                                            $sqlPersonCourse = 'SELECT COUNT(*) FROM dbcourse WHERE has_operator = 0';
                                            $stmtPersonCourse = $conn->prepare($sqlPersonCourse);
                                            $stmtPersonCourse;
                                            ?>
                                            <div class="">
                                                <div class="card-order">
                                                    <label class="main-content-label " style="margin-top: 6px;">بدون پشتیبان</label>
                                                    <p class="mb-0 text-muted">نفر دوره : <span style="color: black;">1222</span></p>
                                                    <p class="mb-0 text-muted">فرد : <span style="color: black;">1222</span></p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class=" custom-card  d-flex col-12 op-section" style="color: #0a0a0a; cursor: pointer;  margin-top:-20px; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; background-color: white; "
                                            onclick="">
                                            <?php
                                            $co_lock = ' SELECT COUNT(*) as coreg FROM `dbcourse` INNER JOIN dbfactor ON dbcourse . numfactor = dbfactor . idfactor INNER JOIN codtbl_course ON codtbl_course.codecourse=dbcourse.codecourse  WHERE dbcourse . activityuser = 1 AND codtbl_course.activecourse=1 AND dbfactor.status=?';
                                            $qu_lock = $conn->prepare($co_lock);
                                            $qu_lock->execute(['موفق']);
                                            $rec = $qu_lock->fetch(PDO::FETCH_OBJ);
                                            $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([1, 1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $user = array();
                                            $id = 0;
                                            if ($query->rowCount() > 0) {

                                                foreach ($result as $res) {
                                                    if (!in_array($res->username, $user)) {
                                                        array_push($user, $res->username);
                                                        $id++;
                                                    }
                                                }
                                            }
                                            ?>
                                            <div class="card-order d-flex align-item-center justify-item-center flex-column w-100">
                                                <label class="main-content-label text-center" style="margin-top: 10px;">همه دانش آموزان (نفردوره)</label>
                                                <p class="mb-0 text-muted text-center">نفر دوره : <span style="color: black;"><?= $rec->coreg ?></span></p>
                                                <p class="mb-0 text-muted text-center">فرد : <span style="color: black;"><?= $id ?></span></p>
                                            </div>
                                        </a>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="card-order">

                                                    <?php
                                                    $sql = "SELECT COUNT(*) AS total_unseen_messages FROM ( SELECT * FROM suport_message WHERE status = ? GROUP BY sender_username ) AS grouped_messages WHERE receiver_username IN ( SELECT username FROM dbadmin WHERE fname = ? OR fname = ? ) OR receiver_username = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute(['sent', 'اپراتور', 'خانم', 'managers']);
                                                    $unseen = $stmt->fetch(PDO::FETCH_OBJ);
                                                    ?>
                                                    <label class="main-content-label mb-3 pt-1">منتظر پاسخ </label>
                                                    <h2 class="text-left"><i
                                                            class="fe fe-edit icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?= $unseen->total_unseen_messages ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-muted">دانش آموز منتظر پاسخ پیام</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                        <div class="card custom-card">
                                            <div class="card-body">
                                                <div class="card-order">

                                                    <label class="main-content-label mb-3 pt-1"> پشتیبانی نشده</label>
                                                    <h2 class="text-left"><i
                                                            class="fe fe-eye-off icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"> <?php $notSupport = notSupportAdmin(2);
                                                                                        echo $notSupport; ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 text-muted">نفر فاقد پشتیبانی </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                        <div class="card custom-card pulse-zoom bg-orange">
                                            <a class="" style="color: #0a0a0a; cursor: pointer;"
                                                onclick="window.open('<?php echo url('admin/panel/lacking_support.php/') ?>',
                                               'lacking support',
                                               'width=1250,height=450,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                                <div class="card-body">
                                                    <div class="card-order">

                                                        <?php
                                                        $sql = 'SELECT COUNT(*) as countNotSuporter FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=1 AND dbcourse.activityuser=1 AND dbcourse.suporter IS NULL   ';
                                                        $query_not = $conn->prepare($sql);
                                                        $query_not->execute();
                                                        $ref = $query_not->fetch();
                                                        ?>
                                                        <label class="main-content-label mb-3 pt-1"> فاقد پشتیبان </label>
                                                        <h2 class="text-left"><i
                                                                class="fe fe-database icon-size float-right text-primary"></i><span
                                                                class="font-weight-bold"><?php echo $ref['countNotSuporter'] ?> </span>
                                                        </h2>
                                                        <p class="mb-0 mt-4 text-muted">نفر دوره </p>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                </div>
                                <?php
                                $dateconfig = delaySupportAdmin(2);
                                ?>
                                <div class="row row-sm">
                                    <div class="col-sm-12 col-md-4 col-lg-4 col-xl-3">
                                        <div class="card custom-card bg-warning">
                                            <div class="card-body">
                                                <div class="card-order ">

                                                    <label class="main-content-label mb-3 pt-1">تاخیر در پشتیبانی</label>
                                                    <h2 class="text-left card-item-icon card-icon">
                                                        <i class="fa fa-calendar-times-o icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo $dateconfig['Last']; ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 ">تاریخ پشتیبانی گذشته</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                        <div class="card custom-card bg-success">
                                            <div class="card-body">
                                                <div class="card-order">

                                                    <label class="main-content-label mb-3 pt-1">پشتیبانی های امروز</label>
                                                    <h2 class="text-left"><i
                                                            class="fa fa-calendar-check-o icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"><?php echo $dateconfig['Today']; ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4 ">برنامه ریزی شده امروز</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                        <div class="card custom-card bg-info">
                                            <div class="card-body">
                                                <div class="card-order">

                                                    <label class="main-content-label mb-3 pt-1"> پشتیبانی فردا</label>
                                                    <h2 class="text-left"><i
                                                            class="fe fe-loader icon-size float-right text-primary"></i><span
                                                            class="font-weight-bold"> <?php echo $dateconfig['Tomarow']; ?></span>
                                                    </h2>
                                                    <p class="mb-0 mt-4"> برنامه ریزی شده فردا </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                                        <div class="card custom-card bg-teal pulse-zoom">
                                            <a class="" style="color: #0a0a0a; cursor: pointer;"
                                                onclick="window.open('<?php echo url('admin/panel/operatorstudents.php/') ?>',
                                               'operatorstudents',
                                               'width=1250,height=450,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=1,location=0'); return false;">
                                                <div class="card-body">
                                                    <div class="card-order">
                                                        <?php
                                                        $sql = 'SELECT COUNT(*) as counReport FROM suport_report ';
                                                        $exe = $conn->prepare($sql);
                                                        $exe->execute();
                                                        $natije = $exe->fetch();
                                                        ?>
                                                        <label class="main-content-label mb-3 pt-1"> تعداد پشتیبانی</label>
                                                        <h2 class="text-left"><i
                                                                class="si si-list icon-size float-right text-primary"></i><span
                                                                class="font-weight-bold"><?php echo $natije['counReport']; ?> </span>
                                                        </h2>
                                                        <p class="mb-0 mt-4 "> گزارش ثبت شده </p>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <!-- COL END -->
                                </div>
                                <div class="row row-sm">
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="card custom-card overflow-hidden">
                                            <div class="card-header  border-bottom-0 pb-0">
                                                <div>
                                                    <div class="d-flex">
                                                        <label class="main-content-label my-auto pt-2">زمانبندی دانش آموزان
                                                        </label>

                                                    </div>
                                                    <span class="d-block tx-12 mt-2 mb-0 text-muted"> تاریخ های برنامه ریزی شده آتی- محاسبه بصورت نفر دوره </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr class="bg-light">
                                                                <th>تاریخ</th>
                                                                <th class="wd-25">تعداد</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $sql = 'SELECT COUNT(*) as countdate,`afterdate` FROM `suport_user` LEFT JOIN dbcourse ON suport_user.username=dbcourse.username LEFT JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse  WHERE  dbcourse.activityuser=1 AND codtbl_course.activecourse=1 GROUP BY `afterdate` ORDER BY afterdate ASC ';
                                                            $wqu = $conn->prepare($sql);
                                                            $wqu->execute();
                                                            $username = array();
                                                            $require = $wqu->fetchAll(PDO::FETCH_OBJ);
                                                            if ($wqu->rowCount() > 0) {
                                                                foreach ($require as $res) {
                                                            ?>
                                                                    <tr>
                                                                        <td>
                                                                            <?php
                                                                            $last = $res->afterdate;
                                                                            $last = explode('-', $last);
                                                                            $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                                                                            $last = implode('/', $last);
                                                                            echo $last;
                                                                            ?>
                                                                        </td>
                                                                        <td><?php echo $res->countdate ?></td>
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
                                    <!-- COL END -->
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="row">
                                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                                <div class="card custom-card overflow-hidden">
                                                    <div class="card-header  border-bottom-0 pb-0">
                                                        <div>
                                                            <div class="d-flex">
                                                                <label class="main-content-label my-auto pt-2">جابجایی پشتیبان
                                                                    ها</label>

                                                            </div>
                                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> جابجایی کلی از طریق افزودن فایل CSV </span>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <form method="post" data-parsley-validate=""
                                                                enctype="multipart/form-data">
                                                                <div class="card-body border ">
                                                                    <div class="row">
                                                                        <div class="col-xl-12">

                                                                            <div class="input-group file-browser">


                                                                                <input type="text"
                                                                                    class="form-control border-left-0 browse-file"
                                                                                    placeholder="فایل مطابق با الگو بارگذاری نمایید*"
                                                                                    readonly="">
                                                                                <label class="input-group-btn">
                                                                                    <span class="btn btn-primary">
                                                                                        انتخاب فایل CSV<input type="file"
                                                                                            name="fileToUpload"
                                                                                            style="display: none;"
                                                                                            required>
                                                                                    </span>
                                                                                </label>
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                فایل الگو را از <a class="font-weight-bolder"
                                                                                    href="<?php echo url('assets/doc/changeSupport.xlsx') ?>"
                                                                                    target="_blank">اینجا</a>
                                                                                و فایل راهنما را <a class="font-weight-bolder"
                                                                                    href="<?php echo url('assets/doc/s3.pdf') ?>"
                                                                                    target="_blank"> از
                                                                                    اینجا </a>
                                                                                دانلود کنید
                                                                            </div>
                                                                            <?php
                                                                            $hash = bin2hex(random_bytes(32));
                                                                            $_SESSION['token'] = $hash;
                                                                            ?>
                                                                            <input class="form-control" name="token"
                                                                                type="hidden"
                                                                                value="<?php echo $hash ?>">
                                                                            <div class="mt-3 text-center">
                                                                                <button type="submit" name="changesuporter"
                                                                                    class="btn btn-warning  mr-1">
                                                                                    بررسی فایل و جایگزینی
                                                                                </button>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </form>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                                <?php if (isset($_SESSION['success'])) { ?>
                                                    <div class="alert alert-success" role="alert">
                                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                            type="button">
                                                            <span aria-hidden="true">×</span>
                                                        </button>
                                                        <?= $_SESSION['success'] ?>
                                                    </div>
                                                <?php unset($_SESSION['success']);
                                                } ?>
                                            </div>
                                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                                <div class="card custom-card overflow-hidden">
                                                    <div class="card-header  border-bottom-0 pb-0">
                                                        <div>
                                                            <div class="d-flex">
                                                                <label class="main-content-label my-auto pt-2">تعداد
                                                                    دانش آموزان هر دوره
                                                                </label>

                                                            </div>
                                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> </span>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="col-xl-12">
                                                            <div class="table">
                                                                <table class="table table-bordered mg-b-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>ردیف</th>
                                                                            <th>دوره</th>
                                                                            <th>تعداد</th>
                                                                            <th>اقدامات</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $sql = "SELECT *, COUNT(*) AS count FROM `dbcourse` INNER JOIN `codtbl_course` ON dbcourse.codecourse = codtbl_course.codecourse WHERE  dbcourse.activityuser= 1 AND codtbl_course.activecourse= 1 GROUP BY dbcourse.codecourse ORDER BY dbcourse.codecourse DESC";
                                                                        $stmt = $conn->prepare($sql);
                                                                        $stmt->execute();
                                                                        $courses = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                                        $id = 1;
                                                                        if ($stmt->rowCount() > 0) {
                                                                            foreach ($courses as $course) {
                                                                                if (iscourseActivate($course->codecourse)) {
                                                                                    $coursee = returnCourse($course->codecourse);
                                                                        ?>
                                                                                    <tr>
                                                                                        <td class="wd-15"><?= $id ?></td>
                                                                                        <td class="wd-500"><?= $coursee->coursename ?></td>
                                                                                        <td class="wd-500"><?= $course->count ?></td>
                                                                                        <td class="wd-5">
                                                                                            <div class="text-wrap">
                                                                                                <div class="text-center">
                                                                                                    <div class="dropdown">
                                                                                                        <button aria-expanded="false"
                                                                                                            aria-haspopup="true"
                                                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                                            data-bs-toggle="dropdown"
                                                                                                            type="button">
                                                                                                            اقدام<i
                                                                                                                class="fas fa-caret-down mr-1"></i>
                                                                                                        </button>
                                                                                                        <div class="dropdown-menu tx-8">
                                                                                                            <a class="dropdown-item text-success"
                                                                                                                href="#"
                                                                                                                onclick="window.open('suportercourses.php?code='+'<?php echo $course->codecourse ?>','مشاهده دوره'
                                                                                                           ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' ); return false;">
                                                                                                                <i class="fe fe-edit"></i>
                                                                                                                مشاهده دانش آموزان</a>

                                                                                                            <a class="dropdown-item text-danger"
                                                                                                                href="?code=<?= $course->codecourse ?>&delallcourse=true"
                                                                                                                onclick="return confirm('آیا با حذف تمامی پیام های دوره موافقید ؟')"><i
                                                                                                                    class="fe fe-x-square"></i>
                                                                                                                حذف تمامی پیام های
                                                                                                                دوره</a>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>
                                                                                    </tr>
                                                                        <?php $id++;
                                                                                }
                                                                            }
                                                                        } ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <!-- COL END -->

                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="col-sm-12 col-lg-12 col-xl-4">
                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                                <div class="card custom-card overflow-hidden">
                                    <div class="card-header  border-bottom-0 pb-0">
                                        <div>
                                            <div class="d-flex">
                                                <label class="main-content-label my-auto pt-2">فعالیت امروز </label>

                                            </div>
                                            <span class="d-block tx-12 mt-2 mb-0 text-muted"> تعداد اقدامات پشتیبانی ثبت شده امروز </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-6 my-auto">
                                                <h6 class="mb-3 font-weight-normal">گزارش پشتیبانی </h6>
                                                <div class="text-right">
                                                    <?php
                                                    $datenow = date('Y-m-d');
                                                    $sql = 'SELECT COUNT(*) as counReportdate FROM suport_report WHERE  datenow=?';
                                                    $exe1 = $conn->prepare($sql);
                                                    $exe1->execute([$datenow]);
                                                    $natije1 = $exe1->fetch();
                                                    ?>
                                                    <h3 class="font-weight-bold ml-3 mb-2 text-primary"><?php echo $natije1['counReportdate']; ?>
                                                        گزارش </h3>
                                                    <p class="tx-13 my-auto text-muted"> <?php
                                                                                            echo jdate('l Y/m/d ');
                                                                                            ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-6 my-auto">
                                                <div class="forth circle">
                                                    <div class="chart-circle-value circle-style">
                                                        <div class="tx-16 font-weight-bold"><?php echo $natije1['counReportdate']; ?> </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mg-b-30">
                                            </div>
                                            <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                                <div class="card custom-card overflow-hidden">
                                                    <?php

                                                    $report = reportCallLMSmanager($datenow, $datenow);
                                                    ?>
                                                    <ul class="crypto-transcation list-unstyled mg-b-0">
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13"> تماس موفق</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall Successful'] ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی تماس</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS VoiceCall NOT'] ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13"> متنی موفق</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT Successful'] ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13"> عدم پاسخگویی متنی</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات پشتیبان ها</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $report['LMS TEXTSUPPORT NOT'] ?></h5>
                                                            </div>
                                                        </li>

                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-6 col-lg-6  col-xl-6">
                                                <div class="card custom-card overflow-hidden">
                                                    <?php

                                                    $start = $datenow . ' 00:01';
                                                    $end = $datenow . ' 23:59';

                                                    $simotel = reportCallSimotel($start, $end, '', 200000);
                                                    ?>
                                                    <ul class="crypto-transcation list-unstyled mg-b-0">
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13">پاسخ داده شده</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                    : <?php echo $simotel['TIME ANSWERED'] ?> </p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13">عدم پاسخ</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                    : <?php echo $simotel['TIME NO ANSWERED'] ?> </p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['NO ANSWERED'] ?></h5>
                                                            </div>
                                                        </li>
                                                        <li class="list-item mb-0 pl-3 pr-3 mt-0 pb-3">
                                                            <div class="media-body ">
                                                                <p class="tx-medium mg-b-3 tx-13"> کل تماس شما</p>
                                                                <p class="tx-11 mg-b-0 tx-gray-900"> زمان
                                                                    : <?php echo $simotel['TIME TOTAL'] ?> </p>
                                                                <p class="tx-11 mg-b-0 tx-gray-500">گزارشات مرکز تماس</p>
                                                            </div>
                                                            <div class="text-right mr-auto my-auto">
                                                                <h5 class="font-weight-semibold tx-16 mb-0"><?php echo $simotel['ANSWERED'] + $simotel['NO ANSWERED']; ?></h5>
                                                            </div>
                                                        </li>


                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="row g-2">
                                                <!-- Button 1 -->
                                                <?php if (!in_array($adminRole, [2, 3], true)): ?>
                                                    <div class="col-12 col-sm-6">
                                                        <div class="btn ripple btn-outline-primary w-100"
                                                            style="white-space: nowrap; min-width: 140px;"
                                                            onclick="window.open('advanced-report.php','گزارش پیشرفته تماس',
                                                                'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                            <i class="fe fe-database"></i>
                                                            گزارش پیشرفته
                                                        </div>
                                                    </div>
                                                <?php endif; ?>


                                                <!-- Button 2 -->
                                                <div class="col-12 col-sm-6">
                                                    <div class="btn ripple btn-outline-danger w-100"
                                                        style="white-space: nowrap; min-width: 140px;"
                                                        onclick="window.open('operatorsreport.php','مقایسه گزارشات',
                                                            'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                        <i class="fe fe-database"></i>
                                                        مقایسه گزارشات
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- COL END -->
                            <?php if (!in_array($adminRole, [2, 3], true)): ?>

                                <div class="row row-sm">
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="col-lg-12 mg-b-25 ">
                                            <a href="<?php echo url('admin/panel.php/') ?>  " target="_blank"
                                                class="btn btn-block btn-outline-info"><i
                                                    class="fe fe-list"></i> ثبت و ایجاد چک لیست </a>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="col-lg-12 mg-b-25 ">
                                            <a href="<?php echo url('admin/panel.php/') ?>  " target="_blank"
                                                class="btn btn-block btn-outline-warning"><i
                                                    class="fe fe-list"></i> گزارش چک لیست های ثبتی </a>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="col-lg-12 mg-b-25 ">
                                            <a class="btn btn-block btn-outline-success" onclick="window.open('dahak_report.php','لیست دهک های 1 تا 4',
                                        'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"><i
                                                    class="fe fe-list"></i> لیست دهک های 1 تا 4 </a>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                        <div class="col-lg-12 mg-b-25 ">
                                            <a class="btn btn-block btn-outline-secondary" onclick="window.open('misscall_report.php','لیست دهک های 1 تا 4',
                                        'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')"><i
                                                    class="fe fe-list"></i> لیست miss call ها </a>
                                        </div>
                                    </div>


                                </div>
                            <?php endif; ?>

                        </div>
                        <!-- COL END -->
                        </div>
                    </div>
            </div>

        </div>

    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>