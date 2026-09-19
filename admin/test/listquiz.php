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
if (!Permision('permision_viewtest')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['search'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $_SESSION['qroupquiz'] = $_POST['group'];
        header('location: listquiz.php');
    }
}
if (isset($_GET['newgroup'])) {
    unset($_SESSION['qroupquiz']);
    header('location: listquiz.php');
    die();
}
if (isset($_GET['clean'])) {
    $clean = intval($_GET['clean']);
    $dcl = 'DELETE FROM result_quiz WHERE quizid=?';
    $qcl = $conn->prepare($dcl);
    $qcl->execute([$clean]);
    header('location: listquiz.php');
}
if (isset($_GET['delete'])) {
    $clean = intval($_GET['delete']);
    $dcl = 'DELETE FROM dbquiz WHERE idquiz=?';
    $qcl = $conn->prepare($dcl);
    $qcl->execute([$clean]);
    header('location: listquiz.php');
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
    <title> لیست آزمون ها |<?php echo __TITLE ?></title>
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
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div>
                                            <h6 class="main-content-label mb-1">لیست آزمون ها</h6>
                                            <p class="text-muted card-sub-title">آزمون های مرتبط با دوره ها
                                            </p>
                                        </div>
                                    </div>

                                    <div class="col-6 text-left">
                                       
                                        <a href="?newgroup" class="btn btn-sm btn-primary"><i
                                                    class="fe fe-refresh-cw"></i> بازخوانی دوره ها </a>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="card">
                                        <?php if (!isset($_SESSION['qroupquiz'])) { ?>
                                            <form method="post" data-parsley-validate="">
                                                <div class="row">
                                                    <div class="col-xl-6 mb-2">
                                                        <label for="sens" class="form-label mg-t-15">انتخاب دوره / طرح
                                                            های فعال
                                                            <span
                                                                    style="color: red">*</span></label>
                                                        <select class="form-control select2 "
                                                                name="group"
                                                                aria-describedby="basic-addon2" required>
                                                            <option label="گروه ">
                                                            </option>
                                                            <?php
                                                            $sql = 'SELECT * FROM codtbl_course  WHERE activecourse=? ORDER BY idcourse DESC ';
                                                            $query = $conn->prepare($sql);
                                                            $query->execute([1]);
                                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                                            if ($query->rowCount() > 0) {
                                                                foreach ($result as $res) {


                                                                    ?>
                                                                    <option value="<?php echo $res->codecourse ?>"><?php echo $res->coursename ?> | <?php echo $res->codecourse ?> </option>
                                                                <?php }
                                                            }
                                                            ?>

                                                        </select>
                                                    </div>

                                                </div>
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">


                                                <div class="text-center mg-10">
                                                    <button type="submit" class="btn btn-success "
                                                            name="search"><i
                                                                class="fa fa-database"></i>
                                                        انتخاب دوره / طرح
                                                    </button>
                                                </div>
                                            </form>
                                            <!--                                        end step 1-->
                                        <?php } else { ?>
                                            <?php
                                            $course = returnCourse($_SESSION['qroupquiz']);
                                            ?>
                                            <div class="header-message">نام دوره : <span
                                                        class="text-warning"><?php echo $course->coursename ?></span>
                                            </div>
                                            <?php   $sql2 = 'SELECT COUNT(idquiz) as codb FROM dbquiz WHERE codecourse=?';
                                                $query2 = $conn->prepare($sql2);
                                                $query2->execute([$_SESSION['qroupquiz']]);
                                                $raa = $query2->fetch(PDO::FETCH_OBJ);
                                                ?>
                                            <div class="header-message">تعداد آزمون : <span
                                                        class="text-info"><?php echo $raa->codb ?></span>
                                            </div>
                                            <table class="table text-nowrap text-md-nowrap table-bordered"
                                                   id="example1">
                                                <thead>
                                                <tr>

                                                    <th>شناسه</th>
                                                    <th>اقدام</th>
                                                    <th>نام آزمون</th>
                                                    <th>درس</th>
                                                    <th>توالی</th>
                                                    <th>شرکت کننده</th>
                                                    <th>غائبین</th>
                                                    <th>معدل</th>
                                                    <th>بالاترین تراز</th>
                                                    <th>پایین تراز</th>

                                                    <th>تاریخ برگزاری</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $sql1 = 'SELECT * FROM dbquiz WHERE codecourse=? ORDER BY datestart DESC ';
                                                $query1 = $conn->prepare($sql1);
                                                $query1->execute([$_SESSION['qroupquiz']]);
                                                $resy = $query1->fetchAll(PDO::FETCH_OBJ);
                                                if ($query1->rowCount() > 0) {

                                                    foreach ($resy as $ret) {

                                                        ?>
                                                        <tr>

                                                            <td class="wd-15 text-danger">
                                                                <strong><?php echo $ret->idquiz ?></strong></td>
                                                            <td class="wd-15">
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
                                                                                <a class="dropdown-item  "
                                                                                   href="addquiz.php?edit=<?php echo $ret->idquiz ?>">
                                                                                    <i class="fe fe-edit-2"></i> ویرایش
                                                                                    آزمون
                                                                                </a>
                                                                                <?php if($ret->resultpdf!= NULL){ ?>
                                                                                <a class="dropdown-item  "
                                                                                   href="<?php echo $ret->resultpdf ?>">
                                                                                    <i class="fe fe-eye"></i> دانلود پاسخنامه

                                                                                </a>
                                                            <?php } ?>
                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('absent.php?Id='+'<?php echo $ret->idquiz ?>',' لیست غائبین'
                                                                                             ,'width=1000,height=900,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-user-x"></i>
                                                                                    لیست غائبین
                                                                                </div>

                                                                                <div class="dropdown-item "
                                                                                     style="text-align: right"
                                                                                     onclick="window.open('list-result.php?Id='+'<?php echo $ret->idquiz ?>',' لیست نتایج'
                                                                                             ,'width=1000,height=900,menubar=0,toolbar=0,resizable=0,left=250,top=50,status=0,scrollbars=0,location=0' )">
                                                                                    <i class="fe fe-user-check"></i>
                                                                                    لیست نتایج
                                                                                </div>
                                                                                <a class="dropdown-item text-warning "
                                                                                   href="?clean=<?php echo $ret->idquiz ?>"
                                                                                   onclick="return confirm('آیا با حذف اطلاعات آزمون  موافقید؟ این اقدام غیر قابل بازگشت است!')">
                                                                                    <i class="fe fe-delete"></i> پاک
                                                                                    کردن اطلاعات

                                                                                </a>
                                                                                <?php if (Permision('permision_deltest')) { ?>

                                                                                    <hr>
                                                                                    <a class="dropdown-item text-danger "
                                                                                       href="?delete=<?php echo $ret->idquiz ?>"
                                                                                       onclick="return confirm('آیا با حذف این آزمون  موافقید؟ این اقدام غیر قابل بازگشت است!')">
                                                                                        <i class="fe fe-x"></i> حذف
                                                                                        آزمون

                                                                                    </a>
                                                                                <?php } ?>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo $ret->quizname ?></td>
                                                            <td class="wd-20"><?php echo getdars($ret->dars) ?></td>
                                                            <td class="wd-10"><?php echo $ret->tasalsol ?></td>
                                                            <td class="wd-15"><?php echo countresultquiz($ret->idquiz) ?></td>
                                                            <td class="wd-15"><?php echo absetuser($ret->idquiz) ?></td>
                                                            <td class="wd-15"><?php echo round(avequiz($ret->idquiz), 2) ?></td>
                                                            <td class="wd-15"><?php echo number_format(maxtaraz($ret->idquiz)) ?></td>
                                                            <td class="wd-15"><?php echo number_format(mintaraz($ret->idquiz)) ?></td>
                                                            <?php
                                                            $dateno = $ret->datestart;
                                                            $dateno = explode('-', $dateno);
                                                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                            $dateno = implode('/', $dateno);
                                                            ?>
                                                            <td class="wd-15"><?php echo $dateno ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                                </tbody>
                                            </table>
                                        <?php } ?>
                                    </div>
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
