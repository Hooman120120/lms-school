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
if (!Permision('permision_managecall')) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['state_ajax']) && !isset($_POST['city_ajax']) && !isset($_POST['confirmAssign'])) {
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

    // ---------- جایگزینِ قطعهٔ انتخاب افراد (از قبلِ SELECT ... LIMIT ...) ----------
    if ($total === 0) {
        $persons = [];
    } else {
        if ($total <= $limit) {
            // اگر تعداد کمتر یا مساوی حد است، همه را برگردان
            $selectSql = "SELECT * FROM temporary_personal" . $whereSql . " ORDER BY id ASC";
            $selectStmt = $conn->prepare($selectSql);
            $selectStmt->execute($params);
            $persons = $selectStmt->fetchAll(PDO::FETCH_OBJ);
        } else {
            // تولید offset رندوم (بدون بایاس)
            $maxOffset = $total - $limit;
            $randOffset = mt_rand(0, $maxOffset);

            // نکته: LIMIT و OFFSET را به صورت ایمن داخل SQL می‌سازیم (intval) تا PDO عددها را به صورت 'string' نفروشد
            $safeLimit = intval($limit);
            $safeOffset = intval($randOffset);

            $selectSql = "SELECT * FROM temporary_personal" . $whereSql . " ORDER BY id ASC LIMIT {$safeLimit} OFFSET {$safeOffset}";

            $selectStmt = $conn->prepare($selectSql);
            // فقط پارامترهای WHERE را پاس می‌دهیم (positional ?)
            $selectStmt->execute($params);
            $persons = $selectStmt->fetchAll(PDO::FETCH_OBJ);

            // برای رندم‌تر شدن ترتیب داخلی
            if (!empty($persons) && count($persons) > 1) {
                shuffle($persons);
            }
        }
    }
}

$_SESSION['temporary_personal_export_ids'] = array_map(function ($p) {
    return (int)$p->id;
}, $persons);

// ---------- پایان بلوک ----------

//$persons = [];
//
//if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['state_ajax']) && !isset($_POST['city_ajax']) && !isset($_POST['confirmAssign'])) {
//    global $conn;
//    $where = [];
//    $params = [];
//    $sql = 'SELECT name FROM city WHERE id = ?';
//    $stmt = $conn->prepare($sql);
//    $stmt->execute([$_POST['city']]);
//    $cityName = $stmt->fetchColumn();
//    $sqll = 'SELECT school_name FROM schools WHERE id = ?';
//    $stmtt = $conn->prepare($sqll);
//    $stmtt->execute([$_POST['school']]);
//    $schoolName = $stmtt->fetchColumn();
//    // استان
//    if (!empty($_POST['state'])) {
//        $where[] = "state LIKE ?";
//        $state = normalize_persian($_POST['state']);
//        $params[] = "%$state%";
//    }
//
//    // شهر
//    if (!empty($_POST['city'])) {
//        $where[] = "city LIKE ?";
//        $city = normalize_persian($cityName);
//        $params[] = "%$city%";
//    }
//
//    if (!empty($_POST['school'])) {
//        $where[] = "school LIKE ?";
//        $school = normalize_persian($schoolName);
//        $params[] = "%$school%";
//    }
//
//    // جنسیت
//    if (!empty($_POST['gender']) && $_POST['gender'] !== 'both') {
//        $where[] = "gender = ?";
//        $params[] = $_POST['gender'];
//    }
//
//    // پایه
//    if (!empty($_POST['grade'])) {
//        $where[] = "grade = ?";
//        $params[] = $_POST['grade'];
//    }
//
//    // رشته
//    if (!empty($_POST['major'])) {
//        $where[] = "major LIKE ?";
//        $major = $_POST['major'];
//        $params[] = "%$major%";
//    }
//
//    $maxIdSql = "SELECT MAX(id) FROM temporary_personal";
//    if (!empty($where)) {
//        $maxIdSql .= " WHERE " . implode(" AND ", $where);
//    }
//
//    $stmtMax = $conn->prepare($maxIdSql);
//    $stmtMax->execute($params);
//    $maxId = $stmtMax->fetchColumn();
//
//    if (!$maxId) {
//        $persons = [];
//    } else {
//
//
//        $randomId = mt_rand(1, $maxId);
//
//        $sql = "SELECT * FROM temporary_personal
//        WHERE id >= ?";
//
//        $paramsFinal = [$randomId];
//
//        if (!empty($where)) {
//            $sql .= " AND " . implode(" AND ", $where);
//            $paramsFinal = array_merge($paramsFinal, $params);
//        }
//
//        $limit = !empty($_POST['count']) ? intval($_POST['count']) : 500;
//
//        $sql .= " ORDER BY id ASC LIMIT $limit";
//
//        $stmt = $conn->prepare($sql);
//        $stmt->execute($paramsFinal);
//        $persons = $stmt->fetchAll(PDO::FETCH_OBJ);
//
//        if (empty($persons)) {
//
//            $sql = "SELECT * FROM temporary_personal
//            WHERE id < ?";
//
//            $paramsFinal = [$randomId];
//
//            if (!empty($where)) {
//                $sql .= " AND " . implode(" AND ", $where);
//                $paramsFinal = array_merge($paramsFinal, $params);
//            }
//
//            $sql .= " ORDER BY id DESC LIMIT $limit";
//
//            $stmt = $conn->prepare($sql);
//            $stmt->execute($paramsFinal);
//            $persons = $stmt->fetchAll(PDO::FETCH_OBJ);
//        }
//    }
//
////    dd([$sql, $params]);
//}

if (isset($_POST['confirmAssign'])) {

    if (empty($_POST['selected_ids']) || empty($_POST['operator_id'])) {
        die('اطلاعات ناقص است');
    }

    $operator = $_POST['operator_id'];
    $ids = $_POST['selected_ids'];
    $group = $_POST['group_id'];

    if (!is_array($ids) || count($ids) == 0) {
        die('هیچ موردی انتخاب نشده است');
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

    /*
     |-------------------------------------------------
     | گرفتن شماره های ثبت شده
     |-------------------------------------------------
     */

    $phones = [];
    foreach ($selectedPersons as $p) {
        if (!empty($p->phone)) {
            $phones[] = $p->phone;
        }
    }

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
     | ثبت اطلاعات
     |-------------------------------------------------
     */

    $insertCount = 0;   // ⭐ شمارنده موفقیت

    foreach ($selectedPersons as $person) {

        if (
            empty($person->phone) ||
            empty($person->first_name) ||
            empty($person->last_name)
        ) {
            continue;
        }

        if (!preg_match('/^09[0-9]{9}$/', $person->phone)) {
            continue;
        }

        if (isset($existingPhones[$person->phone])) {
            continue;
        }

        $checkTel = $conn->prepare("
            SELECT id 
            FROM tel_db 
            WHERE mob1 = ?
        ");

        $checkTel->execute([$person->phone]);

        if ($checkTel->fetch()) {
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
            $operator
        ]);

        $insertCount++;   // ⭐ اگر insert موفق بود
    }

    /*
     |-------------------------------------------------
     | پیام نهایی
     |-------------------------------------------------
     */

    if ($insertCount > 0) {
        echo "<script>alert('{$insertCount} رکورد با موفقیت تخصیص داده شد');</script>";
    } else {
        echo "<script>alert('هیچ رکورد جدیدی برای تخصیص وجود نداشت');</script>";
    }
}


//include_once '../../temp.html';

$state_sql = 'SELECT * FROM state';
$state_stmt = $conn->prepare($state_sql);
$state_stmt->execute();
$states = $state_stmt->fetchAll(PDO::FETCH_OBJ);

$sql_op = 'SELECT * FROM dbadmin WHERE adminrole = 6';
$stmt_op = $conn->prepare($sql_op);
$stmt_op->execute();
$operators = $stmt_op->fetchAll(PDO::FETCH_OBJ);

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
    </style>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->

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
                        <div class="card custom-card">
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
                                                        url: window.location.pathname, // Clean URL without query strings
                                                        data: {state_ajax: stateId},
                                                        success: function (data) {
                                                            $('#city_select').html(data);
                                                            // If you are using Select2, you MUST trigger a change to refresh the UI
                                                        }
                                                    });
                                                }
                                            });
                                            $('#city_select').on('change', function () {
                                                var cityName = $(this).val(); // نام شهر
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
                                                    $('#major_container select').val(''); // Reset major selection
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

                                        <div class="col-md-3">
                                            <select name="operator_id" class="form-control" required>
                                                <option value="">انتخاب اپراتور</option>
                                                <?php foreach ($operators as $op): ?>
                                                    <option value="<?= $op->dakheli ?>"><?= $op->username ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!--                                        <div class="col-md-2">-->
                                        <!--                                            <input type="number" name="assign_count" class="form-control"-->
                                        <!--                                                   placeholder="تعداد رندوم">-->
                                        <!--                                        </div>-->
                                        <!---->
                                        <!--                                        <div class="col-md-3">-->
                                        <!--                                            <button name="assign_action" value="random" class="btn btn-success w-100">-->
                                        <!--                                                تخصیص رندوم-->
                                        <!--                                            </button>-->
                                        <!--                                        </div>-->

                                        <div class="col-md-4 ms-auto">
                                            <button name="assign_action" type="submit" value="manual"
                                                    class="btn btn-success w-100">
                                                تخصیص انتخاب‌شده‌ها
                                            </button>
                                        </div>

                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-hover border-top-0 mb-0 text-nowrap" id="example1">
                                            <thead class="bg-light">
                                            <tr>
                                                <th>
                                                    <input type="checkbox" id="select_all">
                                                </th>
                                                <th>ردیف</th>
                                                <th>دانش آموز</th>
                                                <th>شماره تماس</th>
                                                <th>استان</th>
                                                <th>شهر</th>
                                                <th>جنسیت</th>
                                                <th>مدرسه</th>
                                                <th>پایه</th>
                                                <th>رشته</th>
                                                <!--                                                <th>اقدامات</th>-->
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php $row = 1;
                                            foreach ($persons as $person) { ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_ids[]"
                                                               value="<?= $person->id ?>">
                                                    </td>
                                                    <td><?= $row ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span><?= $person->first_name . ' ' . $person->last_name ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?= $person->phone ?></td>
                                                    <td><?= $person->state ?></td>
                                                    <td><?= $person->city ?></td>
                                                    <td>
                                                        <span class="badge bg-pill bg-warning-light text-warning"><?= $person->gender ?></span>
                                                    </td>
                                                    <td><?= $person->school ?></td>
                                                    <td><?= $person->grade ?></td>
                                                    <td><?= $person->major ?? '--' ?></td>
                                                    <!--                                                    <td>-->
                                                    <!--                                                        <button class="btn btn-sm btn-info-light"><i-->
                                                    <!--                                                                class="fe fe-edit-2"></i>-->
                                                    <!--                                                        </button>-->
                                                    <!--                                                        <button class="btn btn-sm btn-danger-light ms-1"><i-->
                                                    <!--                                                                class="fe fe-trash"></i></button>-->
                                                    <!--                                                    </td>-->
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
        let table;

        $(document).ready(function () {

            if ($.fn.DataTable.isDataTable('#example1')) {
                // $('#example1').DataTable().destroy();
                table = $('#example1').DataTable();
            } else {
                table = $('#example1').DataTable({
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "همه"]],
                    "pageLength": 10,
                    "language": {
                        "lengthMenu": "نمایش _MENU_ رکورد",
                        "zeroRecords": "موردی یافت نشد",
                        "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
                        "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
                        "infoFiltered": "(فیلتر شده از _MAX_ رکورد)",
                        "search": "جستجو:",
                        "paginate": {
                            "first": "اول",
                            "last": "آخر",
                            "next": "بعدی",
                            "previous": "قبلی"
                        }
                    }
                });
            }

            // $('#example1').DataTable({
            //     "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "همه"]],
            //     "pageLength": 10,
            //     "language": {
            //         "lengthMenu": "نمایش _MENU_ رکورد",
            //         "zeroRecords": "موردی یافت نشد",
            //         "info": "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
            //         "infoEmpty": "نمایش 0 تا 0 از 0 رکورد",
            //         "infoFiltered": "(فیلتر شده از _MAX_ رکورد)",
            //         "search": "جستجو:",
            //         "paginate": {
            //             "first": "اول",
            //             "last": "آخر",
            //             "next": "بعدی",
            //             "previous": "قبلی"
            //         }
            //     }
            // });

            // ✅ select all واقعی برای همه صفحات
            $('#select_all').on('click', function () {
                var rows = table.rows({ search: 'applied' }).nodes();
                $('input[name="selected_ids[]"]', rows).prop('checked', this.checked);
            });

            document.getElementById('assignForm').addEventListener('submit', function (e) {
                const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
                if (checked.length === 0) {
                    e.preventDefault();
                    alert('لطفاً حداقل یک مورد را انتخاب کنید');
                    return;
                }

                // جلوگیری از ارسال فرم و باز کردن مودال
                e.preventDefault();
                var modal = new bootstrap.Modal(document.getElementById('assignGroupModal'));
                modal.show();
            });

            document.getElementById('confirmAssign').addEventListener('click', function () {
                const group = document.getElementById('group_select').value;
                if (!group) {
                    alert('لطفاً یک گروه انتخاب کنید');
                    return;
                }

                const form = document.getElementById('assignForm');

                // اضافه کردن group_id
                let inputGroup = document.createElement('input');
                inputGroup.type = 'hidden';
                inputGroup.name = 'group_id';
                inputGroup.value = group;
                form.appendChild(inputGroup);

                // اضافه کردن confirmAssign تا PHP تشخیص بده
                let inputConfirm = document.createElement('input');
                inputConfirm.type = 'hidden';
                inputConfirm.name = 'confirmAssign';
                inputConfirm.value = '1';
                form.appendChild(inputConfirm);

                form.submit();
            });
        });
    </script>
</div>
</body>
</html>
