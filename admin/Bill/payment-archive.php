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
// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!Permision('permision_viewbill')) {
    http_response_code(403);
    exit('مجوز دسترسی به آرشیو اقساط برای شما تعریف نشده است.');
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

// --- Date Filtering Logic ---
$startDateJalali = trim($_GET['start_date'] ?? '');
$endDateJalali   = trim($_GET['end_date'] ?? '');

$whereConditions = [];
$params = [];

// 1. Permission scoping
if (!$canViewAll) {
    $whereConditions[] = "p.operator_username = :username";
    $params[':username'] = $username;
}

// 2. Start Date scoping (Jalali to Gregorian)
if (!empty($startDateJalali)) {
    $p = explode('/', str_replace('-', '/', $startDateJalali));
    if (count($p) === 3) {
        $g = jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        $whereConditions[] = "p.created_at >= :start_date";
        $params[':start_date'] = sprintf('%04d-%02d-%02d 00:00:00', $g[0], $g[1], $g[2]);
    }
}

// 3. End Date scoping (Jalali to Gregorian)
if (!empty($endDateJalali)) {
    $p = explode('/', str_replace('-', '/', $endDateJalali));
    if (count($p) === 3) {
        $g = jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        $whereConditions[] = "p.created_at <= :end_date";
        $params[':end_date'] = sprintf('%04d-%02d-%02d 23:59:59', $g[0], $g[1], $g[2]);
    }
}

$whereSql = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Fetch Statistics
try {
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
        {$whereSql}
    ";

    $stmt = $conn->prepare($statsQuery);
    $stmt->execute($params);
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
    <title> مدیریت اقساط و پرداخت‌ها </title>
    <?php include_once '../link.php'; ?>
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

                <!-- Date Filter Card -->
                <div class="card mb-3 mt-3">
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end g-3">
                            <div class="col-md-3">
                                <label class="form-label">از تاریخ ایجاد:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></div>
                                    </div>
                                    <input type="text" name="start_date" class="form-control fc-datepicker" placeholder="1402/01/01" value="<?= htmlspecialchars($startDateJalali, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تا تاریخ ایجاد:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></div>
                                    </div>
                                    <input type="text" name="end_date" class="form-control fc-datepicker" placeholder="1402/12/29" value="<?= htmlspecialchars($endDateJalali, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill me-1">
                                    <i class="fe fe-filter"></i> اعمال فیلتر
                                </button>
                                <?php if ($canViewAll){ ?>
                                <button type="submit" formaction="payment_excel.php" formtarget="_blank" class="btn btn-success flex-fill me-1">
                                    <i class="fe fe-file-text"></i> دانلود اکسل
                                </button>
                                <?php } ?>
                                <?php if (!empty($startDateJalali) || !empty($endDateJalali)): ?>
                                    <a href="?" class="btn btn-outline-secondary me-1">
                                        <i class="fe fe-refresh-cw"></i> حذف فیلتر
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Summary Statistics Cards -->
                <div class="row row-sm">
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <label class="main-content-label mb-3 pt-1">جمع کل بدهی (مبلغ کل)</label>
                                    <h3 class="text-start card-item-icon card-icon">
                                        <i class="fe fe-credit-card icon-size float-end text-primary"></i>
                                        <span class="font-weight-bold"><?= number_format($stats['total_debt']) ?></span>
                                        <small class="fs-6 text-muted">تومان</small>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <label class="main-content-label mb-3 pt-1">جمع پرداخت شده</label>
                                    <h3 class="text-start card-item-icon card-icon">
                                        <i class="fe fe-check-circle icon-size float-end text-success"></i>
                                        <span class="font-weight-bold text-success"><?= number_format($stats['total_paid']) ?></span>
                                        <small class="fs-6 text-muted">تومان</small>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <label class="main-content-label mb-3 pt-1">جمع باقی مانده</label>
                                    <h3 class="text-start card-item-icon card-icon">
                                        <i class="fe fe-dollar-sign icon-size float-end text-warning"></i>
                                        <span class="font-weight-bold text-warning"><?= number_format($stats['total_balance']) ?></span>
                                        <small class="fs-6 text-muted">تومان</small>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="card-order">
                                    <label class="main-content-label mb-3 pt-1">تعداد موارد معوقه</label>
                                    <h3 class="text-start card-item-icon card-icon">
                                        <i class="fe fe-alert-triangle icon-size float-end text-danger"></i>
                                        <span class="font-weight-bold text-danger"><?= number_format($stats['overdue_count']) ?></span>
                                        <small class="fs-6 text-muted">مورد</small>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mt-2">لیست کلی اقساط و پرداخت‌ها</h5>
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
                                        {$whereSql}
                                        ORDER BY p.created_at DESC
                                    ";

                                    $stmt = $conn->prepare($query);
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
                                                <td><?= htmlspecialchars(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
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
                                                <td>
                                                    <?php if (!empty($row['idfactor'])): ?>
                                                        <?= htmlspecialchars($row['idfactor'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <?php if ($canViewAll): ?>
                                                    <td><?= htmlspecialchars($row['operator_username'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <?php endif; ?>

                                                <td>
                                                    <a href="viewfactor.php?id=<?= htmlspecialchars($row['idfactor'], ENT_QUOTES, 'UTF-8') ?>"
                                                       class="btn btn-sm btn-info"
                                                        <?= empty($row['idfactor']) ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>
                                                       onclick="window.open(this.href, 'FactorPopup', 'width=600,height=700,scrollbars=yes,resizable=yes'); return false;">
                                                        فاکتور
                                                    </a>
                                                    <a href="paymentinstallments.php?id=<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                       class="btn btn-sm btn-primary">جزئیات</a>
                                                </td>
                                            </tr>
                                        <?php }

                                    } else {
                                        $colCount = $canViewAll ? 10 : 9;
                                        echo '<tr><td colspan="' . $colCount . '">هیچ پرداختی در این بازه زمانی یافت نشد.</td></tr>';
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
</body>
</html>
