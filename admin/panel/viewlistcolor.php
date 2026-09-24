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
switch ($_GET['color']) {
    case 0:
        $colors = 'قرمز';
        break;
    case 1:
        $colors = 'نارنجی';
        break;
    case 2:
        $colors = 'زرد';
        break;
    case 3:
        $colors = 'سبز';
        break;
}
error_log("course_color.php: Session ID: " . session_id());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("course_color.php: POST data: " . print_r($_POST, true));
}

if (isset($_POST['course_color_excel'])) {
    $code = isset($_POST['code']) && preg_match('/^[a-zA-Z0-9]+$/', $_POST['code']) ? $_POST['code'] : '';
    $color = isset($_POST['color']) && in_array($_POST['color'], ['0', '1', '2', '3']) ? $_POST['color'] : '';
    if (empty($code) || $color === '') {
        error_log("Invalid or missing code or color parameter in POST request: code=$code, color=$color");
        header('Location: course_color.php?error=invalid_params');
        exit;
    }
    $_SESSION['course_color_excel'] = [
        'code' => $code,
        'color' => $color,
        'export' => true
    ];
    error_log("Session set for course_color_excel with code: $code, color: $color, export: true");
    header('Location: ../Bill/execel.php');
    exit;
}

// Display error messages
if (isset($_GET['error'])) {
    $errorMessages = [
        'invalid_params' => 'خطا: کد پایه یا رنگ نامعتبر است.',
        'session_missing' => 'خطا: اطلاعات جلسه یافت نشد.',
        'no_data' => 'خطا: داده‌ای برای این گزارش یافت نشد.',
        'excel_error' => 'خطا: خطا در تولید فایل اکسل.',
        'function_missing' => 'خطا: توابع مورد نیاز یافت نشد.',
        'db_missing' => 'خطا: اتصال به پایگاه داده برقرار نیست.'
    ];
    $error = $_GET['error'];
    echo '<p class="text-danger">' . ($errorMessages[$error] ?? 'خطا: خطای ناشناخته') . '</p>';
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
    <title>گزارش نفرات رنگی | <?php echo __TITLE ?></title>
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
                                        <h6 class="main-content-label mb-1">گزارش تعداد دانش آموزان <?php echo $colors ?>
                                            <span class="text-primary"></span>
                                        </h6>
                                        <p class="text-muted card-sub-title">کد پایه: <span class="text-primary"><?php echo $_GET['Id'] ?></span></p>
                                    </div>
                                    <?php
                                    $currentUser = $_SESSION['admin']['user'];
                                    if (PermisionUser('excel_permission', $currentUser)):
                                        $code = isset($_GET['Id']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['Id']) ? $_GET['Id'] : '';
                                        $color = isset($_GET['color']) && in_array($_GET['color'], ['0', '1', '2', '3']) ? $_GET['color'] : '';
                                        if (empty($code) || $color === '') {
                                            echo '<p class="text-danger">خطا: کد پایه یا رنگ نامعتبر است.</p>';
                                        } else {
                                            ?>
                                            <form action="" method="post" style="display: inline;">
                                                <input type="hidden" name="course_color_excel" value="1">
                                                <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                                                <input type="hidden" name="color" value="<?= htmlspecialchars($color) ?>">
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
                                            <th>نام پایه</th>
                                            <th>آزمون </th>
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
                                        $sql = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  dbcourse.codecourse=? AND dbcourse.activityuser=1 AND codtbl_course.activecourse=1 AND dbcourse.color=? ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([ $_GET['Id'], $_GET['color']]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($query->rowCount() > 0) {
                                        foreach ($result as $ret) {
                                        ?>
                                        <tr>
                                            <td class="wd-15"><?php echo $id ?></td>

                                            <td class="wd-15">
                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                     onclick="window.open('suporting.php?Id='+'<?php echo $ret->username ?>','پشتیبانی'
                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                    <i class="fa fa-key"></i></div>

                                            </td>
                                            <td class="wd-15"><?php echo $ret->username; ?></td>
                                            <?php
                                           $personal=retPerson($ret->username);

                                            ?>
                                            <td class="wd-15"><?php echo $personal->fname.' '.$personal->lname ?></td>
                                            <?php $namecourse = returnCourse($ret->codecourse); ?>
                                            <td><?php echo $namecourse->coursename ?></td>
                                            <?php
                                            $quiz=getTblQuizColor($ret->codecourse,$ret->username);
                                            ?>
                                            <td class="wd-15"><?php echo $quiz[0] ;  ?></td>



                                            <?php
                                            $suport = getsuport_user($ret->username);
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
                                                if ($suport['typesupport'] == 1) {
                                                    $type = '<span class="badge badge-success">ویـژه</span>';
                                                } elseif ($suport['typesupport'] == 2) {
                                                    $type = '<span class="badge badge-primary">ویژه فعال</span>';
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
                                            switch ($ret->color) {
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
                                            <td class="wd-15"><?php echo $color;  ?></td>
                                            <td class="wd-15"><?php echo $ret->suporter;  ?></td>
                                            <?php
                                            $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE  codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                            $wer = $conn->prepare($swr);
                                            $wer->execute([1, 1, $ret->username]);
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
                                            <td class="wd-20"> <?php echo number_format($sumtoto) ?></td>
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
