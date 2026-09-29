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
$sql = "SELECT * FROM course_demo WHERE codecourse = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$_GET['code']]);
if (!$stmt->rowCount() > 0) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (isset($_GET['del'])) {
    AdminRequire('courses.main.demo-active.students.remove');
    $sql = 'DELETE FROM dbcourse_demo WHERE codecourse = ? AND username = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['code'], $_GET['del']]);
}



//duplicate ends here


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست دانش آموزان | <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row row-sm">
                                    <div class="col-9">
                                        <?php
                                        $sql = "SELECT * FROM course_demo WHERE codecourse = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$_GET['code']]);
                                        $course = $stmt->fetch(PDO::FETCH_OBJ);
                                        ?>

                                        <h6 class="main-content-label mb-1">لیست دانش آموزان پایه <span
                                                    class="text-warning"> <?php echo $course->coursename ?> (<?php echo $_GET['code'] ?>)</span>
                                        </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>

                                    </div>
                                    <div class="col-3 text-left">
                                        <a href="export-student.php?code=<?php echo $_GET['code'] ?> " target="_blank" class="btn btn-sm btn-primary"><i
                                                    class="fe fe-download"></i> خروجی لیست دانش آموزان </a>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0" id="example1">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>#</th>
                                            <th>نام دانش آموز</th>
                                            <th>ایجادکننده</th>
                                            <th>کدملی</th>
                                            <th>شماره تماس</th>
                                            <?php if (AdminCan('courses.main.demo-active.students.remove')) { ?>
                                            <th>اقدامات</th>
                                            <?php } ?>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbcourse_demo WHERE codecourse=?';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$_GET['code']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            $counter = 1;

                                            foreach ($result as $ret) {
                                                $fullname = retPerson($ret->username);

                                                ?>
                                                <tr>
                                                    <td class="wd-15"><?php echo $counter ?></td>
                                                    <td>
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('../panel/suporting.php?Id=<?= htmlspecialchars($ret->username) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                    <td><?php echo $ret->useradder ?></td>
                                                    <td class="wd-100"><?php echo $ret->username ?></td>
                                                    <td class="wd-100"><?php echo $fullname->tel1 ?></td>
                                                    <?php if (AdminCan('courses.main.demo-active.students.remove')) { ?>
                                                        <td class="wd-20">
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown"
                                                                                type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-8">
                                                                            <a class="dropdown-item text-danger"
                                                                               href="list-demo-student.php?code=<?php echo $ret->codecourse ?>&del=<?= $ret->username ?>"
                                                                               onclick="return confirm('آیا با حذف این دانش آموز موافقید؟')"><i
                                                                                        class="fe fe-x-square"></i>
                                                                                حذف دانش آموز
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    <?php } ?>
                                                </tr>

                                                <?php
                                                $counter++;
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
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>