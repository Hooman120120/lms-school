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
if (isset($_GET['del'])) {
    AdminRequire('courses.main.discount.delete');
    $del = intval($_GET['del']);
    $delete = 'DELETE FROM dbdiscount WHERE id=?';
    $query_del = $conn->prepare($delete);
    $query_del->execute([$del]);
    header('location: add-discount.php');
    die();
}
if(isset($_POST['save'])){
    AdminRequire('courses.main.discount.create');
    if(isset($_SESSION['token']) AND $_SESSION['token']== $_POST['token'] ){
        if(!discountEntity($_POST['discount'])){
            $indis='INSERT INTO dbdiscount(name_discount,discount,value_discount,type_discount,role) VALUES (?,?,?,?,?)';
            $qdis=$conn->prepare($indis);
            $qdis->execute([$_POST['name-discount'],$_POST['discount'],$_POST['value'],$_POST['metod'],$_POST['role']]);
        }else{
            $erordublicate = 'این کد وجود دارد';
        }
    }else {
        $erortoken = 'خطای غیر منتظره رخ داد، لطفا مجدد امتحان کنید';
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
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> ایجاد کد تخفیف | <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                کدهای تخفیف صادرشده
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید کدهای تخفیف صادرشده را مشاهده و مدیریت کنید. جدول شامل کد و نام تخفیف، مقدار تخفیف، متد محاسبه، بهره‌بردار یا فرد مجاز به استفاده و تعداد دفعات استفاده است. همچنین امکان حذف کد تخفیف از همین بخش وجود دارد."
                                                  aria-label="راهنمای کدهای تخفیف صادرشده">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <p class="text-muted card-sub-title">
                                            مشاهده مشخصات، میزان استفاده و مدیریت کدهای تخفیف
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>حذف</th>
                                            <th>کد</th>
                                            <th>نام</th>
                                            <th>مقدار</th>
                                            <th>متد</th>
                                            <th>بهره بردار</th>
                                            <th> استفاده</th>

                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbdiscount';
                                        $query = $conn->prepare($sql);
                                        $query->execute();
                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result

                                                     as $ret) {

                                                ?>
                                                <tr>
                                                    <td><a href="?del=<?php echo $ret->id ?>"  onclick="return confirm('آیا با حذف این کد  موافقید؟')"
                                                           class="btn btn-sm btn-danger">X</a></td>
                                                    <td><?php echo $ret->discount ?></td>
                                                    <td><?php echo $ret->name_discount ?></td>
                                                    <td><?php echo $ret->value_discount ?></td>
                                                    <td><?php if ($ret->type_discount == 1) {
                                                            echo 'درصد';
                                                        } else {
                                                            echo 'ثابت';
                                                        } ?></td>
                                                    <td><?php if ($ret->role == 1) {
                                                            echo 'دانش آموز';
                                                        } else {
                                                            echo 'همکار';
                                                        } ?></td>
                                                    <td><?php echo Countdiscount($ret->discount); ?></td>
                                                </tr>
                                            <?php }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <div class="d-flex align-items-center mb-1" style="gap: 12px;">

                                            <h6 class="main-content-label mb-0">
                                                صدور کد تخفیف
                                            </h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش می‌توانید کد تخفیف جدید ایجاد کنید و مشخصاتی مانند نام و کد تخفیف، مقدار تخفیف، روش محاسبه، بهره‌بردار مجاز و تعداد دفعات قابل استفاده را تعیین نمایید."
                                                  aria-label="راهنمای صدور کد تخفیف">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>

                                        </div>

                                        <p class="text-muted card-sub-title">
                                            ایجاد و تنظیم کد تخفیف جدید
                                        </p>
                                    </div>
                                </div>
                                <?php if (isset($erortoken)) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erortoken ?>
                                    </div>
                                <?php } ?>
                                <?php if (isset($erordublicate)) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        <?php echo $erordublicate ?>
                                    </div>
                                <?php } ?>
                                <form method="post"  data-parsley-validate="">
                                    <div class="row">
                                        <div class="col-lg-6 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        کد تخفیف
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="discount"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="">
                                                <?php
                                                $hash = bin2hex(random_bytes(32));
                                                $_SESSION['token'] = $hash;
                                                ?>
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $hash ?>">
                                            </div>

                                        </div>
                                        <div class="col-lg-6 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام تخفیف
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="name-discount"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4 ">
                                            <p class="mg-b-10 mg-t-10"> مقدار <span
                                                        class="tx-danger">*</span></p>
                                            <input class="form-control" name="value"
                                                   aria-describedby="basic-addon2"
                                                   type="text" required
                                                   placeholder="12">
                                        </div>
                                        <div class="col-lg-4 ">
                                            <p class="mg-b-10 mg-t-10"> انتخاب متد <span
                                                        class="tx-danger">*</span></p>
                                            <select class="form-control select2" name="metod"
                                                    required>
                                                <option value="1">
                                                    درصد
                                                </option>
                                                <option value="0">
                                                    ثابت
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-lg-4 ">
                                            <p class="mg-b-10 mg-t-10"> بهره بردار <span
                                                        class="tx-danger">*</span></p>
                                            <select class="form-control select2" name="role"
                                                    required>
                                                <option value="1">
                                                    دانش آموز
                                                </option>
                                                <option value="0">
                                                    همکار
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <button type="submit"
                                                name="save"
                                                class="btn btn-primary  mr-1">
                                            تایید و صدور کد تخفیف
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>