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

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

if ($_SESSION['admin']['adminrole'] != 0 ){
    echo "دسترسی ندارید ";
    die();
}

// Allowed admin roles
$isadmin = in_array((int)($_SESSION['admin']['adminrole'] ?? -1), [0, 51, 52, 53], true);

$users = [];

function faToEnDigits(string $str): string {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, $str);
}

// -----------------------------
// Filter + Date (Apply required)
// -----------------------------
$applied = isset($_POST['apply']); // ONLY run query when Apply is clicked

$filter = trim($_POST['filter'] ?? '');              // '' | all | active | canceled | suspended | cancel_support
$has_operator = trim($_POST['has_operator'] ?? '');  // '' | 1 | 0 (only for active)
$startJalali = trim($_POST['startdate'] ?? '');
$endJalali   = trim($_POST['enddate'] ?? '');

// Default values for inputs (optional)
if ($startJalali === '') $startJalali = jdate('Y/m/d');
if ($endJalali === '')   $endJalali   = jdate('Y/m/d');

function toGregorianDate(?string $input): ?string {
    $s = trim((string)$input);
    if ($s === '') return null;

    $s = faToEnDigits($s);
    $s = str_replace('-', '/', $s);

    if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $s)) return null;

    [$y, $m, $d] = array_map('intval', explode('/', $s));

    // If picker is actually giving Gregorian, don't convert
    if ($y >= 1700) {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    // Otherwise treat as Jalali
    $g = jalali_to_gregorian($y, $m, $d);
    return sprintf('%04d-%02d-%02d', $g[0], $g[1], $g[2]);
}

$startG = toGregorianDate($startJalali);
$endG   = toGregorianDate($endJalali);

// Run query ONLY when apply clicked
if ($isadmin && $applied) {

    if (!$startG || !$endG) {
        $_SESSION['message'] = 'بازه تاریخ را درست وارد کنید';
    } elseif (!in_array($filter, ['all', 'active', 'canceled', 'suspended', 'cancel_support'], true)) {
        $_SESSION['message'] = 'ابتدا یک فیلتر انتخاب کنید';
    } else {

        // Base: date interval on dbpersonal.datecreated
        $sql = "
            SELECT p.*
            FROM dbpersonal p
            WHERE p.datecreated BETWEEN ? AND ?
        ";
        $params = [$startG, $endG];

        if ($filter === 'suspended') {
            // تعلیق
            $sql .= " AND p.suspension_mode = 1 ";
        }

        if ($filter === 'active') {
            // فعال: dbcourse.activityuser=1 AND codtbl_course.activecourse=1
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM dbcourse c
                    JOIN codtbl_course cc ON cc.codecourse = c.codecourse
                    WHERE c.username = p.codemeli
                      AND c.activityuser = 1
                      AND cc.activecourse = 1
            ";

            // با پشتیبان / بدون پشتیبان (optional)
            if ($has_operator === '1' || $has_operator === '0') {
                $sql .= " AND c.has_operator = ? ";
                $params[] = (int)$has_operator;
            }

            $sql .= " ) ";
        }

        if ($filter === 'canceled') {
            $sql .= "
            AND EXISTS (
                SELECT 1
                FROM dbcourse c0
                WHERE c0.username = p.codemeli
            )
            AND NOT EXISTS (
                SELECT 1
                FROM dbcourse c
                JOIN codtbl_course cc ON cc.codecourse = c.codecourse
                WHERE c.username = p.codemeli
                  AND cc.activecourse = 1
                  AND c.activityuser = 1
            )
            ";
        }

        // نوع جدید: لغو پشتیبانی
        if ($filter === 'cancel_support') {
            // 1. حداقل یک درس در dbcourse داشته باشد
            // 2. حداقل یک درس داشته باشد که has_operator آن 0 باشد
            // 3. درخواست لغو تایید شده (وضعیت 1) داشته باشد
            $sql .= "
            AND EXISTS (
                SELECT 1
                FROM dbcourse c0
                WHERE c0.username = p.codemeli
            )
            AND EXISTS (
                SELECT 1
                FROM dbcourse c1
                WHERE c1.username = p.codemeli
                  AND c1.has_operator = 0
            )
            AND EXISTS (
                SELECT 1
                FROM cancel_student_operator_request req1
                INNER JOIN (
                    SELECT student_username, MAX(created_at) as max_date
                    FROM cancel_student_operator_request
                    WHERE requested_mode = 1 AND status = 1
                    GROUP BY student_username
                ) req2 ON req1.student_username = req2.student_username AND req1.created_at = req2.max_date
                WHERE req1.student_username = p.codemeli
            )
            ";
        }

        $sql .= " ORDER BY p.datecreated DESC ";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}

//dd($_POST);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>لیست کاربران | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div style="padding:10px;">
                    <style>
                        .filter-btn.active {
                            border-color: #1cc88a !important;
                            background: #1cc88a !important;
                            color: #fff !important;
                        }
                        .subfilter-btn.active {
                            border-color: #4e73df !important;
                            background: #4e73df !important;
                            color: #fff !important;
                        }
                    </style>
                </div>

                <div class="row row-sm">

                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class="mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php } ?>

                    <?php if (!$isadmin) { ?>
                        <div class="col-12">
                            <div class="alert alert-danger">شما دسترسی مشاهده این صفحه را ندارید.</div>
                        </div>
                    <?php } else { ?>

                        <div class="col-12">

                            <!-- Filter + Date + Apply (integrated) -->
                            <form method="post" class="card custom-card mb-3">
                                <div class="card-body col-12 flex-row d-flex " >

                                    <!-- Hidden values controlled by buttons -->
                                    <div class="col-6 d-flex flex-column justify-content-center align-content-center">
                                        <input type="hidden" name="filter" id="filterInput" value="<?= htmlspecialchars($filter) ?>">
                                        <input type="hidden" name="has_operator" id="hasOpInput" value="<?= htmlspecialchars($has_operator) ?>">

                                        <!-- Main filters -->
                                        <div class="d-flex flex-wrap gap-2 mb-3 justify-content-center ">
                                            <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="all">همه</button>
                                            <button type="button" class="btn btn-outline-success filter-btn" data-filter="active">فعال‌ها</button>
                                            <button type="button" class="btn btn-outline-danger filter-btn" data-filter="canceled">انصرافی‌ها</button>
                                            <button type="button" class="btn btn-outline-warning filter-btn" data-filter="suspended">تعلیق</button>
                                            <button type="button" class="btn btn-outline-info filter-btn" data-filter="cancel_support">لغو پشتیبانی</button>
                                        </div>

                                        <!-- Active subfilters (only when active selected) -->
                                        <div id="activeSubfilters" class="d-none">
                                            <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                                                <button type="button" class="btn btn-outline-primary subfilter-btn" data-hasop="">همه فعال‌ها</button>
                                                <button type="button" class="btn btn-outline-primary subfilter-btn" data-hasop="1">با پشتیبان</button>
                                                <button type="button" class="btn btn-outline-primary subfilter-btn" data-hasop="0">بدون پشتیبان</button>
                                            </div>
                                            <small class="text-muted d-block mb-2 text-center">* زیر-فیلتر فقط روی «فعال‌ها» اعمال می‌شود.</small>
                                        </div>

                                    </div>

                                    <div class="col-4 align-content-center justify-content-center">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-6">
                                                <label class="form-label">از تاریخ <span style="color:red">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                                    <input class="form-control fc-datepicker" type="text" name="startdate"
                                                           value="<?= htmlspecialchars($startJalali) ?>">
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <label class="form-label">تا تاریخ <span style="color:red">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                                    <input class="form-control fc-datepicker" type="text" name="enddate"
                                                           value="<?= htmlspecialchars($endJalali) ?>">
                                                </div>
                                            </div>

                                            <!-- APPLY -->
                                        </div>
                                    </div>
                                    <!-- Date range -->
                                    <div class="col-2 justify-items-center align-items-center d-flex ">
                                        <button type="submit" name="apply" value="1" class="btn btn-primary btn" style="height:30px;">
                                            اعمال (Apply)
                                        </button>
                                    </div>

                                </div>
                            </form>

                            <!-- Results -->
                            <div class="card custom-card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                            <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>کد ملی</th>
                                                <th>تاریخ ثبت نام</th>
                                            </tr>
                                            </thead>

                                            <tbody>
                                            <?php
                                            $colspan = 4; // changed from 7 since there are only 4 columns

                                            if (!$applied) { ?>
                                                <tr class="align-middle text-center">
                                                    <td colspan="<?= $colspan ?>">ابتدا فیلتر را انتخاب کنید، بازه تاریخ را وارد کنید و سپس روی «اعمال» بزنید.</td>
                                                </tr>
                                            <?php } elseif (empty($users)) { ?>
                                                <tr class="align-middle text-center">
                                                    <td colspan="<?= $colspan ?>">موردی یافت نشد</td>
                                                </tr>
                                            <?php } else { ?>

                                                <?php foreach ($users as $u) {

                                                    $dateText = 'مشخص نشده';

                                                    if (!empty($u->datesupport)) {
                                                        $dateText = $u->datesupport; // expected YYYY-MM-DD
                                                    }

                                                    // datecreated -> jalali
                                                    $jalaliCreated = '';
                                                    if (!empty($u->datecreated) && strpos($u->datecreated, '-') !== false) {
                                                        [$gy, $gm, $gd] = explode('-', $u->datecreated);
                                                        $jalaliCreated = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                    }
                                                    ?>
                                                    <tr class="align-middle text-center">
                                                        <td>
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open('suporting.php?Id=<?= htmlspecialchars($u->codemeli) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                <i class="fa fa-key"></i>
                                                            </div>
                                                        </td>

                                                        <td><?= htmlspecialchars($u->fname ." ". $u->lname ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($u->codemeli ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($jalaliCreated) ?></td>

                                                    </tr>
                                                <?php } ?>

                                            <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div>

                    <?php } ?>

                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>

<script>
    (function () {
        const filterInput = document.getElementById('filterInput');
        const hasOpInput = document.getElementById('hasOpInput');

        const filterBtns = document.querySelectorAll('.filter-btn');
        const subBtns = document.querySelectorAll('.subfilter-btn');
        const activeBox = document.getElementById('activeSubfilters');

        function setActiveButton(btns, predicate) {
            btns.forEach(b => b.classList.toggle('active', predicate(b)));
        }

        function toggleActiveSubfilters() {
            const isActive = (filterInput.value === 'active');
            activeBox.classList.toggle('d-none', !isActive);

            // if not active, reset subfilter
            if (!isActive) {
                hasOpInput.value = '';
                setActiveButton(subBtns, b => b.getAttribute('data-hasop') === '');
            }
        }

        // init from server values
        setActiveButton(filterBtns, b => b.getAttribute('data-filter') === filterInput.value);
        setActiveButton(subBtns, b => (b.getAttribute('data-hasop') ?? '') === hasOpInput.value);
        toggleActiveSubfilters();

        // main filter buttons (no submit)
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterInput.value = this.getAttribute('data-filter') || '';
                setActiveButton(filterBtns, b => b === this);

                // default subfilter when switching to active
                if (filterInput.value === 'active' && (hasOpInput.value !== '1' && hasOpInput.value !== '0')) {
                    hasOpInput.value = '';
                    setActiveButton(subBtns, b => (b.getAttribute('data-hasop') ?? '') === '');
                }

                toggleActiveSubfilters();
            });
        });

        // active subfilters (no submit)
        subBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                hasOpInput.value = this.getAttribute('data-hasop') ?? '';
                setActiveButton(subBtns, b => b === this);
            });
        });
    })();
</script>

</body>
</html>
