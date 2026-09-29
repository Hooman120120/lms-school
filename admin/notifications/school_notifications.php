<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    exit;
}
$notificationInstitution = $notificationInstitution ?? 'school';
if (!inst_is($notificationInstitution)) {
    http_response_code(404);
    exit('این صفحه برای نوع مؤسسهٔ فعلی فعال نیست.');
}
$notificationPrefix = inst_is('institute') ? 'institute-notifications' : 'school-notifications';
$notificationTitle = inst_is('institute') ? 'اعلان‌های آموزشگاه' : 'اعلان‌های مدرسه';
$notificationHome = 'admin/notifications/' . (inst_is('institute') ? 'institute_notifications.php' : 'school_notifications.php');
$notificationList = 'admin/notifications/' . (inst_is('institute') ? 'institute_notification_list.php' : 'school_notification_list.php');
$currentUser = (string)($_SESSION['admin']['user'] ?? '');
$isNotificationListPage = !empty($_GET['list']);
$notificationUsesTree = AdminHasAnyCurrentTreePermission();
$directNotificationCount = CountSchoolDirectNotifications($currentUser, false);
$notificationCan = static function (string $suffix) use ($notificationUsesTree, $notificationPrefix): bool {
    if (!$notificationUsesTree) return true;
    // A grant for a page operation must also reveal that page's entry card.
    return str_starts_with($suffix, 'information.') || str_starts_with($suffix, 'financial.')
        ? AdminHasMenuNavigationPermission($notificationPrefix . '.' . $suffix)
        : AdminHasMenuPermission($notificationPrefix . '.' . $suffix);
};
$notificationSectionCan = static function (string $suffix) use ($notificationUsesTree, $notificationPrefix): bool {
    return !$notificationUsesTree || AdminHasMenuNavigationPermission($notificationPrefix . '.' . $suffix);
};
if ($notificationUsesTree && !AdminHasMenuNavigationPermission($notificationPrefix) && $directNotificationCount === 0) {
    http_response_code(403);
    exit('دسترسی شما به اعلان‌ها فعال نشده است.');
}
$allowedTypes = array_values(array_intersect(SchoolNotificationAllowedTypes(), array_keys(SchoolNotificationCatalog())));
if ($notificationUsesTree) {
    $allowedTypes = array_values(array_filter($allowedTypes, static fn(string $type): bool =>
        IsSchoolDirectNotificationType($type)
        || AdminHasMenuPermission($notificationPrefix . '.categories.' . $type)));
}
$canCurrentNotifications = $directNotificationCount > 0
    || $notificationCan('current')
    || ($allowedTypes !== [] && $notificationSectionCan('categories'));
if ($currentUser === '' || SchoolNotificationAllowedTypes() === []) {
    http_response_code(403);
    exit('برای سمت فعلی، دسترسی به اعلان‌ها تعریف نشده است.');
}

if (empty($_SESSION['school_notification_csrf'])) {
    $_SESSION['school_notification_csrf'] = bin2hex(random_bytes(24));
}

$detailNotification = null;
if (isset($_GET['detail']) && ctype_digit((string)$_GET['detail'])) {
    $detailId = (int)$_GET['detail'];
    foreach (GetSchoolNotifications($currentUser, null, 300) as $candidate) {
        if ((int)$candidate['id'] !== $detailId) continue;
        $candidateType = (string)$candidate['event_type'];
        if (!in_array($candidateType, $allowedTypes, true)) break;
        if (!$notificationCan('open') && !IsSchoolDirectNotificationType($candidateType)) {
            http_response_code(403);
            exit('دسترسی مشاهدهٔ جزئیات اعلان فعال نشده است.');
        }
        MarkSchoolNotificationRead($detailId, $currentUser);
        $candidate['is_read'] = 1;
        $detailNotification = $candidate;
        break;
    }
}

// Opening a detail marks the notification as read first, then forwards only
// to an internal LMS URL that the notification itself declared.
if (isset($_GET['open']) && ctype_digit((string)$_GET['open'])) {
    $notificationId = (int)$_GET['open'];
    foreach (GetSchoolNotifications($currentUser, null, 300) as $candidate) {
        if ((int)$candidate['id'] !== $notificationId) continue;
        if (!in_array((string)$candidate['event_type'], $allowedTypes, true)) break;
        if (!$notificationCan('open') && !IsSchoolDirectNotificationType((string)$candidate['event_type'])) {
            http_response_code(403);
            exit('دسترسی مشاهدهٔ جزئیات اعلان فعال نشده است.');
        }
        MarkSchoolNotificationRead($notificationId, $currentUser);
        $target = trim((string)($candidate['target_url'] ?? ''));
        if ($target !== '' && strpos($target, 'admin/') === 0) {
            header('Location: ' . url($target));
            exit;
        }
        break;
    }
    header('Location: ' . url($notificationHome));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['school_notification_csrf'], $token)) {
        http_response_code(419);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    }
    if (isset($_POST['notification_id'])) {
        foreach (GetSchoolNotifications($currentUser, null, 300) as $visibleNotification) {
            if ((int)$visibleNotification['id'] === (int)$_POST['notification_id']
                && in_array((string)$visibleNotification['event_type'], $allowedTypes, true)) {
                if (!$notificationCan('mark-read')
                    && !IsSchoolDirectNotificationType((string)$visibleNotification['event_type'])) {
                    http_response_code(403);
                    exit('دسترسی خواندن اعلان فعال نشده است.');
                }
                MarkSchoolNotificationRead((int)$_POST['notification_id'], $currentUser);
                break;
            }
        }
    } elseif (isset($_POST['mark_all'])) {
        $canMarkAllNotifications = $notificationCan('mark-all');
        foreach (GetSchoolNotifications($currentUser, null, 300) as $visibleNotification) {
            $visibleType = (string)$visibleNotification['event_type'];
            if (in_array($visibleType, $allowedTypes, true)
                && ($canMarkAllNotifications || IsSchoolDirectNotificationType($visibleType))) {
                MarkSchoolNotificationRead((int)$visibleNotification['id'], $currentUser);
            }
        }
    }
    $returnType = (string)($_POST['return_type'] ?? '');
    $returnUrl = ($isNotificationListPage ? $notificationList : $notificationHome);
    $returnQuery = $returnType !== '' ? '?type=' . rawurlencode($returnType) : '';
    header('Location: ' . url($returnUrl) . $returnQuery);
    exit;
}

$catalog = SchoolNotificationCatalog();
$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$legacyCounts = SchoolLegacyNotificationCounts($currentUser);
$selectedType = (string)($_GET['type'] ?? '');
if (!in_array($selectedType, $allowedTypes, true)) {
    $selectedType = '';
}
$view = (string)($_GET['view'] ?? 'unread');
if (!in_array($view, ['unread', 'read'], true)) $view = 'unread';
if ($isNotificationListPage && !($view === 'read' ? ($notificationCan('history') || $directNotificationCount > 0) : $canCurrentNotifications)) {
    http_response_code(403);
    exit('دسترسی این فهرست اعلان‌ها فعال نشده است.');
}
$notificationPage = ($isNotificationListPage ? $notificationList : $notificationHome);
$notifications = array_values(array_filter(
    GetSchoolNotifications($currentUser, $selectedType !== '' ? $selectedType : null, 200, $view === 'read'),
    static fn(array $item): bool => in_array((string)$item['event_type'], $allowedTypes, true)
));
$typeCounts = SchoolNotificationTypeCounts($currentUser);
$unreadCount = 0;
foreach ($allowedTypes as $type) $unreadCount += (int)($typeCounts[$type]['unread'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($notificationTitle) ?> | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .notification-heading {display:flex; align-items:center; gap:10px;}
        .notification-heading .menu-info-trigger {flex-shrink:0;}
        .school-notification {border-right:4px solid #d5d9df; transition:.15s ease;}
        .school-notification.unread {border-right-color:#0d6efd; background:#f5f9ff;}
        .school-notification:hover {box-shadow:0 6px 16px rgba(31,45,61,.09);}
        .notification-meta {display:flex; flex-wrap:wrap; gap:8px; color:#6c757d; font-size:12px;}
        .notification-icon {width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f1ff;color:#0d6efd;font-size:20px;flex:0 0 42px;}
        .notification-detail-backdrop {position:fixed;inset:0;z-index:1060;background:rgba(22,29,37,.58);display:flex;align-items:center;justify-content:center;padding:20px;}
        .notification-detail {width:min(720px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 18px 55px rgba(0,0,0,.28);}
        .notification-detail-header {display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:20px 24px;border-bottom:1px solid #e8ebf0;}
        .notification-detail-close {font-size:28px;line-height:1;color:#6c757d;text-decoration:none;}
        .notification-detail-body {padding:24px;}
        .notification-detail-message {white-space:pre-wrap;line-height:2;background:#f8f9fb;border:1px solid #e8ebf0;border-radius:10px;padding:18px;color:#2f3542;}
        .notification-detail-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:18px;}
        .notification-detail-field {background:#f8f9fb;border-radius:8px;padding:11px 13px;}
        .notification-detail-field small {display:block;color:#7b8190;margin-bottom:3px;}
        @media (max-width:575px) {.notification-detail-grid {grid-template-columns:1fr;}}
    </style>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once __DIR__ . '/../info-tooltip.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5 notification-heading"><?= htmlspecialchars($notificationTitle) ?>
                            <span class="menu-info-trigger" tabindex="0"
                                  data-tooltip="<?= htmlspecialchars(m('menu.school_notifications_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                                  aria-label="<?= htmlspecialchars('راهنمای ' . $notificationTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fe fe-info fs-4" aria-hidden="true"></i>
                            </span>
                        </h2>
                        <p class="text-muted mb-0">اعلان‌های مجاز سازمانی و نامه‌هایی که مستقیماً برای شما ارسال شده‌اند نمایش داده می‌شوند.</p>
                    </div>
                    <?php if ($isNotificationListPage && $unreadCount > 0 && ($notificationCan('mark-all') || $directNotificationCount > 0)): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                            <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                            <button class="btn btn-outline-primary" name="mark_all" value="1">خواندن همه (<?= $unreadCount ?>)</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (!SchoolNotificationsReady()): ?>
                    <div class="alert alert-warning">ساختار دیتابیس اعلان‌ها هنوز نصب نشده است. migration مربوط به اعلان‌ها را اجرا کنید.</div>
                <?php else: ?>
                    <?php if (!$isNotificationListPage && $notificationSectionCan('information') && in_array($schoolRoleKey, ['manager', 'general_deputy', 'executive_deputy', 'counselor', 'financial_deputy'], true)): ?>
                        <div class="card custom-card mb-3">
                            <div class="card-header border-bottom-0">
                                <h5 class="card-title mb-0 notification-heading">اعلان‌های اطلاع‌رسانی
                                    <span class="menu-info-trigger" tabindex="0"
                                          data-tooltip="<?= htmlspecialchars(m('notifications.information_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                                          aria-label="راهنمای اعلان‌های اطلاع‌رسانی">
                                        <i class="fe fe-info fs-4" aria-hidden="true"></i>
                                    </span>
                                </h5>
                                <p class="text-muted mb-0 mt-1">اعلان‌های عمومی و اطلاع‌رسانی متناسب با سمت شما</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy'], true)): ?>
                                        <?php if ($notificationCan('information.rules')): ?><div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-secondary btn-block text-right p-3" href="<?= url('admin/notifications/notif_rule_management.php') ?>">
                                                <i class="fe fe-sliders ml-2"></i> کنترل قوانین اعلان تماس
                                            </a>
                                        </div><?php endif; ?>
                                        <?php if ($notificationCan('information.tickets')): ?><div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-danger btn-block text-right p-3" href="<?= url('admin/notifications/thread_list.php') ?>">
                                                <i class="fe fe-inbox ml-2"></i> تیکت‌های اعلان تماس
                                                <?php if ($legacyCounts['contact_ticket'] > 0): ?><span class="badge badge-danger float-left"><?= (int)$legacyCounts['contact_ticket'] ?></span><?php endif; ?>
                                            </a>
                                        </div><?php endif; ?>
                                        <?php if ($notificationCan('information.operator')): ?><div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-primary btn-block text-right p-3" href="<?= url('admin/notifications/notification_view.php') ?>">
                                                <i class="fe fe-phone-call ml-2"></i> <?= inst_is('institute') ? 'اعلان تماس پشتیبان‌ها' : 'اعلان تماس مشاوران' ?>
                                            </a>
                                        </div><?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($schoolRoleKey === 'counselor' && $notificationCan('information.my-calls')): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-primary btn-block text-right p-3" href="<?= url('admin/notifications/notifications.php') ?>">
                                                <i class="fe fe-phone-call ml-2"></i> <?= inst_is('institute') ? 'اعلان تماس فراگیران من' : 'اعلان تماس دانش‌آموزان من' ?>
                                                <?php if ($legacyCounts['contact_alert'] > 0): ?><span class="badge badge-primary float-left"><?= (int)$legacyCounts['contact_alert'] ?></span><?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($schoolRoleKey === 'counselor' && $notificationCan('information.tickets')): ?>
                                        <div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-danger btn-block text-right p-3" href="<?= url('admin/notifications/thread_list.php') ?>">
                                                <i class="fe fe-inbox ml-2"></i> تیکت‌های اعلان تماس
                                                <?php if ($legacyCounts['contact_ticket'] > 0): ?><span class="badge badge-danger float-left"><?= (int)$legacyCounts['contact_ticket'] ?></span><?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (in_array($schoolRoleKey, ['manager', 'general_deputy', 'executive_deputy'], true)): ?>
                                        <?php if ($notificationCan('information.sms')): ?><div class="col-md-6 col-xl-4 mb-3">
                                            <a class="btn btn-outline-info btn-block text-right p-3" href="<?= url('admin/notifications/unread_sms.php') ?>">
                                                <i class="fe fe-message-square ml-2"></i> پیامک‌های جدید
                                                <?php if ($legacyCounts['unread_sms'] > 0): ?><span class="badge badge-info float-left"><?= (int)$legacyCounts['unread_sms'] ?></span><?php endif; ?>
                                            </a>
                                        </div><?php endif; ?>
                                        <?php if ($notificationCan('information.email')): ?><div class="col-md-6 col-xl-4 mb-3">
                                            <button class="btn btn-outline-dark btn-block text-right p-3" type="button" disabled title="اتصال صندوق ایمیل در مرحله بعد اضافه می‌شود">
                                                <i class="fe fe-mail ml-2"></i> اعلان ایمیل‌های جدید
                                                <span class="badge badge-light float-left">به‌زودی</span>
                                            </button>
                                        </div><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isNotificationListPage && $allowedTypes !== [] && ($canCurrentNotifications || $notificationCan('history') || $directNotificationCount > 0)): ?><div class="card custom-card mb-3">
                        <div class="card-header border-bottom-0">
                            <h5 class="card-title mb-0 notification-heading"><?= inst_is('institute') ? 'اعلان‌های دوره‌ها' : 'اعلان‌های پایه‌ها' ?>
                                    <span class="menu-info-trigger" tabindex="0"
                                          data-tooltip="<?= htmlspecialchars(m('notifications.categories_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                                          aria-label="<?= inst_is('institute') ? 'راهنمای اعلان‌های دوره‌ها' : 'راهنمای اعلان‌های پایه‌ها' ?>">
                                        <i class="fe fe-info fs-4" aria-hidden="true"></i>
                                    </span>
                                </h5>
                            <p class="text-muted mb-0 mt-1">اعلان‌های آموزشی و نامه‌های دریافتی شما در دسته‌های جدا نمایش داده می‌شوند.</p>
                        </div>
                        <div class="card-body">
                            <div class="row">
                            <?php foreach ($allowedTypes as $type): ?>
                                <?php $counts = $typeCounts[$type] ?? ['total' => 0, 'unread' => 0]; ?>
                                <?php $typeView = IsSchoolDirectNotificationType($type) && (int)$counts['unread'] === 0 ? 'read' : ($canCurrentNotifications ? 'unread' : 'read'); ?>
                                <div class="col-md-6 col-xl-4 mb-3">
                                    <a class="btn btn-block text-right p-3 <?= $selectedType === $type ? 'btn-primary' : 'btn-outline-primary' ?>"
                                       href="<?= url($notificationList . '?type=' . rawurlencode($type) . '&view=' . $typeView) ?>">
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
                    <?php endif; ?>

                    <?php if ($isNotificationListPage): ?>
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3" style="gap:8px">
                        <h5 class="mb-0 notification-heading"><?= $selectedType === '' ? 'همه اعلان‌ها' : htmlspecialchars($catalog[$selectedType]['title']) ?>
                            <span class="menu-info-trigger" tabindex="0"
                                  data-tooltip="<?= htmlspecialchars(m('notifications.list_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                                  aria-label="راهنمای فهرست اعلان‌ها">
                                <i class="fe fe-info fs-4" aria-hidden="true"></i>
                            </span>
                        </h5>
                        <div class="btn-group">
                            <?php if ($canCurrentNotifications): ?><a class="btn btn-sm <?= $view === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url($notificationPage . ($selectedType !== '' ? '?type=' . rawurlencode($selectedType) . '&view=unread' : '')) ?>">دریافت‌های جدید<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a><?php endif; ?>
                            <?php if ($notificationCan('history') || $directNotificationCount > 0): ?><a class="btn btn-sm <?= $view === 'read' ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="<?= url($notificationPage . '?view=read' . ($selectedType !== '' ? '&type=' . rawurlencode($selectedType) : '')) ?>">آرشیو خوانده‌شده</a><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($notifications === []): ?>
                        <div class="alert alert-light text-center py-5"><?= $view === 'read' ? 'هنوز اعلانی را نخوانده‌اید.' : 'اعلان خوانده‌نشده‌ای در این بخش ندارید.' ?></div>
                    <?php endif; ?>

                    <?php foreach ($notifications as $notification):
                        $meta = $catalog[$notification['event_type']] ?? ['title' => $notification['event_type'], 'icon' => 'fe-bell'];
                        $target = trim((string)($notification['target_url'] ?? ''));
                        $detailQuery = http_build_query(array_filter([
                            'type' => $selectedType,
                            'view' => $view,
                            'detail' => (int)$notification['id'],
                        ], static fn($value): bool => $value !== ''));
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
                                        <?php if (!empty($notification['priority']) && IsSchoolDirectNotificationType((string)$notification['event_type'])): ?><span><i class="fe fe-flag"></i> <?= htmlspecialchars($notification['priority']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="mt-3 d-flex" style="gap:8px">
                                        <?php if (IsSchoolDirectNotificationType((string)$notification['event_type'])): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(url($notificationPage . '?' . $detailQuery)) ?>">مشاهده کامل</a>
                                        <?php endif; ?>
                                        <?php if ($notificationCan('open') && $target !== '' && strpos($target, 'admin/') === 0): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(url($notificationHome . '?open=' . (int)$notification['id'])) ?>">مشاهده جزئیات</a>
                                        <?php endif; ?>
                                        <?php if (!empty($notification['attachment']) && str_starts_with((string)$notification['attachment'], 'uploads/')): ?>
                                            <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= htmlspecialchars(url((string)$notification['attachment'])) ?>">دریافت پیوست</a>
                                        <?php endif; ?>
                                        <?php if (($notificationCan('mark-read') || IsSchoolDirectNotificationType((string)$notification['event_type'])) && (int)$notification['is_read'] === 0): ?>
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

                    <?php if ($detailNotification !== null):
                        $detailType = (string)$detailNotification['event_type'];
                        $detailMeta = $catalog[$detailType] ?? ['title' => $detailType, 'icon' => 'fe-bell'];
                        $detailCloseQuery = http_build_query(array_filter([
                            'type' => $selectedType,
                            'view' => $view,
                        ], static fn($value): bool => $value !== ''));
                        $detailCloseUrl = url($notificationPage . ($detailCloseQuery !== '' ? '?' . $detailCloseQuery : ''));
                        $detailActor = trim((string)($detailNotification['actor_name'] ?? ''));
                        if ($detailActor === '') $detailActor = trim((string)($detailNotification['actor_username'] ?? ''));
                    ?>
                        <div class="notification-detail-backdrop" role="presentation">
                            <section class="notification-detail" role="dialog" aria-modal="true" aria-labelledby="notificationDetailTitle">
                                <div class="notification-detail-header">
                                    <div>
                                        <span class="badge badge-primary mb-2"><?= htmlspecialchars($detailMeta['title']) ?></span>
                                        <h4 id="notificationDetailTitle" class="mb-0"><?= htmlspecialchars((string)$detailNotification['title']) ?></h4>
                                    </div>
                                    <a class="notification-detail-close" href="<?= htmlspecialchars($detailCloseUrl) ?>" aria-label="بستن">&times;</a>
                                </div>
                                <div class="notification-detail-body">
                                    <div class="notification-detail-message"><?= htmlspecialchars((string)($detailNotification['body'] ?? '')) ?></div>
                                    <div class="notification-detail-grid">
                                        <div class="notification-detail-field"><small>نوع</small><strong><?= htmlspecialchars($detailMeta['title']) ?></strong></div>
                                        <div class="notification-detail-field"><small>فرستنده</small><strong><?= htmlspecialchars($detailActor !== '' ? $detailActor : 'سامانه') ?></strong></div>
                                        <div class="notification-detail-field"><small>تاریخ ارسال</small><strong><?= htmlspecialchars(toJalali((string)$detailNotification['created_at'], 'Y/m/d H:i', 'fa')) ?></strong></div>
                                        <div class="notification-detail-field"><small>اولویت</small><strong><?= htmlspecialchars(trim((string)($detailNotification['priority'] ?? '')) ?: 'عادی') ?></strong></div>
                                    </div>
                                    <div class="mt-4 d-flex flex-wrap" style="gap:8px">
                                        <?php if (!empty($detailNotification['attachment']) && str_starts_with((string)$detailNotification['attachment'], 'uploads/')): ?>
                                            <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= htmlspecialchars(url((string)$detailNotification['attachment'])) ?>"><i class="fe fe-paperclip ml-1"></i> دریافت پیوست</a>
                                        <?php endif; ?>
                                        <a class="btn btn-secondary" href="<?= htmlspecialchars($detailCloseUrl) ?>">بستن</a>
                                    </div>
                                </div>
                            </section>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$isNotificationListPage && $notificationSectionCan('financial') && in_array($schoolRoleKey, ['manager', 'general_deputy', 'financial_deputy'], true)): ?>
                        <div class="card custom-card mt-4">
                            <div class="card-header border-bottom-0">
                                <h5 class="card-title mb-0 notification-heading">اعلان‌های مالی
                                    <span class="menu-info-trigger" tabindex="0"
                                          data-tooltip="<?= htmlspecialchars(m('notifications.financial_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                                          aria-label="راهنمای اعلان‌های مالی">
                                        <i class="fe fe-info fs-4" aria-hidden="true"></i>
                                    </span>
                                </h5>
                                <p class="text-muted mb-0 mt-1">موارد مالی منتظر بررسی و تأیید</p>
                            </div>
                            <div class="card-body"><div class="row">
                                <?php if ($notificationCan('financial.invoices') && (in_array($schoolRoleKey, ['manager', 'general_deputy'], true) || ($schoolRoleKey === 'financial_deputy' && Permision('permision_actfactor')))): ?>
                                    <div class="col-md-6 col-xl-4 mb-3"><a class="btn btn-outline-warning btn-block text-right p-3" href="<?= url('admin/notifications/verify_by_manager.php') ?>"><i class="fe fe-file-text ml-2"></i> فاکتورهای منتظر تأیید<?php if ($legacyCounts['pending_invoice'] > 0): ?><span class="badge badge-warning float-left"><?= (int)$legacyCounts['pending_invoice'] ?></span><?php endif; ?></a></div>
                                <?php endif; ?>
                                <?php if ($notificationCan('financial.wallet') && inst_feature('wallet') && (in_array($schoolRoleKey, ['manager', 'general_deputy'], true) || ($schoolRoleKey === 'financial_deputy' && Permision('permision_actwallet')))): ?>
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
