<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$msg = '';
$failedList = [];
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
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

// after: $re = sessionAdmin(...); and after you validate session_id
$adminUsername = $re['username'];   // e.g. "thisisit"

$stmtRole = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
$stmtRole->execute([$adminUsername]);

$adminrole = (int)($stmtRole->fetchColumn() ?? 0);


// گرفتن شناسه کلاس از URL
if (!isset($_GET['class_id'])) {
    die("کلاس مشخص نشده است.");
}
$class_id = intval($_GET['class_id']);

$courseStmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$courseStmt->execute([$class_id]);
$thisClass = $courseStmt->fetch(PDO::FETCH_OBJ);
$codecourse = $thisClass->codecourse;
// دریافت اطلاعات کلاس
$stmt = $conn->prepare("SELECT c.*, cr.coursename AS course_title 
                        FROM classes c
                        INNER JOIN codtbl_course cr ON c.codecourse = cr.codecourse
                        WHERE c.id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$class) die("کلاس یافت نشد.");

// ثبت دانش آموز جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if (isset($_SESSION['token']) and $_SESSION['token'] == $_POST['token']) {
        $username = $_POST['username'];

        // بررسی تکراری نبودن ثبت‌نام
        $check = $conn->prepare("SELECT * FROM dbcourse WHERE class_id = ? AND username = ?");
        $check->execute([$class_id, $username]);
        if ($check->rowCount() === 0) {
            $insert = $conn->prepare("UPDATE dbcourse SET class_id = ? WHERE username = ? AND codecourse = ?");
            $insert->execute([$class_id, $username, $codecourse]);
            $msg = "✅ دانش آموز با موفقیت ثبت شد.";
        } else {
            $msg = "⚠️ این دانش آموز قبلاً در این کلاس ثبت‌نام شده است.";
        }
        header('Location: assign-student.php?class_id=' . $class_id);
        die();
    }
}

if (isset($_POST['upload_excel'])) {
    if (isset($_SESSION['tokenn']) and $_SESSION['tokenn'] == $_POST['tokenn']) {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === 0) {
            $fileTmpPath = $_FILES['excel_file']['tmp_name'];
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $successCount = 0;
            $failedList = [];

            foreach ($rows as $i => $row) {
                // فرض: ستون اول = کد ملی
                $codemeli = trim($row[0]);
                if (empty($codemeli)) continue;

                // بررسی وجود کاربر در dbpersonal
                $stmtCheck = $conn->prepare("SELECT * FROM dbpersonal WHERE codemeli = ?");
                $stmtCheck->execute([$codemeli]);
                $personal = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$personal) {
                    $failedList[] = [
                        'codemeli' => $codemeli,
                        'reason' => 'کد ملی موجود نیست'
                    ];
                    continue;
                }

                // بررسی اینکه در این کلاس قبلا ثبت نشده باشد
                $stmtCheck2 = $conn->prepare("SELECT * FROM dbcourse WHERE class_id = ? AND username = ?");
                $stmtCheck2->execute([$class_id, $codemeli]);
                if ($stmtCheck2->rowCount() > 0) {
                    $failedList[] = [
                        'codemeli' => $codemeli,
                        'reason' => 'قبلاً در این کلاس ثبت شده است'
                    ];
                    continue;
                }

                // ثبت در کلاس
                $stmtInsert = $conn->prepare("UPDATE dbcourse SET class_id = ? WHERE username = ? AND codecourse = ?");
                $stmtInsert->execute([$class_id, $codemeli, $codecourse]);
                $successCount++;
            }

            $msg = "✅ تعداد ثبت موفق: $successCount";
        } else {
            $msg = "⚠️ فایل اکسل آپلود نشده یا خطا دارد.";
        }
    }
}

// حذف دانش آموز
if (isset($_GET['delete_user'])) {
    $del_user = $_GET['delete_user'];
    $del = $conn->prepare("UPDATE dbcourse SET class_id = NULL WHERE username = ? AND class_id = ?");
    $del->execute([$del_user, $class_id]);
    $msg = "🗑️ دانش آموز با موفقیت حذف شد.";
    header('Location: assign-student.php?class_id=' . $class_id);
    die();
}

// لیست تمام دانش آموزان موجود
$studentsStmt = $conn->prepare("SELECT * FROM dbpersonal INNER JOIN dbcourse ON dbpersonal.codemeli = dbcourse.username WHERE dbcourse.codecourse = ?");
$studentsStmt->execute([$codecourse]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
// لیست دانش آموزان ثبت‌شده در این کلاس
$enrolled = $conn->prepare("SELECT d.idcourse, p.*
                            FROM dbcourse d
                            INNER JOIN dbpersonal p ON d.username = p.codemeli
                            WHERE d.class_id = ?");
$enrolled->execute([$class_id]);
$enrolled_students = $enrolled->fetchAll(PDO::FETCH_ASSOC);

$cntStmt = $conn->prepare("
    SELECT s.student_username, COUNT(DISTINCT s.homework_id) AS cnt
    FROM homework_student s
    INNER JOIN homework_admin h ON h.id = s.homework_id
    WHERE h.class_id = ? AND h.status = 'approved'
    GROUP BY s.student_username
");
$cntStmt->execute([$class_id]);

$subCounts = [];
foreach ($cntStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $subCounts[$r['student_username']] = (int)$r['cnt'];
}


// کل تکالیف این کلاس (پیشنهاد: فقط تایید شده‌ها)
$totalStmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM homework_admin
    WHERE class_id = ? AND status = 'approved'
");
$totalStmt->execute([$class_id]);
$totalHomeworks = (int)$totalStmt->fetchColumn();


?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Product">
    <meta name="author" content="hamsepar.ir">
    <title>ثبت دانش آموزان در کلاس</title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';

    ?>
    <div class="main-content side-content pt-0" >
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card">
                           <?php if ((int)$adminrole !== 3) { ?>
    <div class="card-body">
        <form method="POST">
            <label>انتخاب دانش آموز:</label>
            <select class="form-control select2" name="username" required>
                <option value="">-- انتخاب کنید --</option>
                <?php foreach ($students as $st): ?>
                    <option value="<?= $st['username'] ?>">
                        <?= htmlspecialchars($st['fname']) . ' ' . htmlspecialchars($st['lname']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php
            $hash = bin2hex(random_bytes(32));
            $_SESSION['token'] = $hash;
            ?>
            <input class="form-control" name="token" type="hidden" value="<?= $hash ?>">
            <button class="btn ripple btn-success pd-x-20 mt-3" type="submit">➕ افزودن</button>
        </form>

        <!-- فرم افزودن گروهی با Excel -->
        <?php if (Permision('permision_viewcourse')) { ?>
            <form method="POST" enctype="multipart/form-data" class="mt-4">
                <label>📂 آپلود فایل اکسل (فقط ستون کد ملی):</label>
                <input type="file" name="excel_file" accept=".xlsx,.xls" class="form-control" required>

                <?php
                $hash = bin2hex(random_bytes(32));
                $_SESSION['tokenn'] = $hash;
                ?>
                <input class="form-control" name="tokenn" type="hidden" value="<?= $hash ?>">

                <button type="submit" name="upload_excel" class="btn btn-primary pd-x-20 mt-2">
                    ➕ ثبت گروهی دانش آموزان
                </button>
            </form>

            <?php if (!empty($msg)) echo "<p>$msg</p>"; ?>

            <?php if (!empty($failedList)): ?>
                <h3>❌ ثبت نشده‌ها:</h3>
                <table border="1" cellpadding="5">
                    <thead>
                    <tr>
                        <th >ردیف</th>
                        <th>کد ملی</th>
                        <th>علت</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($failedList as $i => $fail): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($fail['codemeli']) ?></td>
                            <td><?= htmlspecialchars($fail['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php } ?>
    </div>
<?php } ?>

                        </div>
                    </div>
                </div>
                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h3>لیست دانش آموزان ثبت‌شده در این کلاس</h3>
                            </div>
                            <div class="card-body">
                                <table id="example1" border="1" cellpadding="5" class="table table-bordered mg-b-0">
                                    <thead>
                                    <tr>
                                        <th style="width: 10px;">#</th>
                                        <?php if ($adminrole !== 3){ ?>
                                            <th style="width: 50px;">کد ملی</th>
                                        <?php } ?>
<!--                                        --><?php //if ($adminrole == 3){ ?>
                                            <th style="width: 50px;">
                                                ورود
                                            </th>
<!--                                        --><?php //} ?>
                                        <th>نام دانش آموز</th>
                                        <th> تعداد تکالیف ارسال</th>
                                        <?php if (Permision('permision_viewcourse')) { ?>
                                            <th>اقدامات</th>
                                        <?php } ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($enrolled_students) === 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">هنوز دانش آموزی ثبت‌نام نشده است.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($enrolled_students as $i => $en): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <?php if ($adminrole !== 3){ ?>
                                                <td><?= $en['codemeli'] ?></td>
                                                <?php } ?>
<!--                                                --><?php //if ($adminrole == 3){ ?>
                                                    <td class="text-center">
                                                        <a class="btn btn-danger btn-sm"
                                                           href="student_profile.php?idpersonal=<?=
                                                           $en['idpersonal'] ?>">
                                                            <i class="fas fa-key fa-2x"></i>
                                                        </a>
                                                    </td>
<!--                                                --><?php //} ?>
                                                <td><?= htmlspecialchars($en['fname']) . ' ' . htmlspecialchars($en['lname']) ?></td>
                                                <?php $done = $subCounts[$en['codemeli']] ?? 0; ?>
                                                <td><?= $done ?> از <?= $totalHomeworks ?></td>
                                                <?php if (Permision('permision_viewcourse')) { ?>
                                                    <td>
                                                        <a class="btn ripple  btn-danger pd-x-20"
                                                           href="assign-student.php?class_id=<?= $class_id ?>&delete_user=<?= $en['codemeli'] ?>"
                                                           onclick="return confirm('حذف این دانش آموز؟')">حذف</a>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</body>
</html>
