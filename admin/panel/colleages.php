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
if (($_SESSION['admin']['ip'] ?? '') !== GetIp() && ($_SESSION['admin']['os'] ?? '') != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($re['session_id'] ?? '')) {
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
$sql = '';
$params = [];

function faToEnDigits(string $str): string {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, $str);
}

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

// -----------------------------
// Type + Date (Apply required)
// -----------------------------
$applied = isset($_POST['apply']); // ONLY run query when Apply is clicked

$type = trim($_POST['type'] ?? ''); // '' | teachers | staff | external
$startJalali = trim($_POST['startdate'] ?? '');
$endJalali   = trim($_POST['enddate'] ?? '');

// Default values for inputs
if ($startJalali === '') $startJalali = jdate('Y/m/d');
if ($endJalali === '')   $endJalali   = jdate('Y/m/d');

$startG = toGregorianDate($startJalali);
$endG   = toGregorianDate($endJalali);

if ($isadmin && $applied) {

    if (!in_array($type, ['teachers', 'staff', 'external'], true)) {
        $_SESSION['message'] = 'ابتدا نوع را انتخاب کنید (اساتید / کارمندان / همکاران بیرونی).';
    } else {

        if ($type === 'teachers') {
            // اساتید: dbadmin where adminrole=3 (NO date filter)
            $sql = "
                SELECT a.*
                FROM dbadmin a
                WHERE a.adminrole = 3
                ORDER BY a.idadmin DESC
            ";
            $params = [];
        }

        if ($type === 'staff') {
            // کارمندان: dbpersonal where personalcode not null (WITH date filter)
            if (!$startG || !$endG) {
                $_SESSION['message'] = 'بازه تاریخ را درست وارد کنید';
            } else {
                $sql = "
                    SELECT p.*
                    FROM dbpersonal p
                    WHERE p.personalcode IS NOT NULL
                      AND p.datecreated BETWEEN ? AND ?
                    ORDER BY p.datecreated DESC
                ";
                $params = [$startG, $endG];
            }
        }

        if ($type === 'external') {
            // under construction
            $_SESSION['message'] = 'بخش «همکاران بیرونی» در حال توسعه است.';
            $users = [];
            $sql = '';
            $params = [];
        }

        if ($sql !== '') {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        }
    }
}


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
    <title>همکاران | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div class="page">
    <div class="main-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div style="padding:10px;">
                    <style>
                        .type-btn.active {
                            border-color: #1cc88a !important;
                            background: #1cc88a !important;
                            color: #fff !important;
                        }
                        .date-disabled {
                            opacity: 0.5;
                            pointer-events: none;
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

                            <!-- Type + Date + Apply -->
                            <form method="post" class="card custom-card mb-3">
                                <div class="card-body col-12 flex-row d-flex">

                                    <input type="hidden" name="type" id="typeInput" value="<?= htmlspecialchars($type) ?>">

                                    <!-- Types -->
                                    <div class="col-5 d-flex flex-column justify-content-center align-content-center">
                                        <div class="d-flex flex-wrap gap-2 mb-2 justify-content-center">
                                            <button type="button" class="btn btn-outline-success type-btn" data-type="teachers">اساتید</button>
                                            <button type="button" class="btn btn-outline-primary type-btn" data-type="staff">کارمندان</button>
                                            <button type="button" class="btn btn-outline-warning type-btn" data-type="external">همکاران بیرونی</button>
                                        </div>
                                        <small class="text-muted text-center">
                                            * تاریخ فقط روی «کارمندان / همکاران» اعمال می‌شود.
                                        </small>
                                    </div>

                                    <!-- Date range (disabled for teachers) -->
                                    <div class="col-5 align-content-center justify-content-center" id="dateBox">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-6">
                                                <label class="form-label">از تاریخ <span style="color:red">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                                    <input class="form-control fc-datepicker" type="text" name="startdate" id="startdate"
                                                           value="<?= htmlspecialchars($startJalali) ?>">
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <label class="form-label">تا تاریخ <span style="color:red">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                                    <input class="form-control fc-datepicker" type="text" name="enddate" id="enddate"
                                                           value="<?= htmlspecialchars($endJalali) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- APPLY -->
                                    <div class="col-2 justify-items-center align-items-center d-flex">
                                        <button type="submit" name="apply" value="1" class="btn btn-primary" style="height:30px;">
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
                                                <th>نام</th>
                                                <th>شناسه</th>
                                                <th>تاریخ</th>
                                            </tr>
                                            </thead>

                                            <tbody>
                                            <?php
                                            $colspan = 4;

                                            if (!$applied) { ?>
                                                <tr class="align-middle text-center">
                                                    <td colspan="<?= $colspan ?>">ابتدا نوع را انتخاب کنید و سپس روی «اعمال» بزنید.</td>
                                                </tr>
                                            <?php } elseif ($type === 'external') { ?>
                                                <tr class="align-middle text-center">
                                                    <td colspan="<?= $colspan ?>">همکاران بیرونی در حال توسعه است.</td>
                                                </tr>
                                            <?php } elseif (empty($users)) { ?>
                                                <tr class="align-middle text-center">
                                                    <td colspan="<?= $colspan ?>">موردی یافت نشد</td>
                                                </tr>
                                            <?php } else { ?>

                                                <?php foreach ($users as $u) {

                                                    // name
                                                    $name =
                                                        trim(($u->full_name ?? '') ?: '') ? ($u->full_name ?? '') :
                                                            trim(($u->fname ?? '') . ' ' . ($u->lname ?? ''));

                                                    if ($name === '') $name = ($u->name ?? '-') ?: '-';

                                                    // identifier
                                                    $identifier = '-';
                                                    if ($type === 'teachers') {
                                                        $identifier = $u->username ?? $u->user ?? $u->email ?? ($u->idadmin ?? '-');
                                                    } else {
                                                        $identifier = $u->personalcode ?? ($u->codemeli ?? '-');
                                                    }

                                                    // date (only meaningful for staff)
                                                    $dateShow = '-';
                                                    if ($type === 'staff') {
                                                        $dateShow = '';
                                                        if (!empty($u->datecreated) && strpos($u->datecreated, '-') !== false) {
                                                            [$gy, $gm, $gd] = explode('-', $u->datecreated);
                                                            $dateShow = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                        }
                                                        if ($dateShow === '') $dateShow = '-';
                                                    }
                                                    ?>
                                                    <tr class="align-middle text-center">
                                                        <td>#</td>
                                                        <td><?= htmlspecialchars($name) ?></td>
                                                        <td><?= htmlspecialchars((string)$identifier) ?></td>
                                                        <td><?= htmlspecialchars((string)$dateShow) ?></td>
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
        const typeInput = document.getElementById('typeInput');
        const typeBtns = document.querySelectorAll('.type-btn');

        const dateBox = document.getElementById('dateBox');
        const startdate = document.getElementById('startdate');
        const enddate = document.getElementById('enddate');

        function setActive(btns, predicate) {
            btns.forEach(b => b.classList.toggle('active', predicate(b)));
        }

        function toggleDateUI() {
            const isTeachers = (typeInput.value === 'teachers');
            // disable/hide date for teachers
            if (isTeachers) {
                dateBox.classList.add('date-disabled');
                startdate.setAttribute('disabled', 'disabled');
                enddate.setAttribute('disabled', 'disabled');
            } else {
                dateBox.classList.remove('date-disabled');
                startdate.removeAttribute('disabled');
                enddate.removeAttribute('disabled');
            }
        }

        // init
        setActive(typeBtns, b => b.getAttribute('data-type') === typeInput.value);
        toggleDateUI();

        // click
        typeBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                typeInput.value = this.getAttribute('data-type') || '';
                setActive(typeBtns, b => b === this);
                toggleDateUI();
            });
        });
    })();
</script>

</body>
</html>
