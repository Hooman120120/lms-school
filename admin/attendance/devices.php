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

if (empty($_SESSION['attendance_csrf'])) $_SESSION['attendance_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['attendance_csrf'];
$pushUrl = url('api/attendance/push.php');

// ---------------------------------------------------------
// عملیات روی دستگاه‌ها (ثبت، کلید جدید، فعال/غیرفعال، حذف)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminRequire('attendance.devices.manage');
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['attendance_notice'] = ['type' => 'danger', 'message' => 'نشست منقضی شده است؛ دوباره تلاش کنید.'];
        header('Location: devices.php');
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if (isset($_POST['add_device'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                $_SESSION['attendance_notice'] = ['type' => 'danger', 'message' => 'نام دستگاه را وارد کنید (حداکثر ۱۰۰ نویسه).'];
            } else {
                $note = trim((string) ($_POST['location_note'] ?? ''));
                [$newId, $key] = attendance_device_create($conn, $name, (string) ($_POST['type'] ?? ''),
                    (string) ($_POST['location'] ?? ''), $note !== '' ? mb_substr($note, 0, 100) : null, (string) $_SESSION['admin']['user']);
                $_SESSION['attendance_new_key'] = ['id' => $newId, 'name' => $name, 'key' => $key];
            }
        } elseif (isset($_POST['rotate_key']) && $id) {
            $st = $conn->prepare('SELECT name FROM attendance_devices WHERE id = ?');
            $st->execute([$id]);
            if ($name = $st->fetchColumn()) {
                $_SESSION['attendance_new_key'] = ['id' => $id, 'name' => $name, 'key' => attendance_device_rotate_key($conn, $id), 'rotated' => true];
            }
        } elseif (isset($_POST['toggle_device']) && $id) {
            $conn->prepare('UPDATE attendance_devices SET active = 1 - active WHERE id = ?')->execute([$id]);
            $_SESSION['attendance_notice'] = ['type' => 'success', 'message' => 'وضعیت دستگاه تغییر کرد.'];
        } elseif (isset($_POST['delete_device']) && $id) {
            $st = $conn->prepare('SELECT COUNT(*) FROM attendance_events WHERE device_id = ?');
            $st->execute([$id]);
            if ((int) $st->fetchColumn() > 0) {
                $_SESSION['attendance_notice'] = ['type' => 'danger', 'message' => 'این دستگاه رکورد ثبت‌شده دارد و حذف نمی‌شود؛ آن را غیرفعال کنید.'];
            } else {
                $conn->prepare('DELETE FROM attendance_devices WHERE id = ?')->execute([$id]);
                $_SESSION['attendance_notice'] = ['type' => 'success', 'message' => 'دستگاه حذف شد.'];
            }
        } elseif (isset($_POST['relink'])) {
            $n = attendance_relink_unknown($conn);
            $_SESSION['attendance_notice'] = ['type' => 'success', 'message' => $n . ' رکورد به دانش‌آموز متصل شد.'];
        }
    } catch (Throwable $e) {
        error_log('attendance devices: ' . $e->getMessage());
        $_SESSION['attendance_notice'] = ['type' => 'danger', 'message' => 'عملیات انجام نشد.'];
    }
    header('Location: devices.php');
    exit;
}

$notice = $_SESSION['attendance_notice'] ?? null;
$newKey = $_SESSION['attendance_new_key'] ?? null;
unset($_SESSION['attendance_notice'], $_SESSION['attendance_new_key']);

$devices = $conn->query("SELECT d.*,
        (SELECT COUNT(*) FROM attendance_events e WHERE e.device_id = d.id AND e.event_time >= CURDATE()) AS today,
        (SELECT COUNT(*) FROM attendance_events e WHERE e.device_id = d.id) AS total
    FROM attendance_devices d ORDER BY d.active DESC, d.id")->fetchAll(PDO::FETCH_ASSOC);

// آخرین رکوردها
$filterDevice = (int) ($_GET['device'] ?? 0);
$filterUnknown = !empty($_GET['unknown']);
$where = [];
$params = [];
if ($filterDevice) {
    $where[] = 'e.device_id = ?';
    $params[] = $filterDevice;
}
if ($filterUnknown) $where[] = 'e.idpersonal IS NULL';
$st = $conn->prepare('SELECT e.*, d.name AS device_name, p.fname, p.lname
    FROM attendance_events e
    JOIN attendance_devices d ON d.id = e.device_id
    LEFT JOIN dbpersonal p ON p.idpersonal = e.idpersonal'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
    ORDER BY e.event_time DESC, e.id DESC LIMIT 100');
$st->execute($params);
$events = $st->fetchAll(PDO::FETCH_ASSOC);
$unknownCount = (int) $conn->query('SELECT COUNT(DISTINCT codemeli) FROM attendance_events WHERE idpersonal IS NULL')->fetchColumn();

$directionLabels = ['in' => 'ورود', 'out' => 'خروج', 'unknown' => '—'];
$sourceLabels = ['push' => 'دستگاه', 'import' => 'فایل', 'simulator' => 'آزمایشی'];
$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$jd = static fn($t) => $t ? jdate('Y/m/d H:i:s', strtotime($t)) : '—';

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>دستگاه‌های حضور و غیاب | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .att-key { direction: ltr; font-family: monospace; font-size: 1rem; word-break: break-all; }
        .att-code { direction: ltr; text-align: left; font-family: monospace; font-size: .8rem; white-space: pre; background: #f6f7fb; padding: .75rem; border-radius: .4rem; }
        .att-ltr { direction: ltr; unicode-bidi: embed; }
    </style>
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
                        <h2 class="main-content-title tx-24 mg-b-5">دستگاه‌های حضور و غیاب</h2>
                        <p class="text-muted mb-0">هر سامانهٔ تشخیص چهره، اثر انگشت یا کارت می‌تواند با کد ملی دانش‌آموز، ورود و خروج را به این بخش بفرستد.</p>
                    </div>
                    <div>
                        <a class="btn btn-outline-primary" href="import.php"><i class="fe fe-upload"></i> ورود از فایل اکسل</a>
                    </div>
                </div>

                <?php if ($notice) { ?>
                    <div class="alert alert-<?php echo $h($notice['type']) ?>"><?php echo $h($notice['message']) ?></div>
                <?php } ?>

                <?php if ($newKey) { ?>
                    <div class="alert alert-warning">
                        <h6 class="mb-2">
                            <?php echo !empty($newKey['rotated']) ? 'کلید جدید دستگاه' : 'دستگاه ثبت شد:' ?>
                            «<?php echo $h($newKey['name']) ?>»
                        </h6>
                        <p class="mb-2">این کلید فقط همین یک بار نمایش داده می‌شود. آن را کپی کنید و در تنظیمات دستگاه یا نرم‌افزار آن وارد کنید.<?php echo !empty($newKey['rotated']) ? ' کلید قبلی دیگر کار نمی‌کند.' : '' ?></p>
                        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
                            <code class="att-key" id="att-new-key"><?php echo $h($newKey['key']) ?></code>
                            <button type="button" class="btn btn-sm btn-primary" onclick="attCopy('att-new-key', this)">کپی کلید</button>
                        </div>
                    </div>
                <?php } ?>

                <div class="row row-sm">
                    <div class="col-xl-8">
                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-3">دستگاه‌ها</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-nowrap mb-0">
                                        <thead>
                                        <tr>
                                            <th>نام</th>
                                            <th>نوع</th>
                                            <th>محل</th>
                                            <th>وضعیت</th>
                                            <th>آخرین ارسال</th>
                                            <th>امروز</th>
                                            <th>کل</th>
                                            <th>کلید</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$devices) { ?>
                                            <tr><td colspan="9" class="text-center text-muted">هنوز دستگاهی ثبت نشده است.</td></tr>
                                        <?php } ?>
                                        <?php foreach ($devices as $d) { ?>
                                            <tr class="<?php echo $d['active'] ? '' : 'text-muted' ?>">
                                                <td>
                                                    <?php echo $h($d['name']) ?>
                                                    <?php if ($d['location_note']) { ?><div class="tx-12 text-muted"><?php echo $h($d['location_note']) ?></div><?php } ?>
                                                </td>
                                                <td><?php echo $h(ATTENDANCE_DEVICE_TYPES[$d['type']] ?? $d['type']) ?></td>
                                                <td><?php echo $h(ATTENDANCE_LOCATIONS[$d['location']] ?? $d['location']) ?></td>
                                                <td>
                                                    <?php if (!$d['active']) { ?>
                                                        <span class="badge badge-secondary">غیرفعال</span>
                                                    <?php } elseif (!$d['last_seen_at']) { ?>
                                                        <span class="badge badge-warning">منتظر اولین ارسال</span>
                                                    <?php } else { ?>
                                                        <span class="badge badge-success">فعال</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="att-ltr"><?php echo $jd($d['last_seen_at']) ?></td>
                                                <td><?php echo (int) $d['today'] ?></td>
                                                <td><a href="?device=<?php echo (int) $d['id'] ?>"><?php echo (int) $d['total'] ?></a></td>
                                                <td class="att-ltr"><code><?php echo $h($d['key_hint']) ?>…</code></td>
                                                <td>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('کلید جدید ساخته شود؟ دستگاه تا وارد کردن کلید جدید نمی‌تواند ارسال کند.')">
                                                        <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $d['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-warning" name="rotate_key" value="1">کلید جدید</button>
                                                    </form>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                                        <input type="hidden" name="id" value="<?php echo (int) $d['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-secondary" name="toggle_device" value="1"><?php echo $d['active'] ? 'غیرفعال' : 'فعال' ?></button>
                                                    </form>
                                                    <?php if ((int) $d['total'] === 0) { ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('دستگاه حذف شود؟')">
                                                            <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                                            <input type="hidden" name="id" value="<?php echo (int) $d['id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger" name="delete_device" value="1">حذف</button>
                                                        </form>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-3">ثبت دستگاه جدید</h6>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                    <div class="form-group">
                                        <label>نام دستگاه</label>
                                        <input class="form-control" name="name" maxlength="100" required placeholder="مثلاً گیت ورودی اصلی">
                                    </div>
                                    <div class="form-group">
                                        <label>نوع</label>
                                        <select class="form-control" name="type">
                                            <?php foreach (ATTENDANCE_DEVICE_TYPES as $k => $label) { ?>
                                                <option value="<?php echo $k ?>"><?php echo $label ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>محل نصب</label>
                                        <select class="form-control" name="location">
                                            <?php foreach (ATTENDANCE_LOCATIONS as $k => $label) { ?>
                                                <option value="<?php echo $k ?>"><?php echo $label ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>توضیح محل (اختیاری)</label>
                                        <input class="form-control" name="location_note" maxlength="100" placeholder="مثلاً درب شرقی / کلاس ۱۰۲">
                                    </div>
                                    <button class="btn btn-primary btn-block" name="add_device" value="1">ثبت و دریافت کلید</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <a class="main-content-label d-block mb-0" data-bs-toggle="collapse" href="#att-guide" role="button">
                            <i class="fe fe-book-open"></i> راهنمای اتصال برای نصاب یا شرکت پشتیبان دستگاه
                        </a>
                        <div class="collapse <?php echo ($newKey || !$devices) ? 'show' : '' ?> mt-3" id="att-guide">
                            <p>دستگاه یا نرم‌افزار آن باید هر ورود و خروج را با روش <b>POST</b> و قالب <b>JSON</b> به این نشانی بفرستد:</p>
                            <div class="d-flex align-items-center flex-wrap mb-3" style="gap:.5rem">
                                <code class="att-key" id="att-url"><?php echo $h($pushUrl) ?></code>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="attCopy('att-url', this)">کپی نشانی</button>
                            </div>
                            <ul>
                                <li>کلید دستگاه در سرآیند <code class="att-ltr">X-Device-Key</code> فرستاده شود (یا <code class="att-ltr">Authorization: Bearer</code>، یا در صورت ناچاری <code class="att-ltr">?key=</code> در انتهای نشانی).</li>
                                <li>شناسهٔ هر فرد در دستگاه باید <b>کد ملی</b> دانش‌آموز باشد. صفرهای ابتدای کد اگر حذف شده باشند اصلاح می‌شوند.</li>
                                <li>زمان می‌تواند میلادی، شمسی (مثل <span class="att-ltr">1405/07/07 07:42</span>)، ISO یا Unix باشد. ساعت دستگاه باید درست تنظیم شده باشد.</li>
                                <li>جهت اختیاری است: <code class="att-ltr">in</code> / <code class="att-ltr">out</code> (یا 0 / 1).</li>
                                <li>حداکثر ۱۰۰۰ رکورد در هر ارسال. ارسال دوبارهٔ رکوردهای قبلی مشکلی ایجاد نمی‌کند و تکراری‌ها نادیده گرفته می‌شوند.</li>
                            </ul>
<div class="att-code">POST <?php echo $h($pushUrl) . "\n" ?>X-Device-Key: &lt;device key&gt;
Content-Type: application/json

[
  {"user": "0016196406", "time": "2026-09-29 07:42:10", "direction": "in"},
  {"user": "0012345678", "time": "1405/07/07 07:43", "direction": "in", "score": 0.94}
]

→ {"success":true,"received":2,"accepted":2,"duplicates":0,"unknown_person":0,"rejected":[]}</div>
                            <p class="mt-3 mb-0 text-muted">اگر دستگاه امکان ارسال خودکار ندارد، گزارش آن را به اکسل بگیرید و از بخش «ورود از فایل اکسل» بارگذاری کنید.</p>
                        </div>
                    </div>
                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:.5rem">
                            <h6 class="main-content-label mb-0">آخرین رکوردها</h6>
                            <div class="d-flex flex-wrap" style="gap:.5rem">
                                <form method="get" class="d-flex" style="gap:.5rem">
                                    <select class="form-control form-control-sm" name="device" onchange="this.form.submit()">
                                        <option value="0">همهٔ دستگاه‌ها</option>
                                        <?php foreach ($devices as $d) { ?>
                                            <option value="<?php echo (int) $d['id'] ?>" <?php echo $filterDevice === (int) $d['id'] ? 'selected' : '' ?>><?php echo $h($d['name']) ?></option>
                                        <?php } ?>
                                    </select>
                                    <label class="mb-0 d-flex align-items-center text-nowrap" style="gap:.25rem">
                                        <input type="checkbox" name="unknown" value="1" <?php echo $filterUnknown ? 'checked' : '' ?> onchange="this.form.submit()">
                                        فقط کدهای ناشناس
                                    </label>
                                </form>
                                <?php if ($unknownCount) { ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                        <button class="btn btn-sm btn-outline-info" name="relink" value="1" title="رکوردهایی که کد ملی آن‌ها بعداً ثبت‌نام شده به دانش‌آموز متصل می‌شوند">تطبیق دوباره کدهای ناشناس (<?php echo $unknownCount ?>)</button>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered text-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th>زمان</th>
                                    <th>کد ملی</th>
                                    <th>دانش‌آموز</th>
                                    <th>جهت</th>
                                    <th>دستگاه</th>
                                    <th>منبع</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$events) { ?>
                                    <tr><td colspan="6" class="text-center text-muted">رکوردی نیست.</td></tr>
                                <?php } ?>
                                <?php foreach ($events as $e) { ?>
                                    <tr>
                                        <td class="att-ltr"><?php echo $jd($e['event_time']) ?></td>
                                        <td class="att-ltr"><?php echo $h($e['codemeli']) ?></td>
                                        <td>
                                            <?php if ($e['idpersonal']) { ?>
                                                <?php echo $h(trim($e['fname'] . ' ' . $e['lname'])) ?>
                                            <?php } else { ?>
                                                <span class="badge badge-danger">کد ملی ثبت‌نشده</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $h($directionLabels[$e['direction']] ?? $e['direction']) ?></td>
                                        <td><?php echo $h($e['device_name']) ?></td>
                                        <td><?php echo $h($sourceLabels[$e['source']] ?? $e['source']) ?></td>
                                    </tr>
                                <?php } ?>
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
    function attCopy(id, btn) {
        var text = document.getElementById(id).textContent.trim();
        var done = function () { var t = btn.textContent; btn.textContent = 'کپی شد'; setTimeout(function () { btn.textContent = t; }, 1500); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta); done();
        }
    }
</script>
</body>
</html>
