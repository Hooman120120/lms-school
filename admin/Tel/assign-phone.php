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
if (!AdminCanUsePermissionScope('public-relations.safe-call.assignment', ['permision_managecall'])) {
    die('دسترسی ندارید');
}

function normalize_persian(?string $str): string
{
    if (!$str) {
        return '';
    }

    $search = ['ي', 'ك', '‌'];
    $replace = ['ی', 'ک', ''];

    return trim(str_replace($search, $replace, $str));
}

// ========== دریافت اطلاعات اپراتورها ==========
$sql_op = 'SELECT * FROM dbadmin WHERE adminrole = 6';
$stmt_op = $conn->prepare($sql_op);
$stmt_op->execute();
$operators = $stmt_op->fetchAll(PDO::FETCH_OBJ);

// ========== تابع کمکی برای گرفتن نام اپراتور ==========
function getOperatorName($dakheli, $operators) {
    foreach ($operators as $op) {
        if ($op->dakheli == $dakheli) {
            return $op->username;
        }
    }
    return 'نامشخص';
}

// MOVE THIS TO THE ABSOLUTE TOP (Right after dbconfig.php)
if (isset($_POST['state_ajax'])) {
    $state = $_POST['state_ajax'];
    // Use a prepared statement for security!
    $res = $conn->prepare("SELECT city.name AS city_name, city.id FROM city JOIN state ON state.id = city.state_id WHERE state.name = ?");
    $res->execute([$state]);
    $cities = $res->fetchAll(PDO::FETCH_ASSOC);

    $final = '<option value="">انتخاب شهر</option>';
    foreach ($cities as $city) {
        $name = $city['city_name'];
        $id = $city['id'];
        $final .= "<option value=" . $id . ">$name</option>";
    }
    echo $final;
    exit; // Stop execution here so no other HTML is sent
}

if (isset($_POST['city_ajax'])) {
    global $conn;
    if ($_POST['city_ajax']) {

        // گرفتن مدارس آن شهر
        $res = $conn->prepare("SELECT * FROM schools WHERE city_id = ? ORDER BY school_name ASC");
        $res->execute([$_POST['city_ajax']]);
        $schools = $res->fetchAll(PDO::FETCH_OBJ);

        $options = '<option value="">انتخاب مدرسه</option>';
        foreach ($schools as $school) {
            $name = $school->school_name;
            $options .= "<option value=\"$school->id\">$name</option>";
        }

        echo $options;
    } else {
        echo '<option value="">مدرسه‌ای یافت نشد</option>';
    }

    exit;
}

// ---------- جایگزین کاملِ بلوک انتخاب افراد ----------
$persons = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_action']) && !isset($_POST['state_ajax']) && !isset($_POST['city_ajax']) && !isset($_POST['confirmAssign'])) {
    global $conn;
    $where = [];
    $params = [];

    // فقط در صورت ارسال id شهر/مدرسه، نام را از جداول مرتبط بگیر
    $cityName = null;
    $schoolName = null;

    if (!empty($_POST['city'])) {
        $stmt = $conn->prepare("SELECT name FROM city WHERE id = ?");
        $stmt->execute([$_POST['city']]);
        $cityName = $stmt->fetchColumn();
    }

    if (!empty($_POST['school'])) {
        $stmtt = $conn->prepare("SELECT school_name FROM schools WHERE id = ?");
        $stmtt->execute([$_POST['school']]);
        $schoolName = $stmtt->fetchColumn();
    }

    // فیلتر استان (اختیاری)
    if (!empty($_POST['state'])) {
        $state = normalize_persian($_POST['state']);
        $where[] = "state = ?";
        $params[] = $state;
    }

    // فیلتر شهر (اختیاری)
    if (!empty($cityName)) {
        $city = normalize_persian($cityName);
        $where[] = "city = ?";
        $params[] = $city;
    }

    // فیلتر مدرسه (اختیاری)
    if (!empty($schoolName)) {
        $school = normalize_persian($schoolName);
        $where[] = "school = ?";
        $params[] = $school;
    }

    // جنسیت (اختیاری)
    if (!empty($_POST['gender']) && $_POST['gender'] !== 'both') {
        $where[] = "gender = ?";
        $params[] = $_POST['gender'];
    }

    // پایه (اختیاری)
    if (!empty($_POST['grade'])) {
        $where[] = "grade = ?";
        $params[] = $_POST['grade'];
    }

    // رشته (اختیاری — از LIKE استفاده می‌کنیم چون ممکن است کاربر عبارت جزئی بده)
    if (!empty($_POST['major'])) {
        $where[] = "major LIKE ?";
        $params[] = "%" . $_POST['major'] . "%";
    }

    $whereSql = "";
    if (!empty($where)) {
        $whereSql = " WHERE " . implode(" AND ", $where);
    }

    // تعداد کل رکوردهای مطابق فیلتر
    $countSql = "SELECT COUNT(*) FROM temporary_personal" . $whereSql;
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $limit = !empty($_POST['count']) ? intval($_POST['count']) : 500;

    // ---------- روش نمونه‌گیری تصادفی بهینه ----------
    if ($total === 0) {
        $persons = [];
    } else {
        // اگر تعداد کل کمتر یا مساوی limit است، همه را برگردان و شافل کن
        if ($total <= $limit) {
            $selectSql = "SELECT * FROM temporary_personal" . $whereSql . " ORDER BY id ASC";
            $selectStmt = $conn->prepare($selectSql);
            $selectStmt->execute($params);
            $persons = $selectStmt->fetchAll(PDO::FETCH_OBJ);

            // شافل کردن همه رکوردها
            if (!empty($persons) && count($persons) > 1) {
                shuffle($persons);
            }
        } else {
            // ====== روش پیشنهادی: نمونه‌گیری تصادفی با توزیع عادلانه بر اساس شهر ======
            // ابتدا تعداد شهرهای موجود را بدست می‌آوریم
            $cityCountSql = "SELECT COUNT(DISTINCT city) FROM temporary_personal" . $whereSql;
            $cityCountStmt = $conn->prepare($cityCountSql);
            $cityCountStmt->execute($params);
            $cityCount = (int)$cityCountStmt->fetchColumn();

            if ($cityCount > 0 && $cityCount < $limit) {
                // محاسبه تعداد نمونه از هر شهر
                $perCity = max(1, floor($limit / $cityCount));
                $remainder = $limit % $cityCount;

                // دریافت لیست شهرها
                $cityListSql = "SELECT DISTINCT city FROM temporary_personal" . $whereSql . " ORDER BY city";
                $cityListStmt = $conn->prepare($cityListSql);
                $cityListStmt->execute($params);
                $cities = $cityListStmt->fetchAll(PDO::FETCH_COLUMN);

                $persons = [];
                $cityIndex = 0;

                // از هر شهر به تعداد مشخص شده نمونه برداریم
                foreach ($cities as $city) {
                    $count = $perCity;
                    if ($cityIndex < $remainder) {
                        $count++; // یک عدد اضافی به شهرهای اول
                    }

                    // دریافت نمونه‌های تصادفی از این شهر
                    $cityWhere = $whereSql . ($whereSql ? " AND" : " WHERE") . " city = ?";
                    $cityParams = array_merge($params, [$city]);

                    $citySelectSql = "SELECT * FROM temporary_personal" . $cityWhere . " ORDER BY RAND() LIMIT " . intval($count);
                    $citySelectStmt = $conn->prepare($citySelectSql);
                    $citySelectStmt->execute($cityParams);
                    $cityPersons = $citySelectStmt->fetchAll(PDO::FETCH_OBJ);

                    $persons = array_merge($persons, $cityPersons);
                    $cityIndex++;
                }

                // شافل کردن نهایی
                shuffle($persons);
            } else {
                // اگر شهر وجود نداشت یا تعداد شهرها زیاد بود، از روش RAND استفاده کن
                // استفاده از intval برای امنیت
                $safeLimit = intval($limit);
                $selectSql = "SELECT * FROM temporary_personal" . $whereSql . " ORDER BY RAND() LIMIT " . $safeLimit;
                $selectStmt = $conn->prepare($selectSql);
                $selectStmt->execute($params);
                $persons = $selectStmt->fetchAll(PDO::FETCH_OBJ);
            }
        }
    }

    // ========== بررسی وضعیت تخصیص برای هر رکورد ==========
    if (!empty($persons)) {
        // دریافت لیست شماره‌های تخصیص داده شده
        $phones = array_map(function($p) {
            return $p->phone;
        }, $persons);

        $phonePlaceholders = implode(',', array_fill(0, count($phones), '?'));

        $checkSql = "SELECT mob1, dakheli FROM tel_db WHERE mob1 IN ($phonePlaceholders)";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute($phones);
        $assignedPhones = $checkStmt->fetchAll(PDO::FETCH_KEY_PAIR); // کلید: mob1, مقدار: dakheli

        // اضافه کردن وضعیت تخصیص به هر شخص
        foreach ($persons as $person) {
            if (isset($assignedPhones[$person->phone])) {
                $person->is_assigned = true;
                $person->assigned_to = $assignedPhones[$person->phone];
                $person->assigned_to_name = getOperatorName($assignedPhones[$person->phone], $operators);
            } else {
                $person->is_assigned = false;
                $person->assigned_to = null;
                $person->assigned_to_name = null;
            }
        }
    }
}

$_SESSION['temporary_personal_export_ids'] = array_map(function ($p) {
    return (int)$p->id;
}, $persons);

// ========== بخش تخصیص با چند اپراتور ==========
if (isset($_POST['confirmAssign'])) {
    if (empty($_POST['selected_ids']) || empty($_POST['operator_ids'])) {
        $_SESSION['assign_message'] = '⚠️ اطلاعات ناقص است';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $operatorIds = $_POST['operator_ids']; // آرایه ای از اپراتورها
    $ids = $_POST['selected_ids'];
    $group = $_POST['group_id'];

    if (!is_array($ids) || count($ids) == 0) {
        $_SESSION['assign_message'] = '⚠️ هیچ موردی انتخاب نشده است';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if (!is_array($operatorIds) || count($operatorIds) == 0) {
        $_SESSION['assign_message'] = '⚠️ حداقل یک اپراتور انتخاب کنید';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    /*
     |-------------------------------------------------
     | گرفتن اطلاعات افراد انتخاب شده
     |-------------------------------------------------
     */

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT * FROM temporary_personal WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);

    $selectedPersons = $stmt->fetchAll(PDO::FETCH_OBJ);

    // فیلتر کردن افرادی که شماره معتبر دارند
    $validPersons = [];
    $phones = [];

    foreach ($selectedPersons as $p) {
        if (
            !empty($p->phone) &&
            !empty($p->first_name) &&
            !empty($p->last_name) &&
            preg_match('/^09[0-9]{9}$/', $p->phone)
        ) {
            $validPersons[] = $p;
            $phones[] = $p->phone;
        }
    }

    if (empty($validPersons)) {
        $_SESSION['assign_message'] = '⚠️ هیچ فرد معتبری برای تخصیص وجود ندارد';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    /*
     |-------------------------------------------------
     | گرفتن شماره های ثبت شده (تکراری)
     |-------------------------------------------------
     */

    $existingPhones = [];

    if (!empty($phones)) {
        $phonePlaceholders = implode(',', array_fill(0, count($phones), '?'));

        $checkStmt = $conn->prepare("
            SELECT mob1 
            FROM tel_db 
            WHERE mob1 IN ($phonePlaceholders)
        ");

        $checkStmt->execute($phones);
        $existingPhones = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $existingPhones = array_flip($existingPhones);
    }

    /*
     |-------------------------------------------------
     | توزیع مساوی بین اپراتورها
     |-------------------------------------------------
     */

    $numOperators = count($operatorIds);
    $totalValidPersons = count($validPersons);

    // محاسبه تعداد رکورد برای هر اپراتور
    $baseCount = floor($totalValidPersons / $numOperators);
    $remainder = $totalValidPersons % $numOperators;

    // شافل کردن اپراتورها برای توزیع عادلانه‌تر
    $shuffledOperators = $operatorIds;
    shuffle($shuffledOperators);

    // ایجاد آرایه توزیع
    $distribution = array_fill(0, $numOperators, $baseCount);

    // توزیع باقیمانده بین اپراتورها
    for ($i = 0; $i < $remainder; $i++) {
        $distribution[$i]++;
    }

    /*
     |-------------------------------------------------
     | ثبت اطلاعات با توزیع مساوی
     |-------------------------------------------------
     */

    $insertCount = 0;
    $operatorCounts = array_fill(0, $numOperators, 0);
    $operatorIndex = 0;

    // تخصیص هر فرد به یک اپراتور
    foreach ($validPersons as $index => $person) {
        // انتخاب اپراتور بر اساس توزیع
        $currentOperator = $shuffledOperators[$operatorIndex % $numOperators];

        // بررسی تکراری بودن شماره
        if (isset($existingPhones[$person->phone])) {
            $operatorIndex++;
            continue;
        }

        // بررسی مجدد تکراری بودن (برای اطمینان)
        $checkTel = $conn->prepare("SELECT id FROM tel_db WHERE mob1 = ?");
        $checkTel->execute([$person->phone]);

        if ($checkTel->fetch()) {
            $operatorIndex++;
            continue;
        }

        $insert = $conn->prepare("
            INSERT INTO tel_db 
            (`group`,`fname`,`lname`,`guidance`,`state`,`city`,
             `mob1`,`dakheli`)
            VALUES (?,?,?,?,?,?,?,?)
        ");

        $insert->execute([
            $group,
            $person->first_name,
            $person->last_name,
            $person->grade,
            $person->state,
            $person->city,
            $person->phone,
            $currentOperator
        ]);

        $insertCount++;

        // ثبت آمار تخصیص
        $opIndex = array_search($currentOperator, $shuffledOperators);
        if ($opIndex !== false) {
            $operatorCounts[$opIndex]++;
        }

        $operatorIndex++;
    }

    /*
     |-------------------------------------------------
     | ذخیره گزارش در Session
     |-------------------------------------------------
     */

    if ($insertCount > 0) {
        $report = "✅ {$insertCount} رکورد با موفقیت تخصیص داده شد\n\n";
        $report .= "📊 توزیع بین اپراتورها:\n";
        $report .= "━━━━━━━━━━━━━━━━━━━━━━\n";

        foreach ($shuffledOperators as $index => $opId) {
            $opName = getOperatorName($opId, $operators);
            $report .= "👤 {$opName}: {$operatorCounts[$index]} رکورد\n";
        }

        // اضافه کردن اطلاعات تکمیلی
        $report .= "\n📌 تعداد کل افراد معتبر: {$totalValidPersons}";
        $report .= "\n📌 تعداد اپراتورها: {$numOperators}";
        $report .= "\n📌 سهم هر اپراتور: {$baseCount} رکورد";
        if ($remainder > 0) {
            $report .= "\n📌 باقیمانده: {$remainder} رکورد بین اپراتورهای اول توزیع شد";
        }

        $_SESSION['assign_message'] = $report;
    } else {
        $_SESSION['assign_message'] = '⚠️ هیچ رکورد جدیدی برای تخصیص وجود نداشت';
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ========== نمایش پیام ذخیره شده در Session ==========
$assignMessage = '';
if (isset($_SESSION['assign_message'])) {
    $assignMessage = $_SESSION['assign_message'];
    unset($_SESSION['assign_message']); // پاک کردن بعد از نمایش
}

$state_sql = 'SELECT * FROM state';
$state_stmt = $conn->prepare($state_sql);
$state_stmt->execute();
$states = $state_stmt->fetchAll(PDO::FETCH_OBJ);

$gradeCounts = array_fill(1, 12, 0);

$stmt = $conn->prepare("
    SELECT grade, COUNT(*) AS total
    FROM temporary_personal
    WHERE grade BETWEEN 1 AND 12
    GROUP BY grade
    ORDER BY grade ASC
");

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $grade = (int) $row['grade'];
    $gradeCounts[$grade] = (int) $row['total'];
}

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

if (isset($_POST['save'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $in = 'INSERT INTO tel_group (namegroup) VALUES (?)';
        $qin = $conn->prepare($in);
        $qin->execute([$_POST['namegroup']]);
        $_SESSION['assign_message'] = '✅ گروه تماس با موفقیت ایجاد شد';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $del = 'DELETE FROM tel_group WHERE id=?';
    $qdel = $conn->prepare($del);
    $qdel->execute([$id]);
    $_SESSION['assign_message'] = '✅ گروه با موفقیت حذف شد';
    header('location: assign-phone.php');
    die();
}

if (isset($_POST['save-update'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $code=intval($_POST['id']);
        $upd='UPDATE tel_group SET namegroup=? WHERE id=?';
        $actupd=$conn->prepare($upd);
        $actupd->execute([$_POST['namegroup'],$code]);
        $_SESSION['assign_message'] = '✅ گروه با موفقیت ویرایش شد';
        header('location: assign-phone.php');
        die();
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> تماس امن |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>

    <style>
        /* Table Styles */
        .custom-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 10px;
        }

        .table thead th {
            font-weight: 600;
            text-transform: none;
            letter-spacing: normal;
            font-size: 0.85rem;
            color: #495057;
            border-top: none !important;
            cursor: pointer;
            position: relative;
            padding-left: 25px !important;
            user-select: none;
            transition: background-color 0.2s;
        }

        .table thead th:hover {
            background-color: #f1f3f5;
        }

        .table thead th .sort-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: #adb5bd;
            transition: color 0.2s;
        }

        .table thead th.sorted-asc .sort-icon {
            color: #007bff;
        }

        .table thead th.sorted-asc .sort-icon:after {
            content: "▲";
        }

        .table thead th.sorted-desc .sort-icon {
            color: #007bff;
        }

        .table thead th.sorted-desc .sort-icon:after {
            content: "▼";
        }

        .table thead th:not(.sorted-asc):not(.sorted-desc) .sort-icon:after {
            content: "⇅";
        }

        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        /* Light Badges for Modern Look */
        .bg-warning-light {
            background: #fff8e1;
            color: #ff8f00;
        }

        /* Filter Hover Effects */
        .btn-info-light:hover {
            background: #00b8d4;
            color: white;
        }

        .btn-danger-light:hover {
            background: #ff5252;
            color: white;
        }

        /* Select2 Multiple Selection Style */
        .select2-container--default .select2-selection--multiple {
            min-height: 38px;
            border-radius: 5px;
            border: 1px solid #e9edf4;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #5b6e88;
            border: none;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            margin: 3px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: #ff6b6b;
        }

        /* Alert Message Style */
        .assign-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 400px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 15px 20px;
            display: none;
            white-space: pre-line;
            text-align: right;
            direction: rtl;
        }
        .assign-alert.show {
            display: block;
            animation: slideDown 0.5s ease-out;
        }
        @keyframes slideDown {
            from {
                transform: translateX(-50%) translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }
        .assign-alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .assign-alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .assign-alert .close-btn {
            float: left;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
            color: inherit;
            opacity: 0.7;
            background: none;
            border: none;
            padding: 0 8px;
        }
        .assign-alert .close-btn:hover {
            opacity: 1;
        }

        /* Status Badge Styles */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        .status-assigned {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-unassigned {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-assigned .fa {
            margin-left: 4px;
        }
    </style>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->

<!-- ========== نمایش پیام ========== -->
<?php if (!empty($assignMessage)): ?>
    <div id="assignAlert" class="assign-alert <?php echo strpos($assignMessage, '✅') !== false ? 'assign-alert-success' : 'assign-alert-danger'; ?>">
        <button type="button" class="close-btn" onclick="this.parentElement.classList.remove('show')">✕</button>
        <div style="margin-left: 30px;"><?php echo nl2br(htmlspecialchars($assignMessage)); ?></div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var alert = document.getElementById('assignAlert');
            if (alert) {
                alert.classList.add('show');
            }
        });
    </script>
<?php endif; ?>

<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="page-header mt-4">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">مدیریت لیست تماس ها</h2>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">امور مشتریان</li>
                            <li class="breadcrumb-item active" aria-current="page">تماس امن</li>
                            <li class="breadcrumb-item active" aria-current="page">تخصیص شماره</li>
                        </ol>
                    </div>
                </div>

                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card" data-keep-terms>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">تعداد افراد هر پایه</h6>
                                    <span class="badge bg-primary">پایه ۱ تا ۱۲</span>
                                </div>

                                <div class="row g-3">
                                    <?php for ($grade = 1; $grade <= 12; $grade++): ?>
                                        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                                            <div class="border rounded p-3 text-center bg-light h-100">
                                                <div class="text-muted small mb-2">
                                                    پایه <?= $grade ?>
                                                </div>

                                                <div class="fs-4 fw-bold text-primary">
                                                    <?= number_format($gradeCounts[$grade]) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fe fe-filter ms-2 text-primary"></i>
                                    <h6 class="main-content-label mb-0">جستجو و فیلتر</h6>
                                </div>
                                <form method="POST" action="">
                                    <div class="row row-sm">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">انتخاب استان</label>
                                            <select name="state" id="state_select" class="form-control">
                                                <option value="">انتخاب کنید</option>
                                                <?php foreach ($states as $state) { ?>
                                                    <option value="<?= $state->name ?>"><?= $state->name ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">انتخاب شهر</label>
                                            <select name="city" id="city_select" class="form-control">
                                                <option value="">ابتدا استان را انتخاب کنید</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">انتخاب مدرسه</label>
                                            <select name="school" id="school_select" class="form-control">
                                                <option value="">ابتدا شهر را انتخاب کنید</option>
                                            </select>
                                        </div>


                                        <script src="<?php echo url('assets/plugins/jquery/jquery-3.2.1.min.js') ?>"></script>
                                        <script>
                                            $('#state_select').on('change', function () {
                                                var stateId = $(this).val();
                                                if (stateId) {
                                                    $.ajax({
                                                        type: 'POST',
                                                        url: window.location.pathname,
                                                        data: {state_ajax: stateId},
                                                        success: function (data) {
                                                            $('#city_select').html(data);
                                                        }
                                                    });
                                                }
                                            });
                                            $('#city_select').on('change', function () {
                                                var cityName = $(this).val();
                                                if (cityName) {
                                                    $.ajax({
                                                        type: 'POST',
                                                        url: window.location.pathname,
                                                        data: {city_ajax: cityName},
                                                        success: function (data) {
                                                            $('#school_select').html(data);
                                                        }
                                                    });
                                                } else {
                                                    $('#school_select').html('<option value="">ابتدا شهر را انتخاب کنید</option>');
                                                }
                                            });

                                        </script>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">جنسیت</label>
                                            <select name="gender" class="form-control select2">
                                                <option value="">انتخاب کنید</option>
                                                <option value="پسر">پسر</option>
                                                <option value="دختر">دختر</option>
                                                <option value="both">هر دو</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">پایه تحصیلی</label>
                                            <select name="grade" id="grade_select" class="form-control">
                                                <option value="">انتخاب پایه</option>
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?php echo $i; ?>">پایه <?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2 mb-3" id="major_container" style="display:none;">
                                            <label class="form-label">انتخاب رشته تحصیلی</label>
                                            <select name="major" class="form-control">
                                                <option value="">همه رشته‌ها</option>
                                                <option value="ریاضی فیزیک">ریاضی فیزیک</option>
                                                <option value="علوم تجربی">علوم تجربی</option>
                                                <option value="ادبیات و علوم انسانی">ادبیات و علوم انسانی</option>
                                            </select>
                                        </div>

                                        <script>
                                            $('#grade_select').on('change', function () {
                                                var grade = parseInt($(this).val());
                                                if (grade > 9) {

                                                    $('#major_container').fadeIn();
                                                } else {
                                                    $('#major_container').fadeOut();
                                                    $('#major_container select').val('');
                                                }
                                            });
                                        </script>

                                        <div class="col-md-3 mb-3">
                                            <label class="form-label" for="count">تعداد خروجی </label>
                                            <input type="number" value="500" class="form-control" name="count">
                                        </div>

                                        <div class="col-md-12 mb-3 d-flex align-items-end">
                                            <button type="submit" name="filter_action"
                                                    class="btn btn-primary w-100 me-2">اعمال فیلتر
                                            </button>

                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-header border-bottom-0 pb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="main-content-label mb-3">لیست داده‌ها</h6>
                                    <div>
                                        <span class="badge bg-success me-2">🟢 تخصیص داده نشده</span>
                                        <span class="badge bg-danger">🔴 تخصیص داده شده</span>
                                    </div>
                                    <form method="POST" action="excel.php" class="mb-3">
                                        <input type="hidden" name="export_excel" value="1">

                                        <button type="submit" class="btn btn-success btn-sm" <?= empty($persons) ? 'disabled' : '' ?>>
                                            خروجی اکسل
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3" id="assignForm">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label"><?= inst_is('school') ? 'انتخاب مشاوران' : 'انتخاب اپراتورها' ?> <span class="text-danger">*</span></label>
                                            <select name="operator_ids[]" class="form-control select2-multiple" multiple="multiple" required style="width: 100%;">
                                                <?php foreach ($operators as $op): ?>
                                                    <option value="<?= $op->dakheli ?>"><?= $op->username ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted"><?= inst_is('school') ? 'برای انتخاب چند مشاور، کلید Ctrl را نگه دارید یا کلیک کنید' : 'برای انتخاب چند اپراتور، کلید Ctrl را نگه دارید یا کلیک کنید' ?></small>
                                        </div>

                                        <div class="col-md-4 ms-auto d-flex align-items-end">
                                            <button name="assign_action" type="submit" value="manual"
                                                    class="btn btn-success w-100">
                                                <i class="fe fe-check-circle"></i> تخصیص انتخاب‌شده‌ها
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table id="dataTable" class="table table-hover border-top-0 mb-0 text-nowrap">
                                            <thead class="bg-light">
                                            <tr>
                                                <th style="padding-right: 15px !important; cursor: default;">
                                                    <input type="checkbox" id="select_all">
                                                </th>
                                                <th data-sort="number">ردیف <span class="sort-icon"></span></th>
                                                <th data-sort="text">دانش آموز <span class="sort-icon"></span></th>
                                                <th data-sort="text">شماره تماس <span class="sort-icon"></span></th>
                                                <th data-sort="text">استان <span class="sort-icon"></span></th>
                                                <th data-sort="text">شهر <span class="sort-icon"></span></th>
                                                <th data-sort="text">جنسیت <span class="sort-icon"></span></th>
                                                <th data-sort="text">مدرسه <span class="sort-icon"></span></th>
                                                <th data-sort="number">پایه <span class="sort-icon"></span></th>
                                                <th data-sort="text">رشته <span class="sort-icon"></span></th>
                                                <th data-sort="text">وضعیت تخصیص <span class="sort-icon"></span></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php $row = 1;
                                            foreach ($persons as $person) {
                                                if ($person->grade > 12) {$person->major = null;}
                                                ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_ids[]"
                                                               value="<?= $person->id ?>"
                                                            <?php echo isset($person->is_assigned) && $person->is_assigned ? 'disabled' : ''; ?>>
                                                    </td>
                                                    <td data-value="<?= $row ?>"><?= $row ?></td>
                                                    <td data-value="<?= htmlspecialchars($person->first_name . ' ' . $person->last_name) ?>">
                                                        <div class="d-flex align-items-center">
                                                            <span><?= $person->first_name . ' ' . $person->last_name ?></span>
                                                        </div>
                                                    </td>
                                                    <td data-value="<?= $person->phone ?>"><?= $person->phone ?></td>
                                                    <td data-value="<?= htmlspecialchars($person->state) ?>"><?= $person->state ?></td>
                                                    <td data-value="<?= htmlspecialchars($person->city) ?>"><?= $person->city ?></td>
                                                    <td data-value="<?= $person->gender ?>">
                                                        <span class="badge bg-pill bg-warning-light text-warning"><?= $person->gender ?></span>
                                                    </td>
                                                    <td data-value="<?= htmlspecialchars($person->school) ?>"><?= $person->school ?></td>
                                                    <td data-value="<?= $person->grade > 12 ? 13 : $person->grade ?>">
                                                        <?= $person->grade > 12 ? 'فارغ التحصیل' : $person->grade ?>
                                                    </td>
                                                    <td data-value="<?= htmlspecialchars($person->major ?? '--') ?>"><?= $person->major ?? '--' ?></td>
                                                    <td data-value="<?= isset($person->is_assigned) && $person->is_assigned ? 'تخصیص داده شده' : 'تخصیص داده نشده' ?>">
                                                        <?php if (isset($person->is_assigned) && $person->is_assigned): ?>
                                                            <span class="status-badge status-assigned">
                                                                <i class="fa fa-lock"></i>
                                                                تخصیص به: <?= htmlspecialchars($person->assigned_to_name) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="status-badge status-unassigned">
                                                                <i class="fa fa-unlock"></i>
                                                                تخصیص داده نشده
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php $row++;
                                            }

                                            ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row row-sm">
                    <?php if (!isset($_GET['edit'])) { ?>
                        <div class="col-lg-6" >
                            <div class="card custom-card" >
                                <div class="card-body" >
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">ایجاد گروه تماس جدید </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">

                                        <div class="col-xl-12">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        نام گروه تماس:
                                                        <span
                                                                class="tx-danger">*</span></div>
                                                </div>
                                                <input class="form-control" name="namegroup"
                                                       aria-describedby="basic-addon2"
                                                       type="text" required
                                                       placeholder="">
                                                <input class="form-control" name="token" type="hidden"
                                                       value="<?php echo $token ?>">
                                            </div>
                                        </div>
                                        <div class="mt-3 text-center">
                                            <button type="submit"
                                                    name="save"
                                                    class="btn btn-success  mr-1">
                                                ثبت و ایجاد گروه تماس
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="col-lg-6" >
                            <div class="card custom-card"  >
                                <div class="card-body" >
                                    <div class="col-10">
                                        <div>
                                            <h6 class="main-content-label mb-1">ویرایش گروه تماس </h6>
                                            <p class="text-muted card-sub-title">
                                            </p>
                                        </div>
                                    </div>
                                    <form method="post" data-parsley-validate="">
                                        <?php
                                        $irancel = 'SELECT * FROM tel_group WHERE id=?';
                                        $rightel = $conn->prepare($irancel);
                                        $rightel->execute([$_GET['edit']]);
                                        if ($rightel->rowCount() > 0) {
                                            $talia = $rightel->fetch();

                                            ?>
                                            <div class="col-xl-12">
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <div class="input-group-text">
                                                            نام گروه تماس:
                                                            <span
                                                                    class="tx-danger">*</span></div>
                                                    </div>
                                                    <input class="form-control" name="namegroup"
                                                           aria-describedby="basic-addon2"
                                                           type="text" required
                                                           placeholder="" value="<?php echo $talia['namegroup'] ?>">
                                                    <input class="form-control" name="token" type="hidden"
                                                           value="<?php echo $token ?>">
                                                    <input class="form-control" name="id" type="hidden"
                                                           value="<?php echo $_GET['edit'] ?>">
                                                </div>
                                            </div>
                                            <div class="mt-3 text-center">
                                                <button type="submit"
                                                        name="save-update"
                                                        class="btn btn-warning  mr-1">
                                                    ثبت و ویرایش گروه تماس
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php }
                    ?>
                    <div class="col-lg-6">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="col-10">
                                    <div>
                                        <h6 class="main-content-label mb-1">گروه های ایجاد شده </h6>
                                        <p class="text-muted card-sub-title">
                                        </p>
                                    </div>
                                </div>

                                <!-- فیلترها بالاى جدول -->
                                <div class="mb-3 d-flex flex-wrap justify-content-end">
                                    <a href="<?php echo url('admin/Tel/tel-group-archive.php') ?>"
                                       class="btn btn-warning ms-sm-2 mb-2 mb-sm-0 text-white me-auto">
                                        <i class="fa fa-archive"></i>
                                        آرشیو
                                    </a>

                                    <!-- جستجو -->
                                    <input type="text"
                                           id="groupTableSearch"
                                           class="form-control mb-2 mb-sm-0"
                                           style="max-width: 250px;"
                                           placeholder="جستجو در نام / کد گروه...">

                                    <!-- دکمه نمایش همه -->
                                    <button id="groupShowAllBtn"
                                            class="btn btn-secondary ms-sm-2 mb-2 mb-sm-0">
                                        نمایش همه
                                    </button>
                                </div>

                                <!-- جدول با اسکرول افقی برای جلوگیری از بیرون زدن -->
                                <div class="table-responsive">
                                    <table id="groupTable" class="table table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th>کد گروه</th>
                                            <th>نام گروه</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        [$startDate, $endDate] = getJalaliYearWindowApprox();

                                        $sql = "
                                                        SELECT *
                                                        FROM tel_group
                                                        WHERE created_at >= :startDate
                                                          AND created_at < :endDate
                                                    ";

                                        $query = $conn->prepare($sql);
                                        $query->execute([
                                            ':startDate' => $startDate . ' 00:00:00',
                                            ':endDate'   => $endDate . ' 00:00:00',
                                        ]);

                                        $result = $query->fetchAll(PDO::FETCH_OBJ);
                                        if ($query->rowCount() > 0) {
                                            foreach ($result as $ret) {
                                                ?>
                                                <tr>
                                                    <td><?php echo $ret->id ?></td>
                                                    <td><?php echo htmlentities($ret->namegroup) ?></td>
                                                    <td>
                                                        <a href="?del=<?php echo $ret->id ?>"
                                                           onclick="return confirm('با حذف گروه تمام مخاطبین گروه هم حذف میشوند!!')"
                                                           type="button"
                                                           class="btn ripple btn-danger btn-sm">
                                                            حذف
                                                        </a>
                                                        <a href="?edit=<?php echo $ret->id ?>"
                                                           type="button"
                                                           class="btn ripple btn-warning btn-sm">
                                                            ویرایش
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php }
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
        </div>
    </div>
    <!-- Modal تخصیص گروه -->
    <div class="modal fade" id="assignGroupModal" tabindex="-1" aria-labelledby="assignGroupModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignGroupModalLabel">انتخاب گروه برای اپراتور</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="group_select" class="form-label">گروه</label>
                    <select class="form-control" id="group_select" name="group_id" required>
                        <option value="">انتخاب کنید</option>
                        <?php
                        $group_stmt = $conn->prepare("SELECT * FROM tel_group ORDER BY id DESC");
                        $group_stmt->execute();
                        $groups = $group_stmt->fetchAll(PDO::FETCH_OBJ);
                        foreach ($groups as $g) {
                            echo "<option value='{$g->id}'>{$g->namegroup}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="confirmAssign">تخصیص</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('select_all');
            const assignForm = document.getElementById('assignForm');

            // انتخاب همه / خروج از انتخاب همه
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function () {
                    const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:not([disabled])');
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                });
            }

            // بررسی انتخاب حداقل یک مورد قبل از باز شدن مدال
            if (assignForm) {
                assignForm.addEventListener('submit', function (e) {
                    const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
                    if (checked.length === 0) {
                        e.preventDefault();
                        alert('⚠️ لطفاً حداقل یک مورد را انتخاب کنید');
                        return;
                    }

                    // بررسی انتخاب حداقل یک اپراتور
                    const operatorSelect = document.querySelector('select[name="operator_ids[]"]');
                    const selectedOperators = $(operatorSelect).val();
                    if (!selectedOperators || selectedOperators.length === 0) {
                        e.preventDefault();
                        alert('⚠️ لطفاً حداقل یک اپراتور انتخاب کنید');
                        return;
                    }

                    // جلوگیری از ارسال مستقیم فرم و باز کردن مدال
                    e.preventDefault();
                    var modal = new bootstrap.Modal(document.getElementById('assignGroupModal'));
                    modal.show();
                });
            }

            // تایید نهایی و ارسال فرم همراه با اطلاعات مدال
            const confirmAssignBtn = document.getElementById('confirmAssign');
            if (confirmAssignBtn) {
                confirmAssignBtn.addEventListener('click', function () {
                    const group = document.getElementById('group_select').value;
                    if (!group) {
                        alert('⚠️ لطفاً یک گروه انتخاب کنید');
                        return;
                    }

                    // اضافه کردن group_id به فرم
                    let inputGroup = document.createElement('input');
                    inputGroup.type = 'hidden';
                    inputGroup.name = 'group_id';
                    inputGroup.value = group;
                    assignForm.appendChild(inputGroup);

                    // اضافه کردن confirmAssign برای PHP
                    let inputConfirm = document.createElement('input');
                    inputConfirm.type = 'hidden';
                    inputConfirm.name = 'confirmAssign';
                    inputConfirm.value = '1';
                    assignForm.appendChild(inputConfirm);

                    assignForm.submit();
                });
            }

            // ========== پیاده‌سازی سورت دستی ==========
            const table = document.getElementById('dataTable');
            if (table) {
                const headers = table.querySelectorAll('thead th');
                let currentSort = { column: null, direction: 'asc' };

                // تابع سورت
                function sortTable(columnIndex, sortType, headerElement) {
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    // حذف کلاس‌های قبلی
                    headers.forEach(h => {
                        h.classList.remove('sorted-asc', 'sorted-desc');
                    });

                    // تعیین جهت سورت
                    if (currentSort.column === columnIndex) {
                        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSort.direction = 'asc';
                    }
                    currentSort.column = columnIndex;

                    // اضافه کردن کلاس به هدر فعلی
                    headerElement.classList.add(currentSort.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

                    // سورت کردن ردیف‌ها
                    rows.sort((a, b) => {
                        let aVal, bVal;

                        if (sortType === 'number') {
                            // سورت عددی
                            aVal = parseInt(a.querySelectorAll('td')[columnIndex].getAttribute('data-value') || a.querySelectorAll('td')[columnIndex].textContent.trim());
                            bVal = parseInt(b.querySelectorAll('td')[columnIndex].getAttribute('data-value') || b.querySelectorAll('td')[columnIndex].textContent.trim());

                            if (isNaN(aVal)) aVal = 0;
                            if (isNaN(bVal)) bVal = 0;
                        } else {
                            // سورت متنی
                            aVal = (a.querySelectorAll('td')[columnIndex].getAttribute('data-value') || a.querySelectorAll('td')[columnIndex].textContent.trim()).toLowerCase();
                            bVal = (b.querySelectorAll('td')[columnIndex].getAttribute('data-value') || b.querySelectorAll('td')[columnIndex].textContent.trim()).toLowerCase();
                        }

                        if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                        if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                        return 0;
                    });

                    // بازسازی tbody
                    rows.forEach(row => tbody.appendChild(row));
                }

                // اضافه کردن event listener به هدرها
                headers.forEach((header, index) => {
                    // ستون چک‌باکس (ستون 0) را غیرفعال می‌کنیم
                    if (index === 0) return;

                    header.style.cursor = 'pointer';
                    header.addEventListener('click', function() {
                        const sortType = this.getAttribute('data-sort') || 'text';
                        sortTable(index, sortType, this);
                    });
                });
            }
        });

        // فعال‌سازی Select2 برای انتخاب چند اپراتور
        $(document).ready(function() {
            $('.select2-multiple').select2({
                placeholder: <?= json_encode(inst_is('school') ? 'مشاور ها را انتخاب کنید' : 'اپراتورها را انتخاب کنید', JSON_UNESCAPED_UNICODE) ?>,
                allowClear: true,
                width: '100%'
            });
        });
    </script>
    <script>
        // helper to init search + show-all + pagination on any table
        function initSearchableTable(searchInputId, showAllBtnId, tableId, pageSize = 7) {
            const searchInput = document.getElementById(searchInputId);
            const showAllBtn  = document.getElementById(showAllBtnId);
            const table       = document.getElementById(tableId);

            if (!searchInput || !showAllBtn || !table) {
                return; // silently skip if something is missing
            }

            const tbody = table.querySelector('tbody');
            const rows  = Array.from(tbody.querySelectorAll('tr'));

            // pagination state
            let showAllMode   = false;      // first load = empty
            let currentPage   = 1;
            let filteredRows  = [];

            // create pager container just after table
            const pager = document.createElement('div');
            pager.className = 'table-pager d-flex justify-content-between align-items-center mt-2';
            table.parentNode.insertBefore(pager, table.nextSibling);

            function applyPagination() {
                const totalRows  = filteredRows.length;
                const totalPages = totalRows ? Math.ceil(totalRows / pageSize) : 1;

                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                // hide all rows
                rows.forEach(row => {
                    row.style.display = 'none';
                });

                // show only current page rows
                const start = (currentPage - 1) * pageSize;
                const end   = start + pageSize;
                filteredRows.slice(start, end).forEach(row => {
                    row.style.display = '';
                });

                // build pager UI
                pager.innerHTML = '';

                const info = document.createElement('span');
                if (totalRows === 0) {
                    info.textContent = 'هیچ ردیفی برای نمایش نیست';
                } else {
                    info.textContent = 'صفحه ' + currentPage + ' از ' + totalPages + ' ( ' + totalRows + ' ردیف )';
                }
                pager.appendChild(info);

                if (totalPages > 1) {
                    const controls = document.createElement('div');

                    const prevBtn = document.createElement('button');
                    prevBtn.type = 'button';
                    prevBtn.className = 'btn btn-sm btn-light ms-1';
                    prevBtn.textContent = 'قبلی';
                    prevBtn.disabled = currentPage === 1;
                    prevBtn.addEventListener('click', function () {
                        if (currentPage > 1) {
                            currentPage--;
                            applyPagination();
                        }
                    });

                    const nextBtn = document.createElement('button');
                    nextBtn.type = 'button';
                    nextBtn.className = 'btn btn-sm btn-light ms-1';
                    nextBtn.textContent = 'بعدی';
                    nextBtn.disabled = currentPage === totalPages;
                    nextBtn.addEventListener('click', function () {
                        if (currentPage < totalPages) {
                            currentPage++;
                            applyPagination();
                        }
                    });

                    controls.appendChild(prevBtn);
                    controls.appendChild(nextBtn);
                    pager.appendChild(controls);
                }
            }

            function applyFiltersAndPagination() {
                const term = searchInput.value.trim().toLowerCase();

                filteredRows = [];

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    let visible = false;

                    if (term) {
                        // live search: only rows containing the term
                        visible = text.includes(term);
                    } else if (showAllMode) {
                        // show all if button was pressed and no search term
                        visible = true;
                    } else {
                        // initial state: empty
                        visible = false;
                    }

                    if (visible) {
                        filteredRows.push(row);
                    }
                });

                currentPage = 1; // reset to first page whenever filter changes
                applyPagination();
            }

            // live search
            searchInput.addEventListener('input', function () {
                applyFiltersAndPagination();
            });

            // show all
            showAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                showAllMode = true;
                searchInput.value = '';
                applyFiltersAndPagination();
            });

            // start with empty table
            applyFiltersAndPagination();
        }

        document.addEventListener('DOMContentLoaded', function () {
            // table 1: group stats
            initSearchableTable('tableSearch', 'showAllBtn', 'select2', 7);

            // table 2: individual stats
            initSearchableTable('individualTableSearch', 'individualShowAllBtn', 'individualCallsTable', 7);

            // table 3: گروه‌های ایجاد شده
            initSearchableTable('groupTableSearch', 'groupShowAllBtn', 'groupTable', 7);
        });
    </script>

</div>
</body>
</html>