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

// Protecting against SESSION Hijaking
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

if (!Permision('administrator')) {
    header('location: ' . url('admin/index.php'));
    die();
}

include_once '../../temp.html';

$errors = [];
$success = [];

// ---- Flash storage for Post/Redirect/Get ----
if (!isset($_SESSION['flash_success_op'])) $_SESSION['flash_success_op'] = [];
if (!isset($_SESSION['flash_errors_op']))  $_SESSION['flash_errors_op']  = [];

/*
CONFIRMED MAPPING:
- dbpersonal.fname    = first name
- dbpersonal.lname    = last name
- dbpersonal.codemeli = username (same value stored in student_suspension_requests.student_username)
*/

// -------------------- DIRECT UNSUSPEND (Active again) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate_student') {
    $studentUsername = trim($_POST['student_username'] ?? '');
    $requestedBy = $_POST['requested_by'];
    if ($studentUsername === '') {
        $_SESSION['flash_errors_op'][] = 'نام کاربری نامعتبر است.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        date_default_timezone_set('Asia/Tehran');

        $st = $conn->prepare("
            UPDATE dbcourse
            SET suporter_updated_at  = ?,
                has_operator = 1,
                suporter = ?
            WHERE username = ?
        ");
        $st->execute([date("Y-m-d H:i:s"), $requestedBy, $studentUsername]);

        if ($st->rowCount() > 0) {
            $_SESSION['flash_success_op'][] = ' دانش آموز مورد نظر تخصیص پشتیبانی شد.';
        } else {
            $_SESSION['flash_errors_op'][] = 'دانش آموز مورد نظر پیدا نشد یا قبلاً فعال بوده است.';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_errors_op'][] = 'خطا در فعال‌سازی: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// -------------------- HANDLE APPROVE / REJECT --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id']) && in_array($_POST['action'], ['approve', 'reject'], true)) {
    $action     = $_POST['action'];
    $requestId  = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $_SESSION['flash_errors_op'][] = 'شناسه درخواست نامعتبر است.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Fetch pending request
        $stFetch = $conn->prepare("SELECT * FROM cancel_student_operator_request WHERE id = ? AND status = 0 LIMIT 1");
        $stFetch->execute([$requestId]);
        $req = $stFetch->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $conn->rollBack();
            $_SESSION['flash_errors_op'][] = 'درخواست پیدا نشد یا قبلاً بررسی شده است.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $newStatus = ($action === 'approve') ? 1 : 2;
        date_default_timezone_set('Asia/Tehran');

        $rejectReason = null;
        if ($action === 'reject') {
            $rejectReason = trim($_POST['reject_reason'] ?? '');
        }

        $stUpd = $conn->prepare("
            UPDATE cancel_student_operator_request
            SET status = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                reject_reason = ?
            WHERE id = ? AND status = 0
        ");

        $stUpd->execute([
            $newStatus,
            $_SESSION['admin']['user'],
            date("Y-m-d H:i:s"),
            $rejectReason,
            $requestId
        ]);

        // Apply to dbcourse only if approved
        if ($newStatus === 1) {
            $studentUsername = $req['student_username'];
            $requestedMode   = (int)$req['requested_mode'];

            if ($requestedMode === 1) {
                $stApply = $conn->prepare("
            UPDATE dbcourse
            SET has_operator = 0,
                suporter = NULL
            WHERE username = ?
        ");
                $stApply->execute([$studentUsername]);
            } else {
                $stApply = $conn->prepare("
            UPDATE dbcourse
            SET suporter = ?,
                has_operator = 1
            WHERE username = ?
        ");
                $stApply->execute([$req['requested_by'], $studentUsername]);
            }
        }

        $conn->commit();
        $_SESSION['flash_success_op'][] = ($action === 'approve') ? 'درخواست تایید شد.' : 'درخواست رد شد.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['flash_errors_op'][] = 'خطا در انجام عملیات: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ---- Pull flash messages into arrays for display ----
if (!empty($_SESSION['flash_errors_op'])) {
    $errors = array_merge($errors, $_SESSION['flash_errors_op']);
    $_SESSION['flash_errors_op'] = [];
}
if (!empty($_SESSION['flash_success_op'])) {
    $success = array_merge($success, $_SESSION['flash_success_op']);
    $_SESSION['flash_success_op'] = [];
}

// -------------------- FETCH PENDING REQUESTS --------------------
$stPending = $conn->prepare("
    SELECT
        r.*,
        p.fname AS fname,
        p.lname AS lname,
        p.codemeli AS codemeli
    FROM cancel_student_operator_request r
    LEFT JOIN dbpersonal p ON p.codemeli = r.student_username
    WHERE r.status = 0
    ORDER BY r.created_at DESC
");
$stPending->execute();
$pendingRequests = $stPending->fetchAll(PDO::FETCH_ASSOC);

// -------------------- FETCH Op CANCELED STUDENTS --------------------
$stCanc = $conn->prepare("
    SELECT dbcourse.*, dbpersonal.fname, dbpersonal.lname , cancel_student_operator_request.requested_by 
    FROM dbcourse 
    INNER JOIN dbpersonal ON dbcourse.username = dbpersonal.codemeli 
    INNER JOIN cancel_student_operator_request ON dbcourse.username = cancel_student_operator_request.student_username
    WHERE dbcourse.has_operator = 0 GROUP BY dbcourse.username
");
$stCanc->execute();
$opCanceledStudents = $stCanc->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>دانش آموزان لغو پشتیبانی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .course-pill.active {
            box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .25);
        }
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

                <div class="col-12">

                    <?php if (!empty($errors)) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($errors as $e) : ?>
                                <div><?php echo htmlspecialchars($e); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success" role="alert">
                            <?php foreach ($success as $s) : ?>
                                <div><?php echo htmlspecialchars($s); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pending Requests -->
                    <div class="card custom-card">
                        <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">درخواست‌های لغو پشتیبانی (در انتظار بررسی)</label>
                                </div>
                                <span class="d-block tx-12 mt-2 mb-0 text-muted">لیست درخواست‌های جدید اپراتورها</span>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-danger rounded-pill px-3 py-2">
                                    تعداد: <?php echo count($pendingRequests); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example5"  class="table table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>دانش‌آموز</th>
                                        <th>نام کاربری</th>
                                        <th>ارسال‌کننده</th>
                                        <th>نوع درخواست</th>
                                        <th>تاریخ</th>
                                        <th>عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($pendingRequests)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center">درخواستی وجود ندارد.</td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($pendingRequests as $r) : ?>
                                            <tr>
                                                <td class="wd-15">
                                                    <div class="position-relative d-inline-block">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('../panel/suporting.php?Id='+'<?php echo $r['codemeli'] ?>','پشتیبانی'
                                                                     ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $fullName = trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? ''));
                                                    echo htmlspecialchars($fullName !== '' ? $fullName : '—');
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['username'] ?? $r['student_username']); ?></td>
                                                <td><?php echo htmlspecialchars($r['requested_by']); ?></td>
                                                <td><?php echo ((int)$r['requested_mode'] === 1) ? 'لغو پشتیبانی' : 'رفع لغو پشتیبانی'; ?></td>
                                                <td>
                                                    <?php
                                                    $createdAt = $r['created_at'] ?? '';
                                                    if ($createdAt) {
                                                        // expected: 2025-12-28 14:35:10
                                                        $parts = explode(' ', $createdAt);
                                                        $datePart = $parts[0] ?? '';
                                                        $timePart = $parts[1] ?? '';

                                                        $d = explode('-', $datePart);
                                                        if (count($d) === 3) {
                                                            $j = gregorian_to_jalali((int)$d[0], (int)$d[1], (int)$d[2]);
                                                            $jDate = implode('/', $j);
                                                            echo htmlspecialchars(trim($jDate . ' ' . $timePart));
                                                        } else {
                                                            echo htmlspecialchars($createdAt);
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="min-width:220px;">
                                                    <div class="d-flex flex-column gap-2">
                                                        <form method="post" onsubmit="return confirm('آیا از تایید این درخواست مطمئن هستید؟');">
                                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-sm btn-success w-100">تایید</button>
                                                        </form>

                                                        <form method="post" onsubmit="return confirm('آیا از رد این درخواست مطمئن هستید؟');">
                                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="button" class="btn btn-sm btn-danger w-100" data-bs-toggle="modal"
                                                                    data-bs-target="#rejectModal"
                                                                    data-id="<?php echo (int)$r['id']; ?>">رد</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Suspended Students -->
                    <div class=" card custom-card mt-4">
                        <div class="card-header border-bottom-0 pb-0">
                            <div>
                                <div class="d-flex">
                                    <label class="main-content-label my-auto pt-2">دانش آموزان لغو پشتیبانی شده</label>
                                </div>
                            </div>
                            
<div class="card-header border-bottom-0 custom-card-header d-flex justify-content-between align-items-center flex-wrap">

    <!-- فرم انتخاب تاریخ و ارسال به excel.php -->
    <form action="../Bill/execel.php" method="POST" class="d-flex align-items-center" style="gap: 10px;">
        <input type="hidden" name="export_suspended_students" value="1">
        
        <!-- تاریخ شروع -->
        <div class="input-group input-group-sm" style="width: 200px;">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="fa fa-calendar tx-16 lh-0 op-6"></i>
                </div>
            </div>
            <input class="form-control fc-datepicker" name="startdate" placeholder="از تاریخ" type="text" autocomplete="off" required>
        </div>
        
        <!-- تاریخ پایان -->
        <div class="input-group input-group-sm" style="width: 200px;">
            <div class="input-group-prepend">
                <div class="input-group-text">
                    <i class="fa fa-calendar tx-16 lh-0 op-6"></i>
                </div>
            </div>
            <input class="form-control fc-datepicker" name="enddate" placeholder="تا تاریخ" type="text" autocomplete="off" required>
        </div>
        
        <button type="submit" class="btn btn-success btn-sm">
            <i class="fe fe-file-excel"></i> خروجی اکسل
</button>
    </form>
</div>
                            
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example1" class="table table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام و نام خانوادگی</th>
                                        <th>نام کاربری</th>
                                        <th>اپراتور درخواست داده</th>
                                        <th>اقدامات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($opCanceledStudents)) : ?>
                                        <tr>
                                            <td colspan="4" class="text-center">دانش آموز لغوپشتیبانی شده ای وجود ندارد.</td>
                                        </tr>
                                    <?php else : ?>
                                        <?php foreach ($opCanceledStudents as $s) : ?>
                                            <tr>
                                                <td class="wd-15">
                                                    <div class="position-relative d-inline-block">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('../panel/suporting.php?Id='+'<?php echo $r['codemeli'] ?>','پشتیبانی'
                                                                     ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars(trim(($s['fname'] ?? '') . ' ' . ($s['lname'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars($s['username'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($s['requested_by'] ?? '-'); ?></td>
                                                <td style="min-width:140px;">
                                                    <form method="post" onsubmit="return confirm('در صورت انجام عملیات دانش آموز مربوطه به اپراتور درخواست داده شده تخصیص می یابد. انجام شود ؟');">
                                                        <input type="hidden" name="action" value="activate_student">
                                                        <input type="hidden" name="student_username" value="<?php echo htmlspecialchars($s['username']); ?>">
                                                        <input type="hidden" name="requested_by" value="<?php echo htmlspecialchars($s['requested_by']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success w-100">فعال کردن</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div><!-- /col-12 -->

            </div>
        </div>
    </div>
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">رد درخواست</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" id="reject_request_id">

                        <div class="mb-3">
                            <label class="form-label">دلیل رد درخواست</label>
                            <textarea
                                    name="reject_reason"
                                    class="form-control"
                                    rows="4"
                                    required
                                    placeholder="دلیل رد را وارد کنید..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">ثبت رد</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
    <script>
        var rejectModal = document.getElementById('rejectModal');

        rejectModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var requestId = button.getAttribute('data-id');

            document.getElementById('reject_request_id').value = requestId;
        });
        
                const table = $("#example5").DataTable({
            stateSave: true,
            language: {
                searchPlaceholder: "جستجو کردن...",
                sSearch: "",
                lengthMenu: "_MENU_ موارد/صفحه"
            },
            drawCallback: function(settings) {
                let titleColumnIndex = -1;

                // 1. Find the index of the "عنوان" column dynamically
                $(this.api().table().header()).find('th').each(function(index) {
                    if ($(this).text().trim() === 'عنوان') {
                        titleColumnIndex = index;
                        return false; // Exit loop
                    }
                });

                if (titleColumnIndex !== -1) {
                    // 2. Apply the wrapping styles to the <a> tags in the specific column index
                    const columnIndexSelector = `:nth-child(${titleColumnIndex + 1}) a`;

                    $(this.api().table().body()).find('tr td' + columnIndexSelector).css({
                        'display': 'block',
                        'word-break': 'break-word',
                        'overflow-wrap': 'break-word',
                        'white-space': 'normal'
                    });
                }
            }
        });

    </script>
</div>
</body>

</html>