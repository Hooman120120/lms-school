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

$toTime = static function ($v): ?string {
    $v = attendance_normalize_digits((string) $v);
    return preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $v, $m) ? sprintf('%02d:%02d:00', $m[1], $m[2]) : null;
};
// ۱۴۰۵/۰۷/۰۱ → 2026-09-23
$toDate = static function ($v): ?string {
    $v = attendance_normalize_digits((string) $v);
    if ($v === '') return null;
    if (!preg_match('#^(1[34]\d\d)[/-](\d{1,2})[/-](\d{1,2})$#', $v, $m)) return 'invalid';
    [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
};
$toJalali = static function (?string $date): string {
    if (!$date) return '';
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    [$jy, $jm, $jd] = gregorian_to_jalali($y, $m, $d);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AdminRequire('attendance.rules.manage');
    $notice = null;
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $notice = ['type' => 'danger', 'message' => 'نشست منقضی شده است؛ دوباره تلاش کنید.'];
    } elseif (isset($_POST['save_rules'])) {
        $start = $toTime($_POST['school_start'] ?? '');
        $cutoff = $toTime($_POST['absent_cutoff'] ?? '');
        $grace = (int) ($_POST['late_grace'] ?? -1);
        $minPeople = (int) ($_POST['min_people_per_day'] ?? 0);
        $activeFrom = $toDate($_POST['active_from'] ?? '');
        if (!$start || !$cutoff) {
            $notice = ['type' => 'danger', 'message' => 'ساعت‌ها را به شکل ۰۷:۳۰ وارد کنید.'];
        } elseif ($cutoff <= $start) {
            $notice = ['type' => 'danger', 'message' => 'ساعت ثبت غیبت باید بعد از ساعت شروع باشد.'];
        } elseif ($grace < 0 || $grace > 240) {
            $notice = ['type' => 'danger', 'message' => 'مهلت تأخیر باید بین ۰ تا ۲۴۰ دقیقه باشد.'];
        } elseif ($minPeople < 1 || $minPeople > 5000) {
            $notice = ['type' => 'danger', 'message' => 'حداقل تعداد افراد در روز باید بین ۱ تا ۵۰۰۰ باشد.'];
        } elseif ($activeFrom === 'invalid') {
            $notice = ['type' => 'danger', 'message' => 'تاریخ شروع را به شکل ۱۴۰۵/۰۷/۰۱ وارد کنید.'];
        } else {
            $conn->prepare('INSERT INTO attendance_rules (id, enabled, school_start, late_grace, absent_cutoff, min_people_per_day, active_from, updated_by, updated_at)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), school_start = VALUES(school_start), late_grace = VALUES(late_grace),
                    absent_cutoff = VALUES(absent_cutoff), min_people_per_day = VALUES(min_people_per_day),
                    active_from = VALUES(active_from), updated_by = VALUES(updated_by), updated_at = NOW()')
                ->execute([!empty($_POST['enabled']) ? 1 : 0, $start, $grace, $cutoff, $minPeople, $activeFrom, (string) $_SESSION['admin']['user']]);
            $notice = ['type' => 'success', 'message' => 'قوانین حضور و غیاب ذخیره شد.'];
        }
    } elseif (isset($_POST['apply_now'])) {
        $days = max(1, min(90, (int) ($_POST['days'] ?? 7)));
        $from = attendance_now()->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        try {
            $s = attendance_fill_register($conn, null, $from);
            $notice = ['type' => 'success', 'message' => sprintf(
                'اعمال شد: %d حاضر و %d غایب ثبت شد، %d مورد اصلاح شد و %d مورد که استاد ثبت کرده بود دست نخورد.',
                $s['present'], $s['absent'], $s['updated'], $s['skipped_manual'])];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('attendance apply: ' . $e->getMessage());
            $notice = ['type' => 'danger', 'message' => 'اعمال قوانین انجام نشد.'];
        }
    }
    $_SESSION['attendance_notice'] = $notice;
    header('Location: rules.php');
    exit;
}

$notice = $_SESSION['attendance_notice'] ?? null;
unset($_SESSION['attendance_notice']);
$rules = attendance_rules($conn);
$lateAfter = (new DateTime('2000-01-01 ' . $rules['school_start']))->modify('+' . (int) $rules['late_grace'] . ' minutes')->format('H:i');

// امروز
$today = attendance_now()->format('Y-m-d');
$st = $conn->prepare("SELECT codemeli, TIME(MIN(event_time)) t FROM attendance_events
    WHERE event_time >= ? AND event_time < DATE_ADD(?, INTERVAL 1 DAY) AND direction <> 'out' AND idpersonal IS NOT NULL
    GROUP BY codemeli");
$st->execute([$today, $today]);
$todaySeen = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$todayLate = count(array_filter($todaySeen, static fn($t) => attendance_arrival_status($t, $rules) === 'late'));
$todayWorking = isset(attendance_working_days($conn, $today, $today, (int) $rules['min_people_per_day'])[$today]);

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fa = static fn($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
$hm = static fn($t) => substr((string) $t, 0, 5);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>قوانین حضور و غیاب | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>.att-ltr { direction: ltr; text-align: right; }</style>
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
                        <h2 class="main-content-title tx-24 mg-b-5">قوانین حضور و غیاب</h2>
                        <p class="text-muted mb-0">ورودهای ثبت‌شده در دستگاه‌ها با این قوانین به حاضر، تأخیر یا غایب تبدیل و در ستون‌های «حضور و غیاب دستی» دفتر کلاسی ثبت می‌شوند.</p>
                    </div>
                    <div>
                        <a class="btn btn-outline-primary" href="devices.php"><i class="fe fe-cpu"></i> دستگاه‌ها و رکوردها</a>
                    </div>
                </div>

                <?php if ($notice) { ?>
                    <div class="alert alert-<?php echo $h($notice['type']) ?>"><?php echo $h($notice['message']) ?></div>
                <?php } ?>

                <div class="row row-sm">
                    <div class="col-xl-7">
                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-3">قوانین</h6>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                    <div class="form-group">
                                        <label class="d-flex align-items-center" style="gap:.5rem">
                                            <input type="checkbox" name="enabled" value="1" <?php echo (int) $rules['enabled'] ? 'checked' : '' ?>>
                                            <b>ثبت خودکار در دفتر کلاسی فعال باشد</b>
                                        </label>
                                        <small class="text-muted">تا این گزینه روشن نشود، دستگاه‌ها فقط رکورد جمع می‌کنند و چیزی در دفتر کلاسی ثبت نمی‌شود.</small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <label>ساعت شروع مدرسه</label>
                                            <input class="form-control att-ltr" name="school_start" value="<?php echo $h($hm($rules['school_start'])) ?>" placeholder="07:30" required>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>مهلت تأخیر (دقیقه)</label>
                                            <input class="form-control" type="number" min="0" max="240" name="late_grace" value="<?php echo (int) $rules['late_grace'] ?>" required>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>ساعت ثبت غیبت</label>
                                            <input class="form-control att-ltr" name="absent_cutoff" value="<?php echo $h($hm($rules['absent_cutoff'])) ?>" placeholder="09:00" required>
                                        </div>
                                    </div>
                                    <ul class="text-muted tx-13">
                                        <li>ورود تا ساعت <b class="att-ltr"><?php echo $h($fa($lateAfter)) ?></b> «حاضر» و بعد از آن «حاضر با تأخیر» حساب می‌شود.</li>
                                        <li>دانش‌آموزی که تا ساعت <b><?php echo $h($fa($hm($rules['absent_cutoff']))) ?></b> در هیچ دستگاهی ثبت نشده باشد «غایب» ثبت می‌شود. اگر بعداً وارد شود، خودکار به «حاضر» تغییر می‌کند.</li>
                                        <li>هر ورودِ آن روز برای همهٔ ستون‌های حضور و غیاب همان تاریخ در همهٔ کلاس‌های دانش‌آموز حساب می‌شود.</li>
                                        <li>هر خانه‌ای که استاد خودش ثبت یا اصلاح کند، دیگر خودکار تغییر نمی‌کند. ستون‌های قفل‌شده هم تغییر نمی‌کنند.</li>
                                    </ul>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label>حداقل تعداد افراد ثبت‌شده در روز</label>
                                            <input class="form-control" type="number" min="1" max="5000" name="min_people_per_day" value="<?php echo (int) $rules['min_people_per_day'] ?>" required>
                                            <small class="text-muted">اگر دستگاه‌ها در یک روز کمتر از این تعداد نفر را ثبت کنند (مثلاً دستگاه خاموش یا قطع بوده)، آن روز برای هیچ‌کس غیبت ثبت نمی‌شود.</small>
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>شروع از تاریخ (اختیاری)</label>
                                            <input class="form-control att-ltr" name="active_from" value="<?php echo $h($toJalali($rules['active_from'])) ?>" placeholder="1405/07/01">
                                            <small class="text-muted">روزهای قبل از این تاریخ هرگز خودکار پر نمی‌شوند.</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary" name="save_rules" value="1">ذخیرهٔ قوانین</button>
                                    <?php if ($rules['updated_at']) { ?>
                                        <span class="text-muted tx-12 mr-2">آخرین تغییر: <?php echo $h($rules['updated_by']) ?> — <?php echo jdate('Y/m/d H:i', strtotime($rules['updated_at'])) ?></span>
                                    <?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-3">امروز</h6>
                                <div class="row text-center">
                                    <div class="col"><div class="tx-22 font-weight-bold"><?php echo $fa(count($todaySeen)) ?></div><div class="text-muted tx-12">وارد شده</div></div>
                                    <div class="col"><div class="tx-22 font-weight-bold text-warning"><?php echo $fa($todayLate) ?></div><div class="text-muted tx-12">با تأخیر</div></div>
                                </div>
                                <p class="mt-3 mb-0 tx-13 <?php echo $todayWorking ? 'text-success' : 'text-muted' ?>">
                                    <?php echo $todayWorking
                                        ? 'دستگاه‌ها امروز فعال بوده‌اند؛ بعد از ساعت ثبت غیبت، غایبان ثبت می‌شوند.'
                                        : 'امروز هنوز به حداقل تعداد افراد نرسیده است؛ فعلاً غیبتی ثبت نمی‌شود.' ?>
                                </p>
                            </div>
                        </div>

                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-2">اعمال روی روزهای گذشته</h6>
                                <p class="text-muted tx-13">دفتر هر کلاس هنگام باز شدن خودکار به‌روز می‌شود. برای به‌روزرسانی همهٔ کلاس‌ها یکجا (مثلاً بعد از بارگذاری فایل اکسل)، این دکمه را بزنید.</p>
                                <form method="post" class="d-flex align-items-center flex-wrap" style="gap:.5rem">
                                    <input type="hidden" name="csrf" value="<?php echo $h($csrf) ?>">
                                    <span>روزهای اخیر:</span>
                                    <input class="form-control" style="width:6rem" type="number" min="1" max="90" name="days" value="7">
                                    <button class="btn btn-outline-primary" name="apply_now" value="1" <?php echo (int) $rules['enabled'] ? '' : 'disabled title="ابتدا ثبت خودکار را فعال کنید"' ?>>اعمال روی همهٔ کلاس‌ها</button>
                                </form>
                            </div>
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
