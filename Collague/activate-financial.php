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
    <title> صورتحساب های موفق| <?php echo __TITLE ?></title>
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
    <!-- Main Content-->
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

                                <div aria-multiselectable="true" class="accordion" id="accordion" role="tablist">
                                    <?php $dqt = 'SELECT * FROM dbfactor WHERE  useradder=? ORDER BY  idfactor DESC ';
                                    $qrt = $conn->prepare($dqt);
                                    $qrt->execute([$_SESSION['auth']['user']]);
                                    $readio = $qrt->fetchAll(PDO::FETCH_OBJ);
                                    if ($qrt->rowCount() > 0) {
                                        foreach ($readio as $def) {
                                            $rest = retPerson($def->username);
                                            if ($def->status == 'موفق') {
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

                                                                <dd class="text-right mr-auto badge badge-pill badge-success">
                                                                    پرداخت شده
                                                                </dd>


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
                                                                        <th><?php echo t('grade') ?></th>
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


                                                                    <dt class="text-right  mr-auto"> <?php echo $def->metod ?></dt>


                                                                </dl>
                                                                <hr>
                                                                <dl class="dlist-align">


                                                                    <dt><?php echo $tran ?></dt>
                                                                    <dd class="text-right text-success tx-18  mr-auto">
                                                                        <strong>  <?php echo number_format($def->factorfee) ?>
                                                                            تومان </strong></dd>
                                                                </dl>


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


    <!-- Main Footer-->
    <?php include '../footers.php'; ?>
    <!--End Footer-->


</body>
</html>

