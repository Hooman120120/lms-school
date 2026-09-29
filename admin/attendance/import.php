<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once '../../inc/attendance.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    die();
}
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if (!AdminCanUsePermissionScope('attendance.import', ['administrator'])) {
    header('location: ' . url('admin/index.php'));
    die();
}

if (empty($_SESSION['attendance_csrf'])) $_SESSION['attendance_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['attendance_csrf'];
const ATTENDANCE_IMPORT_MAX_ROWS = 20000;

$pending = $_SESSION['attendance_import'] ?? null;
if ($pending && !is_file($pending['file'])) {
    unset($_SESSION['attendance_import']);
    $pending = null;
}
$error = null;

$cancelPending = static function () use (&$pending): void {
    if ($pending && is_file($pending['file'])) @unlink($pending['file']);
    unset($_SESSION['attendance_import']);
    $pending = null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $error = 'نشست منقضی شده است؛ دوباره تلاش کنید.';
    } elseif (isset($_POST['cancel_import'])) {
        $cancelPending();
        header('Location: import.php');
        exit;
    } elseif (isset($_POST['upload_file'])) {
        // ---------- مرحله ۱: بارگذاری فایل ----------
        $cancelPending();
        $deviceId = (string) ($_POST['device'] ?? '');
        $f = $_FILES['file'] ?? null;
        $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $error = 'فایل بارگذاری نشد.';
        } elseif (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            $error = 'فقط فایل‌های xlsx، xls و csv پذیرفته می‌شوند.';
        } elseif ($ext === 'xlsx' && !class_exists('ZipArchive')) {
            $error = 'افزونهٔ zip روی سرور PHP فعال نیست؛ فایل را به صورت csv یا xls ذخیره کنید.';
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            $error = 'حجم فایل بیشتر از ۱۰ مگابایت است.';
        } else {
            if ($deviceId === 'new') {
                [$newId] = attendance_device_create($conn, 'ورود از فایل', 'other', 'gate', null, (string) $_SESSION['admin']['user']);
                $deviceId = (string) $newId;
            }
            $st = $conn->prepare('SELECT id FROM attendance_devices WHERE id = ?');
            $st->execute([(int) $deviceId]);
            if (!$st->fetchColumn()) {
                $error = 'دستگاه را انتخاب کنید.';
            } else {
                $path = sys_get_temp_dir() . '/lms_attendance_' . bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $path);
                try {
                    $rows = attendance_sheet_rows($path, $f['name']);
                } catch (Throwable $e) {
                    $rows = null;
                }
                if (!$rows) {
                    @unlink($path);
                    $error = 'فایل خوانده نشد یا خالی است.';
                } elseif (count($rows) > ATTENDANCE_IMPORT_MAX_ROWS) {
                    @unlink($path);
                    $error = 'فایل بیش از ' . ATTENDANCE_IMPORT_MAX_ROWS . ' ردیف دارد؛ آن را در چند فایل بارگذاری کنید.';
                } else {
                    $_SESSION['attendance_import'] = ['file' => $path, 'name' => $f['name'], 'device' => (int) $deviceId];
                    header('Location: import.php');
                    exit;
                }
            }
        }
    } elseif (isset($_POST['run_import']) && $pending) {
        // ---------- مرحله ۲: انتخاب ستون‌ها و ثبت ----------
        $col = static fn($k) => ($_POST[$k] ?? '') === '' ? null : (int) $_POST[$k];
        $map = ['code' => $col('col_code'), 'date' => $col('col_date'), 'time' => $col('col_time'), 'direction' => $col('col_direction')];
        if ($map['code'] === null || $map['date'] === null) {
            $error = 'ستون کد ملی و ستون تاریخ را مشخص کنید.';
        } else {
            $st = $conn->prepare('SELECT * FROM attendance_devices WHERE id = ?');
            $st->execute([$pending['device']]);
            $device = $st->fetch(PDO::FETCH_ASSOC);
            try {
                $rows = attendance_sheet_rows($pending['file'], $pending['name']);
                $lastId = (int) $conn->query('SELECT COALESCE(MAX(id), 0) FROM attendance_events')->fetchColumn();
                $report = attendance_import_rows($conn, $device, $rows, $map, !empty($_POST['has_header']));
                $report['unknown_codes'] = attendance_unknown_codes($conn, $lastId);
                $report['file'] = $pending['name'];
                $report['device'] = $device['name'];
                $_SESSION['attendance_import_report'] = $report;
                $cancelPending();
                header('Location: import.php');
                exit;
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log('attendance import: ' . $e->getMessage());
                $error = 'ثبت رکوردها انجام نشد.';
            }
        }
    }
}

$report = $_SESSION['attendance_import_report'] ?? null;
unset($_SESSION['attendance_import_report']);

$devices = $conn->query('SELECT id, name FROM attendance_devices WHERE active = 1 ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

// پیش‌نمایش فایل برای انتخاب ستون‌ها
$preview = [];
$columns = [];
$guess = [];
$headerGuessed = false;
if ($pending) {
    try {
        $rows = attendance_sheet_rows($pending['file'], $pending['name']);
    } catch (Throwable $e) {
        $rows = [];
    }
    $totalRows = count($rows);
    $preview = array_slice($rows, 0, 6);
    $width = $rows ? max(array_map('count', $preview)) : 0;
    $guess = attendance_guess_columns($rows[0] ?? []);
    $headerGuessed = $guess['code'] !== null || $guess['date'] !== null;
    if (!$headerGuessed && isset($rows[0])) {
        // بدون سرستون: ستونی که شبیه کد ملی / تاریخ است
        foreach ($rows[0] as $i => $v) {
            $v = attendance_normalize_digits((string) $v);
            if ($guess['code'] === null && preg_match('/^\d{7,10}$/', $v)) $guess['code'] = $i;
            elseif ($guess['date'] === null && (attendance_parse_time($v) || (is_numeric($v) && $v > 20000 && $v < 80000))) $guess['date'] = $i;
        }
    }
    for ($i = 0; $i < $width; $i++) {
        $label = $headerGuessed ? trim((string) ($rows[0][$i] ?? '')) : '';
        $columns[$i] = 'ستون ' . ($i + 1) . ($label !== '' ? ' — ' . $label : '');
    }
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fa = static fn($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
$cellText = static function ($v): string {
    if (is_float($v) && $v == floor($v)) return number_format($v, 0, '', '');
    return (string) $v;
};

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>ورود حضور و غیاب از فایل | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>.att-ltr { direction: ltr; unicode-bidi: embed; }</style>
</head>
<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="page-header">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">ورود حضور و غیاب از فایل</h2>
                        <p class="text-muted mb-0">گزارش ورود و خروج دستگاه را به اکسل یا CSV بگیرید و اینجا بارگذاری کنید. شناسهٔ افراد باید کد ملی باشد.</p>
                    </div>
                    <div>
                        <a class="btn btn-outline-primary" href="devices.php"><i class="fe fe-cpu"></i> دستگاه‌ها و رکوردها</a>
                    </div>
                </div>

                <?php if ($error) { ?>
                    <div class="alert alert-danger"><?php echo $h($error) ?></div>
                <?php } ?>

                <?php if ($report) { ?>
                    <div class="alert alert-<?php echo $report['rejected'] ? 'warning' : 'success' ?>">
                        فایل «<?php echo $h($report['file']) ?>» ثبت شد:
                        <?php echo $fa($report['accepted']) ?> رکورد جدید،
                        <?php echo $fa($report['duplicates']) ?> تکراری،
                        <?php echo $fa(count($report['rejected'])) ?> ردیف دارای خطا.
                        <a href="#" data-bs-toggle="modal" data-bs-target="#att-report">مشاهدهٔ گزارش</a>
                    </div>
                <?php } ?>

                <?php if (!$pending) { ?>
                    <div class="card custom-card">
                        <div class="card-body">
                            <h6 class="main-content-label mb-3">۱. انتخاب فایل</h6>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                <div class="row">
                                    <div class="col-md-4 form-group">
                                        <label>این رکوردها از کدام دستگاه است؟</label>
                                        <select class="form-control" name="device" required>
                                            <?php foreach ($devices as $id => $name) { ?>
                                                <option value="<?php echo (int) $id ?>"><?php echo $h($name) ?></option>
                                            <?php } ?>
                                            <option value="new" <?php echo $devices ? '' : 'selected' ?>>+ دستگاه جدید «ورود از فایل»</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 form-group">
                                        <label>فایل (xlsx، xls یا csv — حداکثر ۱۰ مگابایت)</label>
                                        <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                    </div>
                                    <div class="col-md-3 form-group d-flex align-items-end">
                                        <button class="btn btn-primary btn-block" name="upload_file" value="1">بارگذاری و ادامه</button>
                                    </div>
                                </div>
                            </form>
                            <p class="text-muted mb-0">
                                فایل باید برای هر ورود یا خروج یک ردیف داشته باشد، با ستون کد ملی و ستون تاریخ (ساعت می‌تواند در همان ستون یا ستون جدا باشد).
                                تاریخ شمسی و میلادی هر دو پذیرفته می‌شود. بارگذاری دوبارهٔ همان فایل، رکورد تکراری نمی‌سازد.
                            </p>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="card custom-card">
                        <div class="card-body">
                            <h6 class="main-content-label mb-1">۲. مشخص کردن ستون‌ها</h6>
                            <p class="text-muted">
                                فایل «<?php echo $h($pending['name']) ?>» — <?php echo $fa($totalRows) ?> ردیف.
                                ستون‌ها از روی سرستون حدس زده شده‌اند؛ اگر درست نیست اصلاح کنید.
                            </p>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                <div class="row">
                                    <?php foreach ([
                                        'code' => ['col_code', 'کد ملی', true],
                                        'date' => ['col_date', 'تاریخ (یا تاریخ و ساعت)', true],
                                        'time' => ['col_time', 'ساعت (اگر جداست)', false],
                                        'direction' => ['col_direction', 'ورود / خروج', false],
                                    ] as $key => [$field, $label, $required]) { ?>
                                        <div class="col-md-3 form-group">
                                            <label><?php echo $label ?><?php echo $required ? ' <span class="text-danger">*</span>' : '' ?></label>
                                            <select class="form-control" name="<?php echo $field ?>" <?php echo $required ? 'required' : '' ?>>
                                                <option value=""><?php echo $required ? 'انتخاب کنید' : 'ندارد' ?></option>
                                                <?php foreach ($columns as $i => $colLabel) { ?>
                                                    <option value="<?php echo $i ?>" <?php echo $guess[$key] === $i ? 'selected' : '' ?>><?php echo $h($colLabel) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div class="form-group">
                                    <label class="d-flex align-items-center" style="gap:.4rem">
                                        <input type="checkbox" name="has_header" value="1" <?php echo $headerGuessed ? 'checked' : '' ?>>
                                        ردیف اول سرستون است (ثبت نشود)
                                    </label>
                                </div>

                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered text-nowrap mb-0">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <?php foreach ($columns as $i => $colLabel) { ?><th>ستون <?php echo $fa($i + 1) ?></th><?php } ?>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($preview as $r => $row) { ?>
                                            <tr>
                                                <td><?php echo $fa($r + 1) ?></td>
                                                <?php foreach ($columns as $i => $colLabel) { ?>
                                                    <td class="att-ltr"><?php echo $h($cellText($row[$i] ?? '')) ?></td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <button class="btn btn-primary" name="run_import" value="1">ثبت رکوردها</button>
                                <button class="btn btn-outline-secondary" name="cancel_import" value="1" formnovalidate>انصراف</button>
                            </form>
                        </div>
                    </div>
                <?php } ?>

            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>

<?php if ($report) { ?>
    <div class="modal fade" id="att-report" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">گزارش ورود فایل «<?php echo $h($report['file']) ?>»</h6>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="بستن"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row text-center mb-3">
                        <div class="col"><div class="tx-20 font-weight-bold"><?php echo $fa($report['received']) ?></div><div class="text-muted tx-12">ردیف خوانده‌شده</div></div>
                        <div class="col"><div class="tx-20 font-weight-bold text-success"><?php echo $fa($report['accepted']) ?></div><div class="text-muted tx-12">ثبت شد</div></div>
                        <div class="col"><div class="tx-20 font-weight-bold text-info"><?php echo $fa($report['duplicates']) ?></div><div class="text-muted tx-12">تکراری (قبلاً ثبت شده)</div></div>
                        <div class="col"><div class="tx-20 font-weight-bold text-danger"><?php echo $fa(count($report['rejected'])) ?></div><div class="text-muted tx-12">خطا</div></div>
                    </div>
                    <p class="text-muted tx-12">دستگاه: <?php echo $h($report['device']) ?><?php echo $report['blank'] ? ' — ' . $fa($report['blank']) . ' ردیف خالی نادیده گرفته شد.' : '' ?></p>

                    <?php if ($report['unknown_codes']) { ?>
                        <div class="alert alert-warning">
                            <b><?php echo $fa(count($report['unknown_codes'])) ?> کد ملی</b> در فایل با هیچ دانش‌آموزی تطبیق نداشت.
                            رکوردهای آن‌ها ذخیره شد و پس از ثبت‌نام دانش‌آموز (با «تطبیق دوباره» در صفحهٔ دستگاه‌ها) متصل می‌شوند.
                            <div class="att-ltr mt-2">
                                <?php foreach ($report['unknown_codes'] as $code => $n) { ?>
                                    <span class="badge badge-light m-1"><?php echo $h($code) ?> (<?php echo (int) $n ?>)</span>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if ($report['rejected']) { ?>
                        <h6>ردیف‌های ثبت‌نشده</h6>
                        <div class="table-responsive" style="max-height: 50vh">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>ردیف فایل</th><th>مقدار</th><th>خطا</th></tr></thead>
                                <tbody>
                                <?php foreach ($report['rejected'] as $p) { ?>
                                    <tr>
                                        <td><?php echo $fa($p['row']) ?></td>
                                        <td class="att-ltr"><?php echo $h($p['value']) ?></td>
                                        <td><?php echo $h($p['reason']) ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } elseif (!$report['unknown_codes']) { ?>
                        <div class="alert alert-success mb-0">همهٔ ردیف‌ها بدون مشکل ثبت شدند.</div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-primary" href="devices.php">مشاهدهٔ رکوردها</a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () {
            var el = document.getElementById('att-report');
            if (window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            else if (window.jQuery && jQuery.fn.modal) jQuery(el).modal('show');
        });
    </script>
<?php } ?>
</body>
</html>
