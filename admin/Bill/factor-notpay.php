<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
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
if (AdminHasAnyCurrentTreePermission()
    ? !AdminHasMenuNavigationPermission('finance.unpaid')
    : !Permision('permision_notpay')) {
    http_response_code(403); exit('مجوز مدیریت پرداخت‌های ناموفق برای شما تعریف نشده است.');
}
if (isset($_SESSION['call'])) {
    $name = $_SESSION['call']['name'];
    $call = 1;
    unset($_SESSION['call']);
}
if (isset($_SESSION['message'])) {
    $id = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['fee'])) {
    $fee = $_SESSION['fee']['fee'];
    $name = $_SESSION['fee']['name'];
    unset($_SESSION['fee']);
}
$username = $_SESSION['admin']['user'];

$canViewAll = false;

try {
    $stmt = $conn->prepare("SELECT installment_permission FROM dbadmin WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm && (int)$perm['installment_permission'] === 1) {
        $canViewAll = true;
    }
} catch (PDOException $e) {
    error_log("Permission check failed: " . $e->getMessage());
}
$factorScope = AdminRecordVisibility('finance.unpaid', $canViewAll);
if ($factorScope === null) {
    http_response_code(403); exit('سطح مشاهدهٔ فاکتورهای پرداخت‌نشده برای شما فعال نشده است.');
}
$canCreateInstallment = !AdminHasAnyCurrentTreePermission()
    || AdminHasMenuPermission('finance.unpaid.installment-create');


if (isset($_GET['call']) and $_GET['call'] != '') {
    $factorId = queryFactor($_GET['call']);
    if ($factorId->useradder != NULL) {
        $user = $factorId->useradder;
        $semat = 'همکار آموزشی';
    } else {
        $user = $factorId->username;
        $semat = 'دانش آموز';
    }
    $ret = retPerson($user);
    $tel = $ret->tel1;
    $name = $semat . ' ' . $ret->fname . ' ' . $ret->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: factor-notpay.php');
    die();
}
if (isset($_GET['call-student']) and $_GET['call-student'] != '') {
    $semat = 'دانش آموز';
    $qau = retPerson($_GET['call-student']);
    $tel = $qau->tel1;
    $name = $semat . ' ' . $qau->fname . ' ' . $qau->lname;
    Call($tel, $_SESSION['admin']['dakheli'], $name);
    $_SESSION['call'] = array(
        "name" => $name
    );
    header('location: factor-notpay.php');
    die();
}
if (isset($_GET['unverify'])) {
    $factorId = intval($_GET['unverify']);
    $factor = queryFactor($factorId);
    if ($factor->status != 'موفق') {
        $wallet = $factor->wallet;
        date_default_timezone_set("Iran");
        $datenow = date("Y-m-d");
        $timenow = date('H:i:s');
        $ip = GetIp();
        $os = GetOs();
        if ($wallet > 0) {
            $detail = 'افزایش کیف پول بواسطه حذف فاکتور:' . $factorId;
            if ($factor->useradder != NULL) {
                $username = $factor->useradder;
            } else {
                $username = $factor->username;
            }
            $indb = 'INSERT INTO db_wallet (username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,useractive,dateactive,timeactive,ipactive,osactive) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $exindb = $conn->prepare($indb);
            $exindb->execute([$username, $wallet, 'موفق', $datenow, $timenow, $detail, 1, $factorId, $_SESSION['admin']['user'], $datenow, $timenow, $ip, $os]);
            //
            $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
            $query_del = $conn->prepare($delfactor);
            $query_del->execute([$factorId]);
            $_SESSION['message'] = $factorId;
            $fname = retPerson($username);
            $_SESSION['fee'] = array(
                "fee" => $wallet,
                "name" => $fname->fname . ' ' . $fname->lname
            );
            header('location: factor-notpay.php');
        } else {
            $delfactor = 'DELETE FROM dbfactor WHERE idfactor=?';
            $query_del = $conn->prepare($delfactor);
            $query_del->execute([$factorId]);
            $_SESSION['message'] = $factorId;
            header('location: factor-notpay.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
    try {
        if (!$canCreateInstallment) throw new Exception('مجوز ایجاد قسط برای فاکتور پرداخت‌نشده فعال نشده است.');
        $codemeli = trim($_POST['codemeli']);
        $total_amount = intval($_POST['total_amount']);
        $comment = trim($_POST['comment'] ?? '');
        $idfactor = isset($_POST['idfactor']) ? intval($_POST['idfactor']) : 0;

        // Validate inputs
        if (empty($codemeli) || !preg_match('/^\d{10}$/', $codemeli)) {
            throw new Exception("کدملی باید ۱۰ رقم باشد.");
        }
        if ($total_amount <= 0) {
            throw new Exception("مبلغ کل باید بیشتر از صفر باشد.");
        }
        if ($idfactor <= 0) {
            throw new Exception("شناسه فاکتور نامعتبر است.");
        }
        if (!AdminCanAccessFinanceFactor($idfactor, 'finance.unpaid', $canViewAll)) {
            throw new Exception('شما به این فاکتور دسترسی ندارید.');
        }

        $checkStudent = $conn->prepare("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = :codemeli");
        $checkStudent->execute(['codemeli' => $codemeli]);
        $studentExists = $checkStudent->fetchColumn();

        if (!$studentExists) {
            throw new Exception("کدملی وارد شده در سیستم وجود ندارد.");
        }

        // Insert new payment
        $insert = "
            INSERT INTO payments (codemeli, operator_username, total_amount, paid_amount, status, comment, idfactor, created_at, updated_at)
            VALUES (:codemeli, :username, :total_amount, 0, 'active', :comment, :idfactor, NOW(), NOW())
        ";
        $stmt = $conn->prepare($insert);
        $stmt->execute([
            'codemeli' => $codemeli,
            'username' => $username,
            'total_amount' => $total_amount,
            'comment' => $comment,
            'idfactor' => $idfactor
        ]);

        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'> پرداخت جدید با موفقیت ثبت شد.</div>";
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'> خطا در پایگاه داده: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: factor-notpay.php");
    exit;
}


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> فاکتورهای پرداخت نشده | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--    <div id="global-loader">-->
<!--        <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--    </div>-->
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
                <?php
                if (Permision('permision_notpay')) {

                    if (isset($_SESSION['payment_message'])) {
                        echo $_SESSION['payment_message'];
                        unset($_SESSION['payment_message']);
                    }

                    ?>


                    <!-- Row -->
                    <div class="row row-sm">
                        <div class="col-lg-12">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <?php if (isset($call) and $call === 1) { ?>
                                        <div class="alert alert-outline-success  text-center " role="alert">
                                            <div class="mg-t-10">
                                                <img style="width: 75px; height: 75px;"
                                                     src="../../assets/img/call1.gif">در حال برقراری ارتباط
                                                با <?php echo $name ?>

                                                <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                        type="button">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            </div>

                                        </div>
                                    <?php } ?>
                                    <?php if (isset($id)) { ?>
                                        <div class="alert alert-warning" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            فاکتور شماره : <?php echo $id ?> با موفقیت حذف شد
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($fee)) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            مبلغ <?php echo number_format($fee); ?> تومان با موفقیت به کیف پول " <?php echo $name ?> " افزوده شد .
                                        </div>
                                    <?php } ?>
                                    <?php if (isset($_SESSION['request_success'])) { ?>
                                        <div class="alert alert-success" role="alert">
                                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                                            <button aria-label="بستن" class="close" data-bs-dismiss="alert"
                                                    type="button">
                                                <span aria-hidden="true">×</span>
                                            </button>
                                            <?= $_SESSION['request_success'] ?>
                                        </div>
                                        <?php unset($_SESSION['request_success']);
                                    } ?>
                                    <div class="row">
                                        <div class="col-10">
                                            <div>
                                                <div style="display:flex; align-items:center; gap:12px;">
                                                    <h6 class="main-content-label mb-1">فاکتورهای پرداخت نشده</h6>
                                                    <span class="menu-info-trigger"
                                                          data-tooltip="در این بخش فاکتورهایی نمایش داده می‌شوند که برای دانش آموز صادر شده‌اند اما فرآیند پرداخت آن‌ها هنوز تکمیل نشده است. این لیست به شما کمک می‌کند فاکتورهای معلق را بررسی کرده و وضعیت پرداخت آن‌ها را پیگیری کنید."
                                                          aria-label="راهنمای فاکتورهای پرداخت نشده"
                                                          onclick="event.preventDefault(); event.stopPropagation();">
                                                        <i class="fe fe-info fs-4"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-2 text-left">
                                            <button class="btn btn-sm btn-primary" onclick="window.location.reload()"><i
                                                        class="fe fe-refresh-cw"></i> بارگذاری مجدد
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table">
                                        <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                            <thead>
                                            <tr>
                                                <th>ش.فاکتور</th>
                                                <th>نام دانش آموز</th>
                                                <th>مبلغ فاکتور</th>
                                                <th> ایجاد کننده</th>
                                                <th>زمان ایجاد</th>
                                                <th>اقدامات</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $statuses = ['موفق', 'در انتظار بررسی', 'مسترد شده', 'نیازمند تایید مدیر'];

                                            $sql = 'SELECT dbfactor.*, p.id AS payment_id 
                                                                FROM dbfactor 
                                                                LEFT JOIN payments p ON dbfactor.idfactor = p.idfactor 
                                                                WHERE dbfactor.status != ? 
                                                                  AND dbfactor.status != ? 
                                                                  AND dbfactor.status != ? 
                                                                  AND dbfactor.status != ?';

                                            if ($factorScope === 'own') {
                                                $sql .= ' AND (dbfactor.factoradder = ? OR dbfactor.useradder = ?)';
                                            }

                                            $sql .= ' ORDER BY dbfactor.datecreate DESC';

                                            $query = $conn->prepare($sql);

                                            // Build parameters array
                                            $params = $statuses; // already in correct order
                                            if ($factorScope === 'own') {
                                                $params[] = $username;
                                                $params[] = $username;
                                            }

                                            $query->execute($params);

                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            if ($query->rowCount() > 0) {
                                                foreach (
                                                    $result

                                                    as $res
                                                ) {

                                                    ?>
                                                    <tr>
                                                        <td class="wd-20"><?php echo $res->idfactor ?></td>
                                                        <td><?php $fullname = retPerson($res->username);
                                                            echo $fullname->fname . ' ' . $fullname->lname ?></td>
                                                        <td class="wd-20"> <?php echo number_format($res->factorfee) ?> تومان</td>
                                                        <td class="wd-100"><?php if ($res->factoradder != NULL) {
                                                                echo $res->factoradder;
                                                            } ?></td>

                                                        <?php
                                                        $dateno = $res->datecreate;
                                                        $dateno = explode('-', $dateno);
                                                        $dateno = gregorian_to_jalali($dateno[0], $dateno[1], $dateno[2]);
                                                        $dateno = implode('/', $dateno);
                                                        ?>
                                                        <td class="wd-20"><?php echo $dateno ?></td>
                                                        <td class="wd-20">
                                                            <div class="text-wrap">
                                                                <div class="text-center">
                                                                    <div class="dropdown">
                                                                        <button aria-expanded="false"
                                                                                aria-haspopup="true"
                                                                                class="btn ripple btn-outline-primary dropdown-toggle"
                                                                                data-bs-toggle="dropdown" type="button">
                                                                            اقدام <i
                                                                                    class="fas fa-caret-down mr-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu tx-13">
                                                                            <a class="dropdown-item  "
                                                                               href="?call=<?php echo $res->idfactor ?>"><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با
                                                                                صادرکننده
                                                                            </a>
                                                                            <?php if (!empty($res->payment_id) && (AdminHasAnyCurrentTreePermission() ? AdminHasMenuNavigationPermission('finance.installments') : true)): ?>
                                                                                <a class="dropdown-item text-primary" href="<?php echo url('admin/Bill/paymentinstallments.php?id=' . $res->payment_id) ?>">
                                                                                    <i class="fe fe-eye"></i> مشاهده اقساط
                                                                                </a>
                                                                            <?php elseif ($canCreateInstallment): ?>
                                                                                <a class="dropdown-item text-success" href="#"
                                                                                   data-bs-toggle="modal" data-bs-target="#paymentModal"
                                                                                   data-factor-id="<?= $res->idfactor ?>"
                                                                                   data-codemeli="<?= htmlspecialchars(retPerson($res->username)->codemeli ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                                   data-total-amount="<?= $res->factorfee ?>">
                                                                                    <i class="fe fe-plus-circle"></i> ایجاد قسط
                                                                                </a>
                                                                            <?php endif; ?>
                                                                            <a class="dropdown-item  "
                                                                               href="?call-student=<?php echo $res->username ?>"><i class="fe fe-phone-forwarded"></i> تماس
                                                                                با دانش آموز
                                                                            </a>
                                                                            <div class="dropdown-item text-success "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('include-deposit.php?factor='+'<?php echo $res->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=300,top=100,status=0,scrollbars=0,location=0' )"><i class="fe fe-check-square"></i> ثبت و
                                                                                تایید واریزی
                                                                            </div>
                                                                            <div class="dropdown-item text-warning "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('editfactor.php?id='+'<?php echo $res->idfactor ?>','تغییر وضعیت'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-edit"></i>
                                                                                ویرایش فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('viewfactor.php?id='+'<?php echo $res->idfactor ?>','مشاهده فاکتور'
                                                                                         ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-search"></i>
                                                                                مشاهده فاکتور
                                                                            </div>
                                                                            <div class="dropdown-item "
                                                                                 style="text-align: right"
                                                                                 onclick="window.open('listTransaction.php?id='+'<?php echo $res->idfactor ?>','تراکنش فاکتور'
                                                                                         ,'width=800,height=600,menubar=0,toolbar=0,resizable=0,left=500,top=180,status=0,scrollbars=0,location=0' )">
                                                                                <i class="fe fe-list"></i>
                                                                                تراکنش های فاکتور
                                                                            </div>
                                                                            <hr>
                                                                            <a class="dropdown-item text-danger "
                                                                               href="?unverify=<?php echo $res->idfactor ?>"
                                                                               onclick="return confirm('آیا با حذف این فاکتور موافقید؟')"><i
                                                                                        class="fe fe-x-square"></i>
                                                                                حذف فاکتور </a>


                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </td>

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
                    </div>
                    <!-- End Row -->
                <?php } else { ?>
                    <div class="card-body">

                        <div class="alert alert-danger" role="alert">
                            <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                            شما مجوز لازم برای مشاهده این بخش را ندارید، با مدیر سامانه تماس بگیرید
                        </div>
                        <div style="text-align: center; padding-top: 20px">
                            <img src="../../assets/img/media/alert.png">

                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- End Row -->

    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="paymentForm" method="POST" action="factor-notpay.php">
                    <input type="hidden" name="idfactor" id="idfactor" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">ایجاد اقساط</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                    </div>
                    <div class="modal-body">
                        <!-- کدملی -->
                        <div class="mb-3">
                            <label for="codemeli" class="form-label">کدملی دانشجو</label>
                            <input type="text" name="codemeli" id="codemeli" class="form-control" required pattern="\d{10}" title="کدملی باید ۱۰ رقم باشد" readonly>
                        </div>
                        <!-- مبلغ کل -->
                        <div class="mb-3">
                            <label for="total_amount" class="form-label">مبلغ کل (تومان)</label>
                            <input type="number" name="total_amount" id="total_amount" class="form-control" min="1" required readonly>
                        </div>
                        <!-- توضیحات -->
                        <div class="mb-3">
                            <label for="comment" class="form-label">توضیحات</label>
                            <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" id="submitButton" name="create_payment" class="btn btn-primary">ثبت قسط</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentModal = document.getElementById('paymentModal');
            if (paymentModal) {
                paymentModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (!button) return;

                    document.getElementById('idfactor').value = button.getAttribute('data-factor-id') || '';
                    document.getElementById('codemeli').value = button.getAttribute('data-codemeli') || '';
                    document.getElementById('total_amount').value = button.getAttribute('data-total-amount') || '';
                    document.getElementById('comment').value = '';
                });
            }
        });
    </script>
</body>

</html>
