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
    header('location: login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
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
if (AdminHasAnyCurrentTreePermission()
    ? !AdminHasMenuNavigationPermission('finance.cards')
    : !Permision('permision_viewbill')) {
    header('location:' . url("admin/index.php"));
    die();
}
$usesPermissionTree = AdminHasAnyCurrentTreePermission();
$canCreateCard = !$usesPermissionTree || AdminHasMenuPermission('finance.cards.create');
$canEditCard = !$usesPermissionTree || AdminHasMenuPermission('finance.cards.edit');
$canDeleteCard = !$usesPermissionTree || AdminHasMenuPermission('finance.cards.delete');
if (isset($_POST['save'])) {
    if (!$canCreateCard) {
        http_response_code(403);
        exit('دسترسی افزودن شماره کارت را ندارید.');
    }
    if (entityCard($_POST['cardnum'])) {
        $pic = 'assets/img/pngs/' . $_POST['pic'];
        $ins = 'INSERT INTO db_card (card_num,card_name,card_bank,sheba,card_pic)VALUES (?,?,?,?,?)';
        $qin = $conn->prepare($ins);
        $qin->execute([$_POST['cardnum'], $_POST['name'], $_POST['bank'], $_POST['sheba'], $pic]);
    } else {
        $eror = 'این شماره کارت وجود دارد! امکان افزودن نیست';
    }
}
if (isset($_POST['esave'])) {
    if (!$canEditCard) {
        http_response_code(403);
        exit('دسترسی ویرایش شماره کارت را ندارید.');
    }
    if (entityCard($_POST['ecardnum'])) {
        $pic = 'assets/img/pngs/' . $_POST['epic'];
        $update = 'UPDATE db_card SET card_num=?,card_name=?,card_bank=?,sheba=?,card_pic=? WHERE id=?';
        $qup = $conn->prepare($update);
        $qup->execute([$_POST['ecardnum'], $_POST['ename'], $_POST['ebank'], $_POST['esheba'], $pic, $_GET['edit']]);
        header('location: credit-card.php');

    } else {
        $card = resultCardDB($_GET['edit']);
        if ($card['card_num'] == $_POST['ecardnum']) {
            $pic = 'assets/img/pngs/' . $_POST['epic'];
            $update = 'UPDATE db_card SET card_num=?,card_name=?,card_bank=?,sheba=?,card_pic=? WHERE id=?';
            $qup = $conn->prepare($update);
            $qup->execute([$_POST['ecardnum'], $_POST['ename'], $_POST['ebank'], $_POST['esheba'], $pic, $_GET['edit']]);
            header('location: credit-card.php');
        }else{
            $eror = 'این شماره کارت وجود دارد! امکان ویرایش نیست';
        }
    }
}
if(isset($_GET['del'])){
    if (!$canDeleteCard) {
        http_response_code(403);
        exit('دسترسی حذف شماره کارت را ندارید.');
    }
    $sql='SELECT COUNT(*) as cocd FROM db_card ';
    $query=$conn->prepare($sql);
    $query->execute();
    $redturn=$query->fetch(PDO::FETCH_OBJ);
    if($redturn->cocd>1){
        $dep='SELECT card_num FROM db_card WHERE id=?';
        $ped=$conn->prepare($dep);
        $ped->execute([$_GET['del']]);
        $cardnum=$ped->fetch(PDO::FETCH_OBJ);
        //
        $depi='SELECT card_num FROM db_card WHERE id!=?';
        $pedi=$conn->prepare($depi);
        $pedi->execute([$_GET['del']]);
        $cardnumi=$pedi->fetch(PDO::FETCH_OBJ);
        //
        $uping='UPDATE card_server SET card_num=? WHERE card_num=?';
        $quering=$conn->prepare($uping);
        $quering->execute([$cardnumi->card_num,$cardnum->card_num]);
        //
        $del='DELETE FROM db_card WHERE id=?';
        $acdel=$conn->prepare($del);
        $acdel->execute([$_GET['del']]);
        header('location: credit-card.php');

    }else{
        $eror='شما می بایست حداقل یک شماره کارت داشته باشید! امکان حذف نیست';
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
    <title> شماره کارت | <?php echo __TITLE ?></title>
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
                <div class="row row-sm">
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
                    <?php
                    if (!isset($_GET['edit'])) { ?>
                        <div class="col-sm-12 col-md-12 col-lg-3 col-xl-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-12">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:12px;">
                                                <h6 class="main-content-label mb-1">افزودن شماره کارت</h6>

                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید شماره کارت‌های مورد استفاده سامانه را ثبت و مدیریت کنید. این شماره کارت‌ها به‌صورت خودکار و بر اساس فرآیندهای تعریف‌شده، هنگام ایجاد فاکتور یا ثبت پرداخت به دانش آموزان تخصیص داده می‌شوند. همچنین امکان ویرایش یا غیرفعال کردن شماره کارت‌ها در صورت نیاز وجود دارد."
                                                      aria-label="راهنمای افزودن شماره کارت"
                                                      onclick="event.preventDefault(); event.stopPropagation();">

                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>

                                            <p class="text-muted card-sub-title">
                                                شماره کارت‌هایی که به‌صورت خودکار تخصیص داده می‌شوند.
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">

                                        <div class=" mg-t-20 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام عکس
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="pic"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="cardmellat02.png">

                                            </div>
                                            <p class="text-muted text-left"> /assets/img/pngs</p>
                                        </div>
                                        <div class=" mg-t-20 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        شماره کارت
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="cardnum"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="16 رقم">

                                            </div>

                                        </div>
                                        <div class=" mg-t-20 ">
                                            <label class="form-label">شماره شبای بانکی <span
                                                        class="text-danger">*</span></label>
                                        </div>

                                        <div class="input-group-prepend">

                                            <input type="text" class="form-control" placeholder="24 رقم"
                                                   name="sheba"
                                                   required="">
                                            <div class="input-group-text">
                                                IR
                                            </div>

                                        </div>
                                        <div class=" mg-t-20">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام صاحب حساب
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="name"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="همیار">

                                            </div>
                                        </div>
                                        <div class=" mg-t-20">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام بانک
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="bank"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="ملت">

                                            </div>
                                        </div>
                                        <div class="text-left mg-t-10 ">
                                            <?php if ($canCreateCard): ?><button type="submit" class="btn btn-primary "
                                                    name="save"><i
                                                        class="fa fa-save"></i>
                                                افزودن شماره کارت
                                            </button><?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="col-sm-12 col-md-12 col-lg-3 col-xl-3">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="col-12">
                                        <div>
                                            <h6 class="main-content-label mb-1">ویرایش شماره کارت</h6>
                                            <p class="text-muted card-sub-title">شماره کارت هایی که اتوماتیک تخصیص داده
                                                می
                                                شود
                                            </p>
                                        </div>
                                    </div>
                                    <?php
                                    $card = resultCardDB($_GET['edit']);
                                    $cpic = $card['card_pic'];
                                    $pic = explode('/', $cpic);
                                    $pic = $pic[3];
                                    ?>
                                    <form method="post" data-parsley-validate="">

                                        <div class=" mg-t-20 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام عکس
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="epic"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $pic ?>"
                                                       placeholder="cardmellat02.png">

                                            </div>
                                            <p class="text-muted text-left"> /assets/img/pngs</p>
                                        </div>
                                        <div class=" mg-t-20 ">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        شماره کارت
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="ecardnum"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $card['card_num'] ?>"
                                                       placeholder="16 رقم">

                                            </div>

                                        </div>
                                        <div class=" mg-t-20 ">
                                            <label class="form-label">شماره شبای بانکی <span
                                                        class="text-danger">*</span></label>
                                        </div>

                                        <div class="input-group-prepend">

                                            <input type="text" class="form-control" placeholder="24 رقم"
                                                   name="esheba"
                                                   required="" value="<?php echo $card['sheba'] ?>">
                                            <div class="input-group-text">
                                                IR
                                            </div>

                                        </div>
                                        <div class=" mg-t-20">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام صاحب حساب
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="ename"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $card['card_name'] ?>"
                                                       placeholder="همیار">

                                            </div>
                                        </div>
                                        <div class=" mg-t-20">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام بانک
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="ebank"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required value="<?php echo $card['card_bank'] ?>"
                                                       placeholder="ملت">

                                            </div>
                                        </div>
                                        <div class="text-left mg-t-10 ">
                                            <?php if ($canEditCard): ?><button type="submit" class="btn btn-warning "
                                                    name="esave"><i
                                                        class="fa fa-save"></i>
                                                ویرایش شماره کارت
                                            </button><?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="col-sm-12 col-md-12 col-lg-9 col-xl-9">
                        <div class="card custom-card">
                            <div class="card-body">

                                <div class="col-10">
                                    <div>
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <h6 class="main-content-label mb-1">لیست شماره کارت</h6>

                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش تمامی شماره کارت‌های ثبت‌شده در سامانه نمایش داده می‌شوند. این شماره کارت‌ها به‌صورت خودکار و بر اساس فرآیندهای تعریف‌شده برای فاکتورها و پرداخت‌ها تخصیص داده می‌شوند. همچنین از طریق این صفحه می‌توانید شماره کارت‌ها را مشاهده، ویرایش، فعال یا غیرفعال و در صورت نیاز حذف کنید."
                                                  aria-label="راهنمای لیست شماره کارت"
                                                  onclick="event.preventDefault(); event.stopPropagation();">

                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>

                                        <p class="text-muted card-sub-title">
                                            شماره کارت‌هایی که به‌صورت خودکار تخصیص داده می‌شوند.
                                        </p>
                                    </div>
                                </div>
                                <div class="table">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>عکس</th>
                                            <th>شماره کارت</th>
                                            <th>شماره شبا</th>
                                            <th>بانک</th>
                                            <th>نام</th>
                                            <th>اقدام</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sqt = 'SELECT * from db_card';
                                        $qsqt = $conn->prepare($sqt);
                                        $qsqt->execute();
                                        $result = $qsqt->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;
                                        if ($qsqt->rowCount() > 0) {
                                            foreach ($result as $res) {

                                                ?>
                                                <tr class="border-bottom">
                                                    <th class="wd-20"><?php echo $id ?></th>
                                                    <td class="wd-20"><img
                                                                src="<?php echo url($res->card_pic) ?>"
                                                                class="img-sm product-image border"></td>
                                                    <td class="wd-20"><?php echo $res->card_num ?></td>
                                                    <td class="text-left">IR<?php echo $res->sheba ?></td>
                                                    <td class="wd-20"><?php echo $res->card_bank ?></td>
                                                    <td class="wd-20"><?php echo $res->card_name ?></td>
                                                    <td class="wd-15">
                                                        <?php if ($canEditCard): ?><a href="?edit=<?php echo $res->id ?>"
                                                           class="btn btn-sm btn-warning" title="ویرایش"><i
                                                                    class="fe fe-edit"></i></a><?php endif; ?>
                                                        <?php if ($canDeleteCard): ?><a href="?del=<?php echo $res->id ?>"
                                                           class="btn btn-sm btn-danger" title="حذف"
                                                           onclick="return confirm('آیا با حذف این شماره کارت موافقید؟')"><i
                                                                    class="fe fe-x"></i> </a><?php endif; ?>

                                                    </td>

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


