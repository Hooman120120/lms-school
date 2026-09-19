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

$adminRole = (int)($_SESSION['admin']['adminrole'] ?? 0);
RequireTeacherPermission('teacher_perm_content', url('admin/course/Active-course.php'));
if ($adminRole === 3) {
    http_response_code(403);
    exit('مدیریت درس‌ها برای حساب معلم قابل دسترسی نیست. محتوای کلاس را از صفحه فیلم‌ها و جزوه‌ها مشاهده کنید.');
}
$staffCourseCode = isset($_GET['code']) ? (int)$_GET['code'] : (int)($_POST['codecourse'] ?? 0);
if (StaffCourseScopeRequired() && !StaffHasCourseAccess($staffCourseCode)) {
    http_response_code(403);
    exit('شما به درس‌های این پایه دسترسی ندارید.');
}

$courseCode = $staffCourseCode;
$classStmt = $conn->prepare('SELECT id, name FROM classes WHERE codecourse = ? ORDER BY name ASC');
$classStmt->execute([$courseCode]);
$courseClasses = $classStmt->fetchAll(PDO::FETCH_OBJ);

function validDarsClassIds(array $submittedIds, array $courseClasses): array
{
    $validIds = array_map('intval', array_column($courseClasses, 'id'));
    $submittedIds = array_values(array_unique(array_filter(array_map('intval', $submittedIds))));
    return array_values(array_intersect($submittedIds, $validIds));
}

function syncDarsClasses(PDO $conn, int $darsId, array $classIds): void
{
    $delete = $conn->prepare('DELETE FROM class_dars WHERE dars_id = ?');
    $delete->execute([$darsId]);
    $insert = $conn->prepare('INSERT INTO class_dars (class_id, dars_id) VALUES (?, ?)');
    foreach ($classIds as $classId) {
        $insert->execute([(int)$classId, $darsId]);
    }
}

if (isset($_POST['adddars'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim((string)($_POST['dars_name'] ?? ''));
        $classIds = validDarsClassIds($_POST['classes'] ?? [], $courseClasses);
        if ($name === '' || empty($classIds)) {
            $_SESSION['message'] = 'نام درس و حداقل یک کلاس الزامی است.';
        } else {
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare('INSERT INTO dbdars (dars, codecourse) VALUES (?, ?)');
                $stmt->execute([$name, $courseCode]);
                syncDarsClasses($conn, (int)$conn->lastInsertId(), $classIds);
                $conn->commit();
                $_SESSION['message'] = 'درس و کلاس‌های مربوط به آن با موفقیت ثبت شد.';
            } catch (Throwable $exception) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $exception;
            }
        }
        header('Location: dars.php?code=' . $courseCode);
        die();
    }
}

if (isset($_POST['updatedars'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = trim((string)($_POST['dars_name'] ?? ''));
        $codecourse = (int)$_POST['codecourse'];
        $darsId = (int)($_GET['edit'] ?? 0);
        $classIds = validDarsClassIds($_POST['classes'] ?? [], $courseClasses);
        if ($name === '' || empty($classIds)) {
            $_SESSION['message'] = 'نام درس و حداقل یک کلاس الزامی است.';
            header("Location: dars.php?code=$codecourse&edit=$darsId");
            die();
        }
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare('UPDATE dbdars SET dars = ? WHERE id = ? AND codecourse = ?');
            $stmt->execute([$name, $darsId, $codecourse]);
            syncDarsClasses($conn, $darsId, $classIds);
            $conn->commit();
            $_SESSION['message'] = 'اطلاعات درس و کلاس‌های آن ویرایش شد.';
        } catch (Throwable $exception) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $exception;
        }
        header("Location: dars.php?code=$codecourse");
        die();
    }
}

if (isset($_POST['updateroom'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $room = trim($_POST['room_class']);
        $codecourse = (int)$_POST['codecourse'];
        if ($codecourse !== $courseCode) {
            http_response_code(400);
            exit('پایه انتخاب‌شده معتبر نیست.');
        }
        $sql = "UPDATE dbdars SET link_class = ? WHERE id = ? AND codecourse = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$room === '' ? null : $room, (int)$_GET['room'], $courseCode]);
        $_SESSION['message'] = 'شناسه اتاق چهاردیواری درس با موفقیت ذخیره شد.';
        header("Location: dars.php?code=$codecourse");
        die();
    }
}

if (isset($_GET['delete'])) {
    $sql = "DELETE FROM dbdars WHERE id = ? AND codecourse = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([(int)$_GET['delete'], $courseCode]);
    $code = $courseCode;
    header("Location: dars.php?code=$code");
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
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست درس ها | <?php echo __TITLE ?></title>
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
                          <?php if ($adminRole == 3) :
                            $course = returnCourse($_GET['code']);
                        endif;
                        if ($adminRole !== 3) : ?>
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
                        <?php if (isset($_GET['room'])) {
                            $course = returnCourse($_GET['code']);
                            $sql = "SELECT * FROM dbdars WHERE id = ? AND codecourse = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([(int)$_GET['room'], $courseCode]);
                            $res = $stmt->fetch(PDO::FETCH_OBJ);
                            if (!$res) {
                                echo '<div class="alert alert-danger" role="alert">درس مورد نظر یافت نشد.</div>';
                            } else {
                        ?>
                            <div class="col-lg-3">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <div class="d-flex align-items-center mb-1" style="gap: 12px;">
                                                    <h6 class="main-content-label mb-0">
                                                        اتاق چهاردیواری درس
                                                        <span class="text-info">
                                                            <?php echo htmlspecialchars($res->dars, ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </h6>

                                                    <span class="menu-info-trigger"
                                                          data-tooltip="شناسه اتاق چهاردیواری (اسکای‌روم) مخصوص این درس را وارد کنید. پس از ثبت، از همین منوی اقدام می‌توانید مستقیماً وارد اتاق این درس شوید. برای حذف اتاق، فیلد را خالی بگذارید و ذخیره کنید."
                                                          aria-label="راهنمای تنظیم اتاق چهاردیواری درس">

                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10"> شناسه اتاق چهاردیواری <span
                                                            class="tx-danger">*</span></p>
                                                    <input class="form-control" name="room_class"
                                                           aria-describedby="basic-addon2"
                                                           type="text" placeholder="4562153"
                                                           value="<?php echo htmlspecialchars($res->link_class ?? '', ENT_QUOTES, 'UTF-8'); ?>">
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
                                                    <button class="btn ripple  btn-info pd-x-20" type="submit"
                                                        name="updateroom">ذخیره اتاق
                                                    </button>
                                                    <?php if (!empty($res->link_class)) { ?>
                                                        <a class="btn ripple btn-outline-primary pd-x-20"
                                                           href="login_skyroom.php?logindars=<?php echo $res->id ?>"
                                                           target="_blank">ورود به اتاق</a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <?php } else if (!isset($_GET['edit'])) {
                            $course = returnCourse($_GET['code']);
                            if ($adminRole !== 3) :?>
                            <div class="col-lg-3">
                                <div class="card custom-card">
                                    <div class="card-body" >
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <div>
                                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                                        <h6 class="main-content-label mb-0">
                                                            افزودن درس برای
                                                            <span class="text-info">
                                                                <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                        </h6>

                                                        <span class="menu-info-trigger"
                                                              data-tooltip="در این بخش می‌توانید درس جدیدی برای پایه انتخاب‌شده تعریف کنید. درس ثبت‌شده به این پایه متصل می‌شود و پس از آن می‌توانید کلاس‌ها، سرفصل‌ها و محتوای آموزشی مرتبط با آن را مدیریت کنید."
                                                              aria-label="راهنمای افزودن درس به پایه">

                                                            <i class="fe fe-info fs-4"></i>
                                                        </span>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام درس:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="dars_name"
                                                        placeholder="نام را وارد کنید ..." required>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                        value="<?php echo $hash ?>">
                                                </div>
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">کلاس‌های مربوط به درس:<span class="tx-danger">*</span></p>
                                                    <?php if ($courseClasses): ?>
                                                        <select class="form-control select2" name="classes[]" required multiple
                                                                data-placeholder="یک یا چند کلاس را انتخاب کنید">
                                                            <?php foreach ($courseClasses as $class): ?>
                                                                <option value="<?= (int)$class->id ?>"><?= htmlspecialchars($class->name) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="text-muted">این درس فقط در کلاس‌های انتخاب‌شده نمایش داده می‌شود.</small>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning mb-0">ابتدا برای این پایه کلاس تعریف کنید.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-success pd-x-20" type="submit"
                                                        name="adddars">ثبت درس
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php } else {
                            $sql = "SELECT * FROM dbdars WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$_GET['edit']]);
                            $res = $stmt->fetch(PDO::FETCH_OBJ);
                            $selectedClassStmt = $conn->prepare('SELECT class_id FROM class_dars WHERE dars_id = ?');
                            $selectedClassStmt->execute([(int)$_GET['edit']]);
                            $selectedClassIds = array_map('intval', $selectedClassStmt->fetchAll(PDO::FETCH_COLUMN));
                        ?>
                            <div class="col-lg-3">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-xl-12">
                                                <?php $course = returnCourse($_GET['code']); ?>
                                                <div>
                                                    <h6 class="main-content-label mb-1">ویرایش درس برای پایه <span
                                                            class="text-info"> <?php echo $course->coursename ?></span>
                                                    </h6>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام درس:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="dars_name"
                                                        placeholder="نام را وارد کنید ..." value="<?= $res->dars ?>"
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
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">کلاس‌های مربوط به درس:<span class="tx-danger">*</span></p>
                                                    <select class="form-control select2" name="classes[]" required multiple>
                                                        <?php foreach ($courseClasses as $class): ?>
                                                            <option value="<?= (int)$class->id ?>"<?= in_array((int)$class->id, $selectedClassIds, true) ? ' selected' : '' ?>>
                                                                <?= htmlspecialchars($class->name) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-warning pd-x-20" type="submit"
                                                        name="updatedars">ویرایش درس
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <?php endif; ?>

                        <div   <?php if($adminRole ==3) : ?> style=" width: 100%;" <?php  else : ?>class="col-lg-9" <?php endif; ?> >                            
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-xl-12 mb-4">
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                لیست درس‌های
                                                <span class="text-info">
                                                        <?php echo htmlspecialchars($course->coursename, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید درس‌های تعریف‌شده برای پایه انتخاب‌شده را مشاهده و مدیریت کنید. اطلاعات هر درس و گزینه‌های مرتبط با ویرایش، حذف یا مدیریت محتوای آموزشی آن از این قسمت در دسترس است."
                                                  aria-label="راهنمای لیست درس‌های پایه">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>

                                        </div>
                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                            <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>نام درس</th>
                                                    <th>کلاس‌های درس</th>
                                                    <th>تعداد سرفصل ها</th>
                                                    <th>تعداد فیلم ها</th>
                                                    <th>تعداد جزوات</th>
                                                    <th>اتاق چهاردیواری</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = 'SELECT * FROM dbdars WHERE codecourse = ?';
                                                $query = $conn->prepare($sql);
                                                $query->execute([$_GET['code']]);
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    $rt = 1;
                                                    foreach ($result as $dars) {
                                                ?>

                                                        <tr>
                                                            <td class="wd-15"><?php echo $rt ?></td>
                                                            <td class="wd-15"><?= $dars->dars ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php
                                                                $darsClassesStmt = $conn->prepare("SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '، ') FROM class_dars cd INNER JOIN classes c ON c.id = cd.class_id WHERE cd.dars_id = ?");
                                                                $darsClassesStmt->execute([(int)$dars->id]);
                                                                echo htmlspecialchars((string)($darsClassesStmt->fetchColumn() ?: 'تخصیص داده نشده'));
                                                                ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php
                                                                $sql = 'SELECT COUNT(*) AS count_chapters FROM dblesson WHERE dars_id = ?';
                                                                $stmt = $conn->prepare($sql);
                                                                $stmt->execute([$dars->id]);
                                                                $countChapters = $stmt->fetch(PDO::FETCH_OBJ);
                                                                echo $countChapters->count_chapters;
                                                                ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php
                                                                $sql = 'SELECT COUNT(*) AS count_movies FROM dbmovie WHERE dars_id = ?';
                                                                $stmt = $conn->prepare($sql);
                                                                $stmt->execute([$dars->id]);
                                                                $countMovies = $stmt->fetch(PDO::FETCH_OBJ);
                                                                echo $countMovies->count_movies;
                                                                ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php
                                                                $sql = 'SELECT COUNT(*) AS count_addons FROM dbaddon WHERE dars_id = ?';
                                                                $stmt = $conn->prepare($sql);
                                                                $stmt->execute([$dars->id]);
                                                                $countAddons = $stmt->fetch(PDO::FETCH_OBJ);
                                                                echo $countAddons->count_addons;
                                                                ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php if (!empty($dars->link_class)) { ?>
                                                                    <span class="badge bg-info">
                                                                        <?php echo htmlspecialchars($dars->link_class, ENT_QUOTES, 'UTF-8'); ?>
                                                                    </span>
                                                                <?php } else { ?>
                                                                    <span class="text-muted">تنظیم نشده</span>
                                                                <?php } ?>
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
                                                                                    href="dars-teachers.php?code=<?php echo (int)$dars->codecourse ?>&dars=<?php echo (int)$dars->id ?>">
                                                                                    <i class="fe fe-users"></i> مشاهده معلمان
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                    href="lesson.php?code=<?php echo $dars->codecourse ?>&dars=<?= $dars->id ?>">
                                                                                    <i class="fe fe-book"></i> مدیریت سرفصل ها
                                                                                </a>
                                                                                <?php if (!empty($dars->link_class)) { ?>
                                                                                    <a class="dropdown-item text-primary"
                                                                                        href="login_skyroom.php?logindars=<?= $dars->id ?>"
                                                                                        target="_blank">
                                                                                        <i class="fe fe-tv"></i> ورود به اتاق چهاردیواری
                                                                                    </a>
                                                                                <?php } ?>
                                                                                <?php if ($adminRole == 2 or Permision('permision_deletcourse')) { ?>
                                                                                    <a class="dropdown-item"
                                                                                        href="movie.php?code=<?php echo $dars->codecourse ?>&dars=<?= $dars->id ?>">
                                                                                        <i class="fe fe-film"></i>
                                                                                        مدیریت فیلم ها
                                                                                    </a>
                                                                                    <a class="dropdown-item"
                                                                                        href="addon-doc.php?code=<?php echo $dars->codecourse ?>&dars=<?= $dars->id ?>">
                                                                                        <i class="fe fe-book-open"></i> مدیریت جزوات
                                                                                    </a>
                                                                                    <a class="dropdown-item"
                                                                                        href="dars.php?code=<?php echo $dars->codecourse ?>&room=<?= $dars->id ?>">
                                                                                        <i class="fe fe-video"></i> تنظیم اتاق چهاردیواری
                                                                                    </a>
                                                                                    <hr>
                                                                                    <a class="dropdown-item text-success "
                                                                                        href="dars.php?code=<?php echo $dars->codecourse ?>&edit=<?php echo $dars->id ?>">
                                                                                        <i class="fe fe-edit"></i> ویرایش درس
                                                                                    </a>
                                                                                <?php } ?>
                                                                                <?php
                                                                                if (Permision('permision_deletcourse')) { ?>
                                                                                    <a class="dropdown-item text-danger "
                                                                                        href="?code=<?= $dars->codecourse ?>&delete=<?php echo $dars->id ?>"
                                                                                        onclick="return confirm('آیا با حذف این درس  موافقید؟')"><i
                                                                                            class="fe fe-x-square"></i>
                                                                                        حذف
                                                                                        درس
                                                                                    </a>
                                                                                <?php } ?>


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
                                                        <td colspan="7" class="text-center">درسی موجود نمی باشد</td>
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
