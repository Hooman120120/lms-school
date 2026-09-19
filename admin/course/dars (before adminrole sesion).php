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

if (isset($_POST['adddars'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['dars_name'];
        $sql = "INSERT INTO dbdars (dars, codecourse) VALUES (?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $_GET['code']]);
    }
}

if (isset($_POST['updatedars'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['dars_name'];
        $codecourse = $_POST['codecourse'];
        $sql = "UPDATE dbdars SET dars = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $_GET['edit']]);
        header("Location: dars.php?code=$codecourse");
        die();
    }
}

if (isset($_GET['delete'])) {
    $sql = "DELETE FROM dbdars WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delete']]);
    $code = $_GET['code'];
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
                                                    <h6 class="main-content-label mb-1">افزودن درس برای<span
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
                                                        placeholder="نام را وارد کنید ..." required>
                                                    <?php
                                                    $hash = bin2hex(random_bytes(32));
                                                    $_SESSION['token'] = $hash;
                                                    ?>
                                                    <input class="form-control" name="token" type="hidden"
                                                        value="<?php echo $hash ?>">
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
                        <?php } else {
                            $sql = "SELECT * FROM dbdars WHERE id = ?";
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
                                            </div>
                                            <div class="row">
                                                <div class="text-center mg-t-30">
                                                    <button class="btn ripple  btn-warning pd-x-20" type="submit"
                                                        name="updatedars">ویرایش کلاس
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
                                            <h6 class="main-content-label mb-1">لیست درس های<span
                                                    class="text-info"> <?php echo $course->coursename ?></span></h6>
                                        </div>
                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                            <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>نام درس</th>
                                                    <th>تعداد سرفصل ها</th>
                                                    <th>تعداد فیلم ها</th>
                                                    <th>تعداد جزوات</th>
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
                                                                                    href="lesson.php?code=<?php echo $dars->codecourse ?>&dars=<?= $dars->id ?>">
                                                                                    <i class="fe fe-book"></i> مدیریت سرفصل ها
                                                                                </a>
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
                                                        <td colspan="8" class="text-center">درسی موجود نمی باشد</td>
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