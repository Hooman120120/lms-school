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

$currentUserRole = (int)($_SESSION['admin']['adminrole'] ?? -1);
$adminUsername = $_SESSION['admin']['user'] ?? ''; // نام کاربری داخل سشن

// گرفتن آیدی ادمین از دیتابیس با استفاده از نام کاربری
$currAdminId = 0; // مقدار پیش‌فرض
if (!empty($adminUsername)) {
    $stmtGetId = $conn->prepare("SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1");
    $stmtGetId->execute([$adminUsername]);
    $fetchedId = $stmtGetId->fetchColumn();

    if ($fetchedId) {
        $currAdminId = (int)$fetchedId; // حالا آیدی عددی ادمین را داریم!
    }
}

// دسترسی فقط برای مدیریت (0)، معاونت (52) و نقش (51)
if ($currentUserRole !== 0 && $currentUserRole !== 52 && $currentUserRole !== 51) {
    header('location: ' . url('admin/index.php'));
    die();
}
include_once '../../temp.html';


$whereClause = "";
$params = [];

if ($currentUserRole === 52) {
    // نقش 52 هم ارجاعاتی که به او شده و هم ارجاعاتی که خودش داده را می‌بیند
    $whereClause = "WHERE tr.referred_to_id = ? OR tr.referred_by_id = ?";

    $params[] = $currAdminId; // (referred_to_id)
    $params[] = $currAdminId; //  (referred_by_id)

} elseif ($currentUserRole === 0 or $currentUserRole === 51 ) {
    // نقش 0 و 51 همه چیز را می‌بیند
    $whereClause = "WHERE 1=1";
}

// اضافه شدن ticket_id برای اتصال به جدول اصلی
$query = "SELECT 
            tr.id AS referral_id,
            tr.ticket_id,
            tr.ticket_type,
            tr.status,
            tr.referee_comment,
            tr.created_at,
            tr.updated_at,
            admin_by.username AS referred_by_username,
            admin_to.username AS referred_to_username
          FROM ticket_referrals tr
          LEFT JOIN dbadmin admin_by ON tr.referred_by_id = admin_by.idadmin
          LEFT JOIN dbadmin admin_to ON tr.referred_to_id = admin_to.idadmin
          $whereClause
          ORDER BY tr.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>سوابق ارجاع | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .detail-item { margin-bottom: 12px; font-size: 14px; }
        .detail-label { font-weight: bold; color: #495057; display: inline-block; width: 130px; }
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
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

            <div class="row row-sm mt-4">
                <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 grid-margin">
                    <div class="card custom-card">

                        <div class="d-flex flex-row justify-content-between">
                            <div class="card-header border-bottom-0 pb-0">
                                    <h4 class="card-title mg-b-0">سوابق ارجاعات تیکت‌ها</h4>
                                <p class="tx-12 tx-gray-500 mt-2">لیست تمامی ارجاعات و وضعیت اقدام روی آن‌ها</p>
                            </div>
                            <div class="p-2" >
                                <a class="nav-link p-0" data-bs-toggle="tooltip" href="#" title="در این صفحه سوابق ارجاع  لغو پشتیبانی و تعلیق رو مشاهده میکنید, در ستون آخر اطلاعات کامل تیکتی که از آن ارجاع صورت گرفته شده با کلیک بر دکمه آن نشان داده میشود.">
                                <i class="fe fe-info fs-4" style="color: gray;" title="">
                                    </i>
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive userlist-table">
                                <table class="table card-table table-striped table-vcenter text-nowrap mb-0" id="example1">
                                    <thead>
                                    <tr>
                                        <th class="wd-lg-10p"><span>ارجاع دهنده</span></th>
                                        <th class="wd-lg-10p"><span>ارجاع گیرنده</span></th>
                                        <th class="wd-lg-15p"><span>نوع درخواست</span></th>
                                        <th class="wd-lg-10p"><span>وضعیت ارجاع</span></th>
                                        <th class="wd-lg-25p"><span>توضیحات اقدام</span></th>
                                        <th class="wd-lg-10p"><span>تاریخ ارجاع</span></th>
                                        <th class="wd-lg-15p text-center"><span>عملیات</span></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($referrals as $index => &$row): // استفاده از & برای تغییر مستقیم در آرایه
                                        // برچسب نوع تیکت و تعیین جدول مرجع
                                        if ($row['ticket_type'] === 'student_suspension_requests') {
                                            $ticketTypeLabel = '<span class="badge bg-warning">تعلیقی</span>';
                                            $originalTableName = 'student_suspension_requests';
                                        } elseif ($row['ticket_type'] === 'cancel_student_operator_request') {
                                            $ticketTypeLabel = '<span class="badge bg-info">لغو پشتیبانی</span>';
                                            $originalTableName = 'cancel_student_operator_request';
                                        } else {
                                            $ticketTypeLabel = '<span class="badge bg-secondary">نامشخص</span>';
                                            $originalTableName = null;
                                        }

                                        // تعیین وضعیت ارجاع (نه وضعیت خود تیکت اصلی)
                                        $statusLabel = '<span class="badge bg-secondary">اقدام نشده</span>';
                                        if ($row['status'] === 1) {
                                            $statusLabel = '<span class="badge bg-success">تایید شده</span>';
                                        } elseif ($row['status'] === 0) {
                                            $statusLabel = '<span class="badge bg-danger">رد شده</span>';
                                        }

                                        // واکشی اطلاعات تیکت اصلی
                                        $origTicket = null;
                                        if ($originalTableName && !empty($row['ticket_id'])) {
                                            $stmtOrig = $conn->prepare("SELECT * FROM {$originalTableName} WHERE id = ? LIMIT 1");
                                            $stmtOrig->execute([$row['ticket_id']]);
                                            $origTicket = $stmtOrig->fetch(PDO::FETCH_ASSOC);
                                        }

                                        // ذخیره اطلاعات برای استفاده در حلقه مودال‌ها
                                        $row['orig_ticket'] = $origTicket;
                                        $row['original_table_name'] = $originalTableName;
                                        ?>

                                        <!-- ردیف جدول ارجاعات -->
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['referred_by_username'] ?? 'نامشخص'); ?></td>
                                            <td><?php echo htmlspecialchars($row['referred_to_username'] ?? 'نامشخص'); ?></td>
                                            <td><?php echo $ticketTypeLabel; ?></td>
                                            <td><?php echo $statusLabel; ?></td>
                                            <td>
                                                <div style="white-space: pre-wrap; max-width: 250px;">
                                                    <?php echo htmlspecialchars($row['referee_comment'] ?? '---'); ?>
                                                </div>
                                            </td>
                                            <td dir="ltr" class="text-right">
                                                <?php echo !empty($row['created_at']) ? jdate('Y/m/d - H:i', strtotime($row['created_at'])) : '---'; ?>
                                            </td>
                                            <td class="text-center">
                                                <!-- تغییر دکمه به تریگر مودال -->
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalTicket<?php echo $index; ?>">
                                                    <i class="fe fe-eye"></i> مشاهده تیکت اصلی
                                                </button>
                                            </td>
                                        </tr>

                                    <?php endforeach; unset($row); // آزاد کردن رفرنس حلقه ?>
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
</div>

<!-- ==============================================
     MODALS SECTION (خارج از ساختار اصلی صفحه)
     ============================================== -->
<?php foreach ($referrals as $index => $row):
    $origTicket = $row['orig_ticket'];
    $originalTableName = $row['original_table_name'];
    ?>
    <div class="modal fade" id="modalTicket<?php echo $index; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-primary"><i class="fe fe-file-text"></i> جزئیات تیکت اصلی (کد: <?php echo htmlspecialchars($row['ticket_id'] ?? 'نامشخص'); ?>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if ($origTicket):
                        // ترجمه مقادیر برای جدول لغو پشتیبانی
                        if ($originalTableName === 'cancel_student_operator_request') {
                            $reqModeText = ($origTicket['requested_mode'] == 1) ? '<span class="text-danger">لغو پشتیبانی</span>' : '<span class="text-success">فعال سازی</span>';
                        }
                        // ترجمه مقادیر برای جدول تعلیقی
                        elseif ($originalTableName === 'student_suspension_requests') {
                            $reqModeText = ($origTicket['requested_mode'] == 1) ? '<span class="text-warning">تعلیق</span>' : '<span class="text-info">رفع تعلیق</span>';
                        }

                        // وضعیت تیکت اصلی
                        $origStatusText = '<span class="badge bg-warning">در انتظار بررسی</span>';
                        if ($origTicket['status'] == 1) $origStatusText = '<span class="badge bg-success">تایید شده</span>';
                        if ($origTicket['status'] == 2) $origStatusText = '<span class="badge bg-danger">رد شده</span>';
                        ?>
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h6 class="mb-3 border-bottom pb-2">اطلاعات درخواست</h6>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-user"></i> دانش آموز:</span> <strong><?php echo htmlspecialchars($origTicket['student_username'] ?? '---'); ?></strong></div>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-edit-3"></i> ارسال کننده:</span> <?php echo htmlspecialchars($origTicket['requested_by'] ?? '---'); ?></div>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-tag"></i> نوع درخواست:</span> <strong><?php echo $reqModeText; ?></strong></div>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-calendar"></i> تاریخ ثبت:</span> <span dir="ltr"><?php echo !empty($origTicket['created_at']) ? jdate('Y/m/d - H:i', strtotime($origTicket['created_at'])) : '---'; ?></span></div>
                            </div>
                            <div class="col-md-6 pl-4">
                                <h6 class="mb-3 border-bottom pb-2">وضعیت بررسی</h6>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-activity"></i> وضعیت تیکت:</span> <?php echo $origStatusText; ?></div>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-shield"></i> بررسی کننده:</span> <?php echo htmlspecialchars($origTicket['reviewed_by'] ?? '---'); ?></div>
                                <div class="detail-item"><span class="detail-label"><i class="fe fe-clock"></i> تاریخ بررسی:</span> <span dir="ltr"><?php echo !empty($origTicket['reviewed_at']) ? jdate('Y/m/d - H:i', strtotime($origTicket['reviewed_at'])) : '---'; ?></span></div>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top">
                                <?php if ($originalTableName === 'student_suspension_requests' && !empty($origTicket['requested_reason'])): ?>
                                    <div class="detail-item mb-2">
                                        <span class="detail-label"><i class="fe fe-align-right"></i> علت درخواست:</span>
                                        <div class="mt-2 p-2 bg-light rounded border text-muted">
                                            <?php echo nl2br(htmlspecialchars($origTicket['requested_reason'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($origTicket['reject_reason'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label text-danger"><i class="fe fe-x-circle"></i> علت رد شدن:</span>
                                        <div class="mt-2 p-2 bg-danger-transparent text-danger rounded border border-danger">
                                            <?php echo nl2br(htmlspecialchars($origTicket['reject_reason'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0 text-center">
                            <i class="fe fe-alert-triangle mr-2"></i> اطلاعات تیکت اصلی در دیتابیس یافت نشد (احتمالاً از سیستم حذف شده است).
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن پنجره</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<!-- ============================================== -->

</body>
</html>
