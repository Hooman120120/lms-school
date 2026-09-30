<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (!inst_feature('wallet')) { http_response_code(404); exit('کیف پول در نسخه مدرسه‌ای فعال نیست.'); }
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
$adder = retPerson($_GET['userId']);
if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        if ($_POST['value'] > 0) {
            date_default_timezone_set("Iran");
            $datenow = date("Y-m-d");
            $timenow = date('H:i:s');
            $ip=GetIp();
            $os=GetOs();
            switch ($_POST['action']){
                case '1':
                    $detail='افزایش موجودی بواسطه '.trim($_POST['detail']);
                    $mode=1;
                    $value=$_POST['value'];
                    break;
                case '0':
                    $detail='کاهش موجودی بواسطه '.trim($_POST['detail']);
                    $mode=0;
                    $value=$_POST['value']*-1;
                    break;
            }
            $insert='INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,ip,os,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $qin=$conn->prepare($insert);
            $qin->execute([$_GET['userId'],$value,'موفق',$datenow,$timenow,$detail,$mode,$ip,$os,$_SESSION['admin']['user'],$datenow,$timenow,$ip,$os]);
            echo '<script>window.close() </script>';
            die();

        }else{
            $eror='مبلغ نمی تواند برابر یا کوچکتر از صفر باشد';
        }
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
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> افزایش / کاهش : <?php echo $adder->fname . ' ' . $adder->lname ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<div class="page">
    <div class="alert alert-info" role="alert">
        <span class="alert-inner--icon"><i class="fa fa-asterisk"></i></span>
        پس از تایید این صفحه بسته شده و صفحه قبلی را بارگذاری مجدد (Refresh /F5) نمایید
    </div>
    <?php if (isset($eror)) { ?>
        <div class="alert alert-warning" role="alert">
            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                    type="button">
                <span aria-hidden="true">×</span>
            </button>
            <?php echo $eror ?>

        </div>
    <?php } ?>
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12 mg-t-10">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div>
                                <h6 class="main-content-label mb-1">افزایش / کاهش میزان موجودی : <span
                                            class="text-primary"><?php echo $adder->fname . ' ' . $adder->lname ?></span>
                                </h6>
                                <p class="text-muted card-sub-title">کد ملی: <?php echo $_GET['userId'] ?>
                                </p>
                                <h6 class="main-content-label mb-1"> میزان موجودی کیف پول : <span
                                            class="text-primary"><?php echo number_format(totalWallet($_GET['userId'])) ?> تومان </span>
                                </h6>
                            </div>
                            <form method="post" data-parsley-validate="">
                                <?php
                                $hash = bin2hex(random_bytes(8));
                                $_SESSION['token'] = $hash;
                                ?>
                                <input class="form-control" name="token" type="hidden"
                                       value="<?php echo $hash ?>">

                                <div class="col-lg-12">
                                    <p class="mg-b-10 mg-t-10"> نوع اقدام <span
                                                class="tx-danger">*</span></p>
                                    <select class="form-control select2" name="action"
                                            required>

                                        <option value="1">
                                            افزایش
                                        </option>
                                        <option value="0">
                                            کاهش
                                        </option>

                                    </select>
                                </div>
                                <div class="col-lg-12">
                                    <div class="input-group mg-t-10">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                مبلغ
                                                <span
                                                        class="tx-danger">*</span></div>
                                        </div>
                                        <input class="form-control" name="value"
                                               aria-describedby="basic-addon2"
                                               type="number" required>
                                    </div>
                                </div>
                                <div class="col-xl-12 mg-t-10">
                                    <p class="mg-b-10 "> توضیحات تراکنش <span
                                                style="color: red">*</span></p>
                                    <textarea class="form-control" name="detail" required></textarea>
                                    <p class="text-muted">متن ثابت ابتدای توضیحات درج می گردد: "افزایش / کاهش موجودی
                                        بواسطه:"</p>
                                </div>
                                <div class="text-center mg-10">
                                    <button type="submit" class="btn btn-success "
                                            name="save" onclick="return confirm('آیا این اقدام صورت پذیرد؟')"><i
                                                class="fa fa-save"></i>
                                        ثبت اطلاعات
                                    </button>
                                </div>

                            </form>


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
