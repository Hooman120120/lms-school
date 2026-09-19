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
//if (!Permision('permision_viewcourse')) {
//    header('location: ' . url('admin/index.php'));
//    die();
//}
if (!isset($_GET['id'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!checkvalidcourse(intval($_GET['id']))) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['addatendance'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        $dars = trim($_POST['dars']);
        $codecourse = intval($_GET['id']);
        $duedate = $_POST['datestart'];
        $duedate = explode('/', $duedate);
        $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
        $duedate = implode('-', $duedate);
        $timeok = intval($_POST['time']);
        if ($timeok < 0) {
            $timeok = 0;
        }
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
            echo "<script>alert('   فقط فایل CSV انتخاب کنید ');</script>";

        } else {
            $basePath = __DIR__;
            if (
                move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $basePath . '/ex/hozor.csv')) {
                $CSVfp = fopen("ex/hozor.csv", "r");
                if ($CSVfp !== FALSE) {
                    $icc = 0;

                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 10000, ",");

                        if ($data) {
                            if ($icc != 0) {
                                if ($data[2] != '') {

                                    $sql = "INSERT INTO stdhozor (`user`, `hozor`) VALUES ('" . $data[2] . "', '" . $data[3] . "');\r\n\n";
                                    $ex = $conn->exec($sql);
                                }
                            }
                            $icc++;
                        }
                    }
                }
                fclose($CSVfp);
                $inf = 'INSERT INTO tbl_attendanced(classname,codecourse,datestart) VALUES (?,?,?)';
                $tbl = $conn->prepare($inf);
                $tbl->execute([$dars, $codecourse, $duedate]);
                $lastid = $conn->lastInsertId();

                $sql = 'SELECT * FROM dbcourse WHERE activityuser=1 AND codecourse=? ';
                $crd = $conn->prepare($sql);
                $crd->execute([$codecourse]);
                $dbres = $crd->fetchAll(PDO::FETCH_OBJ);
                if ($crd->rowCount() > 0) {
                    foreach ($dbres as $plan) {
                        $indib = 'INSERT INTO dbattendance (coding,username) VALUES (?,?)';
                        $qact = $conn->prepare($indib);
                        $qact->execute([$lastid, $plan->username]);
                    }
                }

                // پیداکردن مشترک ها و جمع ساعات حضور

                $sql = 'SELECT `id`,`user`,TIME_TO_SEC(hozor) as hozor1 FROM stdhozor';
                $str = $conn->prepare($sql);
                $str->execute();
                $result = $str->fetchAll(PDO::FETCH_OBJ);
                if ($str->rowCount() > 0) {
                    foreach ($result as $rsb) {

                        $id = $rsb->id;
                        $user = $rsb->user;
                        $hozor = $rsb->hozor1;

                        foreach ($result as $bbs) {
                            $isd = $bbs->id;
                            $users = $bbs->user;
                            $hozors = $bbs->hozor1;

                            if ($id != $isd and $user == $users) {
                                $sumh = $hozors + $hozor;
                                $B = $sumh;
                                $saat = intval($B / 3600);
                                $minn = $B - ($saat * 3600);
                                $Min = intval($minn / 60);
                                $secn = $minn - ($Min * 60);
                                $sec = $secn;
                                $ss = $saat . ":" . $Min . ":" . $sec;
                                $update = "UPDATE stdhozor SET hozor=? WHERE id=?";
                                $wer = $conn->prepare($update);
                                $wer->execute([$ss, $id]);


                            }

                        }
                    }
                }
                $what = 'SELECT * from stdhozor ;';
                $query = $conn->prepare($what);
                $query->execute();
                $red = $query->fetchAll(PDO::FETCH_OBJ);
                if ($query->rowCount() > 0) {
                    foreach ($red as $fv) {
                        checkDublicate($fv->user, $fv->id);
                    }
                }
                $what1 = 'SELECT * from stdhozor ;';
                $query2 = $conn->prepare($what1);
                $query2->execute();
                $red2 = $query2->fetchAll(PDO::FETCH_OBJ);
                if ($query2->rowCount() > 0) {
                    foreach ($red2 as $fv2) {
                        $up = 'UPDATE dbattendance SET timehozor =? WHERE `username`=? AND coding=?';
                        $query520 = $conn->prepare($up);
                        $query520->execute([$fv2->hozor, $fv2->user, $lastid]);

                    }
                }
                $what2 = 'SELECT `id`,`user`,TIME_TO_SEC(hozor) as hozor2 FROM stdhozor';
                $query3 = $conn->prepare($what2);
                $query3->execute();
                $red3 = $query3->fetchAll(PDO::FETCH_OBJ);
                if ($query3->rowCount() > 0) {
                    foreach ($red3 as $fv3) {
                        ghayeb($fv3->user, $fv3->hozor2, $timeok, $lastid);

                    }

                }
                $sql = 'TRUNCATE `stdhozor` ';
                $query = $conn->prepare($sql);
                $query->execute();
                $text = 'لیست حضور غیاب با موفقیت ایجاد شد';
// update color student support
                $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.codecourse=? ORDER BY dbcourse.username ASC';
                $query = $conn->prepare($sql);
                $query->execute([1, 1, $codecourse]);
                $result = $query->fetchAll(PDO::FETCH_OBJ);
                $user = array();
                if ($query->rowCount() > 0) {
                    $gh = 0;
                    $haazor = 0;
                    $id = 1;
                    foreach ($result as $res) {
                        if ($res->type_process != 2) {
                            $amar = getTblattendance($res->codecourse, $res->username);
                            $total = $amar[0] + $amar[1];
                            if ($total > 0) {
                                $darsadhozor = $amar[0] / $total;
                                $darsadhozor = $darsadhozor * 100;
                            } else {
                                $darsadhozor = 0;
                            }
                            if ($darsadhozor < 25) {
                                $ccc = 0;
                            }
                            if ($darsadhozor >= 25 and $darsadhozor < 50) {
                                $ccc = 1;
                            }
                            if ($darsadhozor >= 50 and $darsadhozor < 75) {
                                $ccc = 2;
                            }
                            if ($darsadhozor >= 75) {
                                $ccc = 3;
                            }
                            $sql = 'UPDATE dbcourse SET color=? WHERE username=? AND codecourse=?';
                            $qwecolor = $conn->prepare($sql);
                            $qwecolor->execute([$ccc, $res->username, $codecourse]);


                        }
                    }
                }
            }
        }
    }
}
if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    $sql = 'DELETE FROM tbl_attendanced WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$del]);
    header('location:attendance.php?id=' . $_GET['id'] . ' ');
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> ثبت حضور غیاب | <?php echo __TITLE ?></title>
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
                <div class="row row-sm">
                    <div class="col-lg-3">
                        <div class="card custom-card">
                            <?php $course = returnCourse($_GET['id']);
                             if (Permision('permision_viewcourse')) { ?>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-xl-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">افزودن حضورغیاب </h6>
                                            <p class="text-muted card-sub-title"><?php echo $course->coursename ?>
                                            </p>
                                        </div>
                                    </div>

                                </div>
                                <?php if (isset($text)) { ?>
                                    <div class="alert alert-success" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $text ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($erordublicate)) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erordublicate ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($erortoken)) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erortoken ?>
                                    </div>
                                <?php } ?>
                                <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-lg-12 mg-t-20">
                                            <p class="mg-b-10">نام درس و جلسه:<span
                                                        class="tx-danger">*</span></p>
                                            <input class="form-control "
                                                   placeholder="فیزیک نهم جلسه 3"
                                                   type="text" name="dars" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">
                                        <div class="col-lg-12 mg-t-20">
                                            <p class="mg-b-10">تاریخ برگزاری کلاس<span
                                                        class="tx-danger">*</span></p>
                                            <div class="input-group ">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        <i class="fe fe-calendar lh--9 op-6 "></i>
                                                    </div>
                                                </div>
                                                <?php
                                                $datenow = date("Y-m-d");
                                                $datenow = explode('-', $datenow);
                                                $datenow = gregorian_to_jalali($datenow[0], $datenow[1], $datenow[2]);
                                                $datenow = implode('/', $datenow);
                                                ?>
                                                <input class="form-control "
                                                       placeholder="1404/02/20" value="<?php echo $datenow ?>"
                                                       type="text" name="datestart" required>
                                            </div>
                                        </div>
                                        <div class="input-group file-browser mt-3">
                                            <input type="text"
                                                   class="form-control border-left-0 browse-file"
                                                   placeholder=" فایل حضور و غیاب اسکای روم را بارگذاری کنید*"
                                                   readonly="">
                                            <label class="input-group-btn">
														<span class="btn btn-primary">
															انتخاب<input type="file" name="fileToUpload"
                                                                         style="display: none;" required>
														</span>
                                            </label>
                                        </div>
                                        <div class="input-group mt-3">
                                            <p class="mg-b-10">زمان مجاز حضور:<span
                                                        class="tx-danger">*</span></p>
                                        </div>
                                        <div class="input-group-prepend">
                                            <input type="number" class="form-control" name="time" value="900"
                                                   required="">
                                            <div class="input-group-text">
                                                ثانیه
                                            </div>
                                        </div>
                                        <div class="text-muted"> زمان مجاز را بصورت ثانیه وارد کنید</div>
                                    </div>
                                    <div class="row">
                                        <div class="text-center mg-t-30">
                                            <button class="btn ripple  btn-success pd-x-20"
                                                    type="submit"
                                                    name="addatendance">ثبت حضور غیاب
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-xl-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">لیست کلاس های برگزار شده</h6>
                                        <p class="text-primary card-sub-title"><?php echo $course->coursename ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>مشاهده</th>
                                         <?php   if (Permision('permision_viewcourse')) { ?>
                                            <th>اکسل</th>
                                            <?php }?>
                                            <th>نام درس و جلسه</th>
                                            <th>تاریخ برگزاری</th>
                                            <?php   if (Permision('permision_viewcourse')) { ?>
                                            <th>حاضرین</th>
                                            <th>غائبین</th>
                                            <th>حذف</th>
                                            <?php }?>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM tbl_attendanced WHERE codecourse=? ORDER BY datestart DESC ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['id']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            foreach ($result

                                                     as $plr) {
                                                ?>

                                                <tr>
                                                    <td class="wd-15"><?php echo $rt ?></td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('list-attendance.php?code='+'<?php echo $plr->id ?>','مشاهده لیست حضورغیاب'
                                                                     ,'width=1000,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-eye"></i></div>
                                                    </td>
                                                <?php   if (Permision('permision_viewcourse')) { ?>
                                                    <td class="wd-15">
                                                        <a class="btn btn-sm ripple btn-success btn-icon"
                                                           href="export-attendance.php?code=<?php echo $plr->id ?>"
                                                        >
                                                            <i class="fe fe-download-cloud"></i></a>
                                                    </td>
                                                    <?php } ?>
                                                    <td><?php echo $plr->classname ?></td>
                                                    <td class="wd-10"><?php
                                                        $dateno = $plr->datestart;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        echo $dateno;
                                                        ?></td>
                                                    <?php $count = countattendance($plr->id); ?>

                                                    <?php   if (Permision('permision_viewcourse')) { ?>

                                                    <td class="wd-15"> <?php echo $count['hozor'] ?></td>

                                                    <td class="wd-15"><?php echo $count['ghayeb'] ?></td>
                                                    <td class="wd-15">
                                                        <a href="?id=<?php echo $_GET['id'] ?>&delete=<?php echo $plr->id ?>"
                                                           onclick="return confirm('آیا با حذف این حضورغیاب  موافقید؟ این اقدام غیر قابل بازگشت است!')"
                                                           class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                    class="fe fe-x"></i></a>

                                                    </td>
                                                    <?php }?>
                                                </tr>
                                                <?php
                                                $rt++;
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
    </div>
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
