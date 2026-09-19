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

if (isset($_POST['addlesson'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['lesson_name'];
        $sql = "INSERT INTO dblesson (codecourse, name) VALUES (?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$_GET['code'], $name]);
    }
}

if (isset($_POST['updatelesson'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $name = $_POST['lesson_name'];
        $codecourse = $_POST['codecourse'];
        $sql = "UPDATE dblesson SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $_GET['edit']]);
        header("Location: lesson.php?code=$codecourse");
        die();
    }
}

if (isset($_GET['delete'])) {
    $sql = "DELETE FROM dblesson WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['delete']]);
    $code = $_GET['code'];
    header("Location: lesson.php?code=$code");
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
                                                    <h6 class="main-content-label mb-1">افزودن سرفصل برای پایه <span
                                                            class="text-warning"> <?php echo $course->coursename ?></span> </h6>
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
                                                    <h6 class="main-content-label mb-1">ویرایش سرفصل برای پایه <span
                                                            class="text-warning"> <?php echo $course->coursename ?></span> </h6>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="post" data-parsley-validate="">
                                            <div class="row">
                                                <div class="col-lg-12 mg-t-20">
                                                    <p class="mg-b-10">نام سرفصل:<span class="tx-danger">*</span></p>
                                                    <input class="form-control" name="lesson_name"
                                                        placeholder="نام را وارد کنید ..." value="<?= $res->name ?>" required>
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
                                            <h6 class="main-content-label mb-1">لیست سرفصل های پایه <span
                                                    class="text-warning"> <?php echo $course->coursename ?></span></h6>
                                        </div>
                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>نام سرفصل</th>
                                                    <th>تعداد فیلم ها</th>
                                                    <th>تعداد جزوات</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = 'SELECT * FROM dblesson WHERE codecourse = ?';
                                                $query = $conn->prepare($sql);
                                                $query->execute([$_GET['code']]);
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
                                                                                <a class="dropdown-item text-success "
                                                                                    href="?code=<?= $_GET['code'] ?>&edit=<?php echo $lesson->id ?>">
                                                                                    <i class="fe fe-edit"></i> ویرایش سرفصل
                                                                                </a>
                                                                                <a class="dropdown-item text-danger"
                                                                                    href="?code=<?= $_GET['code'] ?>&delete=<?php echo $lesson->id ?>"
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
                                                        <td colspan="5" class="text-center">سرفصلی موجود نمی باشد</td>
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