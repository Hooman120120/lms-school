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
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (isset($_POST['edit-message_single'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $message_text = $_POST['message_text'];
        $sql = "UPDATE suport_message_constant SET content = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$message_text, $_GET['id']]);
        $_SESSION['message'] = 'پیام موردنظر با موفقیت ویرایش شد';
        echo "<script>
        window.opener.location.reload(); 
        window.close();</script>";
    }
}
if (isset($_POST['edit-message_all'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $message_text = $_POST['message_text'];
        //
        $sel = "SELECT * FROM suport_message_constant WHERE id = ?";
        $stm = $conn->prepare($sel);
        $stm->execute([$_GET['id']]);
        $message = $stm->fetch(PDO::FETCH_OBJ);
        $old_message = $message->content;
        //
        $sql = "UPDATE suport_message_constant SET content = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$message_text, $_GET['id']]);
        //
        $up = "UPDATE suport_message SET message_text = ? WHERE message_text = ?";
        $stmt2 = $conn->prepare($up);
        $stmt2->execute([$message_text, $old_message]);
        $_SESSION['message'] = 'پیام موردنظر و پیام های قبلی با موفقیت ویرایش شدند';
        echo "<script>
        window.opener.location.reload(); 
        window.close();</script>";
    }
}
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->

    <title> ویرایش پیام آماده | <?php echo __TITLE ?></title>
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
                                    <div class="col-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">ویرایش پیام آماده
                                            </h6>
                                            <p class="text-muted card-sub-title">شناسه : <?php echo $_GET['id'] ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                    $sql = "SELECT * FROM suport_message_constant WHERE id = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([$_GET['id']]);
                                    $message = $stmt->fetch(PDO::FETCH_OBJ);
                                    ?>
                                    <form method="post" data-parsley-validate="">
                                        <?php
                                        $hash = bin2hex(random_bytes(8));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <div class="col-lg-12">
                                            <input class="form-control" name="token" type="hidden"
                                                value="<?php echo $hash ?>">

                                            <div class="col-lg-12 mg-t-20">
                                                <p class="mg-b-10">متن پیام:<span class="tx-danger">*</span></p>
                                                <textarea class="form-control" name="message_text"
                                                    required><?= $message->content ?></textarea>
                                            </div>
                                        </div>
                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-success " name="edit-message_single"
                                                onclick="return confirm('با این اقدام پیام انتخاب شده ویرایش می شود . آیا انجام شود؟')"><i
                                                    class="fa fa-save"></i>
                                                ثبت و ویرایش پیام
                                            </button>
                                        </div>
                                        <div class="text-center mg-10">
                                            <button type="submit" class="btn btn-info " name="edit-message_all"
                                                onclick="return confirm('با این اقدام تمام پیام های قبلی در گفتگو نیز ویرایش می شوند . آیا انجام شود؟')"><i
                                                    class="fa fa-edit"></i>
                                                 ثبت و ویرایش برای تمام پیام های قبلی
                                            </button>
                                        </div>
                                    </form>
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