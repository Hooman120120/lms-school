<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
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
if ($adminRole != 0 and $adminRole != 1) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (isset($_POST['addlesson'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['lesson_name'];
        $dars = $_POST['dars'];
        $classes = isset($_POST['classes']) ? $_POST['classes'] : NULL;
        $sql = "INSERT INTO dblesson (codecourse, dars_id, name) VALUES (?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['code'], $dars, $name]);
        $lessonLastInsertedId = $conn->lastInsertId();
        if (!is_null($classes)) {
            $insertClassesSql = 'INSERT INTO class_lesson (class_id,lesson_id) VALUES (?,?)';
            $insertClassesStmt = $conn->prepare($insertClassesSql);
            foreach ($classes as $class) {
                $insertClassesStmt->execute([$class, $lessonLastInsertedId]);
            }
        }
    }
}

if (isset($_POST['updatelesson'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['lesson_name'];
        $codecourse = $_POST['codecourse'];
        $dars = $_POST['dars'];
        $classes = isset($_POST['classes']) ? $_POST['classes'] : NULL;
        $sql = "UPDATE dblesson SET name = ?, dars_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $dars, $_GET['edit']]);
        if (!is_null($classes)) {
            $deletePreviousClassesSql = 'DELETE FROM class_lesson WHERE lesson_id = ?';
            $deletePreviousClassesQuery = $conn->prepare($deletePreviousClassesSql);
            $deletePreviousClassesQuery->execute([$_GET['edit']]);

            foreach ($classes as $class) {
                $setNewClassesSql = 'INSERT INTO class_lesson (class_id,lesson_id) VALUES (?,?)';
                $setNewClassesQuery = $conn->prepare($setNewClassesSql);
                $setNewClassesQuery->execute([$class, $_GET['edit']]);
            }
        }
        header("Location: lesson.php?code=$codecourse&dars=$dars");
        die();
    }
}

if (isset($_GET['delete'])) {
    $sql = "DELETE FROM dblesson WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delete']]);
    $code = $_GET['code'];
    $dars = $_GET['dars'];
    header("Location: lesson.php?code=$code&dars=$dars");
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
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> افزودن سرفصل | <?php echo __TITLE ?></title>
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
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?php echo $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>
                    <?php if (!isset($_GET['edit'])) { ?>
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xl-12">
                                            <?php $course = returnCourse($_GET['code']); ?>
                                            <div>
                                                <h6 class="main-content-label mb-1">افزودن سرفصل برای <span
                                                            class="text-warning"> <?php echo $course->coursename ?></span>
                                                </h6>
                                                <p class="text-primary">
                                                    <?php if (isset($_GET['class'])) {
                                                        $sql = 'SELECT * FROM classes WHERE id = ?';
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$_GET['class']]);
                                                        $class = $stmt->fetch(PDO::FETCH_OBJ);
                                                        echo 'کلاس ' . $class->name;
                                                    } ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">نام سرفصل:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="lesson_name"
                                                       placeholder="نام را وارد کنید ..." required>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> کلاس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM classes WHERE codecourse = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code']]);
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
                                                    <select class="form-control select2" name="dars" disabled
                                                            required>
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
                                        </div>
                                        <div class="row">
                                            <div class="text-center mg-t-30">
                                                <button class="btn ripple  btn-success pd-x-20" type="submit"
                                                        name="addlesson">ثبت سرفصل
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } else {
                        $sql = "SELECT * FROM dblesson WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_GET['edit']]);
                        $res = $stmt->fetch(PDO::FETCH_OBJ);
                        ?>
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xl-12">
                                            <?php $course = returnCourse($_GET['code']); ?>
                                            <div>
                                                <h6 class="main-content-label mb-1">ویرایش سرفصل برای دوره <span
                                                            class="text-warning"> <?php echo $course->coursename ?></span>
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <div class="row">
                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">نام سرفصل:<span class="tx-danger">*</span></p>
                                                <input class="form-control" name="lesson_name"
                                                       placeholder="نام را وارد کنید ..." value="<?= $res->name ?>"
                                                       required>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                                <input class="form-control" name="codecourse" type="hidden"
                                                       value="<?php echo $_GET['code'] ?>">
                                            </div>
                                            <div class="col-xl-12 mt-3">
                                                <p class="mg-b-10 mg-t-10"> کلاس مربوطه <span
                                                            class="tx-danger">*</span></p>
                                                <?php
                                                $sql = "SELECT * FROM classes WHERE codecourse = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code']]);
                                                $classes = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                if ($stmt->rowCount() > 0) {
                                                    ?>
                                                    <select class="form-control select2" name="classes[]"
                                                            required multiple>
                                                        <option></option>
                                                        <?php foreach ($classes as $class) {
                                                            $selectedClassSql = 'SELECT * FROM class_lesson WHERE class_id = ? AND lesson_id = ?';
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
                                                    <select class="form-control select2" name="dars"
                                                            required disabled>
                                                        <option></option>
                                                        <?php foreach ($darsHa as $dars) { ?>
                                                            <option value="<?= $dars->id ?>"<?= $dars->id == $res->dars_id ? 'selected' : '' ?>>
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
                                        </div>
                                        <div class="row">
                                            <div class="text-center mg-t-30">
                                                <button class="btn ripple  btn-warning pd-x-20" type="submit"
                                                        name="updatelesson">ویرایش سرفصل
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="col-lg-9">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-xl-12 mb-4">
                                    <div>
                                        <h6 class="main-content-label mb-1">لیست سرفصل های <span
                                                    class="text-warning"> <?php echo $course->coursename ?></span></h6>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>نام سرفصل</th>
                                            <th>درس</th>
                                            <th>تعداد زیرفصل ها</th>
                                            <th>تعداد فیلم ها</th>
                                            <th>تعداد جزوات</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dblesson WHERE codecourse = ? AND dars_id = ?';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['code'], $_GET['dars']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            foreach ($result as $lesson) {
                                                ?>

                                                <tr>
                                                    <td class="wd-15"><?php echo $rt ?></td>
                                                    <td class="wd-15"><?= $lesson->name ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php
                                                        $sql = 'SELECT * FROM dbdars WHERE id = ?';
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$lesson->dars_id]);
                                                        $dars = $stmt->fetch(PDO::FETCH_OBJ);
                                                        if ($stmt->rowCount() > 0) {
                                                            echo $dars->dars;
                                                        } else {
                                                            echo 'تعریف نشده';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php
                                                        $sql = "SELECT COUNT(*) AS count_chapter FROM subchapters WHERE lesson_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$lesson->id]);
                                                        $chapterCount = $stmt->fetch(PDO::FETCH_OBJ);
                                                        echo $chapterCount->count_chapter;
                                                        ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php
                                                        $sql = "SELECT COUNT(*) AS count_movie FROM dbmovie WHERE lesson_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$lesson->id]);
                                                        $movieCount = $stmt->fetch(PDO::FETCH_OBJ);
                                                        echo $movieCount->count_movie;
                                                        ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php
                                                        $sql1 = "SELECT COUNT(*) AS count_addon FROM dbaddon WHERE lesson_id = ?";
                                                        $stmt1 = $conn->prepare($sql1);
                                                        $stmt1->execute([$lesson->id]);
                                                        $addonCount = $stmt1->fetch(PDO::FETCH_OBJ);
                                                        echo $addonCount->count_addon;
                                                        ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <div class="text-wrap">
                                                            <div class="text-center">
                                                                <div class="dropdown">
                                                                    <button aria-expanded="false" aria-haspopup="true"
                                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                                            data-bs-toggle="dropdown" type="button">
                                                                        اقدام <i class="fas fa-caret-down mr-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu tx-8">
                                                                        <a class="dropdown-item"
                                                                           href="#"
                                                                           onclick="window.open('sub-chapters.php?lesson='+'<?php echo $lesson->id ?>','مشاهده دوره'
                                                                                   ,'width=850,height=500,menubar=0,toolbar=0,resizable=0,left=0,top=0,status=0,scrollbars=0,location=0' ); return false;"
                                                                        >
                                                                            <i class="fe fe-layers"></i> مشاهده زیرفصل
                                                                            ها
                                                                        </a>
                                                                        <hr>
                                                                        <a class="dropdown-item text-success "
                                                                           href="?code=<?= $_GET['code'] ?>&dars=<?= $_GET['dars'] ?>&edit=<?php echo $lesson->id ?>">
                                                                            <i class="fe fe-edit"></i> ویرایش سرفصل
                                                                        </a>
                                                                        <a class="dropdown-item text-danger"
                                                                           href="?code=<?= $_GET['code'] ?>&dars=<?= $_GET['dars'] ?>&delete=<?php echo $lesson->id ?>"
                                                                           onclick="return confirm('آیا با حذف این سرفصل  موافقید؟')"><i
                                                                                    class="fe fe-trash"></i>
                                                                            حذف سرفصل
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                                $rt++;
                                            }
                                        } else { ?>
                                            <tr>
                                                <td colspan="7" class="text-center">سرفصلی موجود نمی باشد</td>
                                            </tr>
                                        <?php } ?>
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