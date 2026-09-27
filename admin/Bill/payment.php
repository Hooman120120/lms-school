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
if (AdminHasAnyCurrentTreePermission()
    ? !AdminHasMenuNavigationPermission('finance.installments')
    : !Permision('permision_viewbill')) {
    http_response_code(403); exit('مجوز دسترسی به بخش اقساط برای شما تعریف نشده است.');
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
$canCreateInstallments = AdminCanUsePermissionScope('finance.installments.create', ['permision_viewbill']);
$installmentScope = AdminRecordVisibility('finance.installments', $canViewAll);
if ($installmentScope === null && $canCreateInstallments) $installmentScope = 'own';
if ($installmentScope === null) {
    http_response_code(403); exit('سطح مشاهدهٔ پرونده‌های قسط برای شما فعال نشده است.');
}
$canViewAll = $installmentScope === 'all';
$canEditInstallments = !AdminHasAnyCurrentTreePermission()
    || AdminHasMenuPermission('finance.installments.edit');
list($jalaliStart, $jalaliEnd) = getJalaliYearWindowApprox();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['create_payment']) && !$canEditInstallments) {
            throw new Exception('مجوز ویرایش پرونده‌های قسط فعال نشده است.');
        }
        if (isset($_POST['create_payment'])) {
            if (!$canCreateInstallments) throw new Exception('مجوز افزودن قسط فعال نشده است.');
            $codemeli = trim($_POST['codemeli']);
            $total_amount = intval($_POST['total_amount']);
            $comment = trim($_POST['comment']);

            // Validate inputs
            if (empty($codemeli) || !preg_match('/^\d{10}$/', $codemeli)) {
                throw new Exception("کدملی باید ۱۰ رقم باشد.");
            }
            if ($total_amount <= 0) {
                throw new Exception("مبلغ کل باید بیشتر از صفر باشد.");
            }

            // Check student existence
            $checkStudent = $conn->prepare("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = :codemeli");
            $checkStudent->execute(['codemeli' => $codemeli]);
            $studentExists = $checkStudent->fetchColumn();

            if (!$studentExists) {
                throw new Exception("کدملی وارد شده در سیستم وجود ندارد.");
            }

                // Insert new payment
                $insert = "
                    INSERT INTO payments (codemeli, operator_username, total_amount, paid_amount, status, comment, created_at, updated_at)
                    VALUES (:codemeli, :username, :total_amount, 0, 'active', :comment, NOW(), NOW())
                ";
                $stmt = $conn->prepare($insert);
                $stmt->execute([
                    'codemeli' => $codemeli,
                    'username' => $username,
                    'total_amount' => $total_amount,
                    'comment' => $comment
                ]);

                $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>پرونده قسط با موفقیت ثبت شد.</div>";

        } elseif (isset($_POST['update_payment'])) {
            $codemeli = trim($_POST['codemeli']);
            $total_amount = intval($_POST['total_amount']);
            $comment = trim($_POST['comment']);
            $payment_id = intval($_POST['payment_id']);

            // Validate inputs
            if (empty($codemeli) || !preg_match('/^\d{10}$/', $codemeli)) {
                throw new Exception("کدملی باید ۱۰ رقم باشد.");
            }
            if ($total_amount <= 0) {
                throw new Exception("مبلغ کل باید بیشتر از صفر باشد.");
            }

            // Check student existence
            $checkStudent = $conn->prepare("SELECT COUNT(*) FROM dbpersonal WHERE codemeli = :codemeli");
            $checkStudent->execute(['codemeli' => $codemeli]);
            $studentExists = $checkStudent->fetchColumn();

            if (!$studentExists) {
                throw new Exception("کدملی وارد شده در سیستم وجود ندارد.");
            }

            $conn->beginTransaction();

            try {
                if ($canViewAll) {
                    // Admin update with status
                    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
                    $valid_statuses = ['active', 'paid', 'overdue', 'canceled', 'refund'];
                    if (!in_array($status, $valid_statuses)) {
                        $status = 'active';
                    }

                    $update = "
                        UPDATE payments 
                        SET codemeli = :codemeli, 
                            total_amount = :total_amount, 
                            status = :status, 
                            comment = :comment, 
                            updated_at = NOW()
                        WHERE id = :payment_id
                    ";
                    $stmt = $conn->prepare($update);
                    $stmt->execute([
                        'codemeli' => $codemeli,
                        'total_amount' => $total_amount,
                        'status' => $status,
                        'comment' => $comment,
                        'payment_id' => $payment_id
                    ]);

                    // Cascade status to installments
                    if ($status === 'refund') {
                        $updateInst = "UPDATE payment_installments SET status = 'refund' WHERE payment_id = :payment_id";
                        $conn->prepare($updateInst)->execute(['payment_id' => $payment_id]);
                        $conn->prepare("UPDATE payments SET paid_amount = 0 WHERE id = :payment_id")->execute(['payment_id' => $payment_id]);
                    } elseif ($status === 'canceled') {
                        $updateInst = "
                            UPDATE payment_installments 
                            SET status = 'canceled' 
                            WHERE payment_id = :payment_id 
                            AND status IN ('pending', 'overdue')
                        ";
                        $conn->prepare($updateInst)->execute(['payment_id' => $payment_id]);
                    }
                } else {
                    // Normal user update (no status change)
                    $update = "
                        UPDATE payments 
                        SET codemeli = :codemeli, 
                            total_amount = :total_amount, 
                            comment = :comment, 
                            updated_at = NOW()
                        WHERE id = :payment_id AND operator_username = :username
                    ";
                    $stmt = $conn->prepare($update);
                    $stmt->execute([
                        'codemeli' => $codemeli,
                        'total_amount' => $total_amount,
                        'comment' => $comment,
                        'payment_id' => $payment_id,
                        'username' => $username
                    ]);

                    if ($stmt->rowCount() === 0) {
                        $checkOwner = $conn->prepare("SELECT id FROM payments WHERE id = :id AND operator_username = :username");
                        $checkOwner->execute(['id' => $payment_id, 'username' => $username]);
                        if (!$checkOwner->fetch()) {
                            throw new Exception("شما اجازه ویرایش این پرداخت را ندارید.");
                        }
                    }
                }

                $conn->commit();
                $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ پرداخت با موفقیت ویرایش شد.</div>";

            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } elseif (isset($_POST['delete_payment'])) {
            $payment_id = intval($_POST['payment_id']);

            if ($payment_id > 0) {
                try {
                    $conn->prepare("DELETE FROM payment_installments WHERE payment_id = :payment_id")
                        ->execute(['payment_id' => $payment_id]);

                    $conn->prepare("DELETE FROM payments WHERE id = :payment_id")
                        ->execute(['payment_id' => $payment_id]);

                    $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'> پرداخت با موفقیت حذف شد.</div>";
                } catch (PDOException $e) {
                    error_log("Force delete error: " . $e->getMessage());
                    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'> خطا در حذف پرداخت.</div>";
                }
            } else {
                $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'> شناسه پرداخت نامعتبر است.</div>";
            }
        } else {
            throw new Exception("درخواست نامعتبر است.");
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در پایگاه داده: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: payment.php");
    exit;
}
// Fetch statistics
try {
    if ($canViewAll) {
        $statsQuery = "
            SELECT 
              COALESCE(SUM(
                CASE 
                  WHEN p.status = 'refund' THEN 0
                  WHEN p.status = 'canceled' THEN COALESCE(p.paid_amount, 0)
                  ELSE COALESCE(p.total_amount, 0)
                END
              ), 0) AS total_debt,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status = 'refund' THEN 0
                  ELSE COALESCE(p.paid_amount, 0)
                END
              ), 0) AS total_paid,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status IN ('refund', 'canceled') THEN 0
                  ELSE (COALESCE(p.total_amount, 0) - COALESCE(p.paid_amount, 0))
                END
              ), 0) AS total_balance,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status NOT IN ('canceled', 'refund') AND EXISTS (
                    SELECT 1 
                    FROM payment_installments i
                    WHERE i.payment_id = p.id
                      AND (
                            (i.status = 'pending' AND i.due_date < CURDATE())
                         OR  i.status = 'overdue'
                          )
                  )
                  THEN 1 ELSE 0
                END
              ), 0) AS overdue_count
            FROM payments p
            WHERE p.created_at >= :jalali_start
              AND p.created_at <  :jalali_end
        ";
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute([
            'jalali_start' => $jalaliStart,
            'jalali_end'   => $jalaliEnd
        ]);
    } else {
        $statsQuery = "
            SELECT 
              COALESCE(SUM(
                CASE 
                  WHEN p.status = 'refund' THEN 0
                  WHEN p.status = 'canceled' THEN COALESCE(p.paid_amount, 0)
                  ELSE COALESCE(p.total_amount, 0)
                END
              ), 0) AS total_debt,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status = 'refund' THEN 0
                  ELSE COALESCE(p.paid_amount, 0)
                END
              ), 0) AS total_paid,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status IN ('refund', 'canceled') THEN 0
                  ELSE (COALESCE(p.total_amount, 0) - COALESCE(p.paid_amount, 0))
                END
              ), 0) AS total_balance,
              
              COALESCE(SUM(
                CASE 
                  WHEN p.status NOT IN ('canceled', 'refund') AND EXISTS (
                    SELECT 1 
                    FROM payment_installments i
                    WHERE i.payment_id = p.id
                      AND (
                            (i.status = 'pending' AND i.due_date < CURDATE())
                         OR  i.status = 'overdue'
                          )
                  )
                  THEN 1 ELSE 0
                END
              ), 0) AS overdue_count
            FROM payments p
            WHERE p.operator_username = :username
              AND p.created_at >= :jalali_start
              AND p.created_at <  :jalali_end
        ";
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute([
            ':username'    => $username,
            'jalali_start' => $jalaliStart,
            'jalali_end'   => $jalaliEnd
        ]);
    }

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    error_log("Stats query error: " . $e->getMessage());
    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در دریافت آمار: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    $stats = ['total_debt' => 0, 'total_paid' => 0, 'total_balance' => 0, 'overdue_count' => 0];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title> موارد قسطی </title>
    <?php include_once '../link.php'; ?>

    <style>
        th.sortable {
            cursor: pointer;
            position: relative;
        }
        th.sortable.asc::after {
            content: " ▲";
            font-size: 0.8em;
        }
        th.sortable.desc::after {
            content: " ▼";
            font-size: 0.8em;
        }
        .table-overdue {
            background-color: #f8d7da !important;
        }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <?php
                if (isset($_SESSION['payment_message'])) {
                    echo $_SESSION['payment_message'];
                    unset($_SESSION['payment_message']);
                }
                ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between p-4">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <h4 class="card-title mb-0">داشبورد</h4>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید وضعیت کلی پرداخت‌های اقساطی را مدیریت و بررسی کنید. اطلاعاتی مانند مجموع فروش اقساطی، میزان پرداخت‌شده، مبلغ باقی‌مانده و تعداد اقساط معوق نمایش داده می‌شود. همچنین امکان ثبت پرداخت جدید، مدیریت وضعیت پرداخت‌ها و مشاهده لیست بازگشت وجه از طریق این داشبورد فراهم است."
                                      aria-label="راهنمای داشبورد حسابداری"
                                      onclick="event.preventDefault(); event.stopPropagation();">

                                    <i class="fe fe-info fs-4"></i>
                                </span>
                            </div>
                            <div class="d-flex flex-row flex-wrap gap-2">
                                <?php if ($canCreateInstallments): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal" data-bs-action="create">+ افزودن قسط</button>
                                <?php endif; ?>
                                <a class="badge bg-info rounded-pill px-3 py-2 " style="height: 30px; font-size: 12px;" href="<?php echo url('admin/Bill/payment-history.php') ?>">لیست بازگشت وجه</a>
                                <a class="badge bg-warning rounded-pill px-3 py-2 " style="height: 30px; font-size: 12px;" href="<?php echo url('admin/Bill/payment-archive.php') ?>">آرشیو</a>
                            </div>
                        </div>

                        <!-- Create/Update Payment Modal -->
                        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="paymentForm" method="POST" action="payment.php">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="paymentModalLabel">ویرایش پرداخت</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="payment_id" id="payment_id">
                                            <!-- کدملی -->
                                            <div class="mb-3">
                                                <label for="codemeli" class="form-label">کدملی دانشجو</label>
                                                <input type="text" name="codemeli" id="codemeli" class="form-control" required pattern="\d{10}" title="کدملی باید ۱۰ رقم باشد">
                                            </div>
                                            <?php     if ($perm && (int)$perm['installment_permission'] === 1) {?>
                                                <div class="mb-3" id="status_div" style="display: none;">
                                                    <label for="status" class="form-label">وضعیت پرداخت</label>
                                                    <select name="status" id="status" class="form-select">
                                                        <option value="active">فعال</option>
                                                        <option value="paid">پرداخت شده</option>
                                                        <option value="overdue">معوقه</option>
                                                        <option value="canceled">انصراف </option>
                                                        <option value="refund">بازگشت وجه</option>
                                                    </select>
                                                    <small class="text-danger d-block mt-1" style="font-size: 0.8em;">هشدار! لطفا قبل از انتخاب بازگشت وجه دقت فرمایید.</small>
                                                </div>
                                            <?php } ?>

                                            <!-- مبلغ کل -->
                                            <div class="mb-3">
                                                <label for="total_amount" class="form-label">مبلغ کل (تومان)</label>
                                                <input type="number" name="total_amount" id="total_amount" class="form-control" min="1" required>
                                            </div>
                                            <!-- توضیحات -->
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">توضیحات</label>
                                                <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                            <button type="submit" id="submitButton" name="update_payment" class="btn btn-primary">به روز رسانی</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Display statistics -->
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h5>کل فروش اقساطی</h5>
                                <p><strong><?= number_format($stats['total_debt']) ?> تومان</strong></p>
                            </div>
                            <div class="col-md-3">
                                <h5>کل پرداخت شده</h5>
                                <p><strong><?= number_format($stats['total_paid']) ?> تومان</strong></p>
                            </div>
                            <div class="col-md-3">
                                <h5>کل باقی مانده</h5>
                                <p><strong><?= number_format($stats['total_debt'] - $stats['total_paid']) ?> تومان</strong></p>
                            </div>
                            <div class="col-md-3">
                                <h5>تعداد معوقه‌ها</h5>
                                <p><strong><?= $stats['overdue_count'] ?></strong></p>
                            </div>
                        </div>

                        <!-- Payment list -->
                        <div style="display:flex; align-items:center; gap:12px;">
                            <h5 class="mt-4">پرداخت‌های من</h5>

                            <span class="menu-info-trigger"
                                  data-tooltip="در این بخش لیست پرداخت‌های اقساطی ثبت‌شده نمایش داده می‌شود. می‌توانید وضعیت هر پرداخت، مبلغ پرداختی، تاریخ پرداخت و جزئیات مربوط به اقساط دانش آموز را مشاهده و پیگیری کنید."
                                  aria-label="راهنمای پرداخت‌های من"
                                  onclick="event.preventDefault(); event.stopPropagation();">
                                <i class="fe fe-info fs-4"></i>
                            </span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                <thead class="table-light">
                                <tr>
                                    <th class="sortable">#</th>
                                    <th class="sortable">دانش آموز</th>
                                    <th class="sortable">کدملی</th>
                                    <th class="sortable">مبلغ کل</th>
                                    <th class="sortable">پرداخت شده</th>
                                    <th class="sortable">باقی مانده</th>
                                    <th class="sortable">وضعیت</th>
                                    <th class="sortable">تاریخ ایجاد</th>
                                    <th class="sortable">شماره فاکتور</th>
                                    <?php if ($canViewAll): ?>
                                        <th class="sortable">ثبت‌کننده</th>
                                    <?php endif; ?>
                                    <th>اقدامات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                try {
                                    $query = "
                                            SELECT p.*,
                                                   dp.fname, 
                                                   dp.lname,
                                                   CASE 
                                                       WHEN p.status IN ('refund', 'canceled') THEN 0 
                                                       ELSE (p.total_amount - p.paid_amount) 
                                                   END AS balance,
                                                   CASE 
                                                       WHEN p.status NOT IN ('canceled', 'refund') AND EXISTS (
                                                           SELECT 1 FROM payment_installments i 
                                                           WHERE i.payment_id = p.id 
                                                           AND ((i.status = 'pending' AND i.due_date < CURDATE()) OR i.status = 'overdue')
                                                       ) THEN 1 ELSE 0 
                                                   END AS has_overdue
                                            FROM payments p
                                            LEFT JOIN dbpersonal dp ON p.codemeli = dp.codemeli
                                            WHERE 1=1 
                                            AND p.status != 'refund'
                                        ";

                                    if (!$canViewAll) {
                                        $query .= " AND p.operator_username = :username";
                                    }

                                    $query .= " AND p.created_at >= :jalali_start AND p.created_at < :jalali_end";

                                    $query .= " ORDER BY p.created_at DESC";

                                    $stmt = $conn->prepare($query);

                                    if (!$canViewAll) {
                                        $stmt->execute([
                                            'username'     => $username,
                                            'jalali_start' => $jalaliStart,
                                            'jalali_end'   => $jalaliEnd
                                        ]);
                                    } else {
                                        $stmt->execute([
                                            'jalali_start' => $jalaliStart,
                                            'jalali_end'   => $jalaliEnd
                                        ]);
                                    }

                                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if ($rows) {
                                        foreach ($rows as $row) {
                                            $rowClass = $row['has_overdue'] ? 'table-overdue' : '';
                                            ?>
                                            <tr class="<?= $rowClass ?>">
                                                <td>
                                                    <div class="btn btn-sm ripple btn-info btn-icon"
                                                         onclick="window.open('../panel/suporting.php?Id=<?= htmlspecialchars($row['codemeli']) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                        <i class="fa fa-key"></i>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['codemeli'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format($row['total_amount']) ?> تومان</td>
                                                <td><?= number_format($row['paid_amount']) ?> تومان</td>
                                                <td><?= number_format($row['balance']) ?> تومان</td>
                                                <td>
                                                    <?php if ($row['status'] === 'paid'): ?>
                                                        <span class="badge bg-success">پرداخت شده</span>
                                                    <?php elseif ($row['status'] === 'canceled'): ?>
                                                        <span class="badge bg-secondary">انصراف</span>
                                                    <?php elseif ($row['status'] === 'refund'): ?>
                                                        <span class="badge bg-dark">بازگشت وجه</span>
                                                    <?php elseif ($row['status'] === 'overdue' || $row['has_overdue']): ?>
                                                        <span class="badge bg-danger">معوقه</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">فعال</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= jdate("Y/m/d", strtotime($row['created_at'])) ?></td>
                                                <td>
                                                    <?php if (!empty($row['idfactor'])): ?>
                                                        <?= htmlspecialchars($row['idfactor'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($canViewAll): ?>
                                                    <td><?= htmlspecialchars($row['operator_username'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <?php endif; ?>

                                                <td>
                                                    <a href="paymentinstallments.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                       class="btn btn-sm btn-primary">جزئیات</a>
                                                    <a href="viewfactor.php?id=<?= htmlspecialchars($row['idfactor'], ENT_QUOTES, 'UTF-8') ?>"
                                                       class="btn btn-sm btn-info"
                                                        <?= empty($row['idfactor']) ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>
                                                       onclick="window.open(this.href, 'FactorPopup', 'width=600,height=700,scrollbars=yes,resizable=yes'); return false;">
                                                        فاکتور
                                                    </a>
                                                    <?php if ($canEditInstallments): ?><button type="button" class="btn btn-sm btn-warning"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#paymentModal"
                                                            data-bs-action="update"
                                                            data-payment-id="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-codemeli="<?= htmlspecialchars($row['codemeli'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-total-amount="<?= htmlspecialchars($row['total_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-comment="<?= htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8') ?>">
                                                        ویرایش
                                                    </button><?php endif; ?>
                                                    <?php if ($canViewAll && $canEditInstallments): ?>
                                                        <form action="payment.php" method="POST" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این پرداخت را حذف کنید؟');">
                                                            <input type="hidden" name="payment_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" name="delete_payment" class="btn btn-sm btn-danger">حذف</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php }

                                    } else {
                                        $colCount = $canViewAll ? 11 : 10;
                                        echo '<tr><td colspan="' . $colCount . '">هیچ پرداختی ثبت نشده است.</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    error_log("Payment list query error: " . $e->getMessage());
                                    $colCount = $canViewAll ? 11 : 10;
                                    echo '<tr><td colspan="' . $colCount . '">⚠ خطا در بارگذاری لیست پرداخت‌ها.</td></tr>';
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

    <?php include '../../footers.php'; ?>
</div>
<script>
    // --- Table Sorting Logic ---
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("th.sortable").forEach(function (header, index) {
            header.addEventListener("click", function () {
                const table = header.closest("table");
                const tbody = table.querySelector("tbody");
                const rows = Array.from(tbody.querySelectorAll("tr"));
                const order = header.dataset.order === "asc" ? "desc" : "asc";

                rows.sort((a, b) => {
                    let cellA = a.cells[index].innerText.trim();
                    let cellB = b.cells[index].innerText.trim();

                    // Remove commas and 'تومان' for currency sorting
                    let valA = cellA.replace(/,/g, '').replace(' تومان', '');
                    let valB = cellB.replace(/,/g, '').replace(' تومان', '');

                    if (!isNaN(valA) && !isNaN(valB) && valA !== "" && valB !== "") {
                        return order === "asc" ? Number(valA) - Number(valB) : Number(valB) - Number(valA);
                    }
                    return order === "asc"
                        ? cellA.localeCompare(cellB, "fa")
                        : cellB.localeCompare(cellA, "fa");
                });

                rows.forEach(row => tbody.appendChild(row));

                header.dataset.order = order;
                table.querySelectorAll("th.sortable").forEach(h => h.classList.remove("asc", "desc"));
                header.classList.add(order);
            });
        });
    });
    // --- Modal Logic (Update Toggle) ---
    document.addEventListener('DOMContentLoaded', function () {
        const paymentModal = document.getElementById('paymentModal');
        const modalTitle = document.getElementById('paymentModalLabel');
        const submitButton = document.getElementById('submitButton');
        const statusDiv = document.getElementById('status_div');
        const statusSelect = document.getElementById('status');

        if (paymentModal) {
            paymentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button || button.getAttribute('data-bs-action') !== 'update') {
                    document.getElementById('paymentForm').reset();
                    document.getElementById('payment_id').value = '';
                    modalTitle.textContent = 'افزودن قسط';
                    submitButton.textContent = 'ثبت قسط';
                    submitButton.name = 'create_payment';
                    if (statusDiv && statusSelect) {
                        statusDiv.style.display = 'none';
                        statusSelect.value = 'active';
                    }
                    return;
                }

                // Set update mode
                modalTitle.textContent = '✏️ ویرایش پرداخت';
                submitButton.textContent = 'به‌روزرسانی پرداخت';
                submitButton.name = 'update_payment';

                // Get data from button
                const paymentId = button.getAttribute('data-payment-id');
                const codemeli = button.getAttribute('data-codemeli');
                const totalAmount = button.getAttribute('data-total-amount');
                const comment = button.getAttribute('data-comment') || '';
                const status = button.getAttribute('data-status') || 'active';

                // Populate fields
                document.getElementById('payment_id').value = paymentId;
                document.getElementById('codemeli').value = codemeli;
                document.getElementById('total_amount').value = totalAmount;
                document.getElementById('comment').value = comment;

                // Show status dropdown for admins
                if (statusDiv && statusSelect) {
                    statusDiv.style.display = 'block';
                    statusSelect.value = status;
                }
            });
        }
    });</script>
</body>
</html>
