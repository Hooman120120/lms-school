<?php
include_once 'dbconfig.php';
include_once 'inc/jdf.php';
include_once 'inc/function.php';
include_once 'inc/function_login.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once 'blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: login.php');
    die();
}

//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] != GetIp() and $_SESSION['auth']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
    die();
}
//--End of Protecting against SESSION Hijaking
//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}
//* End Check Session JUST ONE Session
if ($_SESSION['auth']['role'] != 1) {
    header('location: index.php');
    exit;
}
if (isset($_GET['del']) and $_GET['del'] != '') {
    $del = intval($_GET['del']);
    if (!checkout($_SESSION['auth']['user'], $del)) {
        $delete = 'DELETE FROM checkout WHERE username=? AND coursecode=?';
        $actdelete = $conn->prepare($delete);
        $actdelete->execute([$_SESSION['auth']['user'], $del]);
        header('location: checkout.php');

    } else {
        header('location: checkout.php');
    }
}
$dis = 0;
$codedis = NULL;
if (isset($_POST['discount']) and $_POST['discount'] != '') {
    if (discountEntity($_POST['discount']) != false) {
        if (discountUse($_POST['discount'], $_SESSION['auth']['user'])) {

            $return = discountEntity($_POST['discount']);
            if ($return['role'] == 1) {
                if ($return['type_discount'] == 1) {
                    $darsad = $return['value_discount'] / 100;
                    $codedis = $_POST['discount'];
                } else {
                    $dis = $return['value_discount'];
                    $codedis = $_POST['discount'];
                }
                $diseror = 100;
            } else {
                $diseror = 1;
            }

        } else {
            $diseror = 2;
        }

    } else {
        $diseror = 1;
    }

}

//* start Processing Checkout *//

if (isset($_POST['subtotal']) and $_POST['pay'][0] != '') {
    if (importCheckout($_SESSION['auth']['user'])) {
        switch ($_POST['pay'][0]) {
            case '1':
                $method = 'پرداخت آنلاین';
                break;
            case '2':
                $method = 'کارت به کارت';
                break;
            case '3':
                $method = 'بدون وجه';
                break;
        }
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();

        $infact = 'INSERT INTO dbfactor (username,sumitem,discountcode,discountfee,subtotal,wallet,factorfee,metod,datecreate,timecreate,ip,os) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
        $factor = $conn->prepare($infact);
        $factor->execute([$_SESSION['auth']['user'], $_SESSION['factor']['fee'], $_SESSION['factor']['codediscount'], $_SESSION['factor']['value_discount'], $_SESSION['factor']['subtotal'], $_SESSION['factor']['wallet'], $_SESSION['factor']['total'], $method, $datenow, $timenow, $ip, $os]);
        $factorId = $conn->lastInsertId();
        $select_checkout = 'SELECT * FROM checkout WHERE username=?';
        $checking = $conn->prepare($select_checkout);
        $checking->execute([$_SESSION['auth']['user']]);
        $datacourse = $checking->fetchAll(PDO::FETCH_OBJ);
        if ($checking->rowCount() > 0) {
            foreach ($datacourse as $req) {
                if ($req->price_type == 'with') {
                    $insertdb = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor) VALUES (?,?,?,?)';
                    $qinsert = $conn->prepare($insertdb);
                    $qinsert->execute([$_SESSION['auth']['user'], $req->coursecode, 0, $factorId]);
                } else {
                    $insertdb = 'INSERT INTO dbcourse (username,codecourse,activityuser,numfactor, has_operator) VALUES (?,?,?,?,?)';
                    $qinsert = $conn->prepare($insertdb);
                    $qinsert->execute([$_SESSION['auth']['user'], $req->coursecode, 0, $factorId, 0]);
                }
            }

        }
        $del_checkout = 'DELETE FROM checkout WHERE username=?';
        $qdel_checkout = $conn->prepare($del_checkout);
        $qdel_checkout->execute([$_SESSION['auth']['user']]);
        if ($_SESSION['factor']['wallet'] > 0) {
            $factor_wallet = $_SESSION['factor']['wallet'] * -1;
            $data_value = 'کاهش موجودی بخاطر فاکتور شماره : ' . $factorId;
            $insert_wallet = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
            $qwallet = $conn->prepare($insert_wallet);
            $qwallet->execute([$_SESSION['auth']['user'], $factor_wallet, 'موفق', $datenow, $timenow, $data_value, 0, $factorId, $ip, $os]);
        }

        //* Start Payment Processing *//
        switch ($method) {
            case 'بدون وجه':
                if ($_SESSION['factor']['total'] == 0) {
                    $update = 'UPDATE dbfactor SET status=?,dateresponse=?,timeresponse=? WHERE idfactor=?';
                    $query_up = $conn->prepare($update);
                    $query_up->execute(['موفق', $datenow, $timenow, $factorId]);
                    //
                    $update_dbcourse = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
                    $query_db = $conn->prepare($update_dbcourse);
                    $query_db->execute([1, $factorId]);
                    //
                    $_SESSION['payment'] = array(
                        "factorid" => $factorId,
                        "metod" => 3,
                        "verify" => 100,
                        "sumItem" => $_SESSION['factor']['fee'],
                        "subtotal" => $_SESSION['factor']['subtotal'],
                    );
                       include_once 'quizconfig.php';
                    include_once 'inc/function-quiz.php';
                    if(checkUserquiz($_SESSION['auth']['user'])){
                        $person=retPerson($_SESSION['auth']['user']);
                        $login=MD5(passUser($_SESSION['auth']['user']));
                        $inquiz='INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                        $qin=$ponn->prepare($inquiz);
                        $qin->execute([1,$_SESSION['auth']['user'],$login,1,'3021-03-21',$person->fname,$person->lname]);
                    }
                    $cou='SELECT * FROM dbcourse WHERE numfactor=?';
                    $qpo=$conn->prepare($cou);
                    $qpo->execute([$factorId]);
                    $rew=$qpo->fetchAll(PDO::FETCH_OBJ);
                    foreach ($rew as $tbt){
                        if(checkGroupAdd($tbt->codecourse,$tbt->username)){
                            $ugid=retIdGroup($tbt->codecourse);
                            $uid=retIdStudent($tbt->username);
                            $add='INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
                            $qad=$ponn->prepare($add);
                            $qad->execute([$ugid,$uid]);
                        }
                    }
                    unset($_SESSION['factor']);
                    header('location:' . url('pay/verification.php'));
                }
                break;
            case 'پرداخت آنلاین':
                $_SESSION['payment'] = array(
                    "factorid" => $factorId,
                    "metod" => 1,
                    "sumItem" => $_SESSION['factor']['fee'],
                    "subtotal" => $_SESSION['factor']['subtotal'],
                    "total" => $_SESSION['factor']['total']
                );
                unset($_SESSION['factor']);
                header('location:' . url('pay/request.php'));

                break;
            case 'کارت به کارت':
                $_SESSION['Card'] = array(
                    "factorid" => $factorId,
                    "metod" => 2,
                    "sumItem" => $_SESSION['factor']['fee'],
                    "subtotal" => $_SESSION['factor']['subtotal'],
                    "total" => $_SESSION['factor']['total']
                );
                unset($_SESSION['factor']);
                header('location:' . url('pay/CardPal.php'));
                break;

        }
    }
}


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
    <title> سبد خرید |<?php echo __TITLE ?></title>
    <?php include_once 'inc/link.php'; ?>
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
    include_once 'menu.php';
    include_once 'header.php';
    ?>
    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- Row -->
                <div class="row row-sm">
                    <div class="col-lg-12 col-xl-9 col-md-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-3 font-weight-bold tx-14">سبد خرید</h5>
                            </div>
                            <?php
                            if ($wallet < 0){
                                echo '<div class="card-body">
                                            <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        
                                        لطفا ابتدا بدهی خود را از طریق افزایش موجودی پرداخت نمایید.
                                        </div>
                                          <a href=" ' . url("wallet/deposit.php") . ' ">	<button class="btn ripple text-center   btn-warning"><i class="ti-wallet"></i> افزایش موجودی </button></a>
                                </div>
                         </div>
                   </div>
             </div>
        </div>
    </div>
</div>
                    ';
                            }else{
                            ?>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table border table-hover text-nowrap table-shopping-cart mb-0">
                                        <thead class="text-muted">
                                        <tr class="small text-uppercase">
                                            <th scope="col"><?php echo m('col.plan_course') ?></th>
                                            <th scope="col" class="text-center ">حذف</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $checkout = 'SELECT * FROM checkout INNER JOIN codtbl_course ON `checkout`.`coursecode`= `codtbl_course`.`codecourse` WHERE `checkout`.`username`=? AND `codtbl_course`.courseactive=?';
                                        $query1 = $conn->prepare($checkout);
                                        $query1->execute([$_SESSION['auth']['user'], 1]);
                                        $result1 = $query1->fetchAll();
                                        if ($query1->rowCount() > 0) {
                                            foreach ($result1 as $res) {
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="media">
                                                            <div class="card-aside-img">
                                                                <img src="<?php echo $res['piclink'] ?>" alt="img"
                                                                     class="img-sm">
                                                            </div>
                                                            <div class="media-body my-auto">
                                                                <div class="card-item-desc mt-0">
                                                                    <h6 class="font-weight-semibold mt-0 text-uppercase"><?php echo htmlentities($res['coursename']) ?></h6>
                                                                    <dl class="card-item-desc-1">
                                                                        <dt>قیمت:</dt>
                                                                        <dd><?php echo number_format($res['fee']) ?>
                                                                            تومان
                                                                        </dd>
                                                                    </dl>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="?del=<?php echo $res['coursecode'] ?>"
                                                           class="remove-list text-danger tx-20 remove-button"
                                                           data-abc="true"><i class="fa fa-trash"></i></a>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            echo '<div class="text-center text-danger"> سبد خرید شما خالی است </div> ';
                                            $empty = 1;
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    $count = 'SELECT SUM(fee) as sumfee FROM checkout WHERE username=? ';
                    $query2 = $conn->prepare($count);
                    $query2->execute([$_SESSION['auth']['user']]);
                    $ress = $query2->fetch();
                    if (isset($darsad)) {
                        if ($ress['sumfee'] > 0) {
                            $dis = $ress['sumfee'] * $darsad;
                        } else {
                            $dis = 0;
                        }
                    }
                    if ($ress['sumfee'] == NULL) {
                        $dis = 0;
                    }
                    if ($ress['sumfee'] != NULL and $ress['sumfee'] <= $dis) {
                        $dis = $ress['sumfee'];
                    }
                    ?>
                    <div class="col-lg-12 col-xl-3 col-md-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <?php if (isset($diseror) and $diseror == 2) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کد تخفیف استفاده شده است
                                    </div>
                                <?php }
                                if (isset($diseror) and $diseror == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        این کد تخفیف وجود ندارد
                                    </div>
                                <?php }
                                if (isset($diseror) and $diseror == 100 and $dis > 0) { ?>
                                    <div class="alert alert-warning" role="alert">
                                        <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        تخفیف به مبلغ <?php echo number_format($dis); ?> تومان اعمال شد
                                    </div>
                                <?php } ?>
                                <form method="post">
                                    <div class="form-group mb-0"><label>کد تخفیف دارید؟</label>
                                        <div class="input-group"><input type="text" class="form-control coupon"
                                                                        placeholder="کد تخفیف" name="discount" required>
                                            <span class="input-group-append"> <button type="submit"
                                                                                      class="btn btn-primary btn-apply coupon">ثبت </button> </span>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card custom-card cart-details">
                            <div class="card-body">

                                <h5 class="mb-3 font-weight-bold tx-14">صورتحساب</h5>
                                <dl class="dlist-align">
                                    <dt class="">جمع موارد:</dt>

                                    <dd class="text-right mr-auto"> <?php if ($ress['sumfee'] > 0) {
                                            echo number_format($ress['sumfee']);
                                        } else {
                                            $ress['sumfee'] = 0;
                                            echo $ress['sumfee'];
                                        } ?> تومان
                                    </dd>
                                </dl>

                                <dl class="dlist-align">
                                    <dt>تخفیف:</dt>
                                    <dd class="text-right text-danger mr-auto"> <?php echo number_format($dis) ?>
                                        تومان
                                    </dd>

                                </dl>

                                <dl class="dlist-align">
                                    <dt>قیمت کل:</dt>
                                    <?php $total1 = $ress['sumfee'] - $dis; ?>
                                    <dd class="text-right mr-auto"><?php echo number_format($total1) ?> تومان</dd>
                                </dl>
                                <dl class="dlist-align">
                                    <dt>پرداختی از کیف پول:</dt>
                                    <?php
                                    $totals = $total1 - $wallet;
                                    if ($totals <= 0) {
                                        $wallet = $total1;
                                        $totals = 0;
                                    }
                                    ?>

                                    <dd class="text-right mr-auto"><?php echo number_format($wallet) ?> تومان</dd>
                                </dl>
                                <form method="post">
                                    <?php


                                    $_SESSION['factor'] = array(
                                        "fee" => $ress['sumfee'],
                                        "codediscount" => $codedis,
                                        "value_discount" => $dis,
                                        "wallet" => $wallet,
                                        "subtotal" => $total1,
                                        "total" => $totals
                                    );

                                    ?>
                                    <dl class="dlist-align">
                                        <dt>نحوه پرداخت:<span class="text-danger">*</span></dt>
                                        <?php if ($totals <= 0) { ?>

                                            <dt class="text-right  mr-auto">
                                                <div class="payment-type d-flex">
                                                    <input type="radio" name="pay[]" id="3" value="3"
                                                           checked><label class="paypal-label payment-cards four col"
                                                                          for="3"> بدون وجه</label></div>
                                            </dt>
                                        <?php } else { ?>

                                            <dt class="text-right  mr-auto">
                                                <div class="payment-type d-flex">
                                                    <input type="radio" name="pay[]" id="1" value="1" checked><label
                                                          class="paypal-label payment-cards four col" for="1">پرداخت آنلاین
                                                        </label>
                                                    <!--                                                    <input type="radio" name="pay[]" id="2" value="2" checked ><label-->
<!--                                                            class="amex-label payment-cards four col" for="2">کارت به-->
<!--                                                        کارت</label>-->
                                                </div>
                                            </dt>
                                        <?php } ?>
                                    </dl>
                                    <hr>
                                    <dl class="dlist-align">

                                        <dt>مبلغ قابل پرداخت:</dt>
                                        <dd class="text-right text-success tx-18  mr-auto">
                                            <strong><?php echo number_format($totals) ?> تومان </strong></dd>
                                    </dl>
                                    <div class="step-footer">
                                        <?php if (!isset($empty)) { ?>
                                            <button data-direction="next" class="step-btn btn btn-success btn-block"
                                                    type="submit" name="subtotal">ثبت
                                                صورتحساب و پرداخت
                                            </button>
                                        <?php } ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Row -->


            </div>
        </div>
    </div>

    <!-- Main Footer-->
<?php }
include 'footers.php'; ?>
    <!--End Footer-->


</body>
</html>