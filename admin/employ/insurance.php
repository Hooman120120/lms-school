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
if (empty($_SESSION['staff_insurance_csrf'])) $_SESSION['staff_insurance_csrf'] = bin2hex(random_bytes(24));
$message = '';

function insuranceJalaliToGregorian(string $value): ?string
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
    AdminRequire('finance.insurance.manage');
    if (!hash_equals($_SESSION['staff_insurance_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('درخواست نامعتبر است.');
    }
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        if ($deleteId <= 0) {
            $message = 'شناسه رکورد بیمه معتبر نیست.';
        } else {
            $stmt = $conn->prepare('DELETE FROM school_staff_insurance WHERE id=?');
            $stmt->execute([$deleteId]);
            $message = $stmt->rowCount() ? 'رکورد بیمه حذف شد.' : 'رکورد بیمه یافت نشد.';
        }
    } else {
        $username = trim((string)($_POST['admin_username'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        $startDateJalali = trim((string)($_POST['start_date'] ?? ''));
        $endDateJalali = trim((string)($_POST['end_date'] ?? ''));
        $startDate = $startDateJalali !== '' ? insuranceJalaliToGregorian($startDateJalali) : null;
        $endDate = $endDateJalali !== '' ? insuranceJalaliToGregorian($endDateJalali) : null;
        $exists = $conn->prepare('SELECT 1 FROM dbadmin WHERE username=?');
        $exists->execute([$username]);
        if (!$exists->fetchColumn()) $message = 'همکار انتخاب‌شده معتبر نیست.';
        elseif (($startDateJalali !== '' && $startDate === null) || ($endDateJalali !== '' && $endDate === null)) $message = 'تاریخ اعتبار بیمه باید یک تاریخ شمسی معتبر باشد.';
        elseif ($startDate !== null && $endDate !== null && $endDate < $startDate) $message = 'تاریخ پایان بیمه نمی‌تواند قبل از تاریخ شروع باشد.';
        else {
            $values = [$username, trim((string)($_POST['provider'] ?? '')), trim((string)($_POST['policy_number'] ?? '')), $startDate ?: null, $endDate ?: null, in_array($_POST['status'] ?? '', ['active', 'inactive', 'expired'], true) ? $_POST['status'] : 'active', trim((string)($_POST['notes'] ?? ''))];
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE school_staff_insurance SET admin_username=?,provider=?,policy_number=?,start_date=?,end_date=?,status=?,notes=? WHERE id=?');
                $values[] = $id;
            } else {
                $stmt = $conn->prepare('INSERT INTO school_staff_insurance(admin_username,provider,policy_number,start_date,end_date,status,notes,created_by) VALUES(?,?,?,?,?,?,?,?)');
                $values[] = $_SESSION['admin']['user'];
            }
            $stmt->execute($values);
            $message = 'اطلاعات بیمه ذخیره شد.';
        }
    }
}
$staff = $conn->query("SELECT username,CONCAT(COALESCE(fname,''),' ',COALESCE(lname,'')) full_name FROM dbadmin ORDER BY lname,fname")->fetchAll(PDO::FETCH_ASSOC);
$rows = $conn->query("SELECT i.*,CONCAT(COALESCE(a.fname,''),' ',COALESCE(a.lname,'')) full_name,r.title role_title FROM school_staff_insurance i LEFT JOIN dbadmin a ON a.username=i.admin_username LEFT JOIN school_admin_roles r ON r.id=a.school_role_id ORDER BY i.updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$insuranceRow) {
    $insuranceRow['start_date_jalali'] = !empty($insuranceRow['start_date']) ? toJalali($insuranceRow['start_date'], 'Y/m/d', 'en') : '';
    $insuranceRow['end_date_jalali'] = !empty($insuranceRow['end_date']) ? toJalali($insuranceRow['end_date'], 'Y/m/d', 'en') : '';
}
unset($insuranceRow);
$statusLabels = ['active' => 'فعال', 'inactive' => 'غیرفعال', 'expired' => 'منقضی'];
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>بیمه افراد | <?= __TITLE ?></title><?php include '../link.php'; ?>
</head>

<body class="main-body leftmenu">
    <div class="page"><?php include '../menu.php';
                        include '../header.php'; ?><div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="page-header">
                        <h2 class="main-content-title tx-24">بیمه افراد</h2>
                    </div>
                    <?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?><div class="card custom-card mb-3">
                        <div class="card-body">
                            <form method="post" class="row"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['staff_insurance_csrf']) ?>"><input type="hidden" name="id" id="insuranceId">
                                <div class="col-md-4 mb-2"><label>همکار</label><select class="form-control" name="admin_username" id="insuranceUser" required>
                                        <option value="">انتخاب کنید</option><?php foreach ($staff as $person): ?><option value="<?= htmlspecialchars($person['username']) ?>"><?= htmlspecialchars(trim($person['full_name']) . ' - ' . $person['username']) ?></option><?php endforeach; ?>
                                    </select></div>
                                <div class="col-md-4 mb-2"><label>شرکت بیمه</label><input class="form-control" name="provider" id="insuranceProvider"></div>
                                <div class="col-md-4 mb-2"><label>شماره بیمه‌نامه</label><input class="form-control" name="policy_number" id="insurancePolicy"></div>
                                <div class="col-md-3 mb-2"><label>تاریخ شروع (شمسی)</label><input type="text" class="form-control" name="start_date" id="insuranceStart" placeholder="۱۴۰۵/۰۷/۰۱" data-azar-datepicker data-azar-calendar="jalali" data-azar-input-format="YYYY/MM/DD" data-azar-output-format="YYYY/MM/DD"></div>
                                <div class="col-md-3 mb-2"><label>تاریخ پایان (شمسی)</label><input type="text" class="form-control" name="end_date" id="insuranceEnd" placeholder="۱۴۰۶/۰۶/۳۱" data-azar-datepicker data-azar-calendar="jalali" data-azar-input-format="YYYY/MM/DD" data-azar-output-format="YYYY/MM/DD"></div>
                                <div class="col-md-3 mb-2"><label>وضعیت</label><select class="form-control" name="status" id="insuranceStatus">
                                        <option value="active">فعال</option>
                                        <option value="inactive">غیرفعال</option>
                                        <option value="expired">منقضی</option>
                                    </select></div>
                                <div class="col-md-3 mb-2"><label>توضیحات</label><input class="form-control" name="notes" id="insuranceNotes"></div>
                                <div class="col-12"><button class="btn btn-primary">ذخیره اطلاعات بیمه</button></div>
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
                                            <th>بیمه</th>
                                            <th>شماره</th>
                                            <th>اعتبار</th>
                                            <th>وضعیت</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($rows as $row): ?><tr>
                                                <td><?= htmlspecialchars(trim($row['full_name'])) ?><small class="d-block"><?= htmlspecialchars($row['admin_username']) ?></small></td>
                                                <td><?= htmlspecialchars($row['role_title'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars((string)$row['provider']) ?></td>
                                                <td><?= htmlspecialchars((string)$row['policy_number']) ?></td>
                                                <td><?= htmlspecialchars($row['start_date_jalali'] ? tr_num($row['start_date_jalali'], 'fa') : '-') ?> تا <?= htmlspecialchars($row['end_date_jalali'] ? tr_num($row['end_date_jalali'], 'fa') : '-') ?></td>
                                                <td><?= $statusLabels[$row['status']] ?? '-' ?></td>
                                                <td><button type="button" class="btn btn-sm btn-warning edit-insurance" data-row='<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>ویرایش</button>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['staff_insurance_csrf']) ?>"><button class="btn btn-sm btn-danger" name="delete_id" value="<?= (int)$row['id'] ?>">حذف</button></form>
                                                </td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><?php include '../../footers.php'; ?><script>
        document.querySelectorAll('.edit-insurance').forEach(function(b) {
            b.addEventListener('click', function() {
                var r = JSON.parse(this.dataset.row);
                document.getElementById('insuranceId').value = r.id;
                document.getElementById('insuranceUser').value = r.admin_username;
                document.getElementById('insuranceProvider').value = r.provider || '';
                document.getElementById('insurancePolicy').value = r.policy_number || '';
                var startInput = document.getElementById('insuranceStart');
                var endInput = document.getElementById('insuranceEnd');
                if (startInput._azarDatepicker) startInput._azarDatepicker.setValueFromString(r.start_date_jalali || '');
                else startInput.value = r.start_date_jalali || '';
                if (endInput._azarDatepicker) endInput._azarDatepicker.setValueFromString(r.end_date_jalali || '');
                else endInput.value = r.end_date_jalali || '';
                document.getElementById('insuranceStatus').value = r.status;
                document.getElementById('insuranceNotes').value = r.notes || '';
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>
