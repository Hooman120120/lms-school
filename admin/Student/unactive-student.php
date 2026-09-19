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
if (!Permision('permision_editstudent')) {
    header('location: ' . url('admin/index.php'));
    die();
}
if(isset($_GET['activate'])){
    $act='UPDATE dblogin SET activity=? WHERE username=? ';
    $qact=$conn->prepare($act);
    $qact->execute([1,$_GET['activate']]);
    header('location: unactive-student.php');
    die();
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
    <title> دانش آموزان قفل شده |<?php echo __TITLE ?></title>
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
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="col-10">
                                <div>
                                    <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                        <h6 class="main-content-label mb-0">
                                            لیست دانش آموزان غیرفعال‌شده
                                        </h6>

                                        <span class="menu-info-trigger"
                                              data-tooltip=" در این بخش می توانید لیست دانش آموزانی که دسترسی آن ها برای ورود به پنل کاربری خود به دلیل تکرار بیش از حد رمز عبور اشتباه مسدود و غیرفعال شده را مشاهده نمایید و در صورت نیاز اقدامات لازم جهت فعال سازی مجدد را انجام دهید."
                                              aria-label="راهنمای دانش‌آموزان غیرفعال‌شده">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                    </div>

                                    <p class="text-muted card-sub-title">
                                        غیرفعال‌شده به‌دلیل تلاش‌های ناموفق متعدد برای ورود
                                    </p>
                                </div>
                            </div>
                            <div class="table">
                                <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                    <thead>
                                    <tr>
                                        <th>اقدامات</th>
                                        <th>کدملی</th>
                                        <th>نام</th>
                                        <th>استان</th>
                                        <th>شهر</th>
                                        <th>تاریخ عضویت</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $sql='SELECT * FROM dbpersonal INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username WHERE dblogin.activity=? AND dblogin.role=? ';
                                    $query=$conn->prepare($sql);
                                    $query->execute([0,1]);
                                    $result=$query->fetchAll(PDO::FETCH_OBJ);
                                    if($query->rowCount()>0){
                                        foreach ($result as $lock){

                                            ?>
                                            <tr>
                                                <td class="wd-20">
                                                    <div class="text-wrap">
                                                        <div class="text-center">
                                                            <div class="dropdown">
                                                                <button aria-expanded="false"
                                                                        aria-haspopup="true"
                                                                        class="btn ripple btn-outline-primary dropdown-toggle"
                                                                        data-bs-toggle="dropdown" type="button">
                                                                    اقدام <i
                                                                            class="fas fa-caret-down mr-1"></i>
                                                                </button>
                                                                <div class="dropdown-menu tx-8">
                                                                    <a class="dropdown-item text-success "
                                                                       href="?activate=<?php echo $lock->username ?>"
                                                                    ><i class="fe fe-check-circle"></i>فعال سازی

                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="wd-20"><?php echo $lock->codemeli ?></td>
                                                <td><?php echo htmlentities($lock->fname).' '. htmlentities($lock->lname) ?></td>
                                                <td class="wd-20"><?php echo $lock->state ?></td>
                                                <td class="wd-20"><?php echo $lock->city ?></td>
                                                <?php
                                                $dateno = $lock->datecreated;
                                                $dateno = explode('-', $dateno);
                                                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                $dateno = implode('/', $dateno);
                                                ?>
                                                <td class="wd-20"><?php echo $dateno ?></td>
                                            </tr>
                                            <?php
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
    <!-- End Row -->


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
