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
if (!AdminCanUsePermissionScope('attendance.report', ['administrator'])) {
    header('location: ' . url('admin/index.php'));
    die();
}

// ---------------------------------------------------------
// ورودی‌ها: تاریخ شمسی، کلاس، وضعیت
// ---------------------------------------------------------
$today = attendance_now()->format('Y-m-d');
$date = $today;
$jInput = attendance_normalize_digits((string) ($_GET['date'] ?? ''));
if (preg_match('#^(1[34]\d\d)[/-](\d{1,2})[/-](\d{1,2})$#', $jInput, $m)) {
    [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    if (checkdate($gm, $gd, $gy)) $date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}
$toJalali = static function (string $g): string {
    [$y, $m, $d] = array_map('intval', explode('-', $g));
    [$jy, $jm, $jd] = gregorian_to_jalali($y, $m, $d);
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
};
$shift = static fn(int $days) => (new DateTime($date))->modify(($days > 0 ? '+' : '') . $days . ' day')->format('Y-m-d');
$classId = (int) ($_GET['class'] ?? 0) ?: null;
$statusFilter = isset(ATTENDANCE_DAY_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';

$report = attendance_day_report($conn, $date, $classId);
$rows = $statusFilter === '' ? $report['rows']
    : array_values(array_filter($report['rows'], static fn($r) => $r['status'] === $statusFilter));
$classes = $conn->query('SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
$teacherLabels = ['1' => 'حاضر', '0' => 'غایب'];
$hm = static fn($t) => $t ? substr((string) $t, 0, 5) : '';

// ---------------------------------------------------------
// خروجی اکسل
// ---------------------------------------------------------
if (isset($_GET['export'])) {
    $header = ['ردیف', 'کد ملی', 'نام', 'نام خانوادگی', 'کلاس', 'ورود', 'خروج', 'وضعیت', 'ثبت دستی استاد'];
    $data = [];
    foreach ($rows as $i => $r) {
        $data[] = array_map(static fn($v) => trim((string) $v), [$i + 1, $r['codemeli'], $r['fname'], $r['lname'], $r['classes'],
            $hm($r['first_in']), $hm($r['last_out']), ATTENDANCE_DAY_STATUSES[$r['status']], $teacherLabels[$r['teacher']] ?? '']);
    }
    $name = 'attendance-' . str_replace('/', '-', $toJalali($date));
    if (class_exists('ZipArchive')) {
        require_once '../../vendor/autoload.php';
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle('حضور و غیاب');
        $sheet->fromArray($header, null, 'A1');
        // national codes stay text so their leading zeros survive
        foreach ($data as $r => $row) {
            foreach ($row as $c => $v) {
                $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $v,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($data as $row) {
            $row[1] = '="' . $row[1] . '"';
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fa = static fn($n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
$query = static fn(array $over) => '?' . http_build_query(array_filter(array_merge(
    ['date' => $toJalali($date), 'class' => $classId, 'status' => $statusFilter], $over), static fn($v) => $v !== null && $v !== ''));
$badges = ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'no_record' => 'secondary'];
$weekday = jdate('l', strtotime($date));

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>گزارش روزانه حضور و غیاب | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .att-ltr { direction: ltr; unicode-bidi: embed; }
        .att-stat { display: block; text-decoration: none !important; border-radius: .5rem; padding: .75rem; border: 2px solid transparent; }
        .att-stat.active { border-color: currentColor; }
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
                        <h2 class="main-content-title tx-24 mg-b-5">گزارش روزانه حضور و غیاب</h2>
                        <p class="text-muted mb-0"><?php echo $h($weekday) ?> <?php echo $h($fa($toJalali($date))) ?></p>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:.5rem">
                        <a class="btn btn-outline-success" href="<?php echo $h($query(['export' => 1])) ?>"><i class="fe fe-download"></i> خروجی اکسل</a>
                        <a class="btn btn-outline-primary" href="rules.php"><i class="fe fe-sliders"></i> قوانین</a>
                    </div>
                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <form method="get" class="d-flex flex-wrap align-items-end" style="gap:.75rem">
                            <div>
                                <label class="mb-1">تاریخ</label>
                                <div class="d-flex" style="gap:.25rem">
                                    <a class="btn btn-light" title="روز قبل" href="<?php echo $h($query(['date' => $toJalali($shift(-1))])) ?>"><i class="fe fe-chevron-right"></i></a>
                                    <input class="form-control att-ltr" style="width:8rem" name="date" value="<?php echo $h($toJalali($date)) ?>" placeholder="1405/07/07">
                                    <?php if ($date < $today) { ?>
                                        <a class="btn btn-light" title="روز بعد" href="<?php echo $h($query(['date' => $toJalali($shift(1))])) ?>"><i class="fe fe-chevron-left"></i></a>
                                    <?php } ?>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1">کلاس</label>
                                <select class="form-control" name="class">
                                    <option value="">همهٔ کلاس‌ها</option>
                                    <?php foreach ($classes as $id => $name) { ?>
                                        <option value="<?php echo (int) $id ?>" <?php echo $classId === (int) $id ? 'selected' : '' ?>><?php echo $h($name) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php if ($statusFilter !== '') { ?><input type="hidden" name="status" value="<?php echo $h($statusFilter) ?>"><?php } ?>
                            <button class="btn btn-primary">نمایش</button>
                            <?php if ($date !== $today) { ?>
                                <a class="btn btn-light" href="<?php echo $h($query(['date' => $toJalali($today)])) ?>">امروز</a>
                            <?php } ?>
                        </form>
                    </div>
                </div>

                <?php if (!$report['working'] && $report['decided']) { ?>
                    <div class="alert alert-secondary">
                        در این روز دستگاه‌ها کمتر از <?php echo $fa((int) $report['rules']['min_people_per_day']) ?> نفر را ثبت کرده‌اند (تعطیل بوده یا دستگاه کار نکرده)، پس برای کسی غیبت در نظر گرفته نشده است.
                    </div>
                <?php } elseif (!$report['decided']) { ?>
                    <div class="alert alert-info">
                        هنوز به ساعت ثبت غیبت (<?php echo $h($fa($hm($report['rules']['absent_cutoff']))) ?>) نرسیده‌ایم؛ کسانی که هنوز وارد نشده‌اند «بدون رکورد» هستند.
                    </div>
                <?php } ?>

                <div class="row row-sm text-center mb-3">
                    <div class="col-6 col-md">
                        <a class="att-stat bg-white text-dark card custom-card mb-2 <?php echo $statusFilter === '' ? 'active' : '' ?>" href="<?php echo $h($query(['status' => ''])) ?>">
                            <div class="tx-24 font-weight-bold"><?php echo $fa(count($report['rows'])) ?></div><div class="tx-12">همه</div>
                        </a>
                    </div>
                    <?php foreach (ATTENDANCE_DAY_STATUSES as $key => $label) { ?>
                        <div class="col-6 col-md">
                            <a class="att-stat bg-white text-<?php echo $badges[$key] ?> card custom-card mb-2 <?php echo $statusFilter === $key ? 'active' : '' ?>" href="<?php echo $h($query(['status' => $key])) ?>">
                                <div class="tx-24 font-weight-bold"><?php echo $fa($report['totals'][$key]) ?></div><div class="tx-12"><?php echo $label ?></div>
                            </a>
                        </div>
                    <?php } ?>
                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered text-nowrap mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>دانش‌آموز</th>
                                    <th>کد ملی</th>
                                    <th>کلاس</th>
                                    <th>ورود</th>
                                    <th>خروج</th>
                                    <th>وضعیت</th>
                                    <th title="آنچه استاد در ستون‌های حضور و غیاب همین روز خودش ثبت کرده است">ثبت دستی استاد</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$rows) { ?>
                                    <tr><td colspan="8" class="text-center text-muted">موردی نیست.</td></tr>
                                <?php } ?>
                                <?php foreach ($rows as $i => $r) { ?>
                                    <tr>
                                        <td><?php echo $fa($i + 1) ?></td>
                                        <td><?php echo $h(trim($r['fname'] . ' ' . $r['lname'])) ?></td>
                                        <td class="att-ltr"><?php echo $h($r['codemeli']) ?></td>
                                        <td><?php echo $h($r['classes']) ?></td>
                                        <td class="att-ltr"><?php echo $h($fa($hm($r['first_in']))) ?></td>
                                        <td class="att-ltr"><?php echo $h($fa($hm($r['last_out']))) ?></td>
                                        <td><span class="badge badge-<?php echo $badges[$r['status']] ?>"><?php echo ATTENDANCE_DAY_STATUSES[$r['status']] ?></span></td>
                                        <td>
                                            <?php if ($r['teacher'] !== null) {
                                                $differs = ($r['teacher'] === '1') !== in_array($r['status'], ['present', 'late'], true) && $r['status'] !== 'no_record'; ?>
                                                <span class="<?php echo $differs ? 'text-danger font-weight-bold' : '' ?>" <?php echo $differs ? 'title="با رکورد دستگاه یکی نیست"' : '' ?>>
                                                    <?php echo $teacherLabels[$r['teacher']] ?>
                                                </span>
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
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
