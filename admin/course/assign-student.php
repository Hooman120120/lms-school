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

$adminrole = isset($_SESSION['admin']['adminrole'])
    ? (int) $_SESSION['admin']['adminrole']
    : null;
//* End Check Session JUST ONE Session

// گرفتن شناسه کلاس از URL
if (!isset($_GET['class_id'])) {
    die("کلاس مشخص نشده است.");
}
$class_id = intval($_GET['class_id']);

RequireTeacherPermission('teacher_perm_students', url('admin/course/Active-course.php'));
if ($adminrole === 3 && !TeacherHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دانش‌آموزان این کلاس دسترسی ندارید.');
}
if ($adminrole !== 3 && StaffCourseScopeRequired() && !StaffHasClassAccess($class_id)) {
    http_response_code(403);
    exit('شما به دانش‌آموزان این پایه و کلاس دسترسی ندارید.');
}
if ($adminrole === 3 && (
    isset($_POST['username']) || isset($_POST['upload_excel']) || isset($_GET['delete_user'])
)) {
    http_response_code(403);
    exit('معلم مجوز افزودن یا حذف دانش‌آموز کلاس را ندارد.');
}

$courseStmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$courseStmt->execute([$class_id]);
$thisClass = $courseStmt->fetch(PDO::FETCH_OBJ);
if (!$thisClass) {
    http_response_code(404);
    exit('کلاس یافت نشد.');
}
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
            $_SESSION['msg'] = "✅ دانش آموز با موفقیت ثبت شد.";
        } else {
            $_SESSION['msg'] = "⚠️ این دانش آموز قبلاً در این کلاس ثبت‌نام شده است.";
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

            $_SESSION['msg'] = "✅ تعداد ثبت موفق: $successCount";
        } else {
            $_SESSION['msg'] = "⚠️ فایل اکسل آپلود نشده یا خطا دارد.";
        }
    }
}

// حذف دانش آموز
if (isset($_GET['delete_user'])) {
    $del_user = $_GET['delete_user'];
    $del = $conn->prepare("UPDATE dbcourse SET class_id = NULL WHERE username = ? AND class_id = ?");
    $del->execute([$del_user, $class_id]);
    $_SESSION['msg'] = "🗑️ دانش آموز با موفقیت حذف شد.";
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

/* =========================
   TEACHER TICKET (گزارش)
   ========================= */

$THREAD_VIEW_PAGE = 'thread_view.php'; // as you requested
$TEACHERS_NOTIF_PAGE = url('admin/notifications/teachers_notif.php');

// CSRF for creating teacher ticket
if (empty($_SESSION['csrf_teacher_ticket'])) {
    $_SESSION['csrf_teacher_ticket'] = bin2hex(random_bytes(32));
}
$csrfTeacherTicket = $_SESSION['csrf_teacher_ticket'];

// Current logged-in username (teacher_username)
$me = $_SESSION['admin']['user'] ?? '';
$me = trim((string)$me);

// Target group labels
$TG_LABELS = [
    'admins'     => 'مدیر میانی',
    'management' => 'مدیر کل',
    'operators'  => 'پشتیبان',
];

// 1) GET: check existing ticket for teacher + class + codemeli + target_group
if (
    $adminrole === 3 &&
    isset($_GET['ticket_check']) &&
    isset($_GET['cm'], $_GET['tg'])
) {
    if (!TeacherPermission('teacher_perm_messages')) {
        http_response_code(403);
        exit('شما مجوز ارسال گزارش آموزشی را ندارید.');
    }
    $cm = trim((string)$_GET['cm']);
    $tg = trim((string)$_GET['tg']);

    if ($cm !== '' && isset($TG_LABELS[$tg]) && $me !== '') {
        $chk = $conn->prepare("
            SELECT id, status
            FROM teacher_ticket
            WHERE teacher_username = ?
              AND class_id = ?
              AND codemeli = ?
              AND target_group = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $chk->execute([$me, $class_id, $cm, $tg]);
        $t = $chk->fetch(PDO::FETCH_ASSOC);

        if ($t && in_array($t['status'], ['open', 'chatting'], true)) {
            // open popup chat
            header('Location: '.$TEACHERS_NOTIF_PAGE);
          exit;
        }

// no active ticket -> go back to THIS page and open the title modal
        header(
            'Location: assign-student.php?class_id='.(int)$class_id
            .'&show_create_ticket=1'
            .'&cm='.urlencode($cm)
            .'&tg='.urlencode($tg)
        );
        exit;


    }

    // invalid -> just back
    header('Location: assign-student.php?class_id='.(int)$class_id);
    exit;
}

// 2) POST: create ticket (or if active exists, just open it)
if (
    $adminrole === 3 &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (($_POST['action'] ?? '') === 'create_teacher_ticket')
) {
    if (!TeacherPermission('teacher_perm_messages')) {
        http_response_code(403);
        exit('شما مجوز ارسال گزارش آموزشی را ندارید.');
    }
    $csrfOk = isset($_POST['csrf_teacher_ticket']) && hash_equals($_SESSION['csrf_teacher_ticket'], $_POST['csrf_teacher_ticket']);

    $cm    = trim((string)($_POST['codemeli'] ?? ''));
    $tg    = trim((string)($_POST['target_group'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));

    if (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی نامعتبر است (CSRF).';
        header('Location: assign-student.php?class_id='.(int)$class_id);
        exit;
    }

    if ($me === '' || $cm === '' || !isset($TG_LABELS[$tg]) || $title === '') {
        $_SESSION['flash_err'] = 'لطفاً گروه و عنوان را کامل وارد کنید.';
        header('Location: assign-student.php?class_id='.(int)$class_id.'&show_create_ticket=1&cm='.urlencode($cm).'&tg='.urlencode($tg));
        exit;
    }

    // If an active ticket exists, open it instead of creating
    $chk = $conn->prepare("
        SELECT id, status
        FROM teacher_ticket
        WHERE teacher_username = ?
          AND class_id = ?
          AND codemeli = ?
          AND target_group = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $chk->execute([$me, $class_id, $cm, $tg]);
    $t = $chk->fetch(PDO::FETCH_ASSOC);

    if ($t && in_array($t['status'], ['open', 'chatting'], true)) {
        header('Location: '.$TEACHERS_NOTIF_PAGE);
        exit;
    }

    // Create new ticket (status=open)
    $ins = $conn->prepare("
        INSERT INTO teacher_ticket (codemeli, teacher_username, target_group, responder_username, title, status, class_id, created_at, updated_at)
        VALUES (?, ?, ?, NULL, ?, 'open', ?, NOW(), NOW())
    ");
    $ins->execute([$cm, $me, $tg, $title, $class_id]);

    $newId = (int)$conn->lastInsertId();

    header('Location: '.$TEACHERS_NOTIF_PAGE);
    exit;
}

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
                           <?php if ($adminrole !== 3) { ?>
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

            <?php if (!empty($_SESSION['msg'])) { echo "<p>" . $_SESSION['msg'] . "</p>"; unset($_SESSION['msg']); } ?>

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
                                <div class="d-flex align-items-center" style="gap: 12px;">

                                    <h3 class="mb-0">
                                        لیست دانش آموزان ثبت‌شده در این کلاس
                                    </h3>

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید فهرست دانش آموزانی را مشاهده کنید که در این کلاس ثبت‌نام شده‌اند. اطلاعات و وضعیت عضویت هر دانش آموز در کلاس از این قسمت قابل بررسی است."
                                          aria-label="راهنمای دانش آموزان ثبت‌شده در کلاس">

                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                                </div>
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
                                                    <div class="d-flex justify-content-center align-items-center gap-2">

                                                        <!-- KEY BUTTON -->
                                                        <a class="btn btn-danger btn-sm"
                                                           href="../Student/student_profile.php?idpersonal=<?= (int)$en['idpersonal'] ?>&class_id=<?= (int)$class_id ?>"
                                                           onclick="window.open(
                this.href,
                'studentProfile',
                'width=1000,height=800,menubar=0,toolbar=0,location=0,status=0,scrollbars=1,resizable=0,top=80'
           ); return false;">
                                                            <i class="fas fa-key fa-2x"></i>
                                                        </a>

                                                        <!-- REPORT BUTTON (teachers only) -->
                                                        <?php if ($adminrole === 3 && TeacherPermission('teacher_perm_messages')): ?>
                                                            <div class="btn-group">
                                                                <button type="button"
                                                                        class="btn btn-outline-secondary btn-sm dropdown-toggle"
                                                                        data-bs-toggle="dropdown"
                                                                        aria-expanded="false">
                                                                    گزارش
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                           href="assign-student.php?class_id=<?= (int)$class_id ?>&ticket_check=1&cm=<?= urlencode($en['codemeli']) ?>&tg=admins">
                                                                            مدیر میانی
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                           href="assign-student.php?class_id=<?= (int)$class_id ?>&ticket_check=1&cm=<?= urlencode($en['codemeli']) ?>&tg=management">
                                                                            مدیر کل
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                           href="assign-student.php?class_id=<?= (int)$class_id ?>&ticket_check=1&cm=<?= urlencode($en['codemeli']) ?>&tg=operators">
                                                                            پشتیبان
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>

                                                    </div>
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
    <?php
    $openTicketId = isset($_GET['open_ticket_id']) ? (int)$_GET['open_ticket_id'] : 0;
    $showCreate   = isset($_GET['show_create_ticket']) && $adminrole === 3;

    $cmParam = $showCreate ? trim((string)($_GET['cm'] ?? '')) : '';
    $tgParam = $showCreate ? trim((string)($_GET['tg'] ?? '')) : '';
    ?>

    <div class="modal fade" id="createTeacherTicketModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="" class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">ایجاد گزارش جدید</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="action" value="create_teacher_ticket">
                    <input type="hidden" name="csrf_teacher_ticket" value="<?= htmlspecialchars($csrfTeacherTicket, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="codemeli" id="tt_codemeli" value="">
                    <input type="hidden" name="target_group" id="tt_target_group" value="">

                    <div class="mb-2 small text-muted">
                        گروه مقصد: <span id="tt_target_group_label" class="fw-semibold"></span>
                    </div>

                    <label class="form-label">عنوان گزارش</label>
                    <input type="text" class="form-control form-control-sm" name="title" id="tt_title" required>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">ثبت و ورود به گفتگو</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">لغو</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const showCreate = <?= $showCreate ? 'true' : 'false' ?>;
            if (!showCreate) return;

            const cm = <?= json_encode($cmParam) ?>;
            const tg = <?= json_encode($tgParam) ?>;

            // fill hidden fields
            const cmInp = document.getElementById('tt_codemeli');
            const tgInp = document.getElementById('tt_target_group');
            const tgLbl = document.getElementById('tt_target_group_label');

            if (cmInp) cmInp.value = cm;
            if (tgInp) tgInp.value = tg;

            const labels = { management:'مدیر کل',admins:'مدیر میانی', operators:'پشتیبان'};
            if (tgLbl) tgLbl.textContent = labels[tg] || tg;

            // open modal (Bootstrap 5)
            const modalEl = document.getElementById('createTeacherTicketModal');
            if (modalEl && window.bootstrap && bootstrap.Modal) {
                new bootstrap.Modal(modalEl).show();
            }
            // fallback (Bootstrap 4 + jQuery)
            else if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
                jQuery('#createTeacherTicketModal').modal('show');
            }

            // ✅ remove params so refresh won't reopen modal
            const url = new URL(window.location.href);
            url.searchParams.delete('show_create_ticket');
            url.searchParams.delete('cm');
            url.searchParams.delete('tg');
            url.searchParams.delete('ticket_check');
            history.replaceState({}, document.title, url.toString());
        });
    </script>


</body>
</html>
