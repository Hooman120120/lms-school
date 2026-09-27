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
function generateUniqueCode($length = 8)
{
    return bin2hex(random_bytes($length / 2));
}

if (isset($_POST['addclass'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['class_name'];
        $description = $_POST['description'];
        $channel = $_POST['chanel'];

        // ثبت کلاس در دیتابیس LMS
        $academicYearId = selectedAcademicYearId();
        $sql = $academicYearId === null
            ? "INSERT INTO classes (codecourse, name, description, is_active, link_eita) VALUES (?,?,?,?,?)"
            : "INSERT INTO classes (codecourse, name, description, is_active, link_eita, academic_year_id) VALUES (?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $params = [$_GET['code'], $name, $description, 1, $channel];
        if ($academicYearId !== null) {
            $params[] = $academicYearId;
        }
        $stmt->execute($params);
        $classId = $conn->lastInsertId();
        linkCourseToSelectedAcademicYear((int)$_GET['code']);

        if (isset($_POST['activity-quiz'])) {
            include_once '../../inc/function-quiz.php';
            $course = returnCourse($_GET['code']);

            // بررسی اینکه آیا پایه اصلی اصلا در سامانه آزمون ugid دارد یا نه
            if ($course && !empty($course->ugid)) {
                $ugid_parent = $course->ugid;
                $groupCode = generateUniqueCode(8);

                // فراخوانی API و ذخیره کامل پاسخ
                $apiResult = AddSubGroupByApi(1, $groupCode, $name, $ugid_parent);

                // بررسی موفقیت آمیز بودن نتیجه API
                if (isset($apiResult['success']) && $apiResult['success'] === true) {
                    $ugid = $apiResult['ugid'];
                    $sql = 'UPDATE classes SET ugid = ? WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$ugid, $classId]);

                    $_SESSION['success_msg'] = "کلاس با موفقیت ایجاد شد و زیرگروه مربوطه در سامانه آزمون ثبت گردید.";
                } else {
                    // استخراج پیام خطا از API
                    $errorMsg = $apiResult['message'] ?? $apiResult['error'] ?? 'خطای ناشناخته در ارتباط با سامانه آزمون';
                    $_SESSION['error_msg'] = "کلاس در سیستم ایجاد شد اما ساخت زیرگروه در سامانه آزمون با خطا مواجه شد: " . $errorMsg;
                }
            } else {
                $_SESSION['error_msg'] = "کلاس در سیستم ایجاد شد اما پایه اصلی (گروه والد) فاقد شناسه آزمون است، بنابراین زیرگروه ساخته نشد.";
            }
        } else {
            $_SESSION['success_msg'] = "کلاس با موفقیت در سیستم ایجاد شد (بدون آزمون).";
        }
    } else {
        $_SESSION['error_msg'] = "خطای امنیتی (توکن نامعتبر).";
    }
    header('Location: list-class.php?code=' . $_GET['code']);
    die();
}


if (isset($_POST['updateclass'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['class_name'];
        $codecourse = $_POST['codecourse'];
        $description = $_POST['description'];
        $channel = $_POST['chanel'];
        $sql = "UPDATE classes SET name = ?, description = ?, link_eita = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $description, $channel, $_GET['edit']]);
        header("Location: list-class.php?code=$codecourse");
        die();
    }
}

if (isset($_GET['act_class'])) {
    $sql = 'UPDATE classes SET act_class=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_class']]);
    //
    $classId = $_GET['act_class'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_class = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_class = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['act_general'])) {
    $sql = 'UPDATE classes SET act_general=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_general']]);
    //
    $classId = $_GET['act_general'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_general = 1');
        $stmt->execute([$codecourse]);
        $activeGenerals = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalGenerals = $stmtt->fetchColumn();

        $courseStatus = $activeGenerals == $totalGenerals ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_general = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['act_quiz'])) {
    $sql = 'UPDATE classes SET act_quiz=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([1, $_GET['act_quiz']]);
    //
    $classId = $_GET['act_quiz'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_quiz = 1');
        $stmt->execute([$codecourse]);
        $activeQuizzes = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalQuizzes = $stmtt->fetchColumn();

        $courseStatus = $activeQuizzes == $totalQuizzes ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_quiz = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_class'])) {
    $sql = 'UPDATE classes SET act_class=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_class']]);
    //
    $classId = $_GET['expire_class'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_class = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_class = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_general'])) {
    $sql = 'UPDATE classes SET act_general=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_general']]);
    //oh
    $classId = $_GET['expire_general'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_general = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_general = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}
if (isset($_GET['expire_quiz'])) {
    $sql = 'UPDATE classes SET act_quiz=? WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([0, $_GET['expire_quiz']]);
    //
    $classId = $_GET['expire_quiz'];
    $stmtt = $conn->prepare('SELECT codecourse FROM classes WHERE id = ?');
    $stmtt->execute([$classId]);
    $codecourse = $stmtt->fetchColumn();

    if ($codecourse) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ? AND act_quiz = 1');
        $stmt->execute([$codecourse]);
        $activeClasses = $stmt->fetchColumn();

        $stmtt = $conn->prepare('SELECT COUNT(*) FROM classes WHERE codecourse = ?');
        $stmtt->execute([$codecourse]);
        $totalClasses = $stmtt->fetchColumn();

        $courseStatus = $activeClasses == $totalClasses ? 1 : 0;
        $stmttt = $conn->prepare('UPDATE codtbl_course SET act_quiz = ? WHERE codecourse = ?');
        $stmttt->execute([$courseStatus, $codecourse]);
    }
    header('location: ' . url('admin/course/list-class.php?code=' . $codecourse));
    die();
}

if (isset($_GET['unactive'])) {
    $sql = "UPDATE classes SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['unactive']]);
    $code = $_GET['code'];
    header("Location: list-class.php?code=$code");
    die();
}

if (isset($_GET['active'])) {
    $sql = "UPDATE classes SET is_active = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['active']]);
    $code = $_GET['code'];
    header("Location: list-class.php?code=$code");
    die();
}
$adminRole = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;

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
    <title> لیست کلاس ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
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
                    <?php if (isset($_SESSION['success_msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php
                            echo $_SESSION['success_msg'];
                            unset($_SESSION['success_msg']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_msg'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php
                            echo $_SESSION['error_msg'];
                            unset($_SESSION['error_msg']);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
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
                    <?php $course = returnCourse($_GET['code']); ?>
                    <?php if (!isset($_GET['edit'])) { ?>
                        <?php if (Permision('permision_viewcourse')) { ?>
                            <div class="col-12 order-2 mt-4">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-xl-12">

                                                <div>
                                                    <h6 class="main-content-label mb-1">افزودن کلاس برای<span
                                                                class="text-dark"> <?php echo $course->coursename ?></span>
                                                    </h6>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام کلاس:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="class_name"
                                                           placeholder="نام را وارد کنید ..." required>
                                                    <br>
                                                    <p class="mg-b-10">توضیحات:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="description"
                                                           placeholder="توضیحات را وارد کنید ..." required>
                                                    <p class="mg-b-10 mt-4">آدرس کانال پایه:<span
                                                                class="tx-danger">*</span></p>
                                                    <input class="form-control" name="chanel"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="http://eitaa.com/3jkfm54ffgh">
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $hash ?>">
                                                </div>
                                                <div class="col-xl-4 mg-t-10">
                                                    <label class="custom-switch">
                                                        <input type="checkbox"
                                                               name="activity-quiz"
                                                               class="custom-switch-input" checked>
                                                        <span class="custom-switch-indicator"></span>
                                                        <span class=""> ساخت معادل این کلاس در سامانه آزمون </span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-success pd-x-20" type="submit"
                                                            name="addclass">ثبت کلاس
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else {
                        $sql = "SELECT * FROM classes WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$_GET['edit']]);
                        $res = $stmt->fetch(PDO::FETCH_OBJ);
                        ?>
                        <?php if (Permision('permision_viewcourse')) { ?>
                            <div class="col-lg-12 mt-4">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <?php $course = returnCourse($_GET['code']); ?>
                                                <div>
                                                    <h6 class="main-content-label mb-1">ویرایش کلاس برای<span
                                                                class="text-dark"> <?php echo $course->coursename ?></span>
                                                    </h6>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام کلاس:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="class_name"
                                                           placeholder="نام را وارد کنید ..." value="<?= $res->name ?>"
                                                           required>
                                                    <p class="mg-b-10">توضیحات:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="description"
                                                           placeholder="توضیحات را وارد کنید ..."
                                                           value="<?= $res->description ?>" required>
                                                    <p class="mg-b-10 mt-4">آدرس کانال پایه:<span
                                                                class="tx-danger">*</span></p>
                                                    <input class="form-control" name="chanel"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required value="<?= $res->link_eita ?>"
                                                           placeholder="http://eitaa.com/3jkfm54ffgh">
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
                                            <div class="row">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-warning pd-x-20" type="submit"
                                                            name="updateclass">ویرایش کلاس
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                    <div class="<?= Permision('permision_viewcourse') ? 'col-12 order-1' : 'col-12 order-1' ?>">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-xl-12 mb-4">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="main-content-label mb-1">لیست کلاس های<span
                                                    class="text-dark"> <?php echo $course->coursename ?></span></h6>
                                        <?php
                                        $academicYearId = selectedAcademicYearId();
                                        $sql = 'SELECT * FROM classes WHERE codecourse = ?' . ($academicYearId === null ? '' : ' AND academic_year_id = ?');
                                        $query = $conn->prepare($sql);
                                        $params = [$_GET['code']];
                                        if ($academicYearId !== null) $params[] = $academicYearId;
                                        $query->execute($params);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        ?>
                                        <?php if (!in_array($adminRole, [1, 6])) { ?>
                                            <button class="btn ripple  btn-dark pd-x-20"
                                                    onclick="window.location.href='dars.php?code=<?= $_GET['code'] ?>'" <?= $query->rowCount() > 0 ? '' : 'disabled' ?>>
                                                <i class="fe fe-bar-chart-2"></i>
                                                مدیریت درس ها
                                            </button>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <?php if ($adminRole !== 3): ?>
                                                <th>تعداد جلسات برگزار شده</th>
                                                <th>کلاس</th>
                                                <th>مشاوره</th>
                                                <th>آزمون</th>
                                            <?php endif; ?>
                                            <th>نام کلاس</th>
                                            <th>تعداد دانش آموزان</th>
                                            <th>تعداد درس ها</th>
                                            <th>تعداد سرفصل ها</th>
                                            <th>تعداد فیلم ها</th>
                                            <th>تعداد جزوات</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        if ($query->rowCount() > 0) {
                                            $rt = 1;
                                            if (!function_exists('countHeldSessionsByClass')) {
                                                function countHeldSessionsByClass(?string $codecourse = null, ?int $class_id = null): int
                                                {
                                                    global $conn;

                                                    if (empty($codecourse) || empty($class_id)) {
                                                        return 0;
                                                    }

                                                    $sql = "SELECT COUNT(*) FROM tbl_attendanced WHERE codecourse = ? AND class_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$codecourse, $class_id]);

                                                    return (int)$stmt->fetchColumn();
                                                }
                                            }
                                            foreach ($result as $class) {

                                                ?>

                                                <tr>
                                                <td class="wd-15"><?php echo $rt ?></td>
                                                <?php if ($adminRole !== 3): ?>
                                                    <td class="wd-15">
                                                        <?php echo countHeldSessionsByClass($_GET['code'], (int)$class->id); ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php if (act_classSkyroomForThisClass($class->id) == 0) { ?>
                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i>
                                                            </div>
                                                        <?php } else { ?>
                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php if (act_generalSkyroomForThisClass($class->id) == 0) { ?>
                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i>
                                                            </div>
                                                        <?php } else { ?>
                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="wd-15">
                                                        <?php if (act_quizForThisClass($class->id) == 0) { ?>
                                                            <div class="text-danger"><i class="mdi mdi-adjust"></i>
                                                            </div>
                                                        <?php } else { ?>
                                                            <div class="text-success"><i class="fe fe-wifi"></i>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="wd-15"><?= $class->name ?>
                                                </td>
                                                <td class="wd-15">
                                                    <?php
                                                    $sql = "SELECT COUNT(*) AS count_student FROM dbcourse WHERE class_id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$class->id]);
                                                    $movieCount = $stmt->fetch(PDO::FETCH_OBJ);
                                                    echo $movieCount->count_student;
                                                    ?>
                                                </td>
                                                <td class="wd-15">
                                                    <?php
                                                    $sql = 'SELECT COUNT(*) AS count_dars FROM class_dars WHERE class_id = ?';
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$class->id]);
                                                    $countDarsHa = $stmt->fetch(PDO::FETCH_OBJ);
                                                    echo $countDarsHa->count_dars
                                                    ?>
                                                </td>
                                                <td class="wd-15">
                                                    <?php
                                                    $countLessonsSql = 'SELECT COUNT(*) AS count_lessons FROM class_lesson WHERE class_id = ?';
                                                    //                                                        $countChaptersSql = 'SELECT COUNT(*) AS count_chapters
                                                    //FROM (
                                                    //    -- 1. های سرفصل وصل‌شده به کلاس
                                                    //    SELECT cc.id
                                                    //    FROM chapter_class cc
                                                    //    INNER JOIN classes c ON cc.class_id = c.id
                                                    //    WHERE c.codecourse = ?
                                                    //      AND cc.class_id = ?
                                                    //
                                                    //    UNION ALL
                                                    //
                                                    //    -- 2. سرفصل هایی که به هیچ کلاسی وصل نشده‌اند
                                                    //    SELECT l.id
                                                    //    FROM dblesson l
                                                    //    WHERE l.codecourse = ?
                                                    //      AND NOT EXISTS (
                                                    //          SELECT 1
                                                    //          FROM chapter_class cc
                                                    //          WHERE cc.chapter_id = l.id
                                                    //      )
                                                    //) AS all_chapters;
                                                    //';
                                                    $countLessonsQuery = $conn->prepare($countLessonsSql);
                                                    $countLessonsQuery->execute([$class->id]);
                                                    $countLessons = $countLessonsQuery->fetch(PDO::FETCH_OBJ);
                                                    echo $countLessons->count_lessons;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $countMoviesSql = 'SELECT COUNT(*) AS count_movies FROM class_movie WHERE class_id = ?';
                                                    $countMoviesQuery = $conn->prepare($countMoviesSql);
                                                    $countMoviesQuery->execute([$class->id]);
                                                    $countMovies = $countMoviesQuery->fetch(PDO::FETCH_OBJ);
                                                    echo $countMovies->count_movies;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $countAddonsSql = 'SELECT COUNT(*) AS count_addons FROM addon_class WHERE class_id = ?';
                                                    $countAddonsQuery = $conn->prepare($countAddonsSql);
                                                    $countAddonsQuery->execute([$class->id]);
                                                    $countAddons = $countAddonsQuery->fetch(PDO::FETCH_OBJ);
                                                    echo $countAddons->count_addons;
                                                    ?>
                                                </td>
                                                <td class="wd-15">
                                                <div class="text-wrap">
                                                <div class="text-center">
                                                <div class="dropdown">
                                                <?php if ($class->is_active == 1 or Permision('permision_viewcourse')) { ?>
                                                    <button aria-expanded="false" aria-haspopup="true"
                                                            class="btn ripple btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" type="button">
                                                        اقدام <i class="fas fa-caret-down mr-1"></i>
                                                    </button>

                                                <?php }
                                                if ($class->is_active != 0) { ?>
                                                    <div class="dropdown-menu tx-8">
                                                        <a class="dropdown-item  "
                                                           href="class-detail.php?class_id=<?php echo $class->id ?>&codecourse=<?= $class->codecourse ?>">
                                                            <i class="fe fe-check-circle"></i>
                                                            اطلاعات کلاس
                                                        </a>

                                                        <a class="dropdown-item  "
                                                           href="assign-student.php?class_id=<?php echo $class->id ?>">
                                                            <i class="fe fe-check-circle"></i>
                                                            مدیریت دانش آموزان
                                                        </a>
                                                        <a class="dropdown-item  "
                                                           href="homeworks.php?class_id=<?php echo $class->id ?>">
                                                            <i class="fe fe-check-square"></i>
                                                            مدیریت تکالیف
                                                        </a>

                                                        <a class="dropdown-item  "
                                                           href="attendance.php?id=<?php echo $class->codecourse ?>&class=<?= $class->id ?>">
                                                            <i class="fe fe-check-square"></i>
                                                            حضور و غیاب
                                                        </a>
                                                        <?php if (Permision('permision_viewcourse')) { ?>
                                                            <div class="dropdown-item disabled">
                                                                <i class="fe fe-cpu"></i> تنظیم
                                                                برنامه
                                                                کلاسی
                                                            </div>
                                                            <hr>
                                                            <?php if (act_classSkyroomForThisClass($class->id) == 0) { ?>
                                                                <a class="dropdown-item text-info "
                                                                   href="?act_class=<?php echo $class->id ?>">
                                                                    <i class="fe fe-video"></i>
                                                                    فعالسازی کلاس
                                                                </a>
                                                            <?php } else { ?>
                                                                <a class="dropdown-item text-warning "
                                                                   href="?expire_class=<?php echo $class->id ?>">
                                                                    <i class="fe fe-video-off"></i>
                                                                    قطع کلاس
                                                                </a>
                                                            <?php } ?>
                                                            <?php if (act_generalSkyroomForThisClass($class->id) == 0) { ?>
                                                                <a class="dropdown-item text-primary "
                                                                   href="?act_general=<?php echo $class->id ?>">
                                                                    <i class="fe fe-video"></i>
                                                                    فعالسازی مشاوره
                                                                </a>
                                                            <?php } else { ?>
                                                                <a class="dropdown-item text-warning "
                                                                   href="?expire_general=<?php echo $class->id ?>">
                                                                    <i class="fe fe-video-off"></i>
                                                                    قطع مشاوره
                                                                </a>
                                                            <?php } ?>
                                                            <?php if (act_quizForThisClass($class->id) == 0) { ?>
                                                                <a class="dropdown-item text-secondary "
                                                                   href="?act_quiz=<?php echo $class->id ?>">
                                                                    <i class="fe fe-grid"></i>
                                                                    فعالسازی آزمون
                                                                </a>
                                                            <?php } else { ?>
                                                                <a class="dropdown-item text-warning "
                                                                   href="?expire_quiz=<?php echo $class->id ?>">
                                                                    <i class="fe fe-stop-circle"></i>
                                                                    قطع آزمون
                                                                </a>
                                                            <?php } ?>
                                                            <a class="dropdown-item text-primary "
                                                               href="<?php echo 'login_skyroom.php?loginskyroom=' . $class->codecourse ?>"
                                                               target="_blank">
                                                                <i class="fe fe-tv"> </i>ورود به
                                                                کلاس
                                                                آنلاین

                                                            </a>
                                                            <hr>
                                                            <a class="dropdown-item text-success "
                                                               href="list-class.php?code=<?php echo $class->codecourse ?>&edit=<?php echo $class->id ?>">
                                                                <i class="fe fe-edit"></i> ویرایش
                                                                کلاس
                                                            </a>
                                                            <?php
                                                            if (Permision('permision_deletcourse')) { ?>
                                                                <a class="dropdown-item text-danger "
                                                                   href="?code=<?= $class->codecourse ?>&unactive=<?php echo $class->id ?>"
                                                                   onclick="return confirm('آیا با این اقدام موافقید؟')"><i
                                                                            class="fe fe-x-square"></i>
                                                                    غیرفعال کردن
                                                                    کلاس
                                                                </a>
                                                            <?php } ?>
                                                        <?php } ?>
                                                    </div>
                                                <?php } else {
                                                    if (Permision('permision_viewcourse')) { ?>
                                                        <div class="dropdown-menu tx-8">
                                                            <a class="dropdown-item  "
                                                               href="class-detail.php?class_id=<?php echo $class->id ?>&codecourse=<?= $class->codecourse ?>">
                                                                <i class="fe fe-check-circle"></i>
                                                                اطلاعات کلاس
                                                            </a>

                                                            <a class="dropdown-item  "
                                                               href="assign-student.php?class_id=<?php echo $class->id ?>">
                                                                <i class="fe fe-check-circle"></i>
                                                                مدیریت دانش آموزان
                                                            </a>
                                                            <a class="dropdown-item  "
                                                               href="homeworks.php?class_id=<?php echo $class->id ?>">
                                                                <i class="fe fe-check-square"></i>
                                                                مدیریت تکالیف
                                                            </a>

                                                            <a class="dropdown-item  "
                                                               href="attendance.php?id=<?php echo $class->codecourse ?>&class=<?= $class->id ?>">
                                                                <i class="fe fe-check-square"></i>
                                                                حضور و غیاب
                                                            </a>
                                                            <?php if (Permision('permision_viewcourse')) { ?>
                                                                <div class="dropdown-item disabled">
                                                                    <i class="fe fe-cpu"></i> تنظیم
                                                                    برنامه
                                                                    کلاسی
                                                                </div>
                                                                <hr>
                                                                <?php if (act_classSkyroomForThisClass($class->id) == 0) { ?>
                                                                    <a class="dropdown-item text-info "
                                                                       href="?act_class=<?php echo $class->id ?>">
                                                                        <i class="fe fe-video"></i>
                                                                        فعالسازی کلاس
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item text-warning "
                                                                       href="?expire_class=<?php echo $class->id ?>">
                                                                        <i class="fe fe-video-off"></i>
                                                                        قطع کلاس
                                                                    </a>
                                                                <?php } ?>
                                                                <?php if (act_generalSkyroomForThisClass($class->id) == 0) { ?>
                                                                    <a class="dropdown-item text-primary "
                                                                       href="?act_general=<?php echo $class->id ?>">
                                                                        <i class="fe fe-video"></i>
                                                                        فعالسازی مشاوره
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item text-warning "
                                                                       href="?expire_general=<?php echo $class->id ?>">
                                                                        <i class="fe fe-video-off"></i>
                                                                        قطع مشاوره
                                                                    </a>
                                                                <?php } ?>
                                                                <?php if (act_quizForThisClass($class->id) == 0) { ?>
                                                                    <a class="dropdown-item text-secondary "
                                                                       href="?act_quiz=<?php echo $class->id ?>">
                                                                        <i class="fe fe-grid"></i>
                                                                        فعالسازی آزمون
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item text-warning "
                                                                       href="?expire_quiz=<?php echo $class->id ?>">
                                                                        <i class="fe fe-stop-circle"></i>
                                                                        قطع آزمون
                                                                    </a>
                                                                <?php } ?>
                                                                <a class="dropdown-item text-primary "
                                                                   href="<?php echo 'login_skyroom.php?loginskyroom=' . $class->codecourse ?>"
                                                                   target="_blank">
                                                                    <i class="fe fe-tv"> </i>ورود به
                                                                    کلاس
                                                                    آنلاین

                                                                </a>
                                                                <hr>
                                                                <a class="dropdown-item text-success "
                                                                   href="list-class.php?code=<?php echo $class->codecourse ?>&edit=<?php echo $class->id ?>">
                                                                    <i class="fe fe-edit"></i> ویرایش
                                                                    کلاس
                                                                </a>
                                                                <?php
                                                                if (Permision('permision_deletcourse')) { ?>
                                                                    <a class="dropdown-item text-danger "
                                                                       href="?code=<?= $class->codecourse ?>&active=<?php echo $class->id ?>"
                                                                       onclick="return confirm('آیا با این اقدام موافقید؟')"><i
                                                                                class="fe fe-check-circle"></i>
                                                                        فعال کردن
                                                                        کلاس
                                                                    </a>
                                                                <?php } ?>
                                                            <?php } ?>
                                                        </div>
                                                    <?php } else { ?>
                                                        <p class="badge badge-warning">غیرفعال</p>
                                                    <?php } ?>
                                                    </div>
                                                    </div>
                                                    </div>
                                                    </td>
                                                    </tr>
                                                    <?php
                                                    $rt++;
                                                }
                                            }
                                        } else { ?>
                                            <tr>
                                                <td colspan="11" class="text-center">کلاسی موجود نمی باشد</td>
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
