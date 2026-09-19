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
if (!Permision('permision_viewtest')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['addquiz'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if (checkDubDars($_POST['dars'], $_POST['tasalsol'], $_POST['codecourse'])) {
            $code = intval($_POST['codecourse']);
            $namequiz = trim($_POST['name-quiz']);
            $datestart = $_POST['datestart'];
            $dars = $_POST['dars'];
            $tasalsol = $_POST['tasalsol'];
            $datestart = explode('/', $datestart);
            $datestart = jalali_to_gregorian($datestart[0], $datestart[1], $datestart[2]);
            $datestart = implode('-', $datestart);
            $insql = 'INSERT INTO `dbquiz`(codecourse,dars,tasalsol,quizname,datestart) VALUES (?,?,?,?,?)';
            $qss = $conn->prepare($insql);
            $qss->execute([$code, $dars, $tasalsol, $namequiz, $datestart]);
            $lastId = $conn->lastInsertId();
            $text = 'پایه با شناسه <strong>' . $lastId . '</strong> ایجاد شد';
        } else {
            $erortoken = 'این آزمون از قبل ایجاد شده است';
        }
    } else {
        $erortoken = 'خطای غیر منتظره رخ داد ! ';
    }
}
if (isset($_POST['uaddquiz'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $quiz = retDbquiz($_GET['edit']);
        if ($quiz->dars == $_POST['udars'] and $quiz->tasalsol == $_POST['utasalsol'] and $quiz->codecourse == $_POST['ucodecourse']) {

            $code = intval($_POST['ucodecourse']);
            $namequiz = trim($_POST['uname-quiz']);
            $datestart = $_POST['udatestart'];
            $datestart = explode('/', $datestart);
            $datestart = jalali_to_gregorian($datestart[0], $datestart[1], $datestart[2]);
            $datestart = implode('-', $datestart);
            $upd = 'UPDATE dbquiz SET codecourse=?,quizname=?,datestart=? WHERE idquiz=?';
            $qup = $conn->prepare($upd);
            $qup->execute([$code, $namequiz, $datestart, $_GET['edit']]);
            $text = 'آزمون با موفقیت ویرایش شد !!!';
            header('location: addquiz.php');
        } else {
            if (checkDubDars($_POST['udars'], $_POST['utasalsol'], $_POST['ucodecourse'])) {
                $code = intval($_POST['ucodecourse']);
                $namequiz = trim($_POST['uname-quiz']);
                $datestart = $_POST['udatestart'];
                $dars = $_POST['udars'];
                $tasalsol = $_POST['utasalsol'];
                $datestart = explode('/', $datestart);
                $datestart = jalali_to_gregorian($datestart[0], $datestart[1], $datestart[2]);
                $datestart = implode('-', $datestart);
                $upd = 'UPDATE dbquiz SET codecourse=?,dars=?,tasalsol=?,quizname=?,datestart=? WHERE idquiz=?';
                $qup = $conn->prepare($upd);
                $qup->execute([$code, $dars, $tasalsol, $namequiz, $datestart, $_GET['edit']]);
                $text = 'آزمون با موفقیت ویرایش شد !!!';
                header('location: addquiz.php');
            } else {
                $erortoken = 'این آزمون از قبل ایجاد شده است';
            }
        }
    }
}
if (isset($_GET['delete'])) {
    if (Permision('permision_deltest')) {
        $del = intval($_GET['delete']);
        $delquiz = 'DELETE FROM dbquiz WHERE idquiz=?';
        $qdel = $conn->prepare($delquiz);
        $qdel->execute([$del]);
        header('location: addquiz.php');
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
    <title> ثبت آزمون جدید | <?php echo __TITLE ?></title>
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
                    <!--                    -->
                    <?php if (!isset($_GET['edit'])) { ?>
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">افزودن آزمون جدید </h6>
                                                <p class="text-muted card-sub-title">ایجاد یک آزمون جدید برگزار شده
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
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-12 mg-t-10 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام آزمون
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="name-quiz"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="ریاضی نهم شماره 1">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">

                                            <div class="col-lg-12">
                                                <p class="mg-b-10 mg-t-20"> انتخاب کد پایه <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="codecourse"
                                                        required>
                                                    <option label="کد پایه ">
                                                    </option>
                                                    <?php
                                                    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result
                                                                 as $res) { ?>
                                                            <option value="<?php echo $res->codecourse ?>">
                                                                <?php echo $res->coursename . ' | ' . $res->codecourse ?>
                                                            </option>
                                                            <?php
                                                        }
                                                    }
                                                    ?>

                                                </select>
                                            </div>
                                            <div class="col-lg-12">
                                                <p class="mg-b-10 mg-t-20"> انتخاب درس <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="dars"
                                                        required>
                                                    <option label="درس">
                                                    </option>
                                                    <?php
                                                    $wtr = 'SELECT * FROM dbdars';
                                                    $qer = $conn->prepare($wtr);
                                                    $qer->execute();
                                                    $retu = $qer->fetchAll(PDO::FETCH_OBJ);
                                                    foreach ($retu as $rep) {


                                                        ?>
                                                        <option value="<?php echo $rep->id ?>"><?php echo $rep->dars ?></option>
                                                        <?php
                                                    }
                                                    ?>


                                                </select>
                                            </div>
                                            <?php
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">

                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">توالی<span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control "
                                                       placeholder="شماره توالی را وارد کنید"
                                                       type="number" name="tasalsol" required>
                                            </div>

                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">تاریخ برگزاری آزمون<span
                                                            class="tx-danger">*</span></p>

                                                <div class="input-group ">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6 "></i>
                                                        </div>
                                                    </div>
                                                    <input class="form-control fc-datepicker"
                                                           placeholder="1402/10/20"
                                                           type="text" name="datestart" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="text-center mg-t-30">
                                                <button class="btn ripple  btn-success pd-x-20"
                                                        type="submit"
                                                        name="addquiz">ایجاد آزمون
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>


                            </div>
                        </div>
                    <?php }
                    if (isset($_GET['edit'])) {
                        ?>
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-9">
                                            <div>
                                                <h6 class="main-content-label mb-1">افزودن آزمون جدید </h6>
                                                <p class="text-muted card-sub-title">ایجاد یک آزمون جدید برگزار شده
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
                                    <?php
                                    $quiz = retDbquiz($_GET['edit']);
                                    ?>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-12 mg-t-10 ">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام آزمون
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="uname-quiz"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="ریاضی نهم شماره 1"
                                                           value="<?php echo $quiz->quizname ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">

                                            <div class="col-lg-12">
                                                <p class="mg-b-10 mg-t-20"> انتخاب کد پایه <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="ucodecourse"
                                                        required>
                                                    <option label="کد پایه ">
                                                    </option>
                                                    <?php
                                                    $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                    $query = $conn->prepare($sql);
                                                    $query->execute([1]);
                                                    $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                    if ($query->rowCount() > 0) {
                                                        foreach ($result
                                                                 as $res) { ?>
                                                            <option value="<?php echo $res->codecourse ?>" <?php if ($quiz->codecourse == $res->codecourse) {
                                                                echo 'selected';
                                                            } ?> >
                                                                <?php echo $res->coursename . ' | ' . $res->codecourse ?>
                                                            </option>
                                                            <?php
                                                        }
                                                    }
                                                    ?>

                                                </select>
                                            </div>
                                            <div class="col-lg-12">
                                                <p class="mg-b-10 mg-t-20"> انتخاب درس <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="udars"
                                                        required>
                                                    <option label="درس">
                                                    </option>
                                                    <?php
                                                    $wtr = 'SELECT * FROM dbdars';
                                                    $qer = $conn->prepare($wtr);
                                                    $qer->execute();
                                                    $retu = $qer->fetchAll(PDO::FETCH_OBJ);
                                                    foreach ($retu as $rep) {


                                                        ?>
                                                        <option value="<?php echo $rep->id ?>" <?php if ($quiz->dars == $rep->id) {
                                                            echo 'selected';
                                                        } ?>><?php echo $rep->dars ?></option>
                                                        <?php
                                                    }
                                                    ?>


                                                </select>
                                            </div>
                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">توالی<span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control "
                                                       placeholder="شماره توالی را وارد کنید"
                                                       value="<?php echo $quiz->tasalsol ?>"
                                                       type="number" name="utasalsol" required>
                                            </div>
                                            <?php
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">


                                            <div class="col-xl-12 mg-t-20">
                                                <p class="mg-b-10">تاریخ برگزاری آزمون<span
                                                            class="tx-danger">*</span></p>

                                                <div class="input-group ">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            <i class="fe fe-calendar lh--9 op-6 "></i>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    $dateno = $quiz->datestart;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <input class="form-control fc-datepicker"
                                                           placeholder="1402/10/20"
                                                           type="text" name="udatestart" required
                                                           value="<?php echo $dateno ?> ">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="text-center mg-t-30">
                                                <button class="btn ripple  btn-warning pd-x-20"
                                                        type="submit"
                                                        name="uaddquiz"> ویرایش آزمون
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <!--                    -->
                    <div class="col-lg-9">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">آخرین آزمون های برگزار شده</h6>
                                        <p class="text-muted card-sub-title">15 آزمون اخیر
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>شناسه</th>
                                            <th>نام آزمون</th>
                                            <th>درس</th>
                                            <th>توالی</th>
                                            <th>پایه</th>
                                            <th>تاریخ برگزاری</th>
                                            <th>ویرایش</th>
                                            <th>حذف</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbquiz  ORDER BY datestart DESC  LIMIT 15 ';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $id = 1;
                                            foreach ($result

                                                     as $res) {
                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $res->idquiz ?></td>
                                                    <td><?php echo $res->quizname ?></td>

                                                    <td class="wd-20"><?php echo getdars($res->dars) ?></td>
                                                    <td class="wd-10"><?php echo $res->tasalsol ?></td>
                                                    <?php $course = returnCourse($res->codecourse); ?>
                                                    <td class="wd-15"> <?php echo $course->coursename ?></td>
                                                    <?php
                                                    $dateno = $res->datestart;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td class="wd-15"><?php echo $dateno ?></td>
                                                    <td class="wd-15">
                                                        <a href="?edit=<?php echo $res->idquiz ?>"
                                                           class="btn   btn-sm btn-warning btn-icon"><i
                                                                    class="fe fe-edit"></i></a>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php if (Permision('permision_deltest')) { ?>
                                                            <a href="?delete=<?php echo $res->idquiz ?>"
                                                               onclick="return confirm('آیا با حذف این آزمون  موافقید؟ این اقدام غیر قابل بازگشت است!')"

                                                               class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                        class="fe fe-x"></i></a>
                                                        <?php } else { ?>
                                                            <button class="btn ripple btn-sm btn-danger" disabled><i
                                                                        class="fe fe-x"></i></button>
                                                        <?php } ?>

                                                    </td>
                                                </tr>
                                                <?php
                                                $id++;
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
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
