<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
//*START  Protecting against SESSION HIJACKING
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Protecting against SESSION HIJACKING

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Check Session JUST ONE Session

if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}
if (isset($_GET['sales']) and $_GET['sales'] == '') {
    header('location: ' . url('Collague/sales-course.php'));
    die();
}
if (isset($_GET['sales']) and keyactcourse($_GET['sales']) === 500) {
    header('location: ' . url('Collague/sales-course.php'));
    die();
}
if (isset($_GET['sales']) and !collague_student($_GET['sales'])) {
    header('location: ' . url('Collague/sales-course.php'));
    die();
}
if (isset($_POST['sales-course'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        if (collague_student($_POST['user'])) {
            if (isset($_POST['course']) and $_POST['course'] > 0) {
                foreach ($_POST['course'] as $code) {
                    if (checkout_collague($_POST['user'], $code)) {
                        $fee = getfee($code);
                        $insert = 'INSERT INTO checkout_collague (useradder,username,codecourse,fee) VALUES (?,?,?,?)';
                        $qe = $conn->prepare($insert);
                        $qe->execute([$_SESSION['auth']['user'], $_POST['user'], $code, $fee]);
                    }
                }
            }
        }
        $_SESSION['collague-checkout'] = $_POST['user'];
        header('location: ' . url('Collague/checkout-collague.php'));
        die();
    }
}
include_once '../temp.html';
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
    <title> خرید پایه برای دانش آموز | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
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
    include_once '../inc/menu-collague.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <section class="section">
                    <div class="row">
                        <div class="card">
                            <?php if (!isset($_GET['sales'])) { ?>
                                <form method="GET" data-parsley-validate="">
                                    <div class="card-body">
                                        <div class="row">

                                            <label for="sens" class="form-label">کد ملی دانش آموز: <span
                                                        style="color: red">*</span></label>
                                            <div class="col-xl-6">
                                                <input class="form-control" name="sales" type="text"
                                                       placeholder="با اعداد انگلیسی وارد شود"
                                                       pattern="[0-9]{10}" required>
                                                <div class="text-muted pd-t-10">
                                                    کدملی دانش آموز را وارد کنید
                                                </div>
                                            </div>


                                            <div class="col-xl-6">
                                                <button type="submit" class="btn btn-success "
                                                ><i class="fe fe-arrow-left"></i>
                                                    بررسی کد ملی
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </form>
                            <?php } else { ?>

                                <div class="card-header border-bottom-0 pb-1">
                                    <label class="main-content-label mb-2 pt-1">پایه های در حال ثبت نام</label>
                                    <p class="tx-12 mb-0 text-muted">پایه های مورد نظر برای دانش آموز را انتخاب کنید</p>
                                </div>
                                <?php
                                $rest = retPerson($_GET['sales']);
                                ?>
                                <label class="main-content-label mb-2 pt-1">نام دانش آموز: <span
                                            class="text-warning">  <?php echo $rest->fname . ' ' . $rest->lname ?> </span></label>
                                <hr>
                                <div class="card-body pt-0">
                                    <form method="post">
                                        <ul class="top-selling-products pb-0 mb-0 pr-0">
                                            <?php
                                            $sql = 'SELECT * FROM codtbl_course WHERE courseactive=? ';
                                            $query = $conn->prepare($sql);
                                            $query->execute([1]);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {
                                                foreach ($result as $results) {
                                                    if (checkactCourse($_GET['sales'], $results->codecourse)) {
                                                        $registercourse = 1;
                                                    } else {
                                                        $registercourse = 0;
                                                    }
                                                    ?>
                                                    <li class="product-item ">
                                                        <div class="product-img"><input type="checkbox" name="course[]"
                                                                                        value="<?php echo $results->codecourse ?>" <?php if ($registercourse == 1) {
                                                                echo 'disabled';
                                                            } ?> ></div>
                                                        <div class="product-info">
                                                            <div class="product-name"><?php if ($registercourse == 1) {
                                                                    echo '<del>' . htmlentities($results->coursename) . '</del>';
                                                                } else {
                                                                    echo htmlentities($results->coursename);
                                                                } ?>  </div>
                                                            <div class="price"><?php echo htmlentities($results->coursemuted) ?></div>
                                                        </div>
                                                        <div class="product-amount">
                                                            <div class="product-price"><?php echo number_format($results->coursefee) ?>
                                                                تومان
                                                            </div>
                                                            <div class="items-sold"><?php if ($registercourse == 1) {
                                                                    echo 'غیرفعال';
                                                                } else {
                                                                    echo 'فعال';
                                                                } ?> </div>
                                                        </div>
                                                    </li>
                                                    <hr>
                                                    <?php

                                                }
                                            }
                                            ?>
                                        </ul>
                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input class="form-control" name="token" type="hidden"
                                               value="<?php echo $hash ?>">
                                        <input type="hidden" name="user" value="<?php echo $_GET['sales'] ?>">
                                        <div class="text-center">
                                            <button class="btn btn-success ripple" name="sales-course">
                                                مشاهده صورتحساب
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

