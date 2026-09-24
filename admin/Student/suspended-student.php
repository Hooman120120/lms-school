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
if (!isset($_SESSION['flash_success'])) $_SESSION['flash_success'] = [];
if (!isset($_SESSION['flash_errors']))  $_SESSION['flash_errors']  = [];

// Unsuspend Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unsuspend_student') {
    $studentUsername = trim($_POST['student_username'] ?? '');

    if ($studentUsername === '') {
        $_SESSION['flash_errors'][] = 'نام کاربری نامعتبر است.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        $response = hf_control_unsuspend_student($conn, $studentUsername);
        if (!isset($response['allowed']) || $response['allowed']) {
            $_SESSION['flash_success'][] = m('msg.student_activated');
        } else {
            $_SESSION['flash_errors'][] = hf_control_denial_message($response);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_errors'][] = 'خطا در فعال‌سازی: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Approve/Reject Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id']) && in_array($_POST['action'], ['approve', 'reject'], true)) {
    $action     = $_POST['action'];
    $requestId  = (int)($_POST['request_id'] ?? 0);

    // Reject reason validation
    $rejectReason = null;
    if ($action === 'reject') {
        $rejectReason = trim($_POST['reject_reason'] ?? '');
        if (empty($rejectReason)) {
            $_SESSION['flash_errors'][] = 'برای رد درخواست، وارد کردن علت اجباری است.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Referral Data extraction
    $referredToId    = isset($_POST['referred_to_id']) ? (int)$_POST['referred_to_id'] : 0;
    $referrerComment = trim($_POST['referrer_comment'] ?? '');

    if ($requestId <= 0) {
        $_SESSION['flash_errors'][] = 'شناسه درخواست نامعتبر است.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Fetch pending request
        $stFetch = $conn->prepare("SELECT * FROM student_suspension_requests WHERE id = ? AND status = 0 LIMIT 1");
        $stFetch->execute([$requestId]);
        $req = $stFetch->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $conn->rollBack();
            $_SESSION['flash_errors'][] = 'درخواست پیدا نشد یا قبلاً بررسی شده است.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        $newStatus = ($action === 'approve') ? 1 : 2;

        $stUpd = $conn->prepare("
            UPDATE student_suspension_requests
            SET status = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                reject_reason = ?
            WHERE id = ? AND status = 0
        ");
        $stUpd->execute([$newStatus, $_SESSION['admin']['user'], $rejectReason, $requestId]);

        // Apply to dbpersonal only if approved
        if ($newStatus === 1) {
            $studentUsername = $req['student_username'];
            $requestedMode   = (int)$req['requested_mode'];
            $requestedReason = $req['requested_reason'] ?? null;

            if ($requestedMode === 1) {
                $stApply = $conn->prepare("UPDATE dbpersonal SET suspension_mode = 1, suspension_reason = ? WHERE codemeli = ? LIMIT 1");
                $stApply->execute([$requestedReason, $studentUsername]);
                hf_control_release($conn, hf_control_login_id($studentUsername), true);
            } else {
                $response = hf_control_unsuspend_student($conn, $studentUsername);
                if (isset($response['allowed']) && !$response['allowed']) {
                    throw new RuntimeException(hf_control_denial_message($response));
                }
            }
        }

        // Handle Referral logic if requested and user is superadmin (role 0)
        if ($referredToId > 0 && isset($_SESSION['admin']['adminrole']) ) {
            // Get current admin's ID
            $stAd = $conn->prepare("SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1");
            $stAd->execute([$_SESSION['admin']['user']]);
            $currAdmin = $stAd->fetch(PDO::FETCH_ASSOC);
            $currAdminId = $currAdmin ? $currAdmin['idadmin'] : null;

            $stRef = $conn->prepare("
                INSERT INTO ticket_referrals 
                (ticket_id, ticket_type, referred_by_id, referred_to_id, referrer_comment, status, created_at)
                VALUES (?, 'student_suspension_requests', ?, ?, ?, NULL, NOW())
            ");
            $stRef->execute([$requestId, $currAdminId, $referredToId, $referrerComment]);
        }

        $conn->commit();
        $_SESSION['flash_success'][] = ($action === 'approve') ? 'درخواست تایید شد.' : 'درخواست رد شد.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['flash_errors'][] = 'خطا در انجام عملیات: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Pull flash messages
if (!empty($_SESSION['flash_errors'])) {
    $errors = array_merge($errors, $_SESSION['flash_errors']);
    $_SESSION['flash_errors'] = [];
}
if (!empty($_SESSION['flash_success'])) {
    $success = array_merge($success, $_SESSION['flash_success']);
    $_SESSION['flash_success'] = [];
}

// Fetch Data
$stPending = $conn->prepare("
    SELECT
        r.*, p.fname AS fname, p.lname AS lname, p.codemeli AS codemeli
    FROM student_suspension_requests r
    LEFT JOIN dbpersonal p ON p.codemeli = r.student_username
    WHERE r.status = 0
    ORDER BY r.created_at DESC
");
$stPending->execute();
$pendingRequests = $stPending->fetchAll(PDO::FETCH_ASSOC);

$stSusp = $conn->prepare("SELECT codemeli, fname, lname, suspension_reason FROM dbpersonal WHERE suspension_mode = 1 ORDER BY lname ASC");
$stSusp->execute();
$suspendedStudents = $stSusp->fetchAll(PDO::FETCH_ASSOC);

// Fetch Support Users for Referral Dropdown (Excluding current user)
$currentUser = $_SESSION['admin']['user'];
$stSupport = $conn->prepare("SELECT idadmin, username, fname, lname FROM dbadmin WHERE (adminrole = 52 OR adminrole = 0) AND username != ?");
$stSupport->execute([$currentUser]);
$supportUsers = $stSupport->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title><?php echo m('page.suspended_students') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .course-pill.active { box-shadow: 0 0 0 .15rem rgba(13,110,253,.25); }
    </style>
</head>

<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="col-12">
                    <?php if (!empty($errors)) : ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($errors as $e) : echo "<div>".htmlspecialchars($e)."</div>"; endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success" role="alert">
                            <?php foreach ($success as $s) : echo "<div>".htmlspecialchars($s)."</div>"; endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pending Requests -->
                    <div class="card custom-card">
                        <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex align-items-center pt-2" style="gap: 12px;">

                                    <label class="main-content-label my-auto">
                                        درخواست‌های تعلیق (در انتظار بررسی)
                                    </label>

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش می‌توانید درخواست‌های جدید تعلیق ثبت‌شده توسط اپراتورها را مشاهده و بررسی کنید و پس از ارزیابی اطلاعات، درباره تأیید یا رد هر درخواست تصمیم بگیرید."
                                          aria-label="راهنمای درخواست‌های تعلیق در انتظار بررسی">

                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                                </div>

                                <span class="d-block tx-12 mt-2 mb-0 text-muted">
                                    لیست درخواست‌های جدید اپراتورها
                                </span>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-danger rounded-pill px-3 py-2">تعداد: <?php echo count($pendingRequests); ?></span>
                                <a class="badge bg-info rounded-pill px-3 py-2" href="<?php echo url('admin/Student/suspension_history.php') ?>">سوابق تعلیق</a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example5" class="table table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th style="width: 100px;"><?php echo m('col.student') ?></th>
                                        <th>نام کاربری</th>
                                        <th>ارسال‌کننده</th>
                                        <th>نوع درخواست</th>
                                        <th style="width: 150px;">علت</th>
                                        <th>تاریخ</th>
                                        <th style="min-width: 150px;">عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($pendingRequests)) : ?>
                                        <tr><td colspan="8" class="text-center">درخواستی وجود ندارد.</td></tr>
                                    <?php else : ?>
                                        <?php foreach ($pendingRequests as $r) : ?>
                                            <tr>
                                                <td class="wd-15">
                                                    <div class="btn btn-sm ripple btn-info btn-icon" onclick="window.open('../panel/suporting.php?Id='+'<?php echo $r['codemeli'] ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )"><i class="fa fa-key"></i></div>
                                                </td>
                                                <td style="max-width: 100px; white-space: normal; word-wrap: break-word;">
                                                    <?php $fullName = trim(($r['fname'] ?? '') . ' ' . ($r['lname'] ?? '')); echo htmlspecialchars($fullName !== '' ? $fullName : '—'); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['codemeli'] ?? $r['student_username']); ?></td>
                                                <td><?php echo htmlspecialchars($r['requested_by']); ?></td>
                                                <td><?php echo ((int)$r['requested_mode'] === 1) ? 'تعلیق' : 'رفع تعلیق'; ?></td>
                                                <td style="max-width: 150px; white-space: normal; word-wrap: break-word;"><?php echo htmlspecialchars($r['requested_reason'] ?? ''); ?></td>
                                                <td>
                                                    <?php
                                                    $createdAt = $r['created_at'] ?? '';
                                                    if ($createdAt) {
                                                        $parts = explode(' ', $createdAt);
                                                        $d = explode('-', $parts[0] ?? '');
                                                        if (count($d) === 3) {
                                                            echo htmlspecialchars(implode('/', gregorian_to_jalali((int)$d[0], (int)$d[1], (int)$d[2])) . ' ' . ($parts[1] ?? ''));
                                                        } else { echo htmlspecialchars($createdAt); }
                                                    } else { echo '-'; }
                                                    ?>
                                                </td>
                                                <td style="min-width: 150px;">
                                                    <div class="d-flex flex-column gap-2">
                                                        <button type="button" class="btn btn-sm btn-success w-100" data-bs-toggle="modal" data-bs-target="#approveModal" data-request-id="<?php echo (int)$r['id']; ?>">تایید</button>
                                                        <button type="button" class="btn btn-sm btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectReasonModal" data-request-id="<?php echo (int)$r['id']; ?>">رد</button>
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
                    <div class="card custom-card mt-4">
                        <div class="card-header border-bottom-0 pb-0">
                            <div class="card-header border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex align-items-center pt-2" style="gap: 12px;">

                                        <label class="main-content-label my-auto">
                                            <?php echo m('label.suspended_students') ?>
</label>

                                        <span class="menu-info-trigger"
                                              data-tooltip="<?php echo m('tooltip.suspended_students') ?>"
                                              aria-label="<?php echo m('aria.suspended_students_help') ?>">

                                            <i class="fe fe-info fs-4"></i>
                                        </span>

                                    </div>
                                </div>

                                <div class="card-header border-bottom-0 custom-card-header d-flex justify-content-between align-items-center flex-wrap">

                                    <form action="../Bill/execel.php" method="POST" class="d-flex align-items-center" style="gap: 10px;">
                                        <input type="hidden" name="export_suspended_students_list" value="1">

                                        <div class="input-group input-group-sm" style="width: 200px;">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fa fa-calendar tx-16 lh-0 op-6"></i>
                                                </div>
                                            </div>
                                            <input class="form-control fc-datepicker" name="startdate" placeholder="از تاریخ" type="text" autocomplete="off" required>
                                        </div>

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
                                            <th>علت تعلیق</th>
                                            <th>عملیات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($suspendedStudents)) : ?>
                                            <tr>
                                                <td colspan="4" class="text-center"><?php echo m('msg.no_suspended_student') ?></td>
                                            </tr>
                                        <?php else : ?>
                                            <?php foreach ($suspendedStudents as $s) : ?>
                                                <tr>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open('../panel/suporting.php?Id='+'<?php echo $s['codemeli'] ?>','پشتیبانی',
                                                                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars(trim(($s['fname'] ?? '') . ' ' . ($s['lname'] ?? ''))); ?></td>
                                                    <td><?php echo htmlspecialchars($s['codemeli'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($s['suspension_reason'] ?? ''); ?></td>
                                                    <td style="min-width:140px;">
                                                        <form method="post" onsubmit="return confirm(<?php echo json_encode(m('confirm.activate_student'), JSON_UNESCAPED_UNICODE) ?>);">
                                                            <input type="hidden" name="action" value="unsuspend_student">
                                                            <input type="hidden" name="student_username" value="<?php echo htmlspecialchars($s['codemeli']); ?>">
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

                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">تایید درخواست</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <p>آیا از تایید این درخواست مطمئن هستید؟</p>
                            <input type="hidden" name="request_id" id="modal_approve_request_id">
                            <input type="hidden" name="action" value="approve">

                            <hr>
                            <button class="btn btn-sm btn-outline-info w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReferApprove" aria-expanded="false" aria-controls="collapseReferApprove">
                                ارجاع  ؟ (کلیک کنید)
                            </button>
                            <div class="collapse" id="collapseReferApprove">
                                <div class="card card-body p-2 border-info">
                                    <select name="referred_to_id" class="form-control mb-2">
                                        <option value="">-- بدون ارجاع (در صورت نیاز انتخاب کنید) --</option>
                                        <?php foreach ($supportUsers as $su): ?>
                                            <option value="<?php echo $su['idadmin']; ?>"><?php echo htmlspecialchars($su['fname'].' '.$su['lname'].' ('.$su['username'].')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <textarea name="referrer_comment" class="form-control" rows="2" placeholder="توضیحات ارجاع (اختیاری)"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-success">تایید درخواست</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectReasonModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">علت رد درخواست</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="rejectForm" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="request_id" id="modal_reject_request_id">
                            <input type="hidden" name="action" value="reject">
                            <div class="mb-3">
                                <label for="reject_reason_text" class="form-label">لطفا علت رد شدن این درخواست را وارد کنید:</label>
                                <textarea class="form-control" id="reject_reason_text" name="reject_reason" rows="4" required maxlength="255"></textarea>
                                <div class="invalid-feedback">وارد کردن علت رد اجباری است.</div>
                            </div>

                            <?php if (isset($_SESSION['admin']['adminrole']) && $_SESSION['admin']['adminrole'] == 0): ?>
                                <hr>
                                <button class="btn btn-sm btn-outline-info w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReferReject" aria-expanded="false" aria-controls="collapseReferReject">
                                    ارجاع به پشتیبان ؟ (کلیک کنید)
                                </button>
                                <div class="collapse" id="collapseReferReject">
                                    <div class="card card-body p-2 border-info">
                                        <select name="referred_to_id" class="form-control mb-2">
                                            <option value="">-- بدون ارجاع (در صورت نیاز انتخاب کنید) --</option>
                                            <?php foreach ($supportUsers as $su): ?>
                                                <option value="<?php echo $su['idadmin']; ?>"><?php echo htmlspecialchars($su['fname'].' '.$su['lname'].' ('.$su['username'].')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="referrer_comment" class="form-control" rows="2" placeholder="توضیحات ارجاع (اختیاری)"></textarea>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                            <button type="submit" class="btn btn-danger">ثبت و رد درخواست</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../../footers.php'; ?>
    </div>
</div>
<script>
    // DataTable Config
    const table = $("#example5").DataTable({
        stateSave: true,
        language: {
            searchPlaceholder: "جستجو کردن...",
            sSearch: "",
            lengthMenu: "_MENU_ موارد/صفحه"
        },
        drawCallback: function(settings) {
            let titleColumnIndex = -1;
            $(this.api().table().header()).find('th').each(function(index) {
                if ($(this).text().trim() === 'عنوان') {
                    titleColumnIndex = index;
                    return false;
                }
            });

            if (titleColumnIndex !== -1) {
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

    // Inject Request ID into Modals and clear data
    $('#approveModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('request-id');
        $(this).find('#modal_approve_request_id').val(id);

        // Reset accordion fields just in case
        $(this).find('select[name="referred_to_id"]').val('');
        $(this).find('textarea[name="referrer_comment"]').val('');
        $('#collapseReferApprove').collapse('hide');
    });

    $('#rejectReasonModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('request-id');
        $(this).find('#modal_reject_request_id').val(id);
        $(this).find('#reject_reason_text').val('').focus();

        // Reset accordion fields just in case
        $(this).find('select[name="referred_to_id"]').val('');
        $(this).find('textarea[name="referrer_comment"]').val('');
        $('#collapseReferReject').collapse('hide');
    });

    // Validations
    const rejectForm = document.getElementById('rejectForm');
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(event) {
            const textarea = document.getElementById('reject_reason_text');
            if (textarea.value.trim() === '') {
                event.preventDefault();
                event.stopPropagation();
                textarea.classList.add('is-invalid');
            } else {
                textarea.classList.remove('is-invalid');
            }
        }, false);
    }
</script>
</body>
</html>
