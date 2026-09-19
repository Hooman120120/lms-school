<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

// Blacklist + session checks
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}
if ($_SESSION['admin']['ip'] !== GetIp() || $_SESSION['admin']['os'] !== GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// PDO exceptions
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = $_SESSION['admin']['user'] ?? null;
$payment_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : null;
if ($payment_id === null) {
    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ شناسه پرداخت معتبر نیست.</div>";
    header("Location: payment.php");
    exit;
}

// -----------------------------
// Handle manual refresh (per payment)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_status'])) {
    try {
        // (optional) permission gate — up to you:
        // if (!$canEdit && !$canViewAll) throw new Exception("مجوز کافی ندارید.");

        refreshPaymentStatusFromLastInstallment($conn, (int)$payment_id);
        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>🔄 وضعیت پرداخت با موفقیت به‌روزرسانی شد.</div>";
    } catch (Throwable $e) {
        error_log("Manual refresh error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در به‌روزرسانی وضعیت پرداخت.</div>";
    }
    header("Location: paymentinstallments.php?id={$payment_id}");
    exit;
}

try {
    $permStmt = $conn->prepare("SELECT installment_permission FROM dbadmin WHERE username = :username LIMIT 1");
    $permStmt->execute([':username' => $username]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canViewAll = ($permRow && (int)$permRow['installment_permission'] === 1);
} catch (PDOException $e) {
    error_log("Permission check error: " . $e->getMessage());
    $canViewAll = false;
}

// Fetch payment
try {
    $paymentQuery = "
        SELECT p.*, pr.fname, pr.lname, pr.codemeli, a.fname AS operator_fname, a.lname AS operator_lname, a.username AS operator_username
        FROM payments p
        JOIN dbpersonal pr ON p.codemeli = pr.codemeli
        LEFT JOIN dbadmin a ON p.operator_username = a.username
        WHERE p.id = :payment_id
    ";
    $params = [':payment_id' => $payment_id];
    if (!$canViewAll) {
        $paymentQuery .= " AND p.operator_username = :username";
        $params[':username'] = $username;
    }

    $paymentStmt = $conn->prepare($paymentQuery);
    $paymentStmt->execute($params);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ پرداخت یافت نشد یا شما مجوز دسترسی به آن را ندارید.</div>";
        header("Location: payment.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Payment fetch error: " . $e->getMessage());
    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در دریافت جزئیات پرداخت.</div>";
    header("Location: payment.php");
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, payment_id, operator_username, amount_due, due_date, paid_date, status, comment, created_at
        FROM payment_installments
        WHERE payment_id = :payment_id
        ORDER BY due_date ASC, id ASC
    ");
    $stmt->execute([':payment_id' => $payment_id]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Installments query error: " . $e->getMessage());
    $installments = [];
    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در دریافت لیست اقساط.</div>";
}

$canEdit = false;
if ($canViewAll) {
    $canEdit = true;
} elseif (isset($payment['operator_username']) && $payment['operator_username'] === $username) {
    $canEdit = true;
}

function refreshPaymentStatusFromLastInstallment(PDO $conn, int $payment_id): void {
    // 1) Get the last installment (by due_date then id). Small, indexed, LIMIT 1
    $q1 = $conn->prepare("
        SELECT id, status, due_date
        FROM payment_installments
        WHERE payment_id = :pid
        ORDER BY due_date DESC, id DESC
        LIMIT 1
    ");
    $q1->execute([':pid' => $payment_id]);
    $last = $q1->fetch(PDO::FETCH_ASSOC);

    // 2) If none exist, decide empty-state and update
    if (!$last) {
        $newStatus = 'canceled'; // or 'active' if that's your policy
        $upd = $conn->prepare("UPDATE payments SET status = :st, updated_at = NOW() WHERE id = :pid");
        $upd->execute([':st' => $newStatus, ':pid' => $payment_id]);
        return;
    }

    // 3) Are there any NOT-PAID installments?
    $q2 = $conn->prepare("
        SELECT COUNT(*) 
        FROM payment_installments 
        WHERE payment_id = :pid AND status <> 'paid'
    ");
    $q2->execute([':pid' => $payment_id]);
    $notPaidCount = (int)$q2->fetchColumn();

    // 4) Compute effective status from last installment
    $isOverdueVisual = ($last['status'] === 'pending'
        && !empty($last['due_date'])
        && $last['due_date'] < date('Y-m-d'));

    if ($last['status'] === 'overdue' || $isOverdueVisual) {
        $newStatus = 'overdue';
    } elseif ($last['status'] === 'paid' && $notPaidCount === 0) {
        $newStatus = 'paid';
    } else {
        $newStatus = 'active';
    }

    // 5) Update parent
    $upd = $conn->prepare("UPDATE payments SET status = :st, updated_at = NOW() WHERE id = :pid");
    $upd->execute([':st' => $newStatus, ':pid' => $payment_id]);
}



// -----------------------------
// Handle mark paid
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    try {
        $installment_id = intval($_POST['installment_id'] ?? 0);
        if ($installment_id <= 0) throw new Exception("قسط نامعتبر است.");

        // Fetch installment
        $stmt = $conn->prepare("SELECT * FROM payment_installments WHERE id = :id AND payment_id = :payment_id LIMIT 1");
        $stmt->execute([':id' => $installment_id, ':payment_id' => $payment_id]);
        $installment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$installment) throw new Exception("قسط مورد نظر یافت نشد.");
        if ($installment['status'] === 'paid') throw new Exception("این قسط قبلاً پرداخت شده است.");

        // Permission check
        if (!$canViewAll) {
            $ownerStmt = $conn->prepare("SELECT operator_username FROM payments WHERE id = :payment_id LIMIT 1");
            $ownerStmt->execute([':payment_id' => $payment_id]);
            $owner = $ownerStmt->fetchColumn();
            if ($owner === false) throw new Exception("پرداخت والد یافت نشد.");
            if ($owner !== $username) throw new Exception("شما مجوز علامت‌گذاری این پرداخت را ندارید.");
        }

        // Mark paid
        $upd = $conn->prepare("UPDATE payment_installments SET status = 'paid', paid_date = CURDATE(), updated_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $installment_id]);

        // Update payments.paid_amount
        $upd2 = $conn->prepare("
            UPDATE payments
            SET paid_amount = paid_amount + :amount_due,
                status = IF(paid_amount + :amount_due >= total_amount, 'paid', status),
                updated_at = NOW()
            WHERE id = :payment_id
        ");
        $upd2->execute([':amount_due' => $installment['amount_due'], ':payment_id' => $payment_id]);
        refreshPaymentStatusFromLastInstallment($conn, $payment_id);

        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ قسط با موفقیت پرداخت شد.</div>";
    } catch (PDOException $e) {
        error_log("Mark paid DB error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در علامت‌گذاری پرداخت.</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: paymentinstallments.php?id={$payment_id}");
    exit;
}

// -----------------------------
// Handle add installment
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_installment'])) {
    try {
        if (!$canEdit) throw new Exception("شما مجوز افزودن قسط برای این پرداخت را ندارید.");

        $amount_due = intval($_POST['amount_due'] ?? 0);
        $jalaliDate = trim($_POST['due_date'] ?? '');
        $comment = htmlspecialchars(trim($_POST['comment'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($amount_due <= 0) throw new Exception("مبلغ قسط باید بیشتر از صفر باشد.");

        $parts = preg_split('/[\/\-\.]/', $jalaliDate);
        if (count($parts) !== 3) throw new Exception("تاریخ سررسید معتبر نیست.");
        [$jy, $jm, $jd] = $parts;
        if (!ctype_digit($jy) || !ctype_digit($jm) || !ctype_digit($jd)) throw new Exception("تاریخ سررسید معتبر نیست.");

        $gregorianArr = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
        if (!is_array($gregorianArr) || count($gregorianArr) < 3) throw new Exception("خطا در تبدیل تاریخ.");
        $due_date = sprintf('%04d-%02d-%02d', (int)$gregorianArr[0], (int)$gregorianArr[1], (int)$gregorianArr[2]);

        // Payment totals
        $stmt = $conn->prepare("SELECT total_amount, paid_amount FROM payments WHERE id = :payment_id LIMIT 1");
        $stmt->execute([':payment_id' => $payment_id]);
        $paymentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$paymentRow) throw new Exception("پرداخت والد یافت نشد.");

        $remaining = $paymentRow['total_amount'] - $paymentRow['paid_amount'];
        $sumStmt = $conn->prepare("SELECT COALESCE(SUM(amount_due),0) FROM payment_installments WHERE payment_id = :payment_id AND status != 'canceled'");
        $sumStmt->execute([':payment_id' => $payment_id]);
        $total_installments = (float)$sumStmt->fetchColumn();

        if ($total_installments + $amount_due > $paymentRow['total_amount']) {
            throw new Exception("مجموع اقساط نمی‌تواند از مبلغ کل پرداخت بیشتر باشد.");
        }

        // Insert
        $ins = $conn->prepare("
            INSERT INTO payment_installments
            (payment_id, operator_username, amount_due, due_date, status, comment, created_at, updated_at)
            VALUES (:payment_id, :username, :amount_due, :due_date, 'pending', :comment, NOW(), NOW())
        ");
        $ins->execute([
            ':payment_id' => $payment_id,
            ':username'   => $username,
            ':amount_due' => $amount_due,
            ':due_date'   => $due_date,
            ':comment'    => $comment
        ]);
        refreshPaymentStatusFromLastInstallment($conn, $payment_id);

        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ قسط جدید اضافه شد.</div>";
    } catch (PDOException $e) {
        error_log("Add installment DB error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در افزودن قسط.</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: paymentinstallments.php?id={$payment_id}");
    exit;
}

// -----------------------------
// Handle update installment
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_installment'])) {
    try {
        if (!$canEdit) throw new Exception("شما مجوز ویرایش این قسط را ندارید.");

        $installment_id = intval($_POST['installment_id'] ?? 0);
        $amount_due = intval($_POST['amount_due'] ?? 0);
        $jalaliDate = trim($_POST['due_date'] ?? '');
        $comment = htmlspecialchars(trim($_POST['comment'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($installment_id <= 0) throw new Exception("قسط نامعتبر است.");
        if ($amount_due <= 0) throw new Exception("مبلغ قسط باید بیشتر از صفر باشد.");

        $parts = preg_split('/[\/\-\.]/', $jalaliDate);
        if (count($parts) !== 3) throw new Exception("تاریخ سررسید معتبر نیست.");
        [$jy, $jm, $jd] = $parts;
        $gregorianArr = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
        $due_date = sprintf('%04d-%02d-%02d', (int)$gregorianArr[0], (int)$gregorianArr[1], (int)$gregorianArr[2]);

        $upd = $conn->prepare("
            UPDATE payment_installments
            SET amount_due = :amount_due,
                due_date   = :due_date,
                comment    = :comment,
                updated_at = NOW()
            WHERE id = :id AND payment_id = :payment_id
        ");
        $upd->execute([
            ':amount_due' => $amount_due,
            ':due_date'   => $due_date,
            ':comment'    => $comment,
            ':id'         => $installment_id,
            ':payment_id' => $payment_id
        ]);
        refreshPaymentStatusFromLastInstallment($conn, $payment_id);
        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ قسط با موفقیت ویرایش شد.</div>";
    } catch (PDOException $e) {
        error_log("Update installment DB error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در ویرایش قسط.</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: paymentinstallments.php?id={$payment_id}");
    exit;
}

// -----------------------------
// Handle delete installment
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_installment'])) {
    try {
        if (!$canViewAll) {
            throw new Exception("شما مجوز حذف این قسط را ندارید.");
        }

        $installment_id = intval($_POST['installment_id'] ?? 0);
        if ($installment_id <= 0) {
            throw new Exception("قسط نامعتبر است.");
        }

        // Fetch installment to check status and amount
        $stmt = $conn->prepare("SELECT status, amount_due FROM payment_installments WHERE id = :id AND payment_id = :payment_id LIMIT 1");
        $stmt->execute([':id' => $installment_id, ':payment_id' => $payment_id]);
        $installment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$installment) {
            throw new Exception("قسط مورد نظر یافت نشد.");
        }

        // Prevent deletion of paid installments

        // Delete the installment
        $delete = $conn->prepare("DELETE FROM payment_installments WHERE id = :id AND payment_id = :payment_id");
        $delete->execute([':id' => $installment_id, ':payment_id' => $payment_id]);

// Recalculate total and paid amounts after deletion
        $sumStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(amount_due), 0) AS total_installments,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_due ELSE 0 END), 0) AS total_paid
    FROM payment_installments
    WHERE payment_id = :payment_id
");
        $sumStmt->execute([':payment_id' => $payment_id]);
        $sum = $sumStmt->fetch(PDO::FETCH_ASSOC);

// Update payments table totals
        $updatePaymentTotals = $conn->prepare("
    UPDATE payments
    SET total_amount = :total_installments,
        paid_amount = :total_paid,
        updated_at = NOW()
    WHERE id = :payment_id
");
        $updatePaymentTotals->execute([
            ':total_installments' => $sum['total_installments'],
            ':total_paid' => $sum['total_paid'],
            ':payment_id' => $payment_id
        ]);

// Determine and update new status
        $newStatus = ($sum['total_installments'] == 0)
            ? 'canceled'
            : (($sum['total_paid'] >= $sum['total_installments']) ? 'paid' : 'active');

        $updateStatus = $conn->prepare("
    UPDATE payments
    SET status = :status, updated_at = NOW()
    WHERE id = :payment_id
");
        $updateStatus->execute([':status' => $newStatus, ':payment_id' => $payment_id]);
        refreshPaymentStatusFromLastInstallment($conn, $payment_id);

        $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ قسط با موفقیت حذف شد.</div>";
    } catch (PDOException $e) {
        error_log("Delete installment DB error: " . $e->getMessage());
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در حذف قسط.</div>";
    } catch (Exception $e) {
        $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
    }
    header("Location: paymentinstallments.php?id={$payment_id}");
    exit;
}


?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title> قسط دانش آموز | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .table-overdue { background-color: #ffe5e5 !important; }
        .table-overdue td { background-color: #ffe5e5 !important; }
        th.sortable { cursor: pointer; position: relative; }
        th.sortable::after { content: "⇅"; font-size: 0.7em; margin-right: 5px; opacity: 0.4; }
        th.sortable.asc::after { content: "↑"; opacity: 0.8; }
        th.sortable:not(.asc)::after { content: "↓"; opacity: 0.8; }
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <!-- session messages -->
                <?php
                if (isset($_SESSION['payment_message'])) {
                    echo $_SESSION['payment_message'];
                    unset($_SESSION['payment_message']);
                }
                ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between p-4">
                            <h4 class="card-title">📊 جزئیات پرداخت برای <?= htmlspecialchars($payment['fname'] . ' ' . $payment['lname']) ?> (کدملی: <?= htmlspecialchars($payment['codemeli']) ?>)
                                <?php if ($canViewAll && isset($payment['operator_username']) && $payment['operator_username'] !== $username): ?>
                                    <span class="badge bg-info ms-2">نمایش ادمین</span>
                                <?php endif; ?>
                            </h4>

                            <?php if ($canEdit): ?>
                                <button class="btn" data-bs-toggle="modal" data-bs-target="#addInstallmentModal" style="background-color: deeppink; color: white;">+ افزودن قسط جدید</button>
                            <?php endif; ?>
<!--                            --><?php //if ($canEdit): ?>
<!--                                <form method="POST" style="display:inline;">-->
<!--                                    <button type="submit" name="refresh_status" class="btn btn-outline-secondary">-->
<!--                                        🔄 بروزرسانی وضعیت از آخرین قسط-->
<!--                                    </button>-->
<!--                                </form>-->
<!--                            --><?php //endif; ?>
                        </div>

                        <!-- Payment Summary -->
                        <div class="row text-center mb-4">
                            <div class="col-md-3"><h5>مبلغ کل</h5><p><strong><?= number_format($payment['total_amount']) ?> تومان</strong></p></div>
                            <div class="col-md-3"><h5>پرداخت شده</h5><p><strong><?= number_format($payment['paid_amount']) ?> تومان</strong></p></div>
                            <div class="col-md-3"><h5>باقی مانده</h5><p><strong><?= number_format($payment['total_amount'] - $payment['paid_amount']) ?> تومان</strong></p></div>
                            <div class="col-md-3"><h5>وضعیت</h5><p>
                                    <?php if ($payment['status'] == 'paid'): ?>
                                        <span class="badge bg-success">پرداخت شده</span>
                                    <?php elseif ($payment['status'] == 'overdue'): ?>
                                        <span class="badge bg-danger">معوقه</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">فعال</span>
                                    <?php endif; ?>
                                </p></div>
                            <?php if (!empty($payment['comment'])): ?>
                                <div class="row mt-3"><div class="col"><div class="alert alert-info text-center"><strong>توضیحات: </strong><?= htmlspecialchars($payment['comment'], ENT_QUOTES, 'UTF-8') ?></div></div></div>
                            <?php endif; ?>
                        </div>

                        <!-- Modal -->
                        <div class="modal fade" id="addInstallmentModal" tabindex="-1" aria-labelledby="addInstallmentModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" id="installmentForm">
                                        <input type="hidden" name="installment_id" id="installment_id" value="">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addInstallmentModalLabel">➕ افزودن قسط جدید</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="amount_due" class="form-label">مبلغ قسط (تومان)</label>
                                                <input type="number" name="amount_due" id="amount_due" class="form-control" min="1" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="due_date" class="form-label">تاریخ سررسید <span style="color:red">*</span></label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></div>
                                                    </div>
                                                    <input class="form-control fc-datepicker" placeholder="1402/09/10" required type="text" id="due_date" name="due_date">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">توضیحات</label>
                                                <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                            <button type="submit" name="add_installment" id="formSubmitBtn" class="btn btn-primary">افزودن قسط</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- List of Installments -->
                        <h5 class="mt-4">📋 لیست اقساط</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th class="sortable">مبلغ قسط</th>
                                    <th class="sortable">تاریخ سررسید</th>
                                    <th class="sortable">تاریخ پرداخت</th>
                                    <th class="sortable">وضعیت</th>
                                    <th>توضیحات</th>
                                    <th class="sortable">تاریخ ایجاد</th>
                                    <th>اقدامات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($installments): ?>
                                    <?php $today = new DateTime('today'); ?>
                                    <?php foreach ($installments as $installment):
                                        $rowClass = '';
                                        $dueDate = null;
                                        if (!empty($installment['due_date']) && $installment['due_date'] !== '0000-00-00') {
                                            try {
                                                $dueDate = new DateTime($installment['due_date']);
                                            } catch (Exception $e) {
                                                error_log("Invalid installment due_date for id {$installment['id']}: " . $installment['due_date']);
                                                $dueDate = null;
                                            }
                                        }
                                        if ($installment['status'] === 'pending' && $dueDate instanceof DateTime && $dueDate <= $today) {
                                            $rowClass = 'table-overdue';
                                        }
                                        ?>
                                        <tr class="<?= $rowClass ?>">
                                            <td><?= number_format($installment['amount_due']) ?> تومان</td>
                                            <td><?= jdate("Y/m/d", strtotime($installment['due_date'])) ?></td>
                                            <td><?= $installment['paid_date'] ? jdate("Y/m/d", strtotime($installment['paid_date'])) : '-' ?></td>
                                            <td>
                                                <?php if ($installment['status'] == 'paid'): ?>
                                                    <span class="badge bg-success">پرداخت شده</span>
                                                <?php elseif ($installment['status'] == 'overdue'): ?>
                                                    <span class="badge bg-danger">معوقه</span>
                                                <?php elseif ($installment['status'] == 'pending' && $dueDate instanceof DateTime && $dueDate <= $today): ?>
                                                    <span class="badge bg-danger">سررسید</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">در انتظار</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($installment['comment'] ?? '-') ?></td>
                                            <td><?= jdate("Y/m/d", strtotime($installment['created_at'])) ?></td>
                                            <td>
                                                <?php if ($canEdit): ?>
                                                    <?php if ($installment['status'] != 'paid'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="installment_id" value="<?= htmlspecialchars($installment['id']) ?>">
                                                            <button type="submit" name="mark_paid" class="btn btn-sm btn-success">علامت‌گذاری به عنوان پرداخت شده</button>
                                                        </form>
                                                <?php endif; ?>
                                                        <!-- Update button -->
                                                        <button type="button"
                                                                class="btn btn-sm btn-warning edit-installment"
                                                                data-id="<?= $installment['id'] ?>"
                                                                data-amount="<?= $installment['amount_due'] ?>"
                                                                data-due="<?= $installment['due_date'] ?>"
                                                                data-comment="<?= htmlspecialchars($installment['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#addInstallmentModal">
                                                            ویرایش
                                                        </button>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این قسط را حذف کنید؟');">
                                                            <input type="hidden" name="installment_id" value="<?= htmlspecialchars($installment['id']) ?>">
                                                            <button type="submit" name="delete_installment" class="btn btn-sm btn-danger">حذف</button>
                                                        </form>

                                                <?php else: ?>
                                                    <span class="text-muted">دسترسی ندارد</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7">هیچ قسطی تعریف نشده است.</td></tr>
                                <?php endif; ?>
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
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("installmentForm");
        const installmentIdInput = document.getElementById("installment_id");
        const amountInput = document.getElementById("amount_due");
        const dueInput = document.getElementById("due_date");
        const commentInput = document.getElementById("comment");
        const submitBtn = document.getElementById("formSubmitBtn");
        const modalTitle = document.getElementById("addInstallmentModalLabel");

        // Reset modal for "Add"
        const addTriggers = document.querySelectorAll("[data-bs-target='#addInstallmentModal']");
        addTriggers.forEach(trigger => {
            trigger.addEventListener("click", function() {
                installmentIdInput.value = "";
                form.setAttribute("method", "POST");
                submitBtn.name = "add_installment";
                submitBtn.textContent = "افزودن قسط";
                modalTitle.textContent = "➕ افزودن قسط جدید";
                amountInput.value = "";
                dueInput.value = "";
                commentInput.value = "";
            });
        });

        // Handle "Edit" buttons
        document.querySelectorAll(".edit-installment").forEach(btn => {
            btn.addEventListener("click", function() {
                installmentIdInput.value = this.dataset.id;
                amountInput.value = this.dataset.amount;
                dueInput.value = this.dataset.due;
                commentInput.value = this.dataset.comment;

                submitBtn.name = "update_installment";
                submitBtn.textContent = "ویرایش قسط";
                modalTitle.textContent = "✏️ ویرایش قسط";
            });
        });

        // Sorting headers
        document.querySelectorAll("th.sortable").forEach(header => {
            header.addEventListener("click", () => {
                const table = header.closest("table");
                const tbody = table.querySelector("tbody");
                const rows = Array.from(tbody.querySelectorAll("tr"));
                const index = Array.from(header.parentNode.children).indexOf(header);
                const isAscending = header.classList.toggle("asc");

                rows.sort((a, b) => {
                    const cellA = a.children[index].innerText.trim();
                    const cellB = b.children[index].innerText.trim();
                    const numA = parseFloat(cellA.replace(/,/g, ""));
                    const numB = parseFloat(cellB.replace(/,/g, ""));
                    if (!isNaN(numA) && !isNaN(numB)) return isAscending ? numA - numB : numB - numA;
                    return isAscending ? cellA.localeCompare(cellB, 'fa') : cellB.localeCompare(cellA, 'fa');
                });

                rows.forEach(row => tbody.appendChild(row));
            });
        });
    });
</script>
</body>
</html>