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
if (!isset($_GET['userId'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['save'])) {
    if (isset($_POST['username'])) {
        if ($_POST['act'] == '01') {
            foreach ($_POST['username'] as $username) {
                $uptru = 'UPDATE dblogin SET true_key=? WHERE username=?';
                $query = $conn->prepare($uptru);
                $query->execute([1, $username]);
            }

        }
        if ($_POST['act'] == '02') {
            foreach ($_POST['username'] as $username) {
                $uptru = 'UPDATE dbpersonal SET useradder=? WHERE codemeli=?';
                $query = $conn->prepare($uptru);
                $query->execute([NULL, $username]);
                //
                $tri = 'UPDATE dblogin SET true_key=? WHERE username=?';
                $wer = $conn->prepare($tri);
                $wer->execute([1, $username]);
            }
        }
    }
}
$adder = retPerson($_GET['userId']);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست زیرمجموعه : <?php echo $adder->fname . ' ' . $adder->lname ?> | <?php echo __TITLE ?></title>
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
                                <div class="col-9">
                                    <div>
                                        <h6 class="main-content-label mb-1">لیست زیر مجموعه های : <span
                                                    class="text-primary"><?php echo $adder->fname . ' ' . $adder->lname ?></span>
                                        </h6>
                                        <p class="text-muted card-sub-title">کد ملی: <?php echo $_GET['userId'] ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-3 text-left">
                                    <a href="export-collague.php?subset=<?php echo $_GET['userId'] ?> " target="_blank"
                                       class="btn btn-sm btn-primary"><i
                                                class="fe fe-download"></i> خروجی اکسل لیست زیر </a>
                                </div>

                            </div>
                            <form method="post">
                                <div class="row">
                                    <div class="col-4 mg-b-5">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                اقدام انتخاب شده ها<span
                                                        class="tx-danger">*</span>
                                            </div>
                                            <select class="form-control text-danger select2 "
                                                    aria-describedby="basic-addon2"
                                                    required="" name="act">
                                                <option label="پایه ">
                                                </option>
                                                <option value="01">تاییدیه کد</option>
                                                <option value="02">رها سازی</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-3 mg-b-5">
                                        <button type="submit" class="btn btn-primary  "
                                                name="save"><i
                                                    class="fe fe-check"></i>

                                        </button>

                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <table class="table text-nowrap text-md-nowrap table-bordered">
                                            <thead>
                                            <tr>
                                                <th></th>
                                                <th>ردیف</th>
                                                <th>کد ملی</th>
                                                <th>نام دانش آموز</th>
                                                <th>پایه</th>
                                                <th>تاییدیه</th>
                                                <th>تعداد پایه</th>
                                                <th>تاریخ عضویت</th>

                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $sub = 'SELECT * FROM dbpersonal INNER JOIN dblogin ON dbpersonal.codemeli=dblogin.username WHERE dbpersonal.useradder=?';
                                            $ert = $conn->prepare($sub);
                                            $ert->execute([$_GET['userId']]);
                                            $try = $ert->fetchAll(PDO::FETCH_OBJ);
                                            $radif = 1;
                                            if ($ert->rowCount() > 0) {
                                                foreach ($try as $cath) {

                                                    ?>
                                                    <tr>
                                                        <td class="wd-10"><input type="checkbox" name="username[]"
                                                                                 value="<?php echo $cath->codemeli ?>">
                                                        </td>

                                                        <td class="wd-10"><?php echo $radif ?></td>
                                                        <td class="wd-10"><?php echo $cath->codemeli ?></td>
                                                        <td><?php echo htmlentities($cath->fname . ' ' . $cath->lname) ?></td>
                                                        <td class="wd-10">
                                                            <?php echo $cath->paye ?>
                                                        </td>
                                                        <td class="wd-10">
                                                            <?php
                                                            if ($cath->keyactive != NULL and $cath->true_key == 1) {
                                                                ?>
                                                                <span class="badge badge-success-light">تایید</span>
                                                            <?php } else { ?>
                                                            <span class="badge badge-warning-light">عدم تایید</span>
                                                            <?php } ?>
                                                        </td>
                                                        <?php
                                                        $cop = 'SELECT COUNT(*) as coco FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE dbcourse.username=? AND dbcourse.activityuser=? AND activecourse=?';
                                                        $zat = $conn->prepare($cop);
                                                        $zat->execute([$cath->codemeli, 1, 1]);
                                                        $cin = $zat->fetch(PDO::FETCH_OBJ);
                                                        ?>
                                                        <td class="wd-10">
                                                            <?php echo $cin->coco ?>
                                                        </td>
                                                        <?php
                                                        $dateno = $cath->datecreated;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>
                                                    </tr>
                                                    <?php
                                                    $radif++;
                                                }
                                            }
                                            ?>

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
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
