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
if (!Permision('permision_viewcourse')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (StaffCourseScopeRequired() && !StaffHasCourseAccess((int)$_GET['code'])) {
    http_response_code(403);
    exit('شما به جزوه‌های این پایه دسترسی ندارید.');
}

if (!checkvalidcourse(intval($_GET['code']))) {
    header('location: ' . url('admin/index.php'));
    die();
}
function addonAllowedClassIds(PDO $conn, int $darsId, array $submittedClasses): array
{
    $stmt = $conn->prepare('SELECT class_id FROM class_dars WHERE dars_id = ?');
    $stmt->execute([$darsId]);
    $allowed = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_intersect(array_unique(array_map('intval', $submittedClasses)), $allowed));
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
        $dars = $_POST['dars'];
        $lesson = isset($_POST['lesson']) ? intval($_POST['lesson']) : NULL;
        $chapters = isset($_POST['chapterr']) ? $_POST['chapterr'] : NULL;
        $classes = addonAllowedClassIds($conn, (int)$dars, $_POST['classes'] ?? []);
        if (empty($classes)) {
            exit('حداقل یک کلاسِ مربوط به این درس را انتخاب کنید.');
        }

        $sql = 'INSERT INTO dbaddon (codecourse,dars_id,lesson_id,docname,typedoc,linkdoc,host,useradder,datecreate,timecreate) VALUES (?,?,?,?,?,?,?,?,?,?)';
        $query = $conn->prepare($sql);
        $query->execute([$code, $dars, $lesson, $name, $format, $link, $_POST['host'], $_SESSION['admin']['user'], $datenow, $timenow]);
        $addonLastInsertedId = $conn->lastInsertId();
        if (!empty($classes)) {
            $insertClassesSql = 'INSERT INTO addon_class (class_id,addon_id) VALUES (?,?)';
            $insertClassesStmt = $conn->prepare($insertClassesSql);
            foreach ($classes as $class) {
                $insertClassesStmt->execute([$class, $addonLastInsertedId]);
            }
        }
        if (!is_null($chapters)) {
            $insertChaptersSql = 'INSERT INTO subchapter_addon (chapter_id,addon_id) VALUES (?,?)';
            $insertChaptersStmt = $conn->prepare($insertChaptersSql);
            foreach ($chapters as $chapter) {
                $insertChaptersStmt->execute([$chapter, $addonLastInsertedId]);
            }
        }
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
        case 'DOCX':
            $format = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
    }
    $dars = $_POST['dars'];
    $lesson = isset($_POST['lesson']) ? intval($_POST['lesson']) : NULL;
    $chapters = isset($_POST['chapterr']) ? $_POST['chapterr'] : NULL;
    $classes = addonAllowedClassIds($conn, (int)$dars, $_POST['classes'] ?? []);
    if (empty($classes)) {
        exit('حداقل یک کلاسِ مربوط به این درس را انتخاب کنید.');
    }
    $update = 'UPDATE dbaddon SET dars_id=?,lesson_id=?,docname=?,typedoc=?,linkdoc=?,host=?,useradder=? WHERE id=?';
    $reject = $conn->prepare($update);
    $reject->execute([$dars, $lesson, $name, $format, $link, $_POST['host'], $_SESSION['admin']['user'], $_GET['edit']]);
    if (!empty($classes)) {
        $deletePreviousClassesSql = 'DELETE FROM addon_class WHERE addon_id = ?';
        $deletePreviousClassesQuery = $conn->prepare($deletePreviousClassesSql);
        $deletePreviousClassesQuery->execute([$_GET['edit']]);

        foreach ($classes as $class) {
            $setNewClassesSql = 'INSERT INTO addon_class (class_id,addon_id) VALUES (?,?)';
            $setNewClassesQuery = $conn->prepare($setNewClassesSql);
            $setNewClassesQuery->execute([$class, $_GET['edit']]);
        }
    }
    if (!is_null($chapters)) {
        $deletePreviousChaptersSql = 'DELETE FROM subchapter_addon WHERE addon_id = ?';
        $deletePreviousChaptersQuery = $conn->prepare($deletePreviousChaptersSql);
        $deletePreviousChaptersQuery->execute([$_GET['edit']]);

        foreach ($chapters as $chapter) {
            $setNewChaptersSql = 'INSERT INTO subchapter_addon (chapter_id,addon_id) VALUES (?,?)';
            $setNewChaptersQuery = $conn->prepare($setNewChaptersSql);
            $setNewChaptersQuery->execute([$chapter, $_GET['edit']]);
        }
    }
    header('location: addon-doc.php?code=' . $code . '&dars=' . $dars);
    die();
}
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $del = 'DELETE FROM dbaddon WHERE id=?';
    $qdel = $conn->prepare($del);
    $qdel->execute([$id]);
    header('location: addon-doc.php?code=' . $_GET['code'] . '&dars=' . $_GET['dars']);
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
    <style>
        #dars option {
            text-align: center;
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
            <div class="inner-body">
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <?php $course = returnCourse($_GET['code']); ?>
                                    <div>
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                جزوات ایجادشده برای پایه
                                                <span class="text-primary">
                                                    <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید جزوات و فایل‌های آموزشی ثبت‌شده برای پایه انتخاب‌شده را مشاهده و مدیریت کنید. اطلاعات هر جزوه و گزینه‌های مربوط به ویرایش، حذف، دانلود یا بررسی جزئیات آن از این قسمت در دسترس است."
                                                  aria-label="راهنمای جزوات آموزشی پایه">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <p class="text-primary mb-0">
                                            مشاهده و مدیریت جزوات آموزشی پایه
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
                                            <th>درس</th>
                                            <th>سرفصل</th>


                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql1 = 'SELECT * FROM dbaddon WHERE codecourse =? AND dars_id = ?';
                                        $query1 = $conn->prepare($sql1);
                                        $query1->execute([$_GET['code'], $_GET['dars']]);
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
                                                                           href="?code=<?php echo $erp->codecourse ?>&dars=<?= $_GET['dars'] ?>&edit=<?php echo $erp->id ?>"><i
                                                                                    class="fe fe-link"></i>
                                                                            ویرایش آدرس
                                                                        </a>
                                                                        <hr>
                                                                        <a class="dropdown-item text-danger "
                                                                           href="?code=<?php echo $erp->codecourse ?>&dars=<?= $_GET['dars'] ?>&del=<?php echo $erp->id ?>"
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
                                                    <?php
                                                    $sql = "SELECT * FROM dbdars WHERE id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$erp->dars_id]);
                                                    $dars = $stmt->fetch(PDO::FETCH_OBJ);
                                                    ?>
                                                    <td><?php echo empty($dars->dars) ? "ندارد" : $dars->dars ?></td>
                                                    <?php
                                                    $sql = "SELECT * FROM dblesson WHERE id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$erp->lesson_id]);
                                                    $lesson = $stmt->fetch(PDO::FETCH_OBJ);
                                                    ?>
                                                    <td><?php echo empty($lesson->name) ? "ندارد" : $lesson->name ?></td>
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
                                            <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                <h6 class="main-content-label mb-0">
                                                    افزودن جزوه جدید به پایه
                                                    <span class="text-primary">
                                                        <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید جزوه یا فایل آموزشی جدیدی برای پایه انتخاب‌شده ثبت کنید. جزوه پس از ثبت به درس و پایه مربوط متصل می‌شود و اطلاعاتی مانند عنوان، کلاس مربوطه، لینک فایل و سایر تنظیمات آن قابل تعیین است."
                                                      aria-label="راهنمای افزودن جزوه آموزشی جدید">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                            </div>

                                            <p class="text-muted card-sub-title">
                                                ثبت جزوه و فایل آموزشی جدید برای پایه
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
                                                <p class="mg-b-10 mg-t-10"> کلاس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT c.* FROM classes c INNER JOIN class_dars cd ON cd.class_id = c.id WHERE c.codecourse = ? AND cd.dars_id = ? ORDER BY c.name";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $_GET['dars']]);
                                                $classes = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2" name="classes[]"
                                                            required multiple>
                                                        <option></option>
                                                        <?php foreach ($classes as $class) { ?>
                                                            <option value="<?= $class->id ?>">
                                                                <?= $class->name ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center"> کلاسی موجود
                                                        نمی باشد</p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> درس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM dbdars WHERE codecourse = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code']]);
                                                $darsHa = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2 text-center" name="dars"
                                                            id="dars"
                                                            required disabled>
                                                        <option></option>
                                                        <?php foreach ($darsHa as $dars) { ?>
                                                            <option value="<?= $dars->id ?>"<?= $_GET['dars'] == $dars->id ? 'selected' : '' ?>>
                                                                <?= $dars->dars ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <input type="hidden" name="dars" value="<?= $_GET['dars'] ?>">
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center">
                                                        درسی موجود نمی باشد
                                                    </p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> سرفصل مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM dblesson WHERE codecourse = ? AND dars_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $_GET['dars']]);
                                                $lessons = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2" name="lesson" id="lesson"
                                                            onchange="loadChapters(this.value)"
                                                            required>
                                                        <option></option>
                                                        <?php foreach ($lessons as $lesson) { ?>
                                                            <option value="<?= $lesson->id ?>">
                                                                <?= $lesson->name ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center"> سرفصلی موجود
                                                        نمی باشد</p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> زیرفصل مربوطه</p>
                                                <select class="form-control select2" name="chapterr[]" id="chapterr"
                                                        multiple>
                                                    <option></option>
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
                        $sql2 = 'SELECT * FROM dbaddon WHERE id=?';
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
                                                <p class="mg-b-10 mg-t-10"> کلاس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT c.* FROM classes c INNER JOIN class_dars cd ON cd.class_id = c.id WHERE c.codecourse = ? AND cd.dars_id = ? ORDER BY c.name";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $_GET['dars']]);
                                                $classes = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2" name="classes[]"
                                                            required multiple>
                                                        <option></option>
                                                        <?php foreach ($classes as $class) {
                                                            $selectedClassSql = 'SELECT * FROM addon_class WHERE class_id = ? AND addon_id = ?';
                                                            $selectedClassQuery = $conn->prepare($selectedClassSql);
                                                            $selectedClassQuery->execute([$class->id, $_GET['edit']]);
                                                            $selectedClass = $selectedClassQuery->fetch(PDO::FETCH_OBJ);
                                                            if ($selectedClassQuery->rowCount() > 0) {
                                                                ?>
                                                                <option value="<?= $class->id ?>"<?= $class->id == $selectedClass->class_id ? 'selected' : '' ?>>
                                                                    <?= $class->name ?>
                                                                </option>
                                                            <?php } else { ?>
                                                                <option value="<?= $class->id ?>">
                                                                    <?= $class->name ?>
                                                                </option>
                                                            <?php }
                                                        } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center"> کلاسی موجود
                                                        نمی باشد</p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> درس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM dbdars WHERE codecourse = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code']]);
                                                $darsHa = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2 text-center" name="dars"
                                                            id="dars"
                                                            required disabled>
                                                        <option></option>
                                                        <?php foreach ($darsHa as $dars) { ?>
                                                            <option value="<?= $dars->id ?>"<?php if (isset($request['dars_id'])) {
                                                                echo $dars->id == $request['dars_id'] ? 'selected' : '';
                                                            } ?> >
                                                                <?= $dars->dars ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <input type="hidden" name="dars" value="<?= $_GET['dars'] ?>">
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center">
                                                        درسی موجود نمی باشد
                                                    </p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> سرفصل مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM dblesson WHERE codecourse = ? AND dars_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], $_GET['dars']]);
                                                $lessons = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2" name="lesson" id="lesson"
                                                            onchange="loadChapters(this.value)"
                                                            required>
                                                        <option></option>
                                                        <?php foreach ($lessons as $lesson) { ?>
                                                            <option value="<?= $lesson->id ?>" <?php if ($request['lesson_id'] == $lesson->id) {
                                                                echo 'selected';
                                                            } ?>>
                                                                <?= $lesson->name ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <p class="mg-b-10 mg-t-10 form-control text-center"> سرفصلی موجود
                                                        نمی باشد</p>
                                                <?php } ?>
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> زیرفصل مربوطه</p>
                                                <?php
                                                $sql = "SELECT * FROM subchapters WHERE lesson_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$request['lesson_id']]);
                                                $chapters = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                ?>
                                                <select class="form-control select2" name="chapterr[]" id="chapterr"
                                                        multiple>
                                                    <option></option>
                                                    <?php foreach ($chapters as $chapter) {
                                                        $sql = "SELECT * FROM subchapter_addon WHERE chapter_id = ? AND addon_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$chapter->id, $_GET['edit']]);
                                                        $selected = $stmt->fetch(PDO::FETCH_OBJ);
                                                        if ($stmt->rowCount() > 0) {
                                                            ?>
                                                            <option value="<?= $chapter->id ?>" <?= $chapter->id == $selected->chapter_id ? 'selected' : '' ?>>
                                                                <?= $chapter->name ?>
                                                            </option>
                                                        <?php } else { ?>
                                                            <option value="<?= $chapter->id ?>">
                                                                <?= $chapter->name ?>
                                                            </option>
                                                        <?php }
                                                    }
                                                    ?>
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
    <script>
        function loadChapters(lessonId) {
            const chapterSelect = document.getElementById('chapterr');

            chapterSelect.innerHTML = '<optionv value="">-- سرفصل را انتخاب کنید --</option>';

            if (lessonId == '') {
                return;
            }

            fetch('get_chapters.php?lesson_id=' + lessonId)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.name;
                            chapterSelect.appendChild(option);
                        });
                        chapterSelect.disabled = false;
                    } else {
                        chapterSelect.innerHTML = '<option value="">سرفصلی موجود نمی باشد</option>';
                    }
                })
                .catch(err => {
                    console.error('خطا در لود سرفصل ها:', err);
                });
        }
    </script>

</body>

</html>
