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
    $stmt = $conn->prepare("SELECT installment_permission FROM dbadmin WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($perm && (int)$perm['installment_permission'] === 1) {
        $canViewAll = true;
    }
} catch (PDOException $e) {
    error_log("Permission check failed: " . $e->getMessage());
}
// Handle payment form submission (create, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                // Update existing payment
                $payment_id = intval($_POST['payment_id']);

                if ($payment_id <= 0) {
                    throw new Exception("شناسه پرداخت معتبر نیست.");
                }

                // Check if payment exists and belongs to the user (if not superadmin)
                $checkPaymentQuery = "SELECT COUNT(*) FROM payments WHERE id = :payment_id";
                if (!$canViewAll) {
                    $checkPaymentQuery .= " AND operator_username = :username";
                }
                $checkPayment = $conn->prepare($checkPaymentQuery);
                $params = ['payment_id' => $payment_id];
                if (!$canViewAll) {
                    $params['username'] = $username;
                }
                $checkPayment->execute($params);
                $paymentExists = $checkPayment->fetchColumn();

                if (!$paymentExists) {
                    throw new Exception("پرداخت مورد نظر یافت نشد یا شما اجازه ویرایش آن را ندارید.");
                }

                // Update payment
                $update = "
                    UPDATE payments 
                    SET codemeli = :codemeli, 
                        total_amount = :total_amount, 
                        comment = :comment, 
                        updated_at = NOW()
                    WHERE id = :payment_id
                ";
                $stmt = $conn->prepare($update);
                $stmt->execute([
                    'codemeli' => $codemeli,
                    'total_amount' => $total_amount,
                    'comment' => $comment,
                    'payment_id' => $payment_id
                ]);

                $_SESSION['payment_message'] = "<div class='alert alert-success mt-3'>✅ پرداخت با موفقیت به‌روزرسانی شد.</div>";
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
        }
        else {
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


// Fetch statistics
try {
    if ($canViewAll) {
        // Superadmin: stats for ALL payments (no username filter)
        $statsQuery = "
            SELECT 
              COALESCE(SUM(COALESCE(p.total_amount,0)), 0)                           AS total_debt,
              COALESCE(SUM(COALESCE(p.paid_amount,0)), 0)                             AS total_paid,
              COALESCE(SUM(COALESCE(p.total_amount,0) - COALESCE(p.paid_amount,0)), 0) AS total_balance,
              COALESCE(SUM(
                CASE 
                  WHEN EXISTS (
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
        ";
        $stmt = $conn->prepare($statsQuery);
        $stmt->execute(); // no params
    } else {
        // Normal operator: only their own payments
        $statsQuery = "
            SELECT 
              COALESCE(SUM(COALESCE(p.total_amount,0)), 0)                           AS total_debt,
              COALESCE(SUM(COALESCE(p.paid_amount,0)), 0)                             AS total_paid,
              COALESCE(SUM(COALESCE(p.total_amount,0) - COALESCE(p.paid_amount,0)), 0) AS total_balance,
              COALESCE(SUM(
                CASE 
                  WHEN EXISTS (
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
        $stmt->execute([':username' => $username]);  // bind param
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
                        <div class="d-flex justify-content-between p-4">
                            <h4 class="card-title">📊 داشبورد </h4>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">➕ ثبت پرداخت جدید</button>                            </div>

                        <!-- Create Payment Modal -->
                        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="paymentForm" method="POST" action="payment.php">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="paymentModalLabel">➕ ثبت پرداخت جدید</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="payment_id" id="payment_id">
                                            <!-- کدملی -->
                                            <div class="mb-3">
                                                <label for="codemeli" class="form-label">کدملی دانشجو</label>
                                                <input type="text" name="codemeli" id="codemeli" class="form-control" required pattern="\d{10}" title="کدملی باید ۱۰ رقم باشد">
                                            </div>
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
                                            <button type="submit" id="submitButton" name="create_payment" class="btn btn-primary">ثبت پرداخت</button>
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
                        <h5 class="mt-4">📋 پرداخت‌های من</h5>
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
                                    $query = "SELECT p.id, p.total_amount, p.paid_amount,  p.comment,
                                               (p.total_amount - p.paid_amount) AS balance,
                                               p.status, p.created_at,
                                               pr.fname, pr.lname, pr.codemeli,
                                               p.operator_username,
                                               CASE 
                                                   WHEN EXISTS (
                                                       SELECT 1 
                                                       FROM payment_installments i
                                                       WHERE i.payment_id = p.id
                                                         AND i.status = 'pending'
                                                         AND i.due_date < CURDATE()
                                                   ) THEN 1
                                                   ELSE 0
                                               END AS has_overdue
                                        FROM payments p
                                        JOIN dbpersonal pr ON p.codemeli = pr.codemeli
                                        ";

                                    if (!$canViewAll) {
                                        $query .= " WHERE p.operator_username = :username";
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
                                                    <button type="button" class="btn btn-sm btn-warning"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#paymentModal"
                                                            data-bs-action="update"
                                                            data-payment-id="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-codemeli="<?= htmlspecialchars($row['codemeli'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-total-amount="<?= htmlspecialchars($row['total_amount'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-comment="<?= htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8') ?>
">
                                                         ویرایش
                                                    </button>
                                                    <?php if ($canViewAll): ?>
                                                        <form action="payment.php" method="POST" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این پرداخت را حذف کنید؟');">
                                                            <input type="hidden" name="payment_id" value="<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" name="delete_payment" class="btn btn-sm btn-danger">حذف</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php }

                                    } else {
                                        echo '<tr><td colspan="8">هیچ پرداختی ثبت نشده است.</td></tr>';
                                    }
                                } catch (PDOException $e) {
                                    error_log("Payment list query error: " . $e->getMessage());
                                    echo '<tr><td colspan="8">⚠ خطا در بارگذاری لیست پرداخت‌ها: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</td></tr>';
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

                    if (!isNaN(cellA) && !isNaN(cellB)) {
                        return order === "asc" ? cellA - cellB : cellB - cellA;
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

    document.addEventListener('DOMContentLoaded', function () {
        const paymentModal = document.getElementById('paymentModal');
        const paymentForm = document.getElementById('paymentForm');
        const modalTitle = document.getElementById('paymentModalLabel');
        const submitButton = document.getElementById('submitButton');

        paymentModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Button that triggered the modal
            const isUpdate = button.getAttribute('data-bs-action') === 'update';

            if (isUpdate) {
                // Update mode
                modalTitle.textContent = '✏️ ویرایش پرداخت';
                submitButton.textContent = 'به‌روزرسانی پرداخت';
                submitButton.name = 'update_payment';
                paymentForm.action = 'payment.php';

                // Get payment data from button attributes
                const paymentId = button.getAttribute('data-payment-id');
                const codemeli = button.getAttribute('data-codemeli');
                const totalAmount = button.getAttribute('data-total-amount');
                const comment = button.getAttribute('data-comment') || '';

                // Populate form fields
                document.getElementById('payment_id').value = paymentId;
                document.getElementById('codemeli').value = codemeli;
                document.getElementById('total_amount').value = totalAmount;
                document.getElementById('comment').value = comment;
            } else {
                // Create mode
                modalTitle.textContent = '➕ ثبت پرداخت جدید';
                submitButton.textContent = 'ثبت پرداخت';
                submitButton.name = 'create_payment';
                paymentForm.action = 'payment.php';

                // Clear form fields
                paymentForm.reset();
                document.getElementById('payment_id').value = '';
            }
        });
    });
</script>
</body>
</html>