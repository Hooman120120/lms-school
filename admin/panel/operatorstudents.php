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
//HARD CODE
if ($_SESSION['admin']['user'] != 'op213') {
    if (!Permision('permision_manage_suporter')) {
        header('location: ' . url('admin/index.php'));
        die();
    }
}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
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

                    <?php
                    // Query operators with their student counts
                    $sql = 'SELECT COUNT(DISTINCT dbcourse.username) as coreg, dbadmin.* 
            FROM dbcourse 
            INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor 
            INNER JOIN codtbl_course ON codtbl_course.codecourse = dbcourse.codecourse 
            RIGHT JOIN dbadmin ON dbadmin.username = dbcourse.suporter  
            WHERE dbcourse.activityuser = 1 AND codtbl_course.activecourse = 1 AND dbfactor.status=? 
            GROUP BY dbcourse.suporter 
            ORDER BY coreg DESC';
                    $query = $conn->prepare($sql);
                    $query->execute(['موفق']);
                    $operators = $query->fetchAll(PDO::FETCH_OBJ);
                    ?>

                    <div class="card shadow-lg p-3">
                        <h5 class="card-title mb-4">انتخاب اپراتور</h5>
                        <div class="d-flex flex-wrap gap-3 justify-content-center align-content-center"
                             style="max-width: 100%; flex: 0 1 auto;">
                            <?php
                            if (!empty($operators)) {
                                foreach ($operators as $op) {
                                    ?>
                                    <div class="card text-center shadow-sm"
                                         style="width: 200px; background-color: #e6f3ff;">
                                        <!-- Light blue background -->
                                        <div class="card-body d-flex flex-column justify-content-between p-3">
                                            <h6 class="card-title mb-3 fw-bold text-dark"><?php echo htmlspecialchars($op->username); ?></h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <button
                                                        type="button"
                                                        class="btn btn-success btn-sm px-2 py-1"
                                                        onclick="window.open(
                                                                'suporterstudents.php?Op=<?php echo $op->username; ?>',
                                                                'مشاهده پشتیبان',
                                                                'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                ); return false;">
                                                    <?php echo $op->coreg; ?> دانش آموز
                                                </button>
                                                <!--                                          HARD - CODE       -->
                                                <?php if ($_SESSION['admin']['user'] != 'op213') { ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-danger btn-sm dropdown-toggle"
                                                                type="button" id="dropdown<?php echo $op->idadmin; ?>"
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            اقدام
                                                        </button>
                                                        <ul class="dropdown-menu"
                                                            aria-labelledby="dropdown<?php echo $op->idadmin; ?>">
                                                            <li>
                                                                <a class="dropdown-item text-danger"
                                                                   href="?Op=<?php echo $op->username; ?>&delallsuport=true"
                                                                   onclick="return confirm('آیا با حذف تمامی پیام های پشتیبان موافقید ؟');">
                                                                    پاک کردن پیام ها
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php } ?>
                                            </div>

                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <!-- col -->
            </div> <!-- row -->

        </div>
    </div>

    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        const operatorButtons = document.querySelectorAll('button[data-id]');
        operatorButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const operatorId = btn.dataset.id;

                // Active toggle
                operatorButtons.forEach(b => b.classList.remove('btn-primary'));
                operatorButtons.forEach(b => b.classList.add('btn-outline-primary'));
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');

                console.log("Operator clicked:", operatorId);
            });
        });
    </script>

</body>

</html>