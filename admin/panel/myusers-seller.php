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

$sessionUser = $_SESSION['admin']['user'];
$urlUser     = trim($_GET['user'] ?? '');
$urlType     = trim($_GET['type'] ?? '');
$isImpersonate = ($_SESSION['admin']['can_impersonate']) == 1;

$type = (int)($_GET['type'] ?? 0);

$allowedTypes = [3,2,1];
if (!in_array($type, $allowedTypes, true)) {
    echo 'دسترسی ندارید';
    exit;
}

$currentUser = $urlUser === '' ? $sessionUser : $urlUser;
if (AdminHasAnyCurrentTreePermission()) {
    $scopeCode = 'dashboard.summary.overview.desktop.seller-users';
    $scopeLeaf = $currentUser === $sessionUser ? '.own' : '.others';
    if (!AdminHasMenuPermission($scopeCode . $scopeLeaf) && !($scopeLeaf === '.own' && AdminHasMenuPermission($scopeCode . '.others'))) {
        http_response_code(403);
        exit('دسترسی به ثبت‌نام‌های این اپراتور تعریف نشده است.');
    }
} elseif ($currentUser !== $sessionUser && !$isImpersonate) {
    http_response_code(403);
    exit('دسترسی ندارید');
}

$users = [];

if ($type === 3) {
    $listSql = "
    SELECT
      p.codemeli,
      CONCAT(p.fname, ' ', p.lname) AS full_name,
      COUNT(DISTINCT d.codecourse) AS course_count,
      GROUP_CONCAT(DISTINCT c.codecourse ORDER BY c.codecourse SEPARATOR ' ، ') AS course_codes,
      GROUP_CONCAT(DISTINCT c.coursename ORDER BY c.coursename SEPARATOR ' ، ') AS course_names
    FROM dbcourse d
    INNER JOIN dbfactor f ON d.numfactor = f.idfactor
    INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
    INNER JOIN dbpersonal p ON p.codemeli = f.username
    WHERE d.activityuser = 1
      AND c.activecourse = 1
      AND f.status = ?
      AND f.factoradder = ?
      AND p.creator = f.factoradder
    GROUP BY p.codemeli, p.fname, p.lname
    ORDER BY full_name
    ";

    $stmt = $conn->prepare($listSql);
    $stmt->execute(['موفق', $currentUser]);
    $users = $stmt->fetchAll(PDO::FETCH_OBJ);
}
elseif ($type === 2) {
    $listSql = "
    SELECT
      p.codemeli,
      CONCAT(p.fname, ' ', p.lname) AS full_name,
      0 AS course_count,
      '' AS course_codes,
      '' AS course_names
    FROM dbpersonal p
    WHERE p.creator = ?
      AND CHAR_LENGTH(p.codemeli) = 11
    ORDER BY full_name
    ";
    $stmt = $conn->prepare($listSql);
    $stmt->execute([$currentUser]);
    $users = $stmt->fetchAll(PDO::FETCH_OBJ);
}
elseif($type === 1) {
    $listSql = "
    SELECT
      p.codemeli,
      CONCAT(p.fname, ' ', p.lname) AS full_name,
      0 AS course_count,
      '' AS course_codes,
      '' AS course_names
    FROM dbpersonal p
    WHERE p.creator = ?
      AND CHAR_LENGTH(p.codemeli) = 10
    ORDER BY full_name
";
    $stmt = $conn->prepare($listSql);
    $stmt->execute([$currentUser]);
    $users = $stmt->fetchAll(PDO::FETCH_OBJ);
}
else{
    echo "پیدا نشد";
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
    <title> پایه های فعال | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!-- <div id="global-loader">
    <img src="<//?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div> -->
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
                <div style="padding:10px;">
                    <style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.1);
                        }

                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2);
                        }

                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.25);
                        }
                        
                    </style>
                </div>

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


                    <div class="tab-content custom-tab-pane">

                        <!-- Type 1 tab -->
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">
                            <?php if ($type==3) { ?>
                            <p class="fs-6 font-weight-bold">
                                <?= inst_is('school') ? 'لیست دانش‌آموزان کارشناس فروش' : 'لیست نفر و نفر دوره کارشناس فروش' ?>
                            </p>
                            <?php } ?>
                            <?php if ($type==1) { ?>
                                <p class="fs-6 font-weight-bold">
            لیست نفرات قطعی شما
                                </p>
                            <?php } ?>
                            <?php if ($type==2) { ?>
                                <p class="fs-6 font-weight-bold">
            لیست نفرات موقت شما
                                </p>
                            <?php } ?>
                            <div class="col-lg-12">
                                <div class="card custom-card">

                                    <div class="card-body">
                                        <div class="table">
                                            <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>کد ملی</th>
                                                    <th>وضعیت دانش آموز</th>
                                                    <?php if($type==3){  ?>
                                                        <th style="width:70px; max-width:70px; white-space:normal; overflow-wrap:anywhere; word-break:break-word;">
                                                            پایه ها
                                                        </th>
                                                        <th>اقدامات</th>
                                                    <?php } ?>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($users)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="5">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php  $count=0;  foreach ($users as $u) { ?>
                                                        <tr class="align-middle text-center">
                                                            <td>
                                                                <?php if ($type == 2) { ?>
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                     onclick="window.open('suporting.php?Id='+'<?php echo $u->codemeli; ?>','پشتیبانی'
                                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                                    <i class="fa fa-key"></i></div>
                                                                <?php } else {
                                                                    $count++;
                                                                    echo $count;
                                                                 } ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($u->full_name) ?></td>
                                                            <td><?= htmlspecialchars($u->codemeli) ?></td>

                                                            <td>
                                                                <span class="badge bg-success">فعال</span>
                                                            </td>
                                                            <?php if($type==3){  ?>
                                                            <td>

                                                                <?= htmlspecialchars($u->course_names) ?>
                                                            </td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        اقدام <i class="bi bi-caret-down-fill ms-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-end text-start">
                                                                        <!-- نمونه اکشن‌ها -->
                                                                        <a class="dropdown-item" href="user.php?codemeli=<?= urlencode($u->codemeli) ?>">مشاهده</a>
                                                                        <hr class="dropdown-divider">
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <?php } ?>

                                                        </tr>
                                                    <?php } ?>
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
        </div>
    </div>
</div>
<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->


</body>

</html>
