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
$attendanceCourseId = (int)$_GET['id'];
$attendanceClassId = isset($_GET['class']) ? (int)$_GET['class'] : 0;
RequireTeacherPermission('teacher_perm_attendance', url('admin/course/Active-course.php'));
if ((int)($_SESSION['admin']['adminrole'] ?? -1) === 3) {
    if ($attendanceClassId <= 0 || !TeacherHasClassAccess($attendanceClassId)) {
        http_response_code(403);
        exit('شما به حضور و غیاب این کلاس دسترسی ندارید.');
    }
    $teacherClassStmt = $conn->prepare('SELECT 1 FROM classes WHERE id = ? AND codecourse = ? LIMIT 1');
    $teacherClassStmt->execute([$attendanceClassId, $attendanceCourseId]);
    if (!$teacherClassStmt->fetchColumn()) {
        http_response_code(403);
        exit('پایه و کلاس انتخاب‌شده با یکدیگر مطابقت ندارند.');
    }
} elseif (StaffCourseScopeRequired() && (
    !StaffHasCourseAccess($attendanceCourseId) ||
    ($attendanceClassId > 0 && !StaffHasClassAccess($attendanceClassId))
)) {
    http_response_code(403);
    exit('شما به حضور و غیاب این پایه یا کلاس دسترسی ندارید.');
}
if (!isset($_GET['demo'])) {
    if (!checkvalidcourse(intval($_GET['id']))) {
        header('location: ' . url('admin/index.php'));
        die();
    }
}
if (isset($_POST['addatendance'])) {
    if ($_SESSION['token'] == $_POST['token']) {
        $dars = trim($_POST['dars']);
        $codecourse = intval($_GET['id']);
        $classId = isset($_GET['class']) ? intval($_GET['class']) : null;
        $duedate = $_POST['datestart'];
        $duedate = explode('/', $duedate);
        $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
        $duedate = implode('-', $duedate);
        $timeok = intval($_POST['time']);
        if ($timeok < 0) {
            $timeok = 0;
        }

        // 1. Check Course Type and Group Logic
        $baghal = [30, 31, 32];
        $group_id = null; // Default 0 for normal courses

        // Fetch course info to check type
        if (!isset($_POST['demo'])) {
            // Assuming table is codtbl_course based on standard structure, or getting it from dbcourse join
            // Using returnCourse helper if available, otherwise direct query:
            $sqlInfo = "SELECT course_type FROM codtbl_course WHERE codecourse = ?";
            $stmtInfo = $conn->prepare($sqlInfo);
            $stmtInfo->execute([$codecourse]);
            $resInfo = $stmtInfo->fetch(PDO::FETCH_OBJ);

            if ($resInfo && in_array($resInfo->course_type, $baghal)) {
                if (isset($_POST['group_id']) && !empty($_POST['group_id'])) {
                    $group_id = intval($_POST['group_id']);
                } else {
                    echo "<script>alert('لطفا گروه را انتخاب کنید');</script>";
                    // Stop execution or handle error
                }
            }
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
            if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $basePath . '/ex/hozor.csv')) {

                $isProblematicSession = false; // flag default

                $CSVfp = fopen("ex/hozor.csv", "r");
                if ($CSVfp !== FALSE) {
                    $icc = 0;

                    while (!feof($CSVfp)) {
                        $data = fgetcsv($CSVfp, 10000, ",");

                        if ($data) {
                            // first row, first column: check for "problematic"
                            if ($icc == 0) {
                                if (isset($data[0]) && strtolower(trim($data[0])) === 'problematic') {
                                    $isProblematicSession = true;
                                    // if you want you could break here
                                }
                            } else {
                                // only insert into stdhozor if NOT problematic session
                                if (!$isProblematicSession && $data[2] != '') {
                                    $sql = "INSERT INTO stdhozor (`user`, `hozor`) VALUES ('" . $data[2] . "', '" . $data[3] . "');\r\n\n";
                                    $ex = $conn->exec($sql);
                                }
                            }
                            $icc++;
                        }
                    }

                    fclose($CSVfp);
                }

                $inf = 'INSERT INTO tbl_attendanced(classname,codecourse,datestart,class_id,group_id) VALUES (?,?,?,?,?)';
                $tbl = $conn->prepare($inf);
                $tbl->execute([$dars, $codecourse, $duedate, $classId, $group_id]);
                $lastid = $conn->lastInsertId();
                if (!isset($_POST['demo'])) {
                    if ($group_id != null) {
                        // It is a group course, filter by group_id
                        $sql = 'SELECT * FROM dbcourse WHERE activityuser=1 AND codecourse=? AND group_id=?';
                        $crd = $conn->prepare($sql);
                        $crd->execute([$codecourse, $group_id]);
                    } else {
                        // Standard course
                        $sql = 'SELECT * FROM dbcourse WHERE activityuser=1 AND codecourse=? AND class_id=?';
                        $crd = $conn->prepare($sql);
                        $crd->execute([$codecourse, $classId]);
                    }
                } else {
                    $sql = 'SELECT * from dbcourse_demo WHERE codecourse= ?';
                    $crd = $conn->prepare($sql);
                    $crd->execute([$codecourse]);
                }
                $dbres = $crd->fetchAll(PDO::FETCH_OBJ);
                if ($crd->rowCount() > 0) {
                    foreach ($dbres as $plan) {
                        $indib = 'INSERT INTO dbattendance (coding,username) VALUES (?,?)';
                        $qact = $conn->prepare($indib);
                        $qact->execute([$lastid, $plan->username]);
                    }
                }

                if ($isProblematicSession) {

                    // mark all rows for this session as problematic
                    $up = 'UPDATE dbattendance 
                               SET status = ?, timehozor = ?
                               WHERE coding = ?';
                    $query = $conn->prepare($up);
                    $query->execute(['problematic', 'problematic', $lastid]);

                    // clean temp table just in case
                    $sql = 'TRUNCATE `stdhozor` ';
                    $query = $conn->prepare($sql);
                    $query->execute();

                    $text = 'این جلسه به‌صورت "problematic" ثبت شد و در آمار رنگ‌بندی لحاظ نمی‌شود.';
                } else {

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
                    if (!isset($_POST['demo'])) {
                        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.codecourse=? AND dbcourse.class_id=? ORDER BY dbcourse.username ASC';
                        $query = $conn->prepare($sql);
                        $query->execute([1, 1, $codecourse, $classId]);
                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                        $user = array();
                        if ($query->rowCount() > 0) {
                            $gh = 0;
                            $haazor = 0;
                            $id = 1;
                            foreach ($result as $res) {
                                if ($res->type_process != 2) {
                                    $amar = getTblattendanceWithClass($res->codecourse, $res->class_id, $res->username);
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
                                    $sql = 'UPDATE dbcourse SET color=? WHERE username=? AND codecourse=? AND class_id=?';
                                    $qwecolor = $conn->prepare($sql);
                                    $qwecolor->execute([$ccc, $res->username, $codecourse, $classId]);
                                }
                            }
                        }
                    }
                } // end normal case
            }
        }
    }
}
if (isset($_POST['updateAtt'])) {
    $id = $_GET['id'];
    $classname = $_POST['dars'];
    $duedate = $_POST['datestart'];
    $duedate = explode('/', $duedate);
    $duedate = jalali_to_gregorian($duedate[0], $duedate[1], $duedate[2]);
    $duedate = implode('-', $duedate);
    $sql = 'UPDATE tbl_attendanced SET classname = ?, datestart = ? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$classname, $duedate, $_GET['edit']]);
    header('Location: attendance.php?id=' . $id . isset($_GET['demo']) ? '&demo' : '');
    die();
}
if (isset($_GET['edit'])) {
    $sql = 'SELECT * FROM tbl_attendanced WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['edit']]);
    $currentAttendance = $stmt->fetch(PDO::FETCH_OBJ);
    $datestart = explode('-', $currentAttendance->datestart);
    $datestart = gregorian_to_jalali($datestart[0], $datestart[1], $datestart[2]);
    $datestart = implode('/', $datestart);
}

if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    $sql = 'DELETE FROM tbl_attendanced WHERE id=?';
    $query = $conn->prepare($sql);
    $query->execute([$del]);
    header('location:attendance.php?id=' . $_GET['id'] . isset($_GET['demo']) ? '&demo' : '');
}

$baghal = [30, 31, 32];

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
                    <?php
                    if (!isset($_GET['demo'])) {
                        $course = returnCourse($_GET['id']);
                        $sql = 'SELECT * FROM classes WHERE id = ?';
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_GET['class']]);
                        $class = $stmt->fetch(PDO::FETCH_OBJ);
                    } else {
                        $sql = 'SELECT * FROM course_demo WHERE codecourse = ?';
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_GET['id']]);
                        $course = $stmt->fetch(PDO::FETCH_OBJ);
                    } ?>
                    <?php if (Permision('permision_viewcourse')) { ?>
                    <div class="col-lg-3">
                        <div class="card custom-card">

                            <div class="card-body">
                                <div class="row">
                                    <div class="col-xl-12">
                                        <div>

                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    <?= isset($_GET['edit'])
                                                        ? 'ویرایش حضور و غیاب'
                                                        : 'افزودن حضور و غیاب' ?>
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="<?= isset($_GET['edit'])
                                                          ? 'در این بخش می‌توانید اطلاعات حضور و غیاب ثبت‌شده دانش آموزان این کلاس را بررسی و ویرایش کنید.'
                                                          : 'در این بخش می‌توانید وضعیت حضور، غیبت و سایر اطلاعات حضور و غیاب دانش آموزان را برای کلاس و پایه انتخاب‌شده ثبت کنید.' ?>"
                                                      aria-label="<?= isset($_GET['edit'])
                                                          ? 'راهنمای ویرایش حضور و غیاب'
                                                          : 'راهنمای افزودن حضور و غیاب' ?>">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                            </div>

                                            <p class="text-muted card-sub-title">
                                                <?php
                                                echo htmlspecialchars(
                                                    $course->coursename . ' (' . $class->name . ')',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
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
                                                   type="text" name="dars"
                                                   value="<?= isset($_GET['edit']) ? $currentAttendance->classname : '' ?>"
                                                   required>
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
                                                <input class="form-control fc-datepicker"
                                                       placeholder="1404/02/20"
                                                       value="<?= isset($_GET['edit']) ? $datestart : $datenow ?>"
                                                       type="text" name="datestart" required>
                                            </div>
                                        </div>
                                        <?php if (!isset($_GET['edit'])) { ?>
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
                                            <?php
                                            if (!isset($_GET['demo']) and in_array($course->course_type, $baghal)) { ?>
                                                <div class="input-group mt-3">
                                                    <label class="input-group">
                                                        انتخاب گروه <span class="tx-danger">*</span>
                                                    </label>

                                                    <?php
                                                    $sql = 'SELECT * FROM handhug_groups WHERE codecourse = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$_GET['id']]);
                                                    $groups = $stmt->fetchAll(PDO::FETCH_OBJ);

                                                    if ($stmt->rowCount() > 0): ?>
                                                        <select class="form-control select2" required name="group_id">
                                                            <option value="">انتخاب کنید...</option>
                                                            <?php foreach ($groups as $group): ?>
                                                                <option value="<?= $group->id ?>"><?= $group->group_name ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning w-100">
                                                            <i class="fa fa-exclamation-triangle me-2"></i>
                                                            گروهی برای این پایه تعریف نشده است.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php } ?>
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
                                        <?php } ?>
                                    </div>
                                    <div class="row">
                                        <?php if (isset($_GET['demo'])) { ?>
                                            <input type="hidden" name="demo" value="demo">
                                        <?php } ?>
                                        <div class="text-center mg-t-30">
                                            <button class="btn ripple <?= isset($_GET['edit']) ? 'btn-warning' : 'btn-success' ?> pd-x-20"
                                                    type="submit" <?= (!isset($_GET['demo']) and in_array($course->course_type, $baghal) and $stmt->rowCount() <= 0) ? 'disabled' : '' ?>
                                                    name="<?= isset($_GET['edit']) ? 'updateAtt' : 'addatendance' ?>"><?= isset($_GET['edit']) ? 'ویرایش حضور و غیاب' : 'افزودن حضور و غیاب' ?>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <?php if (!isset($_GET['edit'])) { ?>
                                <p class="d-flex align-self-center flex-column">
                                    <a href="<?php echo url('assets/doc/problematic.csv') ?>"
                                       class="btn btn-info ripple"> دانلود فایل نمونه</a>
                                </p>
                                <p class="d-flex align-self-center flex-column m-4" style="color: gray;">
                                    برای حضور و غیاب هایی که فایلی موجود نیست یا مشکلی دارند فایل نمونه را دانلود کرده و
                                    بدون تغییر آن به جای فایل حضورغیاب آپلود کنید , بقیه اطلاعات مثل قبل.
                                </p>

                            <?php }
                            } ?>
                        </div>
                    </div>
                    <div <?php if (Permision('permision_viewcourse')) { ?> class="col-lg-9" <?php } ?>
                            style="width: 100%; ">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-xl-12">
                                    <div>
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                لیست کلاس‌های برگزارشده
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید فهرست جلسات و کلاس‌های برگزارشده مربوط به پایه و کلاس انتخاب‌شده را مشاهده کنید و اطلاعات هر جلسه را برای بررسی حضور و غیاب و جزئیات برگزاری در دسترس داشته باشید.البته در دوره‌های فوق برنامه موجودیت کلاس وجود ندارد"
                                                  aria-label="راهنمای لیست کلاس‌های برگزارشده">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <p class="text-primary card-sub-title">
                                            <?php
                                            $class = isset($class->name) ? ' (' . $class->name . ')' : '';
                                            echo htmlspecialchars(
                                                $course->coursename . $class,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>مشاهده</th>
                                            <?php if (Permision('permision_viewcourse')) { ?>
                                                <th>اکسل</th>
                                            <?php } ?>
                                            <th>نام درس و جلسه</th>
                                            <?php if (!isset($_GET['demo']) and in_array($course->course_type, $baghal)) { ?>
                                                <th>گروه مربوطه</th>
                                            <?php } ?>
                                            <th>تاریخ برگزاری</th>
                                            <?php if (Permision('permision_viewcourse')) { ?>
                                                <th>حاضرین</th>
                                                <th>غائبین</th>
                                                <th>حذف</th>
                                                <th>ویرایش</th>
                                            <?php } ?>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM tbl_attendanced WHERE codecourse=? AND class_id=? ORDER BY datestart DESC ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['id'], $_GET['class']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            foreach (
                                                $result

                                                as $plr
                                            ) {
                                                ?>

                                                <tr>
                                                    <td class="wd-15"><?php echo $rt ?></td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('list-attendance.php?code='+'<?php echo $plr->id ?><?= isset($_GET['demo']) ? '&demo' : "&class=$class->id" ?>','مشاهده لیست حضورغیاب'
                                                                     ,'width=1000,height=800,menubar=0,toolbar=0,resizable=0,left=180,top=100,status=0,scrollbars=0,location=0' )">
                                                            <i class="fe fe-eye"></i>
                                                        </div>
                                                    </td>
                                                    <?php if (Permision('permision_viewcourse')) { ?>
                                                        <td class="wd-15">
                                                            <a class="btn btn-sm ripple btn-success btn-icon"
                                                               href="export-attendance.php?code=<?php echo $plr->id ?>">
                                                                <i class="fe fe-download-cloud"></i></a>
                                                        </td>
                                                    <?php } ?>
                                                    <td><?php echo $plr->classname ?></td>
                                                    <?php if (!isset($_GET['demo']) and in_array($course->course_type, $baghal)) {
                                                        $sqlGroup = 'SELECT * FROM handhug_groups WHERE id = ?';
                                                        $stmtGroup = $conn->prepare($sqlGroup);
                                                        $stmtGroup->execute([$plr->group_id]);
                                                        $group = $stmtGroup->fetch(PDO::FETCH_OBJ);
                                                        ?>
                                                        <td><?= $group->group_name ?></td>
                                                    <?php } ?>
                                                    <td class="wd-10"><?php
                                                        $dateno = $plr->datestart;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        echo $dateno;
                                                        ?></td>
                                                    <?php if (!isset($_GET['demo'])) {
//                                                        $count = countattendance($plr->id);
                                                        $sab = 'SELECT COUNT(*) as ghc FROM dbattendance INNER JOIN dbcourse ON dbattendance.username = dbcourse.username WHERE dbattendance.coding = ? AND dbattendance.status = ? AND dbcourse.class_id = ?';
                                                        $query1 = $conn->prepare($sab);
                                                        $query1->execute([$plr->id, 'غایب', $_GET['class']]);
                                                        $ter = $query1->fetch();
                                                        $sab = 'SELECT COUNT(*) as hoco FROM dbattendance INNER JOIN dbcourse ON dbattendance.username = dbcourse.username WHERE dbattendance.coding = ? AND dbattendance.status != ? AND dbcourse.class_id = ?';
                                                        $query2 = $conn->prepare($sab);
                                                        $query2->execute([$plr->id, 'غایب', $_GET['class']]);
                                                        $ert = $query2->fetch();
                                                        $count = array (
                                                                "ghayeb" => $ter['ghc'],
                                                                "hozor" =>  $ert['hoco']
                                                        );
                                                    } else {
                                                        $sab = 'SELECT COUNT(*) as ghc FROM dbattendance WHERE coding=? AND status=? AND CHAR_LENGTH(username) = 11';
                                                        $query1 = $conn->prepare($sab);
                                                        $query1->execute([$plr->id, 'غایب']);
                                                        $ter = $query1->fetch();
                                                        $sab = 'SELECT COUNT(*) as hoco FROM dbattendance WHERE coding=? AND status !=? AND CHAR_LENGTH(username) = 11';
                                                        $query2 = $conn->prepare($sab);
                                                        $query2->execute([$plr->id, 'غایب']);
                                                        $ert = $query2->fetch();

                                                        $count = array(
                                                            "ghayeb" => $ter['ghc'],
                                                            "hozor" => $ert['hoco']
                                                        );
                                                    } ?>

                                                    <?php if (Permision('permision_viewcourse')) { ?>

                                                        <td class="wd-15"> <?php echo $count['hozor'] ?></td>

                                                        <td class="wd-15"><?php echo $count['ghayeb'] ?></td>
                                                        <td class="wd-15">
                                                            <a href="?id=<?php echo $_GET['id'] ?>&delete=<?php echo $plr->id ?><?= isset($_GET['demo']) ? '&demo' : '' ?>"
                                                               onclick="return confirm('آیا با حذف این حضورغیاب  موافقید؟ این اقدام غیر قابل بازگشت است!')"
                                                               class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                        class="fe fe-x"></i></a>

                                                        </td>
                                                        <td class="wd-15">
                                                            <a href="?id=<?= $_GET['id'] ?>&edit=<?php echo $plr->id ?><?= isset($_GET['demo']) ? '&demo' : '' ?>"
                                                               class="btn btn-warning" title="ویرایش"><i
                                                                        class="fe fe-edit"></i></a>
                                                        </td>
                                                    <?php } ?>
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
