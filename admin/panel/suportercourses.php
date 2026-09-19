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
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
    die();
}
//* End Check Session JUST ONE Session


if (isset($_POST['course_excel'])) {
    $code = isset($_GET['code']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['code']) ? $_GET['code'] : '';
    if (empty($code)) {
        error_log("Invalid or missing code parameter in POST request");
        header('Location: course_learners.php?error=invalid_code');
        exit;
    }
    $_SESSION['course_excel'] = array(
        'code' => $code
    );
    error_log("Session set for course_learners with code: $code");
    header('Location: ../Bill/execel.php');
    exit;
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
    <title>گزارش نفرات دوره | <?php echo __TITLE ?></title>
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
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 d-flex align-items-center justify-content-between">
                                        <div>
                                            <h6 class="main-content-label mb-1">گزارش تعداد دانش آموزان دوره
                                                <?php
                                                $course = returnCourse($_GET['code']);
                                                echo $course->coursename ?>
                                                <span class="text-primary"></span>
                                            </h6>
                                            <p class="text-muted card-sub-title">کد دوره: <span class="text-primary"><?php echo $_GET['code'] ?></span></p>
                                        </div>
                                        <?php
                                        $currentUser = $_SESSION['admin']['user'];
                                        if (PermisionUser('excel_permission', $currentUser)):
                                            $code = isset($_GET['code']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['code']) ? $_GET['code'] : '';
                                            if (empty($code)) {
                                                echo '<p class="text-danger">خطا: کد دوره نامعتبر یا وجود ندارد.</p>';
                                            } else {
                                                ?>
                                                <form action="" method="post" style="display: inline;">
                                                    <input type="hidden" name="course_excel" value="1">
                                                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fa fa-file-excel-o"></i> دانلود اکسل
                                                    </button>
                                                </form>
                                                <?php
                                            }
                                        endif;
                                        ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <table class="table text-nowrap text-md-nowrap table-bordered" id="example1">
                                            <thead>
                                                <tr>
                                                    <th>ردیف</th>
                                                    <th>ورود</th>
                                                    <th>کد ملی</th>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>نام دوره</th>
                                                    <th>آزمون</th>
                                                    <th>آخرین پشتیبانی</th>
                                                    <th>پشتیبانی بعدی</th>
                                                    <th>نوع</th>
                                                    <th>رنگ</th>
                                                    <th>پشتیبان</th>
                                                    <th>پرداختی</th>

                                                </tr>
                                            </thead>
                                            <tbody>

                                                <?php
                                                $sql = "SELECT * FROM `dbcourse` WHERE  `activityuser`= 1 AND codecourse = ? GROUP BY username";
                                                $query = $conn->prepare($sql);
                                                $query->execute([$_GET['code']]);
                                                $learners = $query->fetchAll(PDO::FETCH_OBJ);
                                                $id = 1;
                                                if ($query->rowCount() > 0) {
                                                    foreach ($learners as $learner) {
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $id ?></td>
                                                            <td class="wd-15">
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                    onclick="window.open('suporting.php?Id='+'<?php echo $learner->username ?>','پشتیبانی'
                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                                    <i class="fa fa-key"></i>
                                                                </div>
                                                            </td>
                                                            <td><?php echo $learner->username; ?></td>
                                                            <?php
                                                            $personal = retPerson($learner->username);

                                                            ?>
                                                            <td><?php echo $personal->fname . ' ' . $personal->lname ?></td>
                                                            <?php $dore = getCourses($learner->username);
                                                            $dore = implode(' || ', $dore);
                                                            $displayDore = mb_strlen($course->coursename) > 50 ? mb_substr($course->coursename, 0, 50) . '...' : $course->coursename;
                                                            $course = returnCourse($_GET['code']);
                                                            ?>
                                                            <td title="<?= $dore ?>"><?php echo $displayDore ?></td>
                                                            <?php
                                                            $quiz = getTblQuizColor($_GET['code'], $learner->username);
                                                            ?>
                                                            <td class="w-auto"><?= $quiz[0] ?></td>

                                                            <?php
                                                            $suport = getsuport_user($learner->username);
                                                            if ($suport) {
                                                                $last = $suport['lastdate'];
                                                                $last = explode('-', $last);
                                                                $last = gregorian_to_jalali($last[0], $last[1], $last[2]);
                                                                $last = implode('/', $last);
                                                                //
                                                                $after = $suport['afterdate'];
                                                                $after = explode('-', $after);
                                                                $after = gregorian_to_jalali($after[0], $after[1], $after[2]);
                                                                $after = implode('/', $after);
                                                                $type = $suport['typesupport'];
                                                                if ($type == 1) {
                                                                    $type = '<span class="badge badge-success">ویـژه </span>';
                                                                } else {
                                                                    $type = 'عــادی';
                                                                }
                                                            } else {
                                                                $last = 'ندارد';
                                                                $after = 'ندارد';
                                                                $type = 'عادی';
                                                            }
                                                            ?>
                                                            <td><?php echo $last ?> </td>
                                                            <td><?php echo $after ?></td>
                                                            <td><?php echo $type ?></td>
                                                            <?php
                                                            switch ($learner->color) {
                                                                case 0:
                                                                    $color = 'قرمز';
                                                                    break;
                                                                case 1:
                                                                    $color = 'نارنجی';
                                                                    break;
                                                                case 2:
                                                                    $color = 'زرد';
                                                                    break;
                                                                case 3:
                                                                    $color = 'سبز';
                                                                    break;
                                                            }
                                                            ?>
                                                            <td><?php echo $color; ?></td>
                                                            <td><?= empty($learner->suporter) ? 'ندارد' : $learner->suporter ?>
                                                            </td>
                                                            <?php
                                                            $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                                            $wer = $conn->prepare($swr);
                                                            $wer->execute([1, 1, $learner->username]);
                                                            $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                                            $total = array();
                                                            $factorCheck = array();
                                                            if ($wer->rowCount() > 0) {
                                                                foreach ($recive as $rey) {
                                                                    if (!in_array($rey->numfactor, $factorCheck)) {
                                                                        $factor = queryFactor($rey->numfactor);
                                                                        $sum = $factor->wallet + $factor->factorfee;
                                                                        array_push($total, $sum);
                                                                        array_push($factorCheck, $rey->numfactor);

                                                                    }
                                                                }
                                                            }
                                                            $sumtoto = 0;
                                                            foreach ($total as $sto) {
                                                                $sumtoto = $sto + $sumtoto;
                                                            }

                                                            ?>
                                                            <td class="w-auto"> <?php echo number_format($sumtoto) ?></td>
                                                        </tr>
                                                        <?php
                                                        $id++;
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