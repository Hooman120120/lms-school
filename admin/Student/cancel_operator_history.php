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

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>سوابق لغو پشتیبانی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
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
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex flex-row justify-content-between">
                            <div>
                                <h6 class="card-title mb-1">سوابق درخواست‌های لغو پشتیبانی</h6>
                                <p class="text-muted card-sub-title">لیست تمامی درخواست‌ها و تاریخچه ارجاعات آن‌ها</p>
                            </div>
                            <div ><a class="nav-link" data-bs-toggle="tooltip" href="#" title="در این صفحه سوابق لغو پشتیبانی رو مشاهده میکنید, در ستون آخر اگر ارجاعی صورت گرفته باشد جزییات آن را با کلیک بر روی دکمه آن میبینید.">
                                    <i class="fe fe-info fs-4" style="color: gray;" title="">
                                    </i>
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="example1" class="table table-bordered table-striped text-center mg-b-0 text-md-nowrap">
                                <thead>
                                <tr>
                                    <th><?php echo m('col.student') ?></th>
                                    <th>ارسال‌کننده</th>
                                    <th>نوع درخواست</th>
                                    <th>بررسی‌کننده</th>
                                    <th>وضعیت</th>
                                    <th>دلیل رد</th>
                                    <th>زمان ثبت</th>
                                    <th>عملیات / ارجاعات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // متغیری برای ذخیره کردن کدهای تمام مودال‌ها خارج از جدول
                                $modalsHtml = '';

                                // Fetch all cancel requests
                                $stmt = $conn->prepare("SELECT * FROM cancel_student_operator_request ORDER BY id DESC");
                                $stmt->execute();
                                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($requests as $row) {
                                    // Translate Status
                                    $statusText = '';
                                    $statusColor = '';
                                    if ($row['status'] == 0) {
                                        $statusText = 'در حال بررسی';
                                        $statusColor = 'warning';
                                    } elseif ($row['status'] == 1) {
                                        $statusText = 'تایید شده';
                                        $statusColor = 'success';
                                    } elseif ($row['status'] == 2) {
                                        $statusText = 'رد شده';
                                        $statusColor = 'danger';
                                    }

                                    // Translate Mode (1=cancel, 0=activate)
                                    $modeText = ($row['requested_mode'] == 1) ? '<span class="text-danger">لغو پشتیبانی</span>' : '<span class="text-success">فعال‌سازی پشتیبانی</span>';

                                    // Check if there are referrals
                                    $refStmt = $conn->prepare("
                                        SELECT tr.*, 
                                               u1.username AS referred_by_user, 
                                               u2.username AS referred_to_user
                                        FROM ticket_referrals tr
                                        LEFT JOIN dbadmin u1 ON tr.referred_by_id = u1.idadmin
                                        LEFT JOIN dbadmin u2 ON tr.referred_to_id = u2.idadmin
                                        WHERE tr.ticket_id = ? AND tr.ticket_type = 'cancel_student_operator_request'
                                        ORDER BY tr.created_at ASC
                                    ");
                                    $refStmt->execute([$row['id']]);
                                    $referrals = $refStmt->fetchAll(PDO::FETCH_ASSOC);
                                    $hasReferrals = count($referrals) > 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['student_username'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['requested_by'] ?? ''); ?></td>
                                        <td><?php echo $modeText; ?></td>
                                        <td><?php echo htmlspecialchars($row['reviewed_by'] ?? '-'); ?></td>
                                        <td><span class="badge bg-<?php echo $statusColor; ?> p-2"><?php echo $statusText; ?></span></td>
                                        <td class="text-wrap" style="max-width: 200px; text-align: right;">
                                            <?php echo htmlspecialchars($row['reject_reason'] ?? '-'); ?>
                                        </td>
                                        <td dir="ltr"><?php echo jdate('Y/m/d H:i', strtotime($row['created_at'])); ?></td>

                                        <!-- دکمه باز کردن مودال -->
                                        <td>
                                            <?php if ($hasReferrals): ?>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalRef<?php echo $row['id']; ?>">
                                                    مشاهده ارجاعات (<?php echo count($referrals); ?>)
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">بدون ارجاع</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php
                                    // ساخت بدنه مودال و اضافه کردن آن به متغیر (برای نمایش در بیرون جدول)
                                    if ($hasReferrals):
                                        ob_start(); // شروع بافر کردن خروجی
                                        ?>
                                        <!-- ساختار مودال بوت استرپ 5 -->
                                        <div class="modal fade" id="modalRef<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="modalRefLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-light">
                                                        <h5 class="modal-title text-info" id="modalRefLabel<?php echo $row['id']; ?>">
                                                            <?php echo m('label.referral_history_student') ?> <?php echo htmlspecialchars($row['student_username'] ?? ''); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-0">
                                                        <div class="table-responsive">
                                                            <table class="table table-striped table-hover mb-0 text-center">
                                                                <thead class="bg-light">
                                                                <tr>
                                                                    <th>ارجاع دهنده</th>
                                                                    <th>ارجاع شونده</th>
                                                                    <th>توضیحات ارجاع دهنده</th>
                                                                    <th>توضیحات انجام دهنده</th>
                                                                    <th>وضعیت کار</th>
                                                                    <th>تاریخ</th>
                                                                </tr>
                                                                </thead>
                                                                <tbody>
                                                                <?php foreach ($referrals as $ref): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($ref['referred_by_user'] ?? 'سیستم'); ?></td>
                                                                        <td><?php echo htmlspecialchars($ref['referred_to_user'] ?? 'نامشخص'); ?></td>
                                                                        <td class="text-wrap text-right" style="max-width: 250px;">
                                                                            <?php echo htmlspecialchars($ref['referrer_comment'] ?? '-'); ?>
                                                                        </td>
                                                                        <td class="text-wrap text-right" style="max-width: 250px;">
                                                                            <?php echo htmlspecialchars($ref['referee_comment'] ?? '-'); ?>
                                                                        </td>
                                                                        <td>
                                                                            <?php
                                                                            if ($ref['status'] === null) echo '<span class="badge bg-warning text-dark p-2">در انتظار</span>';
                                                                            elseif ($ref['status'] == 1) echo '<span class="badge bg-success p-2">تایید شده</span>';
                                                                            elseif ($ref['status'] == 0) echo '<span class="badge bg-danger p-2">رد شده</span>';
                                                                            ?>
                                                                        </td>
                                                                        <td dir="ltr"><?php echo jdate('Y/m/d H:i', strtotime($ref['created_at'])); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer justify-content-center">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن پنجره</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $modalsHtml .= ob_get_clean(); // اضافه کردن بافر به متغیر و پاک کردن بافر
                                    endif;
                                } // پایان حلقه foreach
                                ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- چاپ کردن تمام مودال ها بیرون از تگ table -->
                        <?php echo $modalsHtml; ?>

                    </div>
                </div>

            </div>
        </div>


        <?php include '../../footers.php'; ?>
    </div>
</div>
</body>
</html>
