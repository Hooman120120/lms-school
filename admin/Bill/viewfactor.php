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
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
if (!isset($_GET['id'])) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
/*
 * Read-only view. Besides the statement flag, it opens for any invoice the
 * account can see in a list that links here, including the installment cases
 * (payment.php), where "own" means payments.operator_username.
 */
$viewFactorId = (int)$_GET['id'];
$viewFactorAllowed = Permision('permision_bill')
    || AdminCanAccessFinanceFactor($viewFactorId, 'finance.pending')
    || AdminCanAccessFinanceFactor($viewFactorId, 'finance.unpaid', Permision('installment_permission'))
    || AdminCanAccessFinanceFactor($viewFactorId, 'finance.returned');
if (!$viewFactorAllowed && $viewFactorId > 0) {
    $installmentScope = AdminRecordVisibility('finance.installments', Permision('installment_permission'));
    if ($installmentScope === null && AdminCanUsePermissionScope('finance.installments.create', ['permision_viewbill'])) $installmentScope = 'own';
    if ($installmentScope !== null) {
        $installmentFactor = $conn->prepare('SELECT 1 FROM payments WHERE idfactor = ? AND (? = 1 OR operator_username = ?) LIMIT 1');
        $installmentFactor->execute([$viewFactorId, $installmentScope === 'all' ? 1 : 0, (string)$_SESSION['admin']['user']]);
        $viewFactorAllowed = (bool)$installmentFactor->fetchColumn();
    }
}
if (!$viewFactorAllowed) {
    echo '<strong>403</strong>';
    echo '<script>window.close() </script>';
    exit;
}
if (queryFactor($_GET['id'])) {
    $factor = queryFactor($_GET['id']);
} else {
    echo '<script>window.close() </script>';
    echo '<strong>404</strong>';
    exit;
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
    <title> مشاهده فاکتور | <?php echo __TITLE ?></title>
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
    <div class="card-body">
        <dl class="dlist-align">
            <dt class=""> <?php
                if ($factor->status == 'موفق') {
                    echo 'شماره فاکتور:';
                } else {
                    echo 'شماره پیش فاکتور:';
                } ?></dt>
            <dd class="text-right mr-auto">
                <?php echo '#' . $_GET['id'] ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">تاریخ صدور صورتحساب</dt>
            <?php
            $dateno = $factor->datecreate;
            if ($dateno != NULL) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                $dateno = $factor->timecreate . ' - ' . $dateno;
            }
            ?>


            <dd class="text-right mr-auto">  <?php echo $dateno ?>

            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">نام دانش آموز:</dt>
            <dd class="text-right mr-auto">
                <?php $fullname = retPerson($factor->username);
                echo $fullname->fname . ' ' . $fullname->lname ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">کد ملی دانش آموز:</dt>
            <dd class="text-right mr-auto">
                <?php echo $factor->username; ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">ایجاد کننده:</dt>
            <dd class="text-right mr-auto">
                <?php $fullname = retPerson($factor->username);
                if ($factor->useradder != NULL) {
                    $useradder = retPerson($factor->useradder);
                    echo 'همکار آموزشی:' . $useradder->fname . ' ' . $useradder->lname;
                }
                if ($factor->factoradder != NULL) {
                    $factoradder = retAdmin($factor->factoradder);
                    echo 'کارمند ' . $factoradder->fname . ' ' . $factoradder->lname;
                }
                ?>
            </dd>
        </dl>
        <dl class="dlist-align">
            <dt class="">کد ملی ایجاد کننده:</dt>
            <dd class="text-right mr-auto">
                <?php echo $factor->useradder; ?>
            </dd>
        </dl>
        <div class="table-responsive border">
            <table class="table  text-nowrap text-md-nowrap table-striped mg-b-0">
                <thead>
                <tr>
                    <th>ردیف</th>
                    <th>پایه</th>
                    <th>مبلغ</th>
                    <th>خدمات پشتیبانی</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $sqt = 'SELECT * FROM dbcourse WHERE numfactor=?';
                $qut = $conn->prepare($sqt);
                $qut->execute([$factor->idfactor]);
                $rest = $qut->fetchAll();
                if ($qut->rowCount() > 0) {
                    $id = 1;
                    foreach ($rest as $erp) {

                        ?>
                        <tr>
                            <th><?php echo $id ?></th>
                            <td><?php $db = returnCourse($erp['codecourse']);
                                echo $db->coursename; ?></td>
                            <?php
                            if ((int)$erp['has_operator'] === 0) {
                                $price = $db->coursefee_without;
                            } else {
                                $price = $db->coursefee;
                            }
                            ?>
                            <td><?php echo number_format($price ?? 0) ?>
                                تومان
                            </td>
                            <td><?php echo $erp['has_operator'] == 0 ? 'ندارد' : 'دارد' ?>
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


            <dd class="text-right mr-auto">    <?php echo number_format($factor->sumitem) ?>
                تومان
            </dd>
        </dl>


        <dl class="dlist-align">
            <dt>تخفیف:</dt>
            <dd class="text-right text-danger mr-auto">
                <?php echo number_format($factor->discountfee) ?> تومان
            </dd>

        </dl>
        <dl class="dlist-align">
            <dt>کارمزد همکار:</dt>
            <dd class="text-right text-danger mr-auto">
                <?php echo number_format($factor->wage) ?> تومان
            </dd>

        </dl>

        <dl class="dlist-align">
            <dt>قیمت کل:</dt>

            <dd class="text-right mr-auto">
                <?php echo number_format($factor->subtotal) ?> تومان
            </dd>
        </dl>
        <dl class="dlist-align">
            <?php
            if ($factor->status == 'موفق') {
                $tran = 'وجه پرداختی:';
            } else {
                $tran = 'مبلغ قابل پرداخت:';
            }

            ?>


            <dt>پرداختی از کیف پول:</dt>

            <dd class="text-right mr-auto">
                <?php echo number_format($factor->wallet); ?>
                تومان
            </dd>


        </dl>
        <dl class="dlist-align">
            <dt>شناسه تراکنش:</dt>

            <dd class="text-right mr-auto"> <?php echo $factor->ref_id ?></dd>
        </dl>
        <dl class="dlist-align">
            <dt>زمان پرداخت:</dt>
            <?php

            $dateno = $factor->dateresponse;
            if ($dateno != NULL) {
                $dateno = explode('-', $dateno);
                $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                $dateno = implode('/', $dateno);
                $dateno = $factor->timeresponse . ' - ' . $dateno;
            } else {
                $dateno = '';
            }
            ?>

            <dd class="text-right mr-auto"><?php echo $dateno ?></dd>
        </dl>

        <form method="post">
            <dl class="dlist-align">
                <dt>نحوه پرداخت:</dt>


                <dt class="text-right  mr-auto"> <?php echo $factor->metod ?></dt>


            </dl>
            <hr>
            <dl class="dlist-align">


                <dt><?php echo $tran ?></dt>
                <dd class="text-right text-success tx-18  mr-auto">
                    <strong>  <?php echo number_format($factor->factorfee) ?>
                        تومان </strong></dd>
            </dl>

            <div class="text-center"><?php
                if ($factor->status == 'موفق') {
                    $color = 'text-success';

                } else {
                    $color = 'text-danger';

                }
                echo '<h3 class=" ' . $color . ' ">' . $factor->status . '</h3>'; ?>

            </div>
    </div>


    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>
</html>