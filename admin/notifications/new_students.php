<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (CheckBlacklist_ip(GetIp())) { include_once '../../blackList.html'; exit; }
if (!isset($_SESSION['admin'])) { header('location: ../login.php'); die(); }

// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset(); header('location: ../login.php'); die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset(); header('location: ' . url('admin/login.php')); die();
}

// helpers
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// who am I
$me = $_SESSION['admin']['user'] ?? '';

// --- administrator flag from dbadmin ---
$sq = 'SELECT administrator FROM dbadmin WHERE username = ? LIMIT 1';
$st = $conn->prepare($sq);
$st->execute([$me]);
$adminRow = $st->fetch(PDO::FETCH_ASSOC);
$isAdministrator = !empty($adminRow) && (int)$adminRow['administrator'] === 1;

/**
 * Ticket linkage:
 * We link a “new students batch” row by:
 *   student_username (codemeli) + first_suporter_assigned_at (the batch datetime)
 *
 * IMPORTANT:
 * You said you’ll add a column named `first_suporter_assgined_at` (typo) in admin_tickets.
 * If you actually named it `first_suporter_assigned_at`, change the next line accordingly.
 */
$ticketBatchCol = 'first_suporter_assigned_at'; // <-- change if your column name differs
$ticketType = 'new_students';

/* =========================
   POST actions for "اقدام"
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfOk = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '');

    if (!$csrfOk) {
        $_SESSION['flash_err'] = 'اعتبارسنجی CSRF نامعتبر است.';
    } else {
        $studentUsername = trim((string)($_POST['student_username'] ?? ''));
        $batchAt         = trim((string)($_POST['batch_at'] ?? '')); // expected 'Y-m-d H:i:s'
        $subject         = trim((string)($_POST['subject'] ?? ''));

        if ($studentUsername === '' || $batchAt === '') {
            $_SESSION['flash_err'] = 'اطلاعات ردیف ناقص است.';
        } else {

            // find existing ticket (one per student+batch+type)
            $findSql = "SELECT id FROM admin_tickets WHERE type=? AND student_username=? AND `$ticketBatchCol`=? LIMIT 1";
            $findSt  = $conn->prepare($findSql);
            $findSt->execute([$ticketType, $studentUsername, $batchAt]);
            $existingId = (int)($findSt->fetchColumn() ?: 0);

            if ($action === 'mark_done') {
                // “انجام شد” -> status = 1
                try {
                    if ($existingId > 0) {
                        $u = $conn->prepare("UPDATE admin_tickets SET ticket_status=1, updated_at=NOW() WHERE id=? LIMIT 1");
                        $u->execute([$existingId]);
                    } else {
                        $ins = $conn->prepare("
                            INSERT INTO admin_tickets
                                (type, subject, ticket_creator, ticket_assigned_to, ticket_status, student_username, `$ticketBatchCol`, created_at, updated_at)
                            VALUES
                                (?, NULL, ?, ?, 1, ?, ?, NOW(), NOW())
                        ");
                        $ins->execute([$ticketType, $me, $me, $studentUsername, $batchAt]);
                    }
                    $_SESSION['flash_ok'] = 'ثبت شد: انجام شد ✅ (ردیف از لیست حذف می‌شود)';
                } catch (PDOException $e) {
                    $_SESSION['flash_err'] = 'خطا در ثبت انجام شد: ' . $e->getMessage();
                }
            }

            if ($action === 'create_check_ticket') {
                // “بررسی شود” -> create/open ticket (status=0) with subject
                if ($subject === '') {
                    $_SESSION['flash_err'] = 'موضوع الزامی است.';
                } else {
                    try {
                        if ($existingId > 0) {
                            // If ticket exists, bring it back to open and update subject
                            $u = $conn->prepare("UPDATE admin_tickets SET ticket_status=0, subject=?, updated_at=NOW() WHERE id=? LIMIT 1");
                            $u->execute([$subject, $existingId]);
                        } else {
                            $ins = $conn->prepare("
                                INSERT INTO admin_tickets
                                    (type, subject, ticket_creator, ticket_assigned_to, ticket_status, student_username, `$ticketBatchCol`, created_at, updated_at)
                                VALUES
                                    (?, ?, ?, NULL, 0, ?, ?, NOW(), NOW())
                            ");
                            $ins->execute([$ticketType, $subject, $me, $studentUsername, $batchAt]);
                        }
                        $_SESSION['flash_ok'] = 'تیکت بررسی ایجاد شد ✅';
                    } catch (PDOException $e) {
                        $_SESSION['flash_err'] = 'خطا در ایجاد تیکت: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    // PRG redirect
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $qs   = $_SERVER['QUERY_STRING'] ?? '';
    header("Location: " . $path . ($qs ? "?$qs" : ""));
    exit;
}

/* =========================
   GET: list new students
   - hide rows that are "done" (ticket_status=1)
   ========================= */

$doneFilterSql = "
    AND NOT EXISTS (
        SELECT 1
        FROM admin_tickets at
        WHERE at.type = ?
          AND at.student_username = d.username
          AND at.`$ticketBatchCol` = d.first_suporter_assigned_at
          AND at.ticket_status = 1
        LIMIT 1
    )
";

$ct = isset($_GET['ct']) ? trim((string)$_GET['ct']) : '';   // '', '0','1','2','3'
$courseTypeSql = '';
$courseTypeParams = [];

if ($ct !== '' && in_array($ct, ['0','1','2','3'], true)) {
    if ($ct === '3') {
        // بغل دستی: 3 OR 30..39 (30,31,32,33,...)
        $courseTypeSql = " AND (c.course_type = 3 OR (c.course_type >= 30 AND c.course_type < 40)) ";
    } else {
        $courseTypeSql = " AND c.course_type = ? ";
        $courseTypeParams[] = (int)$ct;
    }
}

if ($isAdministrator) {
    $sql = "
        SELECT
          d.username,
          p.codemeli,
          p.fname,
          p.lname,
          d.suporter,
          MAX(d.first_suporter_assigned_at) AS first_suporter_assigned_at,
          GROUP_CONCAT(DISTINCT c.coursename ORDER BY c.coursename SEPARATOR ' | ') AS coursenames
        FROM dbcourse d
        INNER JOIN dbpersonal p ON p.codemeli = d.username
        INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
        WHERE d.first_suporter_assigned_at IS NOT NULL
            $courseTypeSql
            $doneFilterSql
        GROUP BY d.username, d.suporter, p.codemeli, p.fname, p.lname
        ORDER BY first_suporter_assigned_at DESC
    ";
    $q = $conn->prepare($sql);
    $q->execute(array_merge($courseTypeParams, [$ticketType]));
} else {
    $sql = "
        SELECT
          d.username,
          p.codemeli,
          p.fname,
          p.lname,
          d.suporter,
          MAX(d.first_suporter_assigned_at) AS first_suporter_assigned_at,
          GROUP_CONCAT(DISTINCT c.coursename ORDER BY c.coursename SEPARATOR ' | ') AS coursenames
        FROM dbcourse d
        INNER JOIN dbpersonal p ON p.codemeli = d.username
        INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
        WHERE d.first_suporter_assigned_at IS NOT NULL
          AND d.suporter = ?
            $courseTypeSql
            $doneFilterSql
        GROUP BY d.username, d.suporter, p.codemeli, p.fname, p.lname
        ORDER BY first_suporter_assigned_at DESC
    ";
    $q = $conn->prepare($sql);
    $q->execute(array_merge([$me], $courseTypeParams, [$ticketType]));
}

$learners = $q->fetchAll(PDO::FETCH_OBJ);

function url_with_ct(?string $ctValue): string {
    $qs = $_GET;
    if ($ctValue === null || $ctValue === '') {
        unset($qs['ct']); // show all
    } else {
        $qs['ct'] = $ctValue;
    }
    $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    return $base . (count($qs) ? '?' . http_build_query($qs) : '');
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>لیست دانش آموزان جدید | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .action-col { width: 160px; white-space: nowrap; }
        .course-col { max-width: 320px; white-space: normal; }
    </style>
</head>

<body class="main-body leftmenu">
<div class="page">

    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <?php if (!empty($_SESSION['flash_ok'])): ?>
                    <div class="alert alert-success"><?= h($_SESSION['flash_ok']) ?></div>
                    <?php unset($_SESSION['flash_ok']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['flash_err'])): ?>
                    <div class="alert alert-danger"><?= h($_SESSION['flash_err']) ?></div>
                    <?php unset($_SESSION['flash_err']); ?>
                <?php endif; ?>

                <div class="row row-sm">
                    <div class="col-12">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header border-bottom-0 pb-0">
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">
                                        لیست دانش آموزان جدید
                                    </label>
                                </div>
                            </div>

                            <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap gap-2">
                                        <a href="<?= h(url_with_ct(null)) ?>"
                                           class="btn justify-content-center align-content-center  btn-info <?= ($ct==='') ? 'active' : '' ?>"
                                           style="height: 30px; padding: 0 10px;">
                                            همه
                                        </a>
                                        <a href="<?= h(url_with_ct('0')) ?>"
                                               class="btn  <?= ($ct==='0') ? 'btn-outline-warning' : 'btn-warning' ?>">
                                                آموزشی
                                            </a>

                                            <a href="<?= h(url_with_ct('1')) ?>"
                                               class="btn  <?= ($ct==='1') ? 'btn-outline-primary' : 'btn-primary' ?>">
                                                مهارتی
                                            </a>

                                            <a href="<?= h(url_with_ct('2')) ?>"
                                               class="btn  <?= ($ct==='2') ? 'btn-outline-success' : 'btn-success' ?>">
                                                المپیاد
                                            </a>
                                </div>

                                <div class="table-responsive">
                                    <table id="example1" class="table table-bordered mb-0">
                                        <thead>
                                        <tr>
                                            <th>وضعیت</th>
                                            <th class="action-col">ورود / اقدام</th>
                                            <th>کدملی</th>
                                            <th>نام</th>
                                            <th>نام خانوادگی</th>
                                            <th class="course-col">پایه</th>
                                            <?php if ($isAdministrator): ?>
                                                <th>اپراتور جدید</th>
                                            <?php endif; ?>
                                            <th>تاریخ تغییر</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($learners)): ?>
                                            <?php foreach ($learners as $learner): ?>
                                                <?php
                                                $studentUsername = (string)($learner->username ?? '');
                                                $batchAtRaw = (string)($learner->first_suporter_assigned_at ?? '');
                                                $batchAtForPost = $batchAtRaw ? date('Y-m-d H:i:s', strtotime($batchAtRaw)) : '';
                                                ?>
                                                <tr>
                                                    <td class="wd-15">
                                                        <p class="badge-danger p-1" style="border-radius: 5px;">جدید!</p>
                                                    </td>

                                                    <td class="action-col">
                                                        <div class="d-inline-flex gap-1">

                                                            <!-- key button -->
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open(
                                                                         '../panel/suporting.php?Id=<?php echo rawurlencode($studentUsername); ?>',
                                                                         'پشتیبانی',
                                                                         'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                         )">
                                                                <i class="fa fa-key"></i>
                                                            </div>

                                                            <!-- اقدام dropdown -->
                                                            <div class="btn-group">
                                                                <button type="button"
                                                                        class="btn btn-sm btn-warning dropdown-toggle"
                                                                        data-bs-toggle="dropdown"
                                                                        aria-expanded="false">
                                                                    اقدام
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a href="#"
                                                                           class="dropdown-item action-done"
                                                                           data-student="<?php echo h($studentUsername); ?>"
                                                                           data-batch="<?php echo h($batchAtForPost); ?>">
                                                                            انجام شد
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="#"
                                                                           class="dropdown-item action-check"
                                                                           data-bs-toggle="modal"
                                                                           data-bs-target="#checkModal"
                                                                           data-student="<?php echo h($studentUsername); ?>"
                                                                           data-batch="<?php echo h($batchAtForPost); ?>">
                                                                            بررسی شود
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>

                                                        </div>
                                                    </td>

                                                    <td><?php echo h($learner->codemeli ?? $learner->username); ?></td>
                                                    <td><?php echo h($learner->fname ?? ''); ?></td>
                                                    <td><?php echo h($learner->lname ?? ''); ?></td>

                                                    <td class="course-col">
                                                        <?php echo h($learner->coursenames ?? ''); ?>
                                                    </td>

                                                    <?php if ($isAdministrator): ?>
                                                        <td><?php echo h($learner->suporter ?? ''); ?></td>
                                                    <?php endif; ?>

                                                    <td>
                                                        <?php echo $batchAtRaw ? jdate('Y/m/d', strtotime($batchAtRaw)) : '—'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo $isAdministrator ? 8 : 7; ?>" class="text-center">
                                                    موردی یافت نشد
                                                </td>
                                            </tr>
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
    </div>

    <?php include '../../footers.php'; ?>
</div>

<!-- Hidden form for "انجام شد" -->
<form id="doneForm" method="post" class="d-none">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="mark_done">
    <input type="hidden" name="student_username" value="">
    <input type="hidden" name="batch_at" value="">
</form>

<!-- Modal for "بررسی شود" -->
<div class="modal fade" id="checkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">ایجاد تیکت بررسی</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                <input type="hidden" name="action" value="create_check_ticket">
                <input type="hidden" name="student_username" id="modalStudent" value="">
                <input type="hidden" name="batch_at" id="modalBatch" value="">

                <div class="mb-2">
                    <label class="form-label">موضوع</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>

                <div class="small text-muted">
                    دلیل خود را وارد کنید (کوتاه)
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">ثبت</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const done = e.target.closest('.action-done');
        if (!done) return;

        e.preventDefault();

        const student = done.getAttribute('data-student') || '';
        const batch   = done.getAttribute('data-batch') || '';

        if (!student || !batch) {
            alert('اطلاعات ردیف ناقص است.');
            return;
        }

        if (!confirm('آیا مطمئن هستید؟')) return;

        const form = document.getElementById('doneForm');
        form.querySelector('input[name="student_username"]').value = student;
        form.querySelector('input[name="batch_at"]').value = batch;
        form.submit();
    });

    document.getElementById('checkModal')?.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const student = btn?.getAttribute('data-student') || '';
        const batch   = btn?.getAttribute('data-batch') || '';

        this.querySelector('#modalStudent').value = student;
        this.querySelector('#modalBatch').value   = batch;
    });
</script>

</body>
</html>
