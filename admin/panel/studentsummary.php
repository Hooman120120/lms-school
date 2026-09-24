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
if (!Permision('permision_manage_suporter')) {
    header('location: ' . url('admin/index.php'));
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
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> گزارش دانش آموزان | <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                <div class="card custom-card overflow-hidden">
                       <div class="card-header border-bottom-0 pb-0 align-content-between justify-content-between">
                            <div class="row align-items-center">
                                <!-- Left Column: Buttons (col-md-8) -->
                                <div class="card-header border-bottom-0 pb-0">
                                    <div class=" d-flex justify-content-between align-items-center">

                                        <!-- Right Column: Title -->
                                        <div class="text-end">
                                            <label class="main-content-label my-auto pt-2">خلاصه وضعیت دانش آموزان</label>
                                            <span class="d-block fs-6 mt-2 mb-0 text-muted">وضعیت همه دانش آموزان</span>
                                        </div>

                                        <!-- Middle Column: Spacer -->
                                        <div class="flex-grow-1"></div>

                                        <!-- Left Column: Buttons -->


                                        <div class="col-6 text-end">

                                            <div class="btn-group dropdown me-2 mb-3">
                                                <a href="?paye=all" class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'all') ? 'active' : ''; ?>">
                                                    همه
                                                </a>
                                            </div>

                                            <div class="btn-group dropdown me-2 mb-3">
                                                <a href="?paye=ابتدایی" class="btn btn-info shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ابتدایی') ? 'active' : ''; ?>">
                                                    <i class="fa fa-graduation-cap"></i> گروه ابتدایی
                                                </a>
                                                <button type="button" class="btn btn-info shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'اول') ? 'active' : ''; ?>" href="?paye=اول">کلاس اول دبستان</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوم') ? 'active' : ''; ?>" href="?paye=دوم">کلاس دوم دبستان</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'چهارم') ? 'active' : ''; ?>" href="?paye=چهارم">کلاس چهارم دبستان</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'پنجم') ? 'active' : ''; ?>" href="?paye=پنجم">کلاس پنجم دبستان</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'ششم') ? 'active' : ''; ?>" href="?paye=ششم">کلاس ششم دبستان</a></li>
                                                </ul>
                                            </div>

                                            <!-- Middle School -->
                                            <div class="btn-group dropdown me-2 mb-3">
                                                <a href="?paye=متوسطه_اول" class="btn btn-success shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_اول') ? 'active' : ''; ?>">
                                                    <i class="fa fa-book"></i> متوسطه اول
                                                </a>
                                                <button type="button" class="btn btn-success shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هفتم') ? 'active' : ''; ?>" href="?paye=هفتم">هفتم</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'هشتم') ? 'active' : ''; ?>" href="?paye=هشتم">هشتم</a></li>
                                                    <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'نهم') ? 'active' : ''; ?>" href="?paye=نهم">نهم</a></li>
                                                </ul>
                                            </div>

                                            <!-- High School Dropdown -->
                                            <div class="btn-group dropdown me-2 mb-3">
                                                <a href="?paye=متوسطه_دوم" class="btn btn-warning shadow rounded <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'متوسطه_دوم') ? 'active' : ''; ?>">
                                                    <i class="fa fa-star"></i> متوسطه دوم
                                                </a>
                                                <button type="button" class="btn btn-warning shadow rounded dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <!-- دهم -->
                                                    <li class="dropstart d-flex flex-row-reverse">
                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم') ? 'active' : ''; ?>" href="?paye=دهم">دهم</a>
                                                        <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,ریاضی') ? 'active' : ''; ?>" href="?paye=دهم,ریاضی">ریاضی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,تجربی') ? 'active' : ''; ?>" href="?paye=دهم,تجربی">تجربی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,انسانی') ? 'active' : ''; ?>" href="?paye=دهم,انسانی">انسانی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,فنی') ? 'active' : ''; ?>" href="?paye=دهم,فنی">فنی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دهم,کارودانش') ? 'active' : ''; ?>" href="?paye=دهم,کارودانش">کارودانش</a></li>
                                                        </ul>
                                                    </li>
                                                    <!-- یازدهم -->
                                                    <li class="dropstart d-flex flex-row-reverse">
                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم') ? 'active' : ''; ?>" href="?paye=یازدهم">یازدهم</a>
                                                        <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,ریاضی') ? 'active' : ''; ?>" href="?paye=یازدهم,ریاضی">ریاضی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,تجربی') ? 'active' : ''; ?>" href="?paye=یازدهم,تجربی">تجربی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,انسانی') ? 'active' : ''; ?>" href="?paye=یازدهم,انسانی">انسانی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,فنی') ? 'active' : ''; ?>" href="?paye=یازدهم,فنی">فنی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'یازدهم,کارودانش') ? 'active' : ''; ?>" href="?paye=یازدهم,کارودانش">کارودانش</a></li>
                                                        </ul>
                                                    </li>
                                                    <!-- دوازدهم -->
                                                    <li class="dropstart d-flex flex-row-reverse">
                                                        <a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم') ? 'active' : ''; ?>" href="?paye=دوازدهم">دوازدهم</a>
                                                        <a class="dropdown-item dropdown-toggle" style="width: 25%;" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"></a>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,ریاضی') ? 'active' : ''; ?>" href="?paye=دوازدهم,ریاضی">ریاضی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,تجربی') ? 'active' : ''; ?>" href="?paye=دوازدهم,تجربی">تجربی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,انسانی') ? 'active' : ''; ?>" href="?paye=دوازدهم,انسانی">انسانی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,فنی') ? 'active' : ''; ?>" href="?paye=دوازدهم,فنی">فنی</a></li>
                                                            <li><a class="dropdown-item <?php echo (isset($_GET['paye']) && $_GET['paye'] === 'دوازدهم,کارودانش') ? 'active' : ''; ?>" href="?paye=دوازدهم,کارودانش">کارودانش</a></li>
                                                        </ul>
                                                    </li>
                                                </ul>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                        <div class="card-body">
                            <!-- Table (unchanged) -->
                            <div class="row">
                                <div class="table">
                                    <table class="table table-bordered">
                                        <thead>
                                        <tr class="bg-light">
                                            <th>عنوان</th>
                                            <th>پایه</th>
                                            <th class="wd-15">تعداد</th>
                                            <th class="wd-15"></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <!-- First 2 rows untouched -->
                                        <tr>
                                            <?php
                                            $sql = 'SELECT COUNT(*) as cowait FROM dbcourse WHERE type_process=1 ';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                            $ret = $query->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <td>در انتظار تایید رنگبندی دستی</td>
                                            <td>#</td>
                                            <td><?php echo $ret->cowait ?></td>
                                            <td>
                                                <div class="btn ripple btn-light"
                                                     onclick="window.open('actmanualcolor.php','تایید رنگ بندی',
                                         'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                    مشاهده
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <?php
                                            $sql = 'SELECT COUNT(*) as copr FROM suport_user WHERE typesupport=1 ';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                            $rer = $query->fetch(PDO::FETCH_OBJ);
                                            ?>
                                            <td><span class="badge badge-success">ویــژه</span></td>
                                            <td>#</td>
                                            <td><?php echo $rer->copr ?></td>
                                            <td>
                                                <div class="btn ripple btn-light"
                                                     onclick="window.open('special-user.php','کاربران ویژه',
                                         'width=1250,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                    مشاهده
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Dynamic filtered rows -->
                                        <?php
                                        $whereClause = '';
                                        if (isset($_GET['paye']) && $_GET['paye'] !== 'all') {
                                            $sql = 'SELECT d.color, d.codecourse, COUNT(*) as cocolor
                                        FROM dbcourse d
                                        INNER JOIN codtbl_course c ON d.codecourse = c.codecourse
                                        WHERE d.activityuser=1 
                                        AND c.paye = :paye
                                        GROUP BY d.color, d.codecourse
                                        ORDER BY d.color DESC';
                                            $query = $conn->prepare($sql);
                                            $query->execute([':paye' => $_GET['paye']]);
                                        } else {
                                            $sql = 'SELECT d.color, d.codecourse, COUNT(*) as cocolor
                                        FROM dbcourse d
                                        INNER JOIN codtbl_course c ON d.codecourse = c.codecourse
                                        WHERE d.activityuser=1
                                        GROUP BY d.color, d.codecourse
                                        ORDER BY d.color DESC';
                                            $query = $conn->prepare($sql);
                                            $query->execute();
                                        }

                                        $results = $query->fetchAll(PDO::FETCH_OBJ);

                                        if ($query->rowCount() > 0) {
                                            foreach ($results as $res) {
                                                if (iscourseActivate($res->codecourse)) {
                                                    switch ($res->color) {
                                                        case 0:
                                                            $ccolor = '<div class="rounded-circle bg-danger" data-bs-toggle="tooltip" title="حضور زیر 25% در کلاس آنلاین" style="width: 30px; height: 30px;"></div>';
                                                            $style = 'color:#090909;background-color: #e35b5b;border-color: #000000';
                                                            break;
                                                        case 1:
                                                            $ccolor = '<div class="rounded-circle bg-warning" data-bs-toggle="tooltip" title="حضور بین 25% تا 50% در کلاس آنلاین" style="width: 30px; height: 30px;"></div>';
                                                            $style = 'color:#090909;background-color: #f88b51;border-color: #000000';
                                                            break;
                                                        case 2:
                                                            $ccolor = '<div class="rounded-circle" data-bs-toggle="tooltip" title="حضور بین 50% تا 75% در کلاس آنلاین" style="width: 30px; height: 30px; background-color: #fffb02"></div>';
                                                            $style = 'color:#090909;background-color: #fffb02;border-color: #000000';
                                                            break;
                                                        case 3:
                                                            $ccolor = '<div class="rounded-circle bg-success" data-bs-toggle="tooltip" title="حضور بالای 75% در کلاس آنلاین" style="width: 30px; height: 30px;"></div>';
                                                            $style = 'color:#090909;background-color: #3bd006;border-color: #000000';
                                                            break;
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $ccolor; ?></td>
                                                        <?php $namecourse = returnCourse($res->codecourse); ?>
                                                        <td><?php echo $namecourse->coursename; ?></td>
                                                        <td><?php echo $res->cocolor ?></td>
                                                        <td>
                                                            <div class="btn ripple" style="<?php echo $style ?>"
                                                                 onclick="window.open('viewlistcolor.php?Id=<?php echo $res->codecourse ?>&color=<?php echo $res->color ?>',
                                                                         'مشاهده کاربران',
                                                                         'width=1550,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                مشاهده
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
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
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>