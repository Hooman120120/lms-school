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
$username = $_SESSION['admin']['user'];

$canViewAll = false;

try {
    if (AdminScope('finance.installments') === 'all') {
        $canViewAll = true;
    }
} catch (PDOException $e) {
    error_log("Permission check failed: " . $e->getMessage());
}

// Handle payment form submission (create, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminRequire('finance.installments.history.manage');
    try {
        if (isset($_POST['create_payment']) || isset($_POST['update_payment'])) {
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

            if (isset($_POST['create_payment'])) {
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

                $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ پرداخت جدید با موفقیت ثبت شد.</div>";

            } elseif (isset($_POST['update_payment'])) {
                $payment_id = intval($_POST['payment_id']);
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
                $valid_statuses = ['active', 'paid', 'overdue', 'canceled', 'refund'];
                if (!in_array($status, $valid_statuses)) {
                    $status = 'active';
                }

                $conn->beginTransaction(); // Start Transaction

                try {
                    // 1. Modify the UPDATE query to include status
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

                    // 2. Cascade status to installments
                    if ($status === 'refund') {
                        // Update ALL installments to 'refund'
                        $updateInst = "UPDATE payment_installments SET status = 'refund' WHERE payment_id = :payment_id";
                        $conn->prepare($updateInst)->execute(['payment_id' => $payment_id]);

                        // Reset paid_amount to 0 for strict refund accounting
                        $conn->prepare("UPDATE payments SET paid_amount = 0 WHERE id = :payment_id")->execute(['payment_id' => $payment_id]);

                    } elseif ($status === 'canceled') {
                        // Update ONLY pending or overdue installments to 'canceled'
                        $updateInst = "
                            UPDATE payment_installments 
                            SET status = 'canceled' 
                            WHERE payment_id = :payment_id 
                            AND status IN ('pending', 'overdue')
                        ";
                        $conn->prepare($updateInst)->execute(['payment_id' => $payment_id]);
                    }

                    $conn->commit(); // Commit Transaction
                    $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ پرداخت با موفقیت ویرایش شد.</div>";

                } catch (Exception $e) {
                    $conn->rollBack(); // Rollback if anything fails
                    throw $e;
                }
            }
        } elseif (isset($_POST['delete_payment'])) {
            $payment_id = intval($_POST['payment_id']);

            if ($payment_id > 0) {
                try {
                    // Optional: also delete related installments (to prevent orphaned rows)
                    $conn->prepare("DELETE FROM payment_installments WHERE payment_id = :payment_id")
                        ->execute(['payment_id' => $payment_id]);

                    // Delete payment itself
                    $conn->prepare("DELETE FROM payments WHERE id = :payment_id")
                        ->execute(['payment_id' => $payment_id]);

                    $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ پرداخت با موفقیت حذف شد.</div>";
                } catch (PDOException $e) {
                    error_log("Force delete error: " . $e->getMessage());
                    $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ خطا در حذف پرداخت.</div>";
                }
            } else {
                $_SESSION['payment_message'] = "<div class='alert alert-danger mt-3'>⚠ شناسه پرداخت نامعتبر است.</div>";
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
        // Superadmin: stats for ALL payments (no username filter)
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
            WHERE 1=1
        ";
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute();
    } else {
        // Normal operator: only their own payments
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
        ";
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute([':username' => $username]);
    }

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
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
            background-color: #f8d7da !important; /* Bootstrap soft red */
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
                <!-- Display session messages -->
                <?php
                if (isset($_SESSION['payment_message'])) {
                    echo $_SESSION['payment_message'];
                    unset($_SESSION['payment_message']);
                }
                ?>

                <div class="card mb-3">
                    <div class="card-body">

                        <!-- Payment list -->
                        <h5 class="mt-4">لیست اقساط بازگشت وجه خورده </h5>
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
                                        WHERE p.status = 'refund' 

                                    ";

                                    if (!$canViewAll) {
                                        $query .= " AND p.operator_username = :username";
                                    }

                                    $query .= " ORDER BY p.created_at DESC";

                                    $stmt = $conn->prepare($query);
                                    $params = $canViewAll ? [] : ['username' => $username];
                                    $stmt->execute($params);

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
                                                        <span class="badge bg-secondary">کنسل شده</span>
                                                    <?php elseif ($row['status'] === 'refund'): ?>
                                                        <span class="badge bg-dark">بازگشت وجه</span>
                                                    <?php elseif ($row['status'] === 'overdue' || $row['has_overdue']): ?>
                                                        <span class="badge bg-danger">معوقه</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">فعال</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= jdate("Y/m/d", strtotime($row['created_at'])) ?></td>

                                                <?php if ($canViewAll): ?>
                                                    <td><?= htmlspecialchars($row['operator_username'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <?php endif; ?>

                                                <td>
                                                    <a href="paymentinstallments.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                       class="btn btn-sm btn-primary">جزئیات</a>
                                                </td>
                                            </tr>
                                        <?php }

                                    } else {
                                        $colCount = $canViewAll ? 10 : 9;
                                        echo '<tr><td colspan="' . $colCount . '">هیچ پرداختی ثبت نشده است.</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    error_log("Payment list query error: " . $e->getMessage());
                                    $colCount = $canViewAll ? 10 : 9;
                                    echo '<tr><td colspan="' . $colCount . '">⚠ خطا در بارگذاری لیست پرداخت‌ها: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</td></tr>';
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

    // --- Modal Logic (Create/Update Toggle) ---
    document.addEventListener('DOMContentLoaded', function () {
        const paymentModal = document.getElementById('paymentModal');
        const paymentForm = document.getElementById('paymentForm');
        const modalTitle = document.getElementById('paymentModalLabel');
        const submitButton = document.getElementById('submitButton');
        const statusDiv = document.getElementById('status_div');
        const statusSelect = document.getElementById('status');

        if (paymentModal) {
            paymentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal

                // If button doesn't have data-bs-action, it means it's the "New Payment" button at the top
                const action = button.getAttribute('data-bs-action');
                const isUpdate = action === 'update';

                if (isUpdate) {
                    // --- UPDATE MODE ---
                    modalTitle.textContent = '✏️ ویرایش پرداخت';
                    submitButton.textContent = 'به‌روزرسانی پرداخت';
                    submitButton.name = 'update_payment';
                    paymentForm.action = 'payment.php';

                    // Get payment data from button attributes
                    const paymentId = button.getAttribute('data-payment-id');
                    const codemeli = button.getAttribute('data-codemeli');
                    const totalAmount = button.getAttribute('data-total-amount');
                    const comment = button.getAttribute('data-comment') || '';
                    const status = button.getAttribute('data-status') || 'active';

                    // Populate form fields
                    document.getElementById('payment_id').value = paymentId;
                    document.getElementById('codemeli').value = codemeli;
                    document.getElementById('total_amount').value = totalAmount;
                    document.getElementById('comment').value = comment;

                    // Show Status Dropdown and set value
                    statusDiv.style.display = 'block';
                    statusSelect.value = status;
                } else {
                    // --- CREATE MODE ---
                    modalTitle.textContent = '➕ ثبت پرداخت جدید';
                    submitButton.textContent = 'ثبت پرداخت';
                    submitButton.name = 'create_payment';
                    paymentForm.action = 'payment.php';

                    // Clear form fields
                    paymentForm.reset();
                    document.getElementById('payment_id').value = '';

                    // Hide Status Dropdown and reset to default
                    statusDiv.style.display = 'none';
                    statusSelect.value = 'active';
                }
            });
        }
    });
</script>
</body>
</html>
