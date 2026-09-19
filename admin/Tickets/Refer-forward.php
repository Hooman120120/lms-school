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
if (!Permision('permision_refer')) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}
if (!isset($_GET['ticket'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    die();
}

if(isset($_POST['submit'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] === $_POST['token']) {
        $user = $_POST['admin'];
        $ticket = $_POST['ticket'];
        $uping='UPDATE ticket_topic SET metod=? , responser=? WHERE ticket_num=? ';
        $quering=$conn->prepare($uping);
        $quering->execute([1,$user,$ticket]);
        echo '<script>window.close() </script>';
    }else{
        echo '   <div class="alert alert-danger" role="alert">
                      <span class="alert-inner--icon"><i class="fe fe-lock"></i></span>
                          <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                            </button>
                              خطای غیر منتظره رخ داد .... مجدد امتحان کنید
                       </div>';
    }
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> مشاهده تیکت شماره : <?php echo $_GET['ticket'] ?> |<?php echo __TITLE ?></title>
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

    <div class="alert alert-info" role="alert">
        <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>

        پس از ارجاع این صفحه بسته شده و صفحه قبلی را بارگذاری مجدد (Refresh /F5) نمایید
    </div>

    <div class="card-body">
        <form method="post" data-parsley-validate="">
            <div class="form-group">
                <div class="row row-sm">
                    <?php
                    $sql = 'SELECT * FROM ticket_meta WHERE ticket_num=?  ';
                    $query = $conn->prepare($sql);
                    $query->execute([$_GET['ticket']]);
                    $res = $query->fetch();
                    ?>
                    <div class="text-muted mb-2">
                        تاریخ ارسال:
                        <?php echo $res['timecreated']; ?>
                        -
                        <?php
                        $convert = $res['datecreated'];
                        $convert = explode('-', $convert);
                        $convert = gregorian_to_jalali($convert[0], $convert[1], $convert[2]);
                        $convert = implode('/', $convert);
                        echo $convert; ?>
                    </div>
                    <textarea rows="8" disabled><?php echo $res['detail'] ?></textarea>

                </div>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <div class="input-group-prepend">
                            <div class="input-group-text">
                                ارجاع به:<span
                                        class="tx-danger">*</span>
                            </div>
                        <select class="form-control select2 "
                                name="admin"
                                aria-describedby="basic-addon2">

                            <option label="پیگیری کننده ">
                            </option>
                            <?php
                            $sql='SELECT * FROM dbadmin WHERE adminactive=? ';
                            $option=$conn->prepare($sql);
                            $option->execute([1]);
                            $resu=$option->fetchAll(PDO::FETCH_OBJ);
                            if($option->rowCount()>0){
                            foreach ($resu as $gmt){

                            ?>
                            <option value="<?php echo $gmt->username ?>"><?php echo $gmt->fname.' '. $gmt->lname; ?></option>
                            <?php } } ?>
                        </select>
                        </div>
                    </div>
                    <input type="hidden" name="ticket" value="<?php echo $_GET['ticket'] ?>" required>
                    <?php
                    $hash = bin2hex(random_bytes(32));
                    $_SESSION['token'] = $hash;
                    ?>
                    <input type="hidden" name="token" value="<?php echo $hash ?>"
                           required>
                    <div class="col-6">
                        <button class="btn ripple btn-main-primary pd-x-30 mg-l-5"
                                name="submit"
                                type="submit">ارجاع
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>
