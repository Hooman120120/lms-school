<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    exit;
}
$currentUser = (string)($_SESSION['admin']['user'] ?? '');
if ($currentUser === '' || SchoolNotificationAllowedTypes() === []) {
    http_response_code(403);
    exit('برای سمت فعلی، دسترسی به اعلان‌های مدرسه تعریف نشده است.');
}

if (empty($_SESSION['school_notification_csrf'])) {
    $_SESSION['school_notification_csrf'] = bin2hex(random_bytes(24));
}

// Opening a detail marks the notification as read first, then forwards only
// to an internal LMS URL that the notification itself declared.
if (isset($_GET['open']) && ctype_digit((string)$_GET['open'])) {
    $notificationId = (int)$_GET['open'];
    foreach (GetSchoolNotifications($currentUser, null, 300) as $candidate) {
        if ((int)$candidate['id'] !== $notificationId) continue;
        MarkSchoolNotificationRead($notificationId, $currentUser);
        $target = trim((string)($candidate['target_url'] ?? ''));
        if ($target !== '' && strpos($target, 'admin/') === 0) {
            header('Location: ' . url($target));
            exit;
        }
        break;
    }
    header('Location: ' . url('admin/notifications/school_notifications.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['school_notification_csrf'], $token)) {
        http_response_code(419);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    }
    if (isset($_POST['notification_id'])) {
        MarkSchoolNotificationRead((int)$_POST['notification_id'], $currentUser);
    } elseif (isset($_POST['mark_all'])) {
        foreach (GetSchoolNotifications($currentUser, null, 300) as $visibleNotification) {
            MarkSchoolNotificationRead((int)$visibleNotification['id'], $currentUser);
        }
    }
    $returnType = (string)($_POST['return_type'] ?? '');
    header('Location: ' . url('admin/notifications/school_notifications.php') . ($returnType !== '' ? '?type=' . rawurlencode($returnType) : ''));
    exit;
}

$catalog = SchoolNotificationCatalog();
$allowedTypes = SchoolNotificationAllowedTypes();
$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$legacyCounts = SchoolLegacyNotificationCounts($currentUser);
$selectedType = (string)($_GET['type'] ?? '');
if (!in_array($selectedType, $allowedTypes, true)) {
    $selectedType = '';
}
$view = (string)($_GET['view'] ?? 'unread');
if (!in_array($view, ['unread', 'read'], true)) $view = 'unread';
$notifications = GetSchoolNotifications($currentUser, $selectedType !== '' ? $selectedType : null, 200, $view === 'read');
$unreadCount = CountSchoolNotifications($currentUser, true);
$typeCounts = SchoolNotificationTypeCounts($currentUser);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>اعلان‌های مدرسه | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .school-notification {border-right:4px solid #d5d9df; transition:.15s ease;}
        .school-notification.unread {border-right-color:#0d6efd; background:#f5f9ff;}
        .school-notification:hover {box-shadow:0 6px 16px rgba(31,45,61,.09);}
        .notification-meta {display:flex; flex-wrap:wrap; gap:8px; color:#6c757d; font-size:12px;}
        .notification-icon {width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f1ff;color:#0d6efd;font-size:20px;flex:0 0 42px;}
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">اعلان‌های مدرسه</h2>
                        <p class="text-muted mb-0">فقط اعلان‌های مجاز برای سمت و پایه‌های تخصیص‌یافته شما نمایش داده می‌شود.</p>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                            <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                            <button class="btn btn-outline-primary" name="mark_all" value="1">خواندن همه (<?= $unreadCount ?>)</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!SchoolNotificationsReady()): ?>
                    <div class="alert alert-warning">ساختار دیتابیس اعلان‌های مدرسه هنوز نصب نشده است. migration مربوط به اعلان‌ها را اجرا کنید.</div>
                <?php else: ?>
                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy', 'executive_deputy', 'counselor', 'financial_deputy'], true)): ?>
                        <div class="card custom-card mb-3">
                            <div class="card-header border-bottom-0">
                                <h5 class="card-title mb-0">اعلان‌های اطلاع‌رسانی</h5>
                                <p class="text-muted mb-0 mt-1">اعلان‌های عمومی و اطلاع‌رسانی متناسب با سمت شما</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy', 'executive_deputy'], true)): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-info btn-block text-right p-3" href="<?= url('admin/notifications/unread_sms.php') ?>">
                                                <i class="fe fe-message-square ml-2"></i> پیامک‌های جدید
                                                <?php if ($legacyCounts['unread_sms'] > 0): ?><span class="badge badge-info float-left"><?= (int)$legacyCounts['unread_sms'] ?></span><?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy'], true)): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-primary btn-block text-right p-3" href="<?= url('admin/notifications/notification_view.php') ?>">
                                                <i class="fe fe-phone-call ml-2"></i> اعلان تماس مشاوران
                                            </a>
                                        </div>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-secondary btn-block text-right p-3" href="<?= url('admin/notifications/notif_rule_management.php') ?>">
                                                <i class="fe fe-sliders ml-2"></i> کنترل قوانین اعلان تماس
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($schoolRoleKey === 'counselor'): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-primary btn-block text-right p-3" href="<?= url('admin/notifications/notifications.php') ?>">
                                                <i class="fe fe-phone-call ml-2"></i> اعلان تماس دانش‌آموزان من
                                                <?php if ($legacyCounts['contact_alert'] > 0): ?><span class="badge badge-primary float-left"><?= (int)$legacyCounts['contact_alert'] ?></span><?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy', 'counselor'], true)): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-danger btn-block text-right p-3" href="<?= url('admin/notifications/thread_list.php') ?>">
                                                <i class="fe fe-inbox ml-2"></i> تیکت‌های اعلان تماس
                                                <?php if ($legacyCounts['contact_ticket'] > 0): ?><span class="badge badge-danger float-left"><?= (int)$legacyCounts['contact_ticket'] ?></span><?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card custom-card mb-3">
                        <div class="card-header border-bottom-0">
                            <h5 class="card-title mb-0">دسته‌بندی اعلان‌ها</h5>
                            <p class="text-muted mb-0 mt-1">روی هر دسته بزنید تا فقط اعلان‌های همان بخش را ببینید.</p>
                        </div>
                        <div class="card-body">
                            <div class="row">
                            <?php foreach ($allowedTypes as $type): ?>
                                <?php $counts = $typeCounts[$type] ?? ['total' => 0, 'unread' => 0]; ?>
                                <div class="col-md-6 col-xl-4 mb-3">
                                    <a class="btn btn-block text-right p-3 <?= $selectedType === $type ? 'btn-primary' : 'btn-outline-primary' ?>"
                                       href="<?= url('admin/notifications/school_notifications.php?type=' . rawurlencode($type) . '&view=unread') ?>">
                                        <i class="fe <?= htmlspecialchars($catalog[$type]['icon']) ?> ml-2"></i>
                                        <?= htmlspecialchars($catalog[$type]['title']) ?>
                                        <span class="float-left">
                                            <?php if ($counts['unread'] > 0): ?><span class="badge badge-warning ml-1"><?= (int)$counts['unread'] ?> جدید</span><?php endif; ?>
                                            <span class="badge badge-light"><?= (int)$counts['total'] ?></span>
                                        </span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap:8px">
                        <h5 class="mb-0"><?= $selectedType === '' ? 'همه اعلان‌ها' : htmlspecialchars($catalog[$selectedType]['title']) ?></h5>
                        <div class="btn-group">
                            <a class="btn btn-sm <?= $view === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php' . ($selectedType !== '' ? '?type=' . rawurlencode($selectedType) . '&view=unread' : '')) ?>">دریافت‌های جدید<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a>
                            <a class="btn btn-sm <?= $view === 'read' ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="<?= url('admin/notifications/school_notifications.php?view=read' . ($selectedType !== '' ? '&type=' . rawurlencode($selectedType) : '')) ?>">آرشیو خوانده‌شده</a>
                        </div>
                    </div>

                    <?php if ($notifications === []): ?>
                        <div class="alert alert-light text-center py-5"><?= $view === 'read' ? 'هنوز اعلانی را نخوانده‌اید.' : 'اعلان خوانده‌نشده‌ای در این بخش ندارید.' ?></div>
                    <?php endif; ?>

                    <?php foreach ($notifications as $notification):
                        $meta = $catalog[$notification['event_type']] ?? ['title' => $notification['event_type'], 'icon' => 'fe-bell'];
                        $target = trim((string)($notification['target_url'] ?? ''));
                    ?>
                        <div class="card custom-card school-notification <?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?> mb-2">
                            <div class="card-body d-flex align-items-start" style="gap:14px">
                                <div class="notification-icon"><i class="fe <?= htmlspecialchars($meta['icon']) ?>"></i></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px">
                                        <div>
                                            <span class="badge badge-light mb-1"><?= htmlspecialchars($meta['title']) ?></span>
                                            <h5 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h5>
                                        </div>
                                        <?php if ((int)$notification['is_read'] === 0): ?><span class="badge badge-primary">جدید</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($notification['body'])): ?><p class="mb-2"><?= nl2br(htmlspecialchars($notification['body'])) ?></p><?php endif; ?>
                                    <div class="notification-meta">
                                        <?php if (trim((string)$notification['actor_name']) !== ''): ?><span><i class="fe fe-user"></i> <?= htmlspecialchars(trim($notification['actor_name'])) ?></span><?php endif; ?>
                                        <?php if (!empty($notification['coursename'])): ?><span><i class="fe fe-layers"></i> <?= htmlspecialchars($notification['coursename']) ?></span><?php endif; ?>
                                        <?php if (!empty($notification['class_name'])): ?><span><i class="fe fe-users"></i> <?= htmlspecialchars($notification['class_name']) ?></span><?php endif; ?>
                                        <span><i class="fe fe-clock"></i> <?= htmlspecialchars(toJalali($notification['created_at'], 'Y/m/d H:i', 'fa')) ?></span>
                                    </div>
                                    <div class="mt-3 d-flex" style="gap:8px">
                                        <?php if ($target !== '' && strpos($target, 'admin/') === 0): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(url('admin/notifications/school_notifications.php?open=' . (int)$notification['id'])) ?>">مشاهده جزئیات</a>
                                        <?php endif; ?>
                                        <?php if ((int)$notification['is_read'] === 0): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                                                <button class="btn btn-sm btn-outline-secondary">خواندم</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy', 'financial_deputy'], true)): ?>
                        <div class="card custom-card mt-4">
                            <div class="card-header border-bottom-0"><h5 class="card-title mb-0">اعلان‌های مالی</h5><p class="text-muted mb-0 mt-1">موارد مالی منتظر بررسی و تأیید</p></div>
                            <div class="card-body"><div class="row">
                                <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy'], true) || ($schoolRoleKey === 'financial_deputy' && Permision('permision_actfactor'))): ?>
                                    <div class="col-md-6 col-xl-4 mb-3"><a class="btn btn-outline-warning btn-block text-right p-3" href="<?= url('admin/Bill/active-factor.php') ?>"><i class="fe fe-file-text ml-2"></i> فاکتورهای منتظر تأیید<?php if (countCheckfactor() > 0): ?><span class="badge badge-warning float-left"><?= (int)countCheckfactor() ?></span><?php endif; ?></a></div>
                                    <div class="col-md-6 col-xl-4 mb-3"><a class="btn btn-outline-info btn-block text-right p-3" href="<?= url('admin/notifications/verify_by_manager.php') ?>"><i class="fe fe-credit-card ml-2"></i> فیش‌های واریزی<?php if ($legacyCounts['pending_invoice'] > 0): ?><span class="badge badge-info float-left"><?= (int)$legacyCounts['pending_invoice'] ?></span><?php endif; ?></a></div>
                                <?php endif; ?>
                                <?php if (inst_feature('wallet') && (in_array($schoolRoleKey, ['manager', 'general_deputy'], true) || ($schoolRoleKey === 'financial_deputy' && Permision('permision_actwallet')))): ?>
                                    <div class="col-md-6 col-xl-4 mb-3"><a class="btn btn-outline-success btn-block text-right p-3" href="<?= url('admin/Bill/waitingWallet.php') ?>"><i class="fe fe-credit-card ml-2"></i> تأیید شارژ کیف پول<?php if ($legacyCounts['wallet_charge_pending'] > 0): ?><span class="badge badge-success float-left"><?= (int)$legacyCounts['wallet_charge_pending'] ?></span><?php endif; ?></a></div>
                                <?php endif; ?>
                            </div></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include '../../footers.php'; ?>
</body>
</html>
