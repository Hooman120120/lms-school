<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    exit;
}
if (($_SESSION['admin']['ip'] ?? '') !== GetIp() && ($_SESSION['admin']['os'] ?? '') != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
$sessionRow = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
if (!Permision('permission_monthly_payment')) {
    http_response_code(403);
    exit('مجوز مدیریت واریز ماهانه را ندارید.');
}
if (empty($_SESSION['monthly_payment_csrf'])) $_SESSION['monthly_payment_csrf'] = bin2hex(random_bytes(24));
$message = '';
$jalaliMonthLabels = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
$currentJalaliYear = (int)jdate('Y', time(), '', 'Asia/Tehran', 'en');
$currentJalaliMonth = (int)jdate('n', time(), '', 'Asia/Tehran', 'en');
$selectedPaymentYear = (int)($_POST['payment_year'] ?? $currentJalaliYear);
$selectedPaymentMonth = (int)($_POST['payment_month_number'] ?? $currentJalaliMonth);

function monthlyPaymentJalaliToGregorian(string $value): ?string
{
    $value = function_exists('tr_num') ? tr_num(trim($value), 'en') : trim($value);
    $value = str_replace(['-', '.'], '/', $value);
    if (!preg_match('/^(1[2-5]\d{2})\/(0?[1-9]|1[0-2])\/(0?[1-9]|[12]\d|3[01])$/', $value, $matches)) {
        return null;
    }
    $jy = (int)$matches[1];
    $jm = (int)$matches[2];
    $jd = (int)$matches[3];
    $gregorian = jalali_to_gregorian($jy, $jm, $jd);
    if (!is_array($gregorian) || count($gregorian) !== 3) {
        return null;
    }
    $roundTrip = gregorian_to_jalali((int)$gregorian[0], (int)$gregorian[1], (int)$gregorian[2]);
    if ((int)$roundTrip[0] !== $jy || (int)$roundTrip[1] !== $jm || (int)$roundTrip[2] !== $jd) {
        return null;
    }
    return sprintf('%04d-%02d-%02d', (int)$gregorian[0], (int)$gregorian[1], (int)$gregorian[2]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['monthly_payment_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('درخواست نامعتبر است.');
    }
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        if ($deleteId <= 0) {
            $message = 'شناسه واریز معتبر نیست.';
        } else {
            $stmt = $conn->prepare('DELETE FROM school_staff_monthly_payments WHERE id=?');
            $stmt->execute([$deleteId]);
            $message = $stmt->rowCount() ? 'واریز حذف شد.' : 'واریز موردنظر یافت نشد.';
        }
    } else {
        $username = trim((string)($_POST['admin_username'] ?? ''));
        $selectedPaymentYear = (int)($_POST['payment_year'] ?? 0);
        $selectedPaymentMonth = (int)($_POST['payment_month_number'] ?? 0);
        $month = ($selectedPaymentYear >= 1200 && $selectedPaymentYear <= 1599 && $selectedPaymentMonth >= 1 && $selectedPaymentMonth <= 12)
            ? sprintf('%04d/%02d', $selectedPaymentYear, $selectedPaymentMonth)
            : '';
        $amount = max(0, (int)str_replace([',', '٬'], '', (string)($_POST['amount'] ?? 0)));
        $paidAtJalali = trim((string)($_POST['paid_at'] ?? ''));
        $paidAt = $paidAtJalali !== '' ? monthlyPaymentJalaliToGregorian($paidAtJalali) : null;
        $staffCheck = $conn->prepare('SELECT 1 FROM dbadmin WHERE username=? LIMIT 1');
        $staffCheck->execute([$username]);
        if (!$staffCheck->fetchColumn()) {
            $message = 'همکار انتخاب‌شده معتبر نیست.';
        } elseif ($month === '') {
            $message = 'سال و ماه پرداخت را از فهرست انتخاب کنید.';
        } elseif ($amount <= 0) {
            $message = 'مبلغ واریز باید بیشتر از صفر باشد.';
        } elseif ($paidAtJalali !== '' && $paidAt === null) {
            $message = 'تاریخ واریز باید یک تاریخ شمسی معتبر باشد.';
        } else {
            $stmt = $conn->prepare('INSERT INTO school_staff_monthly_payments(admin_username,payment_month,amount,paid_at,reference_number,notes,created_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),paid_at=VALUES(paid_at),reference_number=VALUES(reference_number),notes=VALUES(notes),created_by=VALUES(created_by)');
            $stmt->execute([$username, $month, $amount, $paidAt, trim((string)($_POST['reference_number'] ?? '')), trim((string)($_POST['notes'] ?? '')), $_SESSION['admin']['user']]);
            $message = 'واریز ماهانه ذخیره شد.';
        }
    }
}
$staff = $conn->query("SELECT username,CONCAT(COALESCE(fname,''),' ',COALESCE(lname,'')) full_name FROM dbadmin ORDER BY lname,fname")->fetchAll(PDO::FETCH_ASSOC);
$rows = $conn->query("SELECT p.*,CONCAT(COALESCE(a.fname,''),' ',COALESCE(a.lname,'')) full_name,r.title role_title FROM school_staff_monthly_payments p LEFT JOIN dbadmin a ON a.username=p.admin_username LEFT JOIN school_admin_roles r ON r.id=a.school_role_id ORDER BY p.payment_month DESC,p.updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>واریز ماهانه | <?= __TITLE ?></title><?php include '../link.php'; ?>
</head>

<body class="main-body leftmenu">
    <div class="page"><?php include '../menu.php';
                        include '../header.php'; ?><div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="page-header">
                        <h2 class="main-content-title tx-24">واریز ماهانه همکاران</h2>
                    </div><?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                    <div class="card custom-card mb-3">
                        <div class="card-body">
                            <form method="post" class="row"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['monthly_payment_csrf']) ?>">
                                <div class="col-lg-2 col-md-4 mb-2"><label>همکار</label><select class="form-control" name="admin_username" required>
                                        <option value="">انتخاب کنید</option><?php foreach ($staff as $person): ?><option value="<?= htmlspecialchars($person['username']) ?>"><?= htmlspecialchars(trim($person['full_name']) . ' - ' . $person['username']) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="col-lg-2 col-md-4 mb-2"><label>سال پرداخت</label><select class="form-control" name="payment_year" required><?php for ($year = $currentJalaliYear + 1; $year >= $currentJalaliYear - 5; $year--): ?><option value="<?= $year ?>" <?= $selectedPaymentYear === $year ? 'selected' : '' ?>><?= tr_num((string)$year, 'fa') ?></option><?php endfor; ?></select></div>
                                <div class="col-lg-2 col-md-4 mb-2"><label>ماه پرداخت</label><select class="form-control" name="payment_month_number" required><?php foreach ($jalaliMonthLabels as $monthNumber => $monthLabel): ?><option value="<?= $monthNumber ?>" <?= $selectedPaymentMonth === $monthNumber ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option><?php endforeach; ?></select></div>
                                <div class="col-lg-2 col-md-4 mb-2"><label>مبلغ (تومان)</label><input type="number" min="0" class="form-control" name="amount" required></div>
                                <div class="col-lg-2 col-md-4 mb-2"><label>تاریخ واریز (شمسی)</label><input type="text" class="form-control" name="paid_at" placeholder="۱۴۰۵/۰۷/۱۵" data-azar-datepicker data-azar-calendar="jalali" data-azar-input-format="YYYY/MM/DD" data-azar-output-format="YYYY/MM/DD"></div>
                                <div class="col-lg-2 col-md-4 mb-2"><label>شماره پیگیری</label><input class="form-control" name="reference_number"></div>
                                <div class="col-md-10 mb-2"><label>توضیحات</label><input class="form-control" name="notes"></div>
                                <div class="col-md-2 d-flex align-items-end mb-2"><button class="btn btn-primary btn-block">ثبت واریز</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                        <tr>
                                            <th>همکار</th>
                                            <th>سمت</th>
                                            <th>ماه</th>
                                            <th>مبلغ</th>
                                            <th>تاریخ</th>
                                            <th>پیگیری</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($rows as $row): ?><tr>
                                                <td><?= htmlspecialchars(trim($row['full_name'])) ?></td>
                                                <td><?= htmlspecialchars($row['role_title'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars(tr_num($row['payment_month'], 'fa')) ?></td>
                                                <td><?= number_format((int)$row['amount']) ?> تومان</td>
                                                <td><?= !empty($row['paid_at']) ? htmlspecialchars(toJalali($row['paid_at'], 'Y/m/d', 'fa')) : '-' ?></td>
                                                <td><?= htmlspecialchars((string)$row['reference_number']) ?></td>
                                                <td>
                                                    <form method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['monthly_payment_csrf']) ?>"><button class="btn btn-sm btn-danger" name="delete_id" value="<?= (int)$row['id'] ?>">حذف</button></form>
                                                </td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><?php include '../../footers.php'; ?>
</body>

</html>
