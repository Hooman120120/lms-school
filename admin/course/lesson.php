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
$adminRole = isset($_SESSION['admin']['adminrole'])
    ? (int) $_SESSION['admin']['adminrole']
    : null;

RequireTeacherPermission('teacher_perm_content', url('admin/course/Active-course.php'));

$lessonCourseCode = isset($_GET['code']) ? (int)$_GET['code'] : (int)($_POST['codecourse'] ?? 0);
$lessonDarsId = isset($_GET['dars']) ? (int)$_GET['dars'] : (int)($_POST['dars'] ?? 0);
$teacherClassId = isset($_GET['class']) ? (int)$_GET['class'] : (int)($_POST['teacher_class_id'] ?? 0);
if ($adminRole === 3) {
    if ($lessonCourseCode <= 0 || $lessonDarsId <= 0 || $teacherClassId <= 0 ||
        !TeacherHasCourseAccess($lessonCourseCode) || !TeacherHasClassAccess($teacherClassId)) {
        http_response_code(403);
        exit('شما به سرفصل‌های این پایه و کلاس دسترسی ندارید.');
    }
    $teacherContextStmt = $conn->prepare('
        SELECT 1
        FROM classes c
        INNER JOIN dbdars d ON d.codecourse = c.codecourse
        INNER JOIN class_dars cd ON cd.class_id = c.id AND cd.dars_id = d.id
        WHERE c.id = ? AND c.codecourse = ? AND d.id = ?
        LIMIT 1
    ');
    $teacherContextStmt->execute([$teacherClassId, $lessonCourseCode, $lessonDarsId]);
    if (!$teacherContextStmt->fetchColumn()) {
        http_response_code(403);
        exit('کلاس، پایه و درس انتخاب‌شده با یکدیگر مطابقت ندارند.');
    }
    if (isset($_POST['updatelesson']) || isset($_GET['edit']) || isset($_GET['delete'])) {
        http_response_code(403);
        exit(m('perm.teacher_lesson_scope'));
    }
}
$staffCourseCode = $lessonCourseCode;
if (StaffCourseScopeRequired() && !StaffHasCourseAccess($staffCourseCode)) {
    http_response_code(403);
    exit('شما به سرفصل‌های این پایه دسترسی ندارید.');
}

function lessonAllowedClassIds(PDO $conn, int $darsId, array $submittedClasses): array
{
    $stmt = $conn->prepare('SELECT class_id FROM class_dars WHERE dars_id = ?');
    $stmt->execute([$darsId]);
    $allowed = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $submitted = array_values(array_unique(array_filter(array_map('intval', $submittedClasses))));
    return array_values(array_intersect($submitted, $allowed));
}

if (isset($_POST['addlesson'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim((string)$_POST['lesson_name']);
        $dars = $adminRole === 3 ? $lessonDarsId : (int)$_POST['dars'];
        $classes = $adminRole === 3
            ? [$teacherClassId]
            : ($_POST['classes'] ?? []);
        $classes = lessonAllowedClassIds($conn, $dars, $classes);
        if ($name === '' || empty($classes)) {
            $_SESSION['message'] = 'نام سرفصل و حداقل یک کلاسِ مربوط به این درس الزامی است.';
            header('Location: lesson.php?code=' . $lessonCourseCode . '&dars=' . $dars . ($teacherClassId ? '&class=' . $teacherClassId : ''));
            die();
        }
        $sql = "INSERT INTO dblesson (codecourse, dars_id, name) VALUES (?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['code'], $dars, $name]);
        $lessonLastInsertedId = $conn->lastInsertId();
        if (!empty($classes)) {
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
        $classes = lessonAllowedClassIds($conn, (int)$dars, $_POST['classes'] ?? []);
        if (empty($classes)) {
            $_SESSION['message'] = 'حداقل یک کلاسِ مربوط به این درس را انتخاب کنید.';
            header("Location: lesson.php?code=$codecourse&dars=$dars&edit=" . (int)$_GET['edit']);
            die();
        }
        $sql = "UPDATE dblesson SET name = ?, dars_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $dars, $_GET['edit']]);
        if (!empty($classes)) {
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
                        <?php $course = returnCourse($_GET['code']);  ?>
                        <?php if ($adminRole !== 3 || $teacherClassId > 0) : ?>
                        <div class="col-lg-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xl-12">
                                            <div>
                                                <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                    <h6 class="main-content-label mb-0">
                                                        افزودن سرفصل برای
                                                        <span class="text-warning">
                                                            <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش می‌توانید سرفصل آموزشی جدیدی برای پایه انتخاب‌شده ثبت کنید. سرفصل ایجادشده به درس مربوط متصل می‌شود و برای سازمان‌دهی مباحث و محتوای آموزشی پایه مورد استفاده قرار می‌گیرد."
                                                          aria-label="راهنمای افزودن سرفصل به پایه">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>

                                                </div>

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
                                                if ($adminRole === 3) {
                                                    $stmt = $conn->prepare('SELECT id, name FROM classes WHERE id = ? AND codecourse = ? LIMIT 1');
                                                    $stmt->execute([$teacherClassId, $_GET['code']]);
                                                    $teacherClass = $stmt->fetch(PDO::FETCH_OBJ);
                                                    ?>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($teacherClass->name ?? '') ?>" disabled>
                                                    <input type="hidden" name="classes[]" value="<?= (int)$teacherClassId ?>">
                                                    <input type="hidden" name="teacher_class_id" value="<?= (int)$teacherClassId ?>">
                                                <?php } else {
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
                                                <?php }
                                                } ?>
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
                        <?php endif; ?>
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
                                                <h6 class="main-content-label mb-1">ویرایش سرفصل برای پایه <span
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
                                                $sql = "SELECT c.* FROM classes c INNER JOIN class_dars cd ON cd.class_id = c.id WHERE c.codecourse = ? AND cd.dars_id = ? ORDER BY c.name";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$_GET['code'], (int)$res->dars_id]);
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
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                لیست سرفصل‌های
                                                <span class="text-warning">
                                                    <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید سرفصل‌های آموزشی تعریف‌شده برای پایه انتخاب‌شده را مشاهده و مدیریت کنید. اطلاعات هر سرفصل و گزینه‌های مربوط به ویرایش، حذف یا بررسی جزئیات آن از این قسمت در دسترس است."
                                                  aria-label="راهنمای لیست سرفصل‌های پایه">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>
                                    </div>
                                </div>

                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th class="wrap" >نام سرفصل</th>
                                            <th>درس</th>
                                            <th>تعداد زیرفصل ها</th>
                                            <th>تعداد فیلم ها</th>
                                            <th>تعداد جزوات</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if ($adminRole === 3) {
                                            $sql = 'SELECT DISTINCT l.* FROM dblesson l INNER JOIN class_lesson cl ON cl.lesson_id = l.id WHERE l.codecourse = ? AND l.dars_id = ? AND cl.class_id = ?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['code'], $_GET['dars'], $teacherClassId]);
                                        } else {
                                            $sql = 'SELECT * FROM dblesson WHERE codecourse = ? AND dars_id = ?';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['code'], $_GET['dars']]);
                                        }
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            foreach ($result as $lesson) {
                                                ?>

                                                <tr>
                                                    <td class="wd-15"><?php echo $rt ?></td>
                                                    <td <td style="width:120px; white-space: normal !important; overflow-wrap: anywhere; word-break: break-word;">
                                                        <?= $lesson->name ?>
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
                                                                           onclick="window.open('sub-chapters.php?lesson='+'<?php echo $lesson->id ?>','مشاهده پایه'
                                                                                   ,'width=850,height=500,menubar=0,toolbar=0,resizable=0,left=0,top=0,status=0,scrollbars=0,location=0' ); return false;"
                                                                        >
                                                                            <i class="fe fe-layers"></i> مشاهده زیرفصل
                                                                            ها
                                                                        </a>
                                                                        <hr>
                                                                        <?php
                                                                        if ($adminRole !== 3):
                                                                        ?>
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
                                                                        <?php endif; ?>
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
