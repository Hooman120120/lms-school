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
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (!checkvalidcourseDemo(intval($_GET['code']))) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $code = intval($_POST['codecourse']);
        $name = trim($_POST['doc-name']);
        $link = trim($_POST['doc-link']);
        switch ($_POST['format']) {
            case 'PDF':
                $format = 'application/pdf';
                break;
            case 'PPTX':
                $format = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                break;
            case 'XLSX':
                $format = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'JPG':
                $format = 'image/jpeg';
                break;
            case 'PNG':
                $format = 'image/png';
                break;
            case 'Docx':
                $format = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                break;
        }

        $sql = 'INSERT INTO dbaddon_demo (codecourse,docname,typedoc,linkdoc,host,datecreate,timecreate) VALUES (?,?,?,?,?,?,?)';
        $query = $conn->prepare($sql);
        $query->execute([$code, $name, $format, $link, $_POST['host'], $datenow, $timenow]);
    }
}
if (isset($_POST['save-update'])) {
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $code = intval($_POST['codecourse']);
    $name = trim($_POST['doc-name']);
    $link = trim($_POST['doc-link']);
    switch ($_POST['format']) {
        case 'PDF':
            $format = 'application/pdf';
            break;
        case 'PPTX':
            $format = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            break;
        case 'XLSX':
            $format = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            break;
        case 'JPG':
            $format = 'image/jpeg';
            break;
        case 'PNG':
            $format = 'image/png';
            break;
        case 'Docx':
            $format = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
    }
    $update = 'UPDATE dbaddon_demo SET docname=?,typedoc=?,linkdoc=?,host=?,datecreate=?,timecreate=? WHERE id=?';
    $reject = $conn->prepare($update);
    $reject->execute([$name, $format, $link, $_POST['host'], $datenow, $timenow, $_GET['edit']]);
    header('location: addon-doc.php?code=' . $code);
    die();
}
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $del = 'DELETE FROM dbaddon_demo WHERE id=?';
    $qdel = $conn->prepare($del);
    $qdel->execute([$id]);
    header('location: addon-doc.php?code=' . $_GET['code']);
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
    <title> افزودن جزوه به پایه | <?php echo __TITLE ?></title>
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
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <?php 
                                        $sql = 'SELECT * FROM course_demo WHERE codecourse = ?';
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$_GET['code']]);
                                        $course = $stmt->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <div>
                                        <h6 class="main-content-label mb-1">جزوات ایجاد شده برای پایه <span
                                                    class="text-primary"> <?php echo $course->coursename ?></span></h6>
                                        <p class="text-primary">
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>اقدامات</th>
                                            <th>نام جزوه</th>
                                            <th>تاریخ ثبت</th>
                                            <th>تعداد دانلود</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql1 = 'SELECT * FROM dbaddon_demo WHERE codecourse =?';
                                        $query1 = $conn->prepare($sql1);
                                        $query1->execute([$_GET['code']]);
                                        $result = $query1->fetchAll(PDO::FETCH_OBJ);
                                        if ($query1->rowCount() > 0) {
                                            foreach ($result as $erp) {
                                                ?>
                                                <tr>
                                                    <td>
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
                                                                        <a class="dropdown-item "
                                                                           href="<?php echo $erp->linkdoc ?>"
                                                                           target="_blank">
                                                                            <i class="fe fe-download"> </i>دانلود جزوه
                                                                        </a>
                                                                        <a class="dropdown-item"
                                                                           href="?code=<?php echo $erp->codecourse ?>&edit=<?php echo $erp->id ?>"><i
                                                                                    class="fe fe-link"></i>
                                                                            ویرایش آدرس
                                                                        </a>
                                                                        <hr>
                                                                        <a class="dropdown-item text-danger "
                                                                           href="?code=<?php echo $erp->codecourse ?>&del=<?php echo $erp->id ?>"
                                                                           onclick="return confirm('آیا با حذف این فیلم  موافقید؟')"><i
                                                                                    class="fe fe-x-square"></i> حذف این
                                                                            جزوه
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </td>
                                                    <td><?php echo $erp->docname ?></td>
                                                    <?php
                                                    $dateno = $erp->datecreate;
                                                    $dateno = explode('-', $dateno);
                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                    $dateno = implode('/', $dateno);
                                                    ?>
                                                    <td><?php echo $dateno ?></td>
                                                    <td><?php echo $erp->view ?></td>
                                                </tr>
                                            <?php }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!isset($_GET['edit']) and isset($_GET['code'])) { ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">افزودن جزوات جدید به پایه <span
                                                        class="text-primary"> <?php echo $course->coursename ?></span>
                                            </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-xl-12 mt-3">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام جزوه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="doc-name"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="پاور پوینت ریاضی نهم جلسه هفتم">
                                                </div>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            لینک جزوه
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="doc-link"
                                                           aria-describedby="basic-addon2"
                                                           type="text" style="text-align: left; direction: ltr" required
                                                           placeholder="http://dl.Yourdomain.com/2.PDF">
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                    <input class="form-control" name="codecourse" type="hidden"
                                                           value="<?php echo $_GET['code'] ?>">

                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> فرمت جزوه <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="format"
                                                        required>
                                                    <option></option>
                                                    <option>
                                                        PDF
                                                    </option>
                                                    <option>
                                                        XLSX
                                                    </option>
                                                    <option>
                                                        Docx
                                                    </option>
                                                    <option>
                                                        PPTX
                                                    </option>
                                                    <option>
                                                        JPG
                                                    </option>
                                                    <option>
                                                        PNG
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> حجم فایل <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="host"
                                                       aria-describedby="basic-addon2"
                                                       type="text" style="text-align: left; direction: ltr" required
                                                       placeholder="">
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save"
                                                    class="btn btn-primary  mr-1">
                                                تایید و قرارگیری فایل در پروفایل
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if (isset($_GET['edit']) and isset($_GET['code'])) {
                        $sql2 = 'SELECT * FROM dbaddon_demo WHERE id=?';
                        $query2 = $conn->prepare($sql2);
                        $query2->execute([$_GET['edit']]);
                        $request = $query2->fetch();

                        ?>
                        <div class="col-lg-6">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">ویرایش جزوه پایه <span
                                                        class="text-primary"> <?php echo $course->coursename ?></span>
                                            </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام جزوه:
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="doc-name"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder=""
                                                           value="<?php echo htmlentities($request['docname']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            لینک جزوه
                                                            <span
                                                                    class="tx-danger">*</span>
                                                        </div>
                                                    </div>
                                                    <input class="form-control" name="doc-link"
                                                           aria-describedby="basic-addon2"
                                                           type="text" style="text-align: left; direction: ltr" required
                                                           placeholder="http://dl.Yourdomain.com/2.PDF"
                                                           value="<?php echo htmlentities($request['linkdoc']) ?>">
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                    <input class="form-control" name="codecourse" type="hidden"
                                                           value="<?php echo $_GET['code'] ?>">

                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> فرمت جزوه <span
                                                            class="tx-danger">*</span></p>
                                                <select class="form-control select2" name="format"
                                                        required>
                                                    <option></option>
                                                    <option <?php if ($request['typedoc'] == 'application/pdf') {
                                                        echo 'selected';
                                                    } ?>>
                                                        PDF
                                                    </option>
                                                    <option <?php if ($request['typedoc'] == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                                                        echo 'selected';
                                                    } ?>>
                                                        XLSX
                                                    </option>
                                                    <option <?php if ($request['typedoc'] == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                                                        echo 'selected';
                                                    } ?>>
                                                        Docx
                                                    </option>
                                                    <option <?php if ($request['typedoc'] == 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
                                                        echo 'selected';
                                                    } ?>>
                                                        PPTX
                                                    </option>
                                                    <option <?php if ($request['typedoc'] == 'image/jpeg') {
                                                        echo 'selected';
                                                    } ?>>
                                                        JPG
                                                    </option>
                                                    <option <?php if ($request['typedoc'] == 'image/png') {
                                                        echo 'selected';
                                                    } ?>>
                                                        PNG
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> حجم فایل <span
                                                            class="tx-danger">*</span></p>
                                                <input class="form-control" name="host"
                                                       aria-describedby="basic-addon2"
                                                       type="text" style="text-align: left; direction: ltr" required
                                                       placeholder=""
                                                       value="<?php echo htmlentities($request['host']) ?>">
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save-update"
                                                    class="btn btn-warning  mr-1">
                                                ویرایش و قرارگیری فایل در پروفایل
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
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