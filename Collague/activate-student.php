<?php
include_once '../dbconfig.php';
include_once '../inc/jdf.php';
include_once '../inc/function.php';
include_once '../inc/function_login.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../blackList.html';
    exit;
}
//Protecting against SESSION HIJACKING
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['auth']['os'] != GetOs()) {
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
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    die();
}
if (isset($_GET['id']) and $_GET['id'] == ''  ) {
    header('location: ' . url('Collague/activate-student.php'));
    die();
}
if(isset($_GET['id']) and keyactcourse($_GET['id']) !== 500){
    header('location: ' . url('Collague/activate-student.php'));
    die();
}
if (isset($_POST['activate']) ) {
    if ($_SESSION['token'] ==$_POST['token'] and keyactcourse($_GET['id']) === 500) {
        if ($_POST['key'] != '') {
            $sql = 'select keyactive FROM dblogin WHERE username=? ';
            $query = $conn->prepare($sql);
            $query->execute([$_POST['user']]);
            $result = $query->fetch();
            if ($query->rowCount() > 0 and $result['keyactive'] == $_POST['key']) {
                $update = 'update dblogin set true_key=? WHERE username=?';
                $com = $conn->prepare($update);
                $com->execute([1, $_POST['user']]);
                $valid = 1;
            } else {
                $eror = 1;
            }
        }
    }else{
        header('location: ' . url('Collague/activate-student.php'));
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
    <title> <?php echo m('page.activate_student') ?> | <?php echo __TITLE ?></title>
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
                <?php if (isset($_GET['id']) and $_GET['id'] != '' and collague_student($_GET['id']) and keyactcourse($_GET['id']) === 500) { ?>
                    <section class="section">
                        <div class="row">
                            <?php if (isset($eror) and $eror == 1) { ?>
                                <div class="alert alert-danger alert-dismissible fade show"
                                     role="alert">
                                خطا! لطفا مجدد امتحان نمایید
                                    <button type="button" class="close" data-bs-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>
                            <div class="card">
                                <form method="post" data-parsley-validate="">
                                    <div class="card-body">
                                        <div class="row">

                                            <label for="sens" class="form-label"><?php echo m('label.student_verify_code') ?> <span
                                                        style="color: red">*</span> <span class="text-warning"> (<?php echo $_GET['id'] ?>) </span></label>
                                            <div class="col-xl-8">
                                                <input class="form-control" name="key" type="text"
                                                       placeholder="کد با اعداد انگلیسی وارد شود"
                                                       pattern="[0-9]{4}" required>
                                                <div class="text-muted pd-t-10">
                                                    <?php echo m('help.code_visible_in_account') ?>
                                                </div>
                                                <div class="text-muted pd-t-10">
                                                    <?php echo m('help.ask_student_for_code') ?>
                                                </div>
                                            </div>
                                            <?php
                                            $hash = bin2hex(random_bytes(32));
                                            $_SESSION['token'] = $hash;
                                            ?>
                                            <input class="form-control" name="token" type="hidden"
                                                   value="<?php echo $hash ?>">
                                            <input type="hidden" name="user" value="<?php echo $_GET['id'] ?>">

                                            <div class="col-xl-4">
                                                <button type="submit" class="btn btn-success "
                                                        name="activate"><i class="fe fe-arrow-left"></i>
                                                    تایید کد فعالسازی
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </section>

                    <?php
                } else { ?>
                    <section class="section">
                        <div class="row">
                            <?php if (isset($valid) and $valid == 1) { ?>
                                <div class="alert alert-success alert-dismissible fade show"
                                     role="alert">
                                    <?php echo m('msg.student_verified', [':n' => $_POST['user']]) ?>
                                    <button type="button" class="close" data-bs-dismiss="alert"
                                            aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>
                            <div class="card">
                                <form method="GET" data-parsley-validate="">
                                    <div class="card-body">
                                        <div class="row">

                                            <label for="sens" class="form-label"><?php echo m('label.student_nid_colon') ?> <span
                                                        style="color: red">*</span></label>
                                            <div class="col-xl-6">
                                                <input class="form-control" name="id" type="text"
                                                       placeholder="با اعداد انگلیسی وارد شود"
                                                       pattern="[0-9]{10}" required>
                                                <div class="text-muted pd-t-10">
                                                    <?php echo m('help.enter_student_nid') ?>
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
                            </div>
                        </div>
                    </section>
                    <?php
                }
                ?>

            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
