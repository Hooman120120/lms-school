<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ' . url('login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
if ($_SESSION['auth']['role'] != 1) {
    header('location: ' . url('index.php'));
    die();
}
if (!isset($_GET['code']) and $_GET['code'] = '') {
    header('location: ' . url('Course/allcourse.php'));
    die();
}
// if (isset($_POST['chapter'])) {
//     dd($_POST);
// }
$course1 = returnCourse($_GET['code']);
include_once '../temp.html';
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
    <title> آزمون های برگزار شده برای دوره : <?php echo $course1->coursename ?> | <?php echo __TITLE ?></title>
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
        include_once '../menu.php';
        include_once '../header.php';
        ?>
        <div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="row row-sm">
                        <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                            <div class="card custom-card">
                                <div class="card-header  border-bottom-0 pb-0">
                                    <div>
                                        <div class="d-flex">
                                            <label class="main-content-label my-auto pt-2">جزوات و تکالیف ارائه شده
                                            </label>
                                        </div>
                                        <span class="d-block tx-12 mt-2 mb-0 text-muted"> دوره
                                            <?php echo $course1->coursename ?> </span>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-center">
                                        <?php
                                        $sql = "SELECT * FROM dblesson WHERE codecourse = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$_GET['code']]);
                                        $lessons = $stmt->fetchAll(PDO::FETCH_OBJ);
                                        if ($stmt->rowCount() > 0) {
                                            ?>
                                            <form action="" method="POST" class="d-flex align-items-center mx-auto">

                                                <label for="chapter" class="mb-0 ml-3 font-weight-bold text-nowrap">
                                                    انتخاب سرفصل
                                                </label>
                                                <select name="chapter" class="form-control select2"
                                                    onchange="this.form.submit()">
                                                    <option value="" selected disabled>یکی را انتخاب کنید</option>
                                                    <option value="all" <?= (isset($_POST['chapter']) and $_POST['chapter'] == 'all') ? 'selected' : '' ?>>تمام سرفصلها</option>
                                                    <?php foreach ($lessons as $lesson) { ?>
                                                        <option value="<?= $lesson->id ?>" <?= (isset($_POST['chapter']) and $_POST['chapter'] == $lesson->id) ? 'selected' : '' ?>>
                                                            <?= $lesson->name ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    if (isset($_POST['chapter']) and $_POST['chapter'] != 'all') {
                                        $sql = "SELECT * FROM dbaddon WHERE codecourse = ? AND lesson_id = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([$_GET['code'], $_POST['chapter']]);
                                        $addon = $stmt->fetchAll(PDO::FETCH_OBJ);
                                    } else {
                                        $addon = retaddon($_GET['code']);
                                    }
                                    ?>
                                    <div class="card-body">
                                        <div class="product-timeline card-body pt-3 mt-1">
                                            <ul class="timeline-1 mb-0">
                                                <?php
                                                if ($addon) {
                                                    foreach ($addon as $ert) {
                                                        ?>
                                                        <li class="mt-0"><i class="fe fe-file product-icon"></i> <span
                                                                class="font-weight-semibold mb-4 tx-14 "><?php echo $ert->docname ?>
                                                            </span>
                                                            <a href="attachment.php?id=<?php echo $ert->id ?>" target="_blank"
                                                                class="btn btn-sm btn-success  float-left  ">دانلود</a>
                                                            <p class="mb-0 text-muted tx-12"><?php echo $ert->host ?></p>

                                                        </li>

                                                    <?php }
                                                } ?>
                                            </ul>
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
    <?php include '../footers.php'; ?>
    <!--End Footer-->
    <script>
        $('.select2').select2({
            width: 'auto' // یا مثلاً '200px'
        });
    </script>

</body>

</html>