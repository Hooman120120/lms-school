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
//* End Check Session JUST ONE Session
if ($_SESSION['auth']['role'] != 2) {
    header('location: ' . url('index.php'));
    exit;
}
if (isset($_SESSION['mes'])) {
    $mas = 1;
    unset($_SESSION['mes']);
}


if (isset($_GET['delivery']) and $_GET['delivery'] == '00000000notok') {
    $_SESSION['mes'] = 1;
    header('location:' . url('Collague/away-financial.php'));
    die();

}
if (isset($_POST['subtotal'])) {
    if ($_POST['pay'][0] == 1) {
        $erp = queryFactor($_POST['factor']);
        $_SESSION['payment'] = array(
            "factorid" => $_POST['factor'],
            "metod" => $_POST['pay'][0],
            "sumItem" => $erp->sumitem,
            "subtotal" => $erp->subtotal,
            "total" => $erp->factorfee
        );
        $update = 'UPDATE dbfactor SET metod=? WHERE idfactor=?';
        $qup = $conn->prepare($update);
        $qup->execute(['پرداخت آنلاین', $_POST['factor']]);

        header('location:' . url('Payment/request.php'));
        die();
    }
    if ($_POST['pay'][0] == 2) {
        $erp = queryFactor($_POST['factor']);
        if ($erp->status != 'در انتظار بررسی') {
            $_SESSION['Card'] = array(
                "factorid" => $_POST['factor'],
                "metod" => $_POST['pay'][0],
                "sumItem" => $erp->sumitem,
                "student" => $erp->username,
                "subtotal" => $erp->subtotal,
                "total" => $erp->factorfee
            );
            $update = 'UPDATE dbfactor SET metod=? WHERE idfactor=?';
            $qup = $conn->prepare($update);
            $qup->execute(['کارت به کارت', $_POST['factor']]);

            header('location:' . url('Payment/CardPal.php'));
            die();
        }
    }

}
if (isset($_POST['refresh']) and isset($_SESSION['newWallet'])) {
    $newWallet = $_SESSION['newWallet']['wallet'];
    $factorId = $_SESSION['newWallet']['factorid'];
    date_default_timezone_set("Iran");
    $datenow = date("Y-m-d");
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();
    $factor = queryFactor($factorId);
    $totalWallet = totalWallet($_SESSION['auth']['user']);
    if ($newWallet <= $totalWallet) {
        $factorfee = $factor->factorfee - $newWallet;
        if ($factor->wallet > 0) {
            $nWallet = $factor->wallet + $newWallet;
        } else {
            $nWallet = $newWallet;
        }
        $upfactor = 'UPDATE dbfactor SET wallet=? , factorfee=? WHERE idfactor=?';
        $qufactor = $conn->prepare($upfactor);
        $qufactor->execute([$nWallet, $factorfee, $factorId]);
        //
        $newWallet = $newWallet * -1;
        $detail = 'کاهش موجودی بخاطر صورتحساب شماره : ' . $factorId;
        $insertWallet = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os) VALUES (?,?,?,?,?,?,?,?,?,?)';
        $quinsertWallet = $conn->prepare($insertWallet);
        $quinsertWallet->execute([$_SESSION['auth']['user'], $newWallet, 'موفق', $datenow, $timenow, $detail, 0, $factorId, $ip, $os]);
        //

        if ($factorfee == 0) {
            $upfactor = 'UPDATE dbfactor SET metod=? ,status=?,dateresponse=?,timeresponse=?  WHERE idfactor=?';
            $qufactor = $conn->prepare($upfactor);
            $qufactor->execute(['پرداخت از کیف پول', 'موفق', $datenow, $timenow, $factorId]);

            $update_dbcourse = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
            $query_db = $conn->prepare($update_dbcourse);
            $query_db->execute([1, $factorId]);
            unset($_SESSION['newWallet']);
            $_SESSION['payment'] = array(
                "factorid" => $factorId,
                "metod" => 3,
                "verify" => 100
            );
                // Register Quiz
            include_once '../quizconfig.php';
            include_once '../inc/function-quiz.php';
            if(checkUserquiz($factor->username)){
                $person=retPerson($factor->username);
                $login=MD5(passUser($factor->username));
                $inquiz='INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
                $qin=$ponn->prepare($inquiz);
                $qin->execute([1,$factor->username,$login,1,'3021-03-21',$person->fname,$person->lname]);
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
            header('location:' . url('Payment/verify.php'));

        }
        //


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
    <title> صورتحساب پرداخت نشده | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
</head>
<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="col-lg-12 col-xl-12 col-md-12">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-3 font-weight-bold tx-14">صورتحساب</h5>
                        </div>
                        <div class="card-body">
                            <div class="row row-sm">
                                <?php if (isset($mas) and $mas == 1) { ?>
                                    <div class="alert alert-danger" role="alert">
                                                        <span class="alert-inner--icon"><i
                                                                    class="fa fa-shopping-cart"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        پرداخت ناموفق! لطفا پرداخت خود را نهایی کنید
                                    </div>
                                <?php } ?>
                                <?php if (isset($_SESSION['CDcard'])) { ?>
                                    <div class="alert alert-solid-success" role="alert">
                                                        <span class="alert-inner--icon"><i
                                                                    class="fa fa-id-card"></i></span>
                                        <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                type="button">
                                            <span aria-hidden="true">×</span>
                                        </button>
                                        درخواست بررسی واریز وجه برای صورتحساب
                                        <?php echo $_SESSION['CDcard']['factorid']; ?>
                                        با موفقیت ثبت شد
                                    </div>
                                <?php }
                                unset($_SESSION['CDcard']) ?>

                                <div aria-multiselectable="true" class="accordion" id="accordion" role="tablist">
                                    <?php $dqt = 'SELECT * FROM dbfactor WHERE useradder=? ORDER BY  idfactor DESC ';
                                    $qrt = $conn->prepare($dqt);
                                    $qrt->execute([$_SESSION['auth']['user']]);
                                    $readio = $qrt->fetchAll(PDO::FETCH_OBJ);
                                    if ($qrt->rowCount() > 0) {
                                        foreach ($readio as $def) {
                                            $rest = retPerson($def->username);
                                            if ($def->status != 'موفق') {
                                                ?>
                                                <div class="card">
                                                    <div class="card-header" id="heading<?php echo $def->idfactor ?>"
                                                         role="tab">
                                                        <a aria-controls="collapseTwo" aria-expanded="false"
                                                           class="collapsed"
                                                           data-bs-toggle="collapse"
                                                           href="#collapse<?php echo $def->idfactor ?>">
                                                            <dl class="dlist-align">
                                                                <dt class="">صورتحساب
                                                                    شماره: <?php echo $def->idfactor . ' # - ' . $rest->fname . ' ' . $rest->lname ?></dt>
                                                                <?php if ($def->status == 'موفق') {
                                                                    echo '<dd class="text-right mr-auto badge badge-pill badge-success">
                                                    پرداخت شده
                                                    </dd>';
                                                                } else {
                                                                    echo '<dd class="text-right mr-auto badge badge-pill badge-warning">
                                                  ' . $def->status . '
                                                    </dd>';
                                                                }
                                                                ?>

                                                            </dl>
                                                        </a>
                                                    </div>
                                                    <div aria-labelledby="heading<?php echo $def->idfactor ?>"
                                                         class="collapse" data-parent="#accordion"
                                                         id="collapse<?php echo $def->idfactor ?>" role="tabpanel">
                                                        <div class="card-body">
                                                            <dl class="dlist-align">
                                                                <dt class="">تاریخ صدور صورتحساب</dt>
                                                                <?php
                                                                $dateno = $def->datecreate;
                                                                if ($dateno != NULL) {
                                                                    $dateno = explode('-', $dateno);
                                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                                    $dateno = implode('/', $dateno);
                                                                    $dateno = $def->timecreate . ' - ' . $dateno;
                                                                }
                                                                ?>
                                                                <dd class="text-right mr-auto">  <?php echo $dateno ?>
                                                                </dd>
                                                            </dl>
                                                            <div class="table-responsive border">
                                                                <table class="table  text-nowrap text-md-nowrap table-striped mg-b-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>ردیف</th>
                                                                        <th>دوره</th>
                                                                        <th>مبلغ</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <?php
                                                                    $sqt = 'SELECT * FROM dbcourse WHERE numfactor=?';
                                                                    $qut = $conn->prepare($sqt);
                                                                    $qut->execute([$def->idfactor]);
                                                                    $rest = $qut->fetchAll();
                                                                    if ($qut->rowCount() > 0) {
                                                                        $id = 1;
                                                                        foreach ($rest as $erp) {

                                                                            ?>
                                                                            <tr>
                                                                                <th><?php echo $id ?></th>
                                                                                <td><?php $db = returnCourse($erp['codecourse']);
                                                                                    echo $db->coursename; ?></td>
                                                                                <td><?php echo number_format($db->coursefee) ?>
                                                                                    تومان
                                                                                </td>
                                                                            </tr>

                                                                            <?php
                                                                            $id++;
                                                                        }
                                                                    } ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <dl class="dlist-align">
                                                                <dt class="">جمع موارد:</dt>


                                                                <dd class="text-right mr-auto">    <?php echo number_format($def->sumitem) ?>
                                                                    تومان
                                                                </dd>
                                                            </dl>


                                                            <dl class="dlist-align">
                                                                <dt>تخفیف:</dt>
                                                                <dd class="text-right text-danger mr-auto">
                                                                    <?php echo number_format($def->discountfee) ?> تومان
                                                                </dd>
                                                            </dl>
                                                            <dl class="dlist-align">
                                                                <dt>کارمزد همکار:</dt>
                                                                <dd class="text-right text-danger mr-auto">
                                                                    <?php echo number_format($def->wage) ?> تومان
                                                                </dd>
                                                            </dl>

                                                            <dl class="dlist-align">
                                                                <dt>قیمت کل:</dt>
                                                                <dd class="text-right mr-auto">
                                                                    <?php echo number_format($def->subtotal) ?> تومان
                                                                </dd>
                                                            </dl>
                                                            <dl class="dlist-align">
                                                                <?php
                                                                if ($def->status == 'موفق') {
                                                                    $tran = 'وجه پرداختی:';
                                                                } else {
                                                                    $tran = 'مبلغ قابل پرداخت:';
                                                                }

                                                                ?>
                                                                <?php if ($wallet > 0 and $def->factorfee != 0) {
                                                                    if ($def->status != 'موفق') {
                                                                        if ($def->status != 'در انتظار بررسی') {
                                                                            if ($def->factorfee <= $wallet) {
                                                                                $nwallet = $def->factorfee;
                                                                            } else {
                                                                                $nwallet = $wallet;
                                                                            }
                                                                            $_SESSION['newWallet'] = array(
                                                                                "factorid" => $def->idfactor,
                                                                                "wallet" => $nwallet
                                                                            );
                                                                            ?>
                                                                            <dt>اعتبار کیف پول:</dt>
                                                                            <dd class="text-right mr-auto">
                                                                                <form method="post">
                                                                                    <div class="input-group">
                                                                                        <?php echo number_format($nwallet); ?>
                                                                                        تومان
                                                                                        <span class="input-group-btn"><button
                                                                                                    class="btn btn-sm ripple btn-primary"
                                                                                                    type="submit"
                                                                                                    name="refresh">
													<span class="input-group-btn"><i class="fa fa-refresh"></i> اعمال  </span></button></span>
                                                                                    </div>
                                                                                </form>
                                                                            </dd>
                                                                            <?php
                                                                        } else { ?>
                                                                            <dt>پرداختی از کیف پول:</dt>

                                                                            <dd class="text-right mr-auto">
                                                                                <?php echo number_format($def->wallet); ?>
                                                                                تومان
                                                                            </dd>
                                                                        <?php }
                                                                    } else {
                                                                        ?>
                                                                        <dt>پرداختی از کیف پول:</dt>
                                                                        <dd class="text-right mr-auto">
                                                                            <?php echo number_format($def->wallet); ?>
                                                                            تومان
                                                                        </dd>
                                                                    <?php }
                                                                } else { ?>
                                                                    <dt>پرداختی از کیف پول:</dt>

                                                                    <dd class="text-right mr-auto">
                                                                        <?php echo number_format($def->wallet); ?>
                                                                        تومان
                                                                    </dd>
                                                                <?php } ?>
                                                            </dl>
                                                            <dl class="dlist-align">
                                                                <dt>شناسه تراکنش:</dt>
                                                                <dd class="text-right mr-auto"> <?php echo $def->ref_id ?></dd>
                                                            </dl>
                                                            <dl class="dlist-align">
                                                                <dt>زمان پرداخت:</dt>
                                                                <?php
                                                                $dateno = $def->dateresponse;
                                                                if ($dateno != NULL) {
                                                                    $dateno = explode('-', $dateno);
                                                                    $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                                    $dateno = implode('/', $dateno);
                                                                    $dateno = $def->timeresponse . ' - ' . $dateno;
                                                                } else {
                                                                    $dateno = '';
                                                                }
                                                                ?>
                                                                <dd class="text-right mr-auto"><?php echo $dateno ?></dd>
                                                            </dl>
                                                            <form method="post">
                                                                <dl class="dlist-align">
                                                                    <dt>نحوه پرداخت:</dt>
                                                                    <?php if ($def->status != 'موفق' and $def->status != 'در انتظار بررسی') { ?>
                                                                        <dt class="text-right  mr-auto">
                                                                            <div class="payment-type d-flex">
                                                                                <input type="radio" name="pay[]" id="1"
                                                                                       value="1" checked><label
                                                                                        class="paypal-label payment-cards four col"
                                                                                        for="1">درگاه آنلاین</label>
                                                                                <input type="radio" name="pay[]" id="2"
                                                                                       value="2"><label
                                                                                        class="amex-label payment-cards four col"
                                                                                        for="2">کارت به کارت</label>
                                                                            </div>
                                                                        </dt>
                                                                    <?php } else { ?>
                                                                        <dt class="text-right  mr-auto"> <?php echo $def->metod ?></dt> <?php } ?>
                                                                </dl>
                                                                <hr>
                                                                <dl class="dlist-align">
                                                                    <dt><?php echo $tran ?></dt>
                                                                    <dd class="text-right text-success tx-18  mr-auto">
                                                                        <strong>  <?php echo number_format($def->factorfee) ?>
                                                                            تومان </strong></dd>
                                                                </dl>
                                                                <?php if ($def->status != 'موفق' and $def->status != 'در انتظار بررسی')  { ?>
                                                                <input type="hidden" name="factor"
                                                                       value="<?php echo $def->idfactor ?>">
                                                                <button data-direction="next"
                                                                        class="step-btn btn btn-success btn-block"
                                                                        type="submit" name="subtotal">پرداخت صورتحساب
                                                                </button>
                                                            </form>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php }
                                        }
                                    } ?>
                                </div><!-- accordion -->
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <!-- Row -->
            <div class="row row-sm">
            </div>
        </div>
    </div>
    <?php include '../footers.php'; ?>

</body>
</html>
