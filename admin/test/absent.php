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
if (!isset($_GET['Id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
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
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <?php $db = retDbquiz($_GET['Id']); ?>
    <title> لیست غائبین آزمون <?php echo $db->quizname ?> | <?php echo __TITLE ?></title>
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
                                        <h6 class="main-content-label mb-1">لیست غائبین آزمون : <span
                                                    class="text-primary"><?php echo $db->quizname ?></span>
                                        </h6>
                                        <p class="text-muted card-sub-title">کد پایه : <?php echo $db->codecourse ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-3 text-left">
                                    <a href="export-absent.php?exp=<?php echo $_GET['Id'] ?> " target="_blank"
                                       class="btn btn-sm btn-primary"><i
                                                class="fe fe-download"></i> خروجی اکسل لیست زیر </a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <table class="table text-nowrap text-md-nowrap table-bordered">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>کد ملی</th>
                                            <th>نام دانش آموز</th>
                                            <th>زیرمجموعه</th>
                                            <th>عضویت</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $absent = 'SELECT * FROM dbpersonal INNER JOIN dbcourse ON dbpersonal.codemeli=dbcourse.username WHERE dbcourse.activityuser=? AND dbcourse.codecourse=?';
                                        $query = $conn->prepare($absent);
                                        $query->execute([1, $db->codecourse]);
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id=1;
                                        foreach ($result

                                        as $ret){
                                        $requiz = 'SELECT * FROM result_quiz WHERE quizid=? AND username=?';
                                        $qres = $conn->prepare($requiz);
                                        $qres->execute([$_GET['Id'], $ret->username]);
                                        $req = $qres->fetchAll(PDO::FETCH_OBJ);
                                        //
                                        if ($qres->rowCount() == 0){

                                        ?>

                                        <tr>
                                            <td class="wd-15"><?php echo $id  ?></td>
                                            <td class="wd-15"><?php echo $ret->username ?></td>
                                            <td><?php echo $ret->fname .' '.$ret->lname ?></td>
                                            <?php if($ret->useradder !=NULL)
                                            {$adder=retPerson($ret->useradder); $user=$adder->fname.' '.$adder->lname;}else{$user=''; } ?>
                                            <td class="wd-15"><?php echo $user  ?></td>
                                            <?php
                                            $dateno = $ret->datecreated;
                                            $dateno = explode('-', $dateno);
                                            $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                            $dateno = implode('/', $dateno);
                                            ?>
                                            <td class="wd-15"><?php echo $dateno ?></td>
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
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</body>
</html>