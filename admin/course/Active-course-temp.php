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
if (isset($_GET['disable'])) {
    $disable = 'UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([0, $_GET['disable']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['enable'])) {
    $disable = 'UPDATE codtbl_course SET courseactive=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([1, $_GET['enable']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['diactive'])) {
    $disable = 'UPDATE codtbl_course SET activecourse=? WHERE codecourse=?';
    $qupd = $conn->prepare($disable);
    $qupd->execute([0, $_GET['diactive']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['close'])) {
    if (Permision('permision_deletcourse')) {
        include_once '../../quizconfig.php';
        include_once '../../inc/function-quiz.php';
        if (!checkEntityGroupQuiz($_GET['close'])) {
            $delgroups = 'DELETE FROM `_ugroups` WHERE ugtitle=?';
            $dquiz = $ponn->prepare($delgroups);
            $dquiz->execute([$_GET['close']]);
        }
        //
        $disable = 'DELETE FROM  codtbl_course  WHERE codecourse=?';
        $qupd = $conn->prepare($disable);
        $qupd->execute([$_GET['close']]);
        header('location: ' . url('admin/course/Active-course.php'));
        die();
    }
}
if (isset($_GET['act_class'])) {
    $act = 'UPDATE codtbl_course SET act_class=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_class']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['act_general'])) {
    $act = 'UPDATE codtbl_course SET act_general=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_general']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['act_quiz'])) {
    $act = 'UPDATE codtbl_course SET act_quiz=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([1, $_GET['act_quiz']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_class'])) {
    $act = 'UPDATE codtbl_course SET act_class=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_class']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_general'])) {
    $act = 'UPDATE codtbl_course SET act_general=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_general']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['expire_quiz'])) {
    $act = 'UPDATE codtbl_course SET act_quiz=? WHERE codecourse=?';
    $qupd = $conn->prepare($act);
    $qupd->execute([0, $_GET['expire_quiz']]);
    header('location: ' . url('admin/course/Active-course.php'));
    die();
}
if (isset($_GET['loginquiz'])) {
    header('location: startquiz.php?true');

}
if (isset($_GET['schedule'])) {
    $sql = "SELECT * FROM dbschedules WHERE codecourse = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['schedule']]);
    $schedule = $stmt->fetch(PDO::FETCH_OBJ);
    if ($stmt->rowCount() > 0) {
        if (file_exists($schedule->file_path)) {
            unlink($schedule->file_path);
        }
        $delete = "DELETE FROM dbschedules WHERE codecourse = ?";
        $query = $conn->prepare($delete);
        $query->execute([$_GET['schedule']]);
        $_SESSION['message'] = "برنامه هفتگی موردنظر حذف شد";
        header('Location: Active-course.php');
        die();
    } else {
        $_SESSION['message'] = "برنامه هفتگی برای این دوره تعریف نشده است";
        header('Location: Active-course.php');
        die();
    }
}


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> دوره های فعال | <?php echo __TITLE ?></title>
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
                    <div class="row row-sm">
                        <?php if (isset($_SESSION['message'])) { ?>
                            <div class="alert alert-success" role="alert">
                                <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                    <span aria-hidden="true">×</span>
                                </button>
                                <?= $_SESSION['message'] ?>
                            </div>
                            <?php unset($_SESSION['message']);
                        } ?>
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-8">
                                            <div>
                                                <h6 class="main-content-label mb-1">دوره های فعال </h6>
                                                <p class="text-muted card-sub-title">
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-2 text-left mb-3">
                                            <a href="login_skyroom.php?loginPublick " target="_blank"
                                                class="btn btn-sm btn-info"><i class="fa fa-tv"></i> ورود به کلاس مشاوره
                                                (عمومی) </a>
                                        </div>
                                        <?php
                                        if (Permision('permision_viewtest')) {
                                            ?>
                                            <div class="col-2 text-right ">
                                                <a href="?loginquiz" target="_blank" class="btn btn-sm btn-success"><i
                                                        class="fe fe-check-square"></i> ورود بـه سـامانـه آزمـون </a>
                                            </div>
                                        <?php } ?>

                                    </div>
                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                                <tr>
                                                    <th>اقدامات</th>
                                                    <th>کلاس</th>
                                                    <th>مشاوره</th>
                                                    <th>آزمون</th>
                                                    <th>کد دوره</th>
                                                    <th>نام دوره</th>
                                                    <th> موفق</th>
                                                    <th>منتظر</th>
                                                    <th>وضعیت ثبت نام</th>
                                                    <th>غیرفعالسازی</th>


                                                </tr>
                                            </thead>
                                            <tbody>

                                                <?php
                                                $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                $query = $conn->prepare($sql);
                                                $query->execute([1]);
                                                $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                if ($query->rowCount() > 0) {
                                                    foreach ($result as $res) { ?>
                                                        <tr>
                                                            <td class="wd-20">
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
                                                                                    href="add-Course.php?edit=<?php echo $res->codecourse ?>&id=<?php echo $res->idcourse ?>">
                                                                                    <i class="fe fe-edit"></i> ویرایش دوره
                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                    href="movie.php?code=<?php echo $res->codecourse ?>"><i
                                                                                        class="fe fe-film"></i>
                                                                                    افزودن فیلم
                                                                                </a>

                                                                                <a class="dropdown-item "
                                                                                    href="addon-doc.php?code=<?php echo $res->codecourse ?>">
                                                                                    <i class="fe fe-book-open"></i>
                                                                                    افزودن جزوه
                                                                                </a>
                                                                                <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                                    <a class="dropdown-item text-info "
                                                                                        href="?act_class=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-video"></i>
                                                                                        فعالسازی کلاس
                                                                                    </a>
                                                                                <?php } else { ?>
                                                                                    <a class="dropdown-item text-warning "
                                                                                        href="?expire_class=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-video-off"></i>
                                                                                        قطع کلاس
                                                                                    </a>
                                                                                <?php } ?>
                                                                                <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                                    <a class="dropdown-item text-primary "
                                                                                        href="?act_general=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-video"></i>
                                                                                        فعالسازی مشاوره
                                                                                    </a>
                                                                                <?php } else { ?>
                                                                                    <a class="dropdown-item text-warning "
                                                                                        href="?expire_general=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-video-off"></i>
                                                                                        قطع مشاوره
                                                                                    </a>
                                                                                <?php } ?>
                                                                                <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                                    <a class="dropdown-item text-secondary "
                                                                                        href="?act_quiz=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-grid"></i>
                                                                                        فعالسازی آزمون
                                                                                    </a>
                                                                                <?php } else { ?>
                                                                                    <a class="dropdown-item text-warning "
                                                                                        href="?expire_quiz=<?php echo $res->codecourse ?>">
                                                                                        <i class="fe fe-stop-circle"></i>
                                                                                        قطع آزمون
                                                                                    </a>
                                                                                <?php } ?>
                                                                                <a class="dropdown-item  "
                                                                                    href="attendance.php?id=<?php echo $res->codecourse ?>">
                                                                                    <i class="fe fe-check-square"></i>
                                                                                    افزودن حضور و غیاب
                                                                                </a>
                                                                                <?php
                                                                                if (Permision('permision_viewtest')) {
                                                                                    ?>
                                                                                    <a class="dropdown-item  "
                                                                                        href="add-quiz.php?id=<?php echo $res->codecourse ?>">
                                                                                        <i class="fa fa-qrcode"></i>
                                                                                        افزودن آزمون
                                                                                    </a>
                                                                                <?php } ?>

                                                                                <div class="dropdown-item disabled  ">
                                                                                    <i class="fe fe-cpu"></i> تنظیم برنامه کلاسی

                                                                                </div>

                                                                                <a class="dropdown-item text-primary "
                                                                                    href="<?php echo 'login_skyroom.php?loginskyroom=' . $res->codecourse ?>"
                                                                                    target="_blank">
                                                                                    <i class="fe fe-tv"> </i>ورود به کلاس آنلاین

                                                                                </a>
                                                                                <a class="dropdown-item"
                                                                                    href="list-student.php?code=<?php echo $res->codecourse ?>"><i
                                                                                        class="fe fe-more-vertical"></i>
                                                                                    لیست دانش آموزان
                                                                                </a>

                                                                                <a class="dropdown-item text-warning"
                                                                                    href="add-schedule.php?code=<?php echo $res->codecourse ?>"><i
                                                                                        class="fa-brands fa-square-instagram"></i>
                                                                                    افزودن برنامه هفتگی
                                                                                </a>

                                                                                <a class="dropdown-item text-danger"
                                                                                    href="?schedule=<?php echo $res->codecourse ?>"
                                                                                    onclick="return confirm('آیا با حذف این برنامه هفتگی  موافقید؟')"><i
                                                                                        class="fa-brands fa-square-instagram"></i>
                                                                                    حذف برنامه هفتگی
                                                                                </a>

                                                                                <?php
                                                                                if (Permision('permision_deletcourse')) { ?>
                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                        href="?close=<?php echo $res->codecourse ?>"
                                                                                        onclick="return confirm('آیا با حذف این دوره  موافقید؟')"><i
                                                                                            class="fe fe-x-square"></i> حذف دائم
                                                                                        دوره
                                                                                    </a>
                                                                                <?php } ?>


                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php if (act_classSkyroom($res->codecourse) == 0) { ?>
                                                                    <div class="text-danger"><i class="mdi mdi-adjust "></i>
                                                                    </div>
                                                                <?php } else { ?>
                                                                    <div class="text-success"><i class="fe fe-wifi "></i>
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php if (act_generalSkyroom($res->codecourse) == 0) { ?>
                                                                    <div class="text-danger"><i class="mdi mdi-adjust "></i>
                                                                    </div>
                                                                <?php } else { ?>
                                                                    <div class="text-success"><i class="fe fe-wifi "></i>
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-15">
                                                                <?php if (act_quiz($res->codecourse) == 0) { ?>
                                                                    <div class="text-danger"><i class="mdi mdi-adjust "></i>
                                                                    </div>
                                                                <?php } else { ?>
                                                                    <div class="text-success"><i class="fe fe-wifi "></i>
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-20"><?php echo $res->codecourse ?></td>

                                                            <td><?php echo htmlentities($res->coursename) ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <?php echo CountRegisterCourse($res->codecourse, 1) ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <?php echo CountRegisterCourse($res->codecourse, 0) ?>
                                                            </td>

                                                            <td class="wd-20">
                                                                <?php if ($res->courseactive == 1) { ?>
                                                                    <a href="?disable=<?php echo $res->codecourse ?>"
                                                                        class="btn btn-sm ripple btn-success btn-icon"><i
                                                                            class="fe fe-check"></i></a>
                                                                <?php } else { ?>
                                                                    <a href="?enable=<?php echo $res->codecourse ?>"
                                                                        class="btn btn-sm ripple btn-danger btn-icon"><i
                                                                            class="fe fe-x"></i></a>
                                                                <?php } ?>
                                                            </td>
                                                            <td class="wd-20">
                                                                <a href="?diactive=<?php echo $res->codecourse ?>"
                                                                    class="btn btn-sm ripple btn-warning btn-icon"><i
                                                                        class="si si-minus"></i></a>
                                                            </td>

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
                    </div>
                </div>
            </div>
        </div>
        <!-- Main Footer-->
        <?php include '../../footers.php'; ?>
        <!--End Footer-->


</body>

</html>