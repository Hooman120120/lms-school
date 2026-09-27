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
if ($currentUser === '') {
    http_response_code(403);
    exit('برای سمت فعلی، دسترسی به اعلان‌های مدرسه تعریف نشده است.');
}

if (empty($_SESSION['school_notification_csrf'])) {
    $_SESSION['school_notification_csrf'] = bin2hex(random_bytes(24));
}

// مدیریت درخواست‌های ارسال فرم (خواندن اعلان‌ها و نامه‌ها)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['school_notification_csrf'], $token)) {
        http_response_code(419);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید.');
    }
    
    // خواندن یک اعلان عمومی مدرسه
    if (isset($_POST['mark_read_id'])) {
        MarkSchoolNotificationRead((int)$_POST['mark_read_id'], $currentUser);
    } elseif (isset($_POST['notification_id'])) {
        // Backward compatibility for older rendered forms.
        MarkSchoolNotificationRead((int)$_POST['notification_id'], $currentUser);
    } 
    // خواندن یک نامه اختصاصی؛ نامه‌ها نیز در همین سیستم اعلان ثبت می‌شوند.
    elseif (isset($_POST['mark_letter_read_id'])) {
        $letterId = (int)$_POST['mark_letter_read_id'];
        MarkSchoolNotificationRead($letterId, $currentUser);
    }
    // خواندن همه اعلان‌های عمومی
    elseif (isset($_POST['mark_all'])) {
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
$allowedTypes = array_values(array_diff(array_unique(array_merge($allowedTypes, ['announcement', 'circular'])), ['special_letter']));
$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$legacyCounts = SchoolLegacyNotificationCounts($currentUser);
$selectedType = (string)($_GET['type'] ?? '');

// بررسی نوع فیلتر انتخابی
if ($selectedType !== 'special_letters' && $selectedType !== 'sent_letters' && !in_array($selectedType, $allowedTypes, true)) {
    $selectedType = '';
}

// دریافت اعلان‌های سیستم
$notifications = [];
if ($selectedType !== 'special_letters' && $selectedType !== 'sent_letters') {
    $notifications = GetSchoolNotifications($currentUser, $selectedType !== '' ? $selectedType : null, 200);
    $notifications = array_values(array_filter($notifications, static function (array $notification): bool {
        return ($notification['event_type'] ?? '') !== 'special_letter';
    }));

    // اطلاعیه‌ها و بخشنامه‌های اختصاصیِ گیرنده، مستقل از نقش اعلان‌ها نمایش داده می‌شوند.
    $documentTypes = $selectedType === ''
        ? ['announcement', 'circular']
        : [$selectedType];
    if (array_intersect($documentTypes, ['announcement', 'circular']) !== []) {
        $typePlaceholders = implode(',', array_fill(0, count($documentTypes), '?'));
        $documentStmt = $conn->prepare("SELECT n.*, c.coursename, cl.name AS class_name,
                CONCAT(COALESCE(a.fname, ''), ' ', COALESCE(a.lname, '')) AS actor_name,
                n.actor_username,
                CASE WHEN nr.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM school_notifications n
            LEFT JOIN codtbl_course c ON c.codecourse = n.codecourse
            LEFT JOIN classes cl ON cl.id = n.class_id
            LEFT JOIN dbadmin a ON a.username = n.actor_username
            LEFT JOIN school_notification_reads nr
                ON nr.notification_id = n.id AND nr.admin_username = ?
            WHERE n.event_type IN ({$typePlaceholders})
              AND n.recipient_username = ?
              AND n.status = 'published'
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT 200");
        $documentStmt->execute(array_merge([$currentUser], $documentTypes, [$currentUser]));
        $documentNotifications = $documentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $notificationById = [];
        foreach (array_merge($notifications, $documentNotifications) as $notification) {
            $notificationById[(int)$notification['id']] = $notification;
        }
        $notifications = array_values($notificationById);
        usort($notifications, static function (array $left, array $right): int {
            return strcmp((string)$right['created_at'], (string)$left['created_at']);
        });
    }
}

// دریافت نامه‌های اختصاصی دریافتی همکار
$specialLetters = [];
if ($selectedType === '' || $selectedType === 'special_letters') {
    $stmtLetters = $conn->prepare("SELECT n.*, n.title AS subject, n.body AS message,
        n.actor_username AS sender_admin, n.recipient_username AS target_username,
        n.created_at AS datecreate,
        CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM school_notifications n
        LEFT JOIN school_notification_reads r
            ON r.notification_id = n.id AND r.admin_username = ?
        WHERE n.event_type = 'special_letter' AND n.recipient_username = ?
        ORDER BY n.id DESC LIMIT 100");
    $stmtLetters->execute([$currentUser, $currentUser]);
    $specialLetters = $stmtLetters->fetchAll(PDO::FETCH_ASSOC);
}

// دریافت نامه‌های ارسالی توسط این همکار
$sentLetters = [];
if ($selectedType === '' || $selectedType === 'sent_letters') {
    $stmtSent = $conn->prepare("SELECT n.*, n.title AS subject, n.body AS message,
        n.actor_username AS sender_admin, n.recipient_username AS target_username,
        n.created_at AS datecreate,
        CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM school_notifications n
        LEFT JOIN school_notification_reads r
            ON r.notification_id = n.id AND r.admin_username = n.recipient_username
        WHERE n.event_type = 'special_letter' AND n.actor_username = ?
        ORDER BY n.id DESC LIMIT 100");
    $stmtSent->execute([$currentUser]);
    $sentLetters = $stmtSent->fetchAll(PDO::FETCH_ASSOC);
}

$unreadCount = CountSchoolNotifications($currentUser, true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>اعلان‌های مدرسه و نامه‌ها | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .school-notification {border-right:4px solid #d5d9df; transition:.15s ease;}
        .school-notification.unread {border-right-color:#0d6efd; background:#f5f9ff;}
        .school-notification.letter-card {border-right-color:#6f42c1;}
        .school-notification.letter-card.unread {border-right-color:#dc3545; background:#fff5f5;}
        .school-notification:hover {box-shadow:0 6px 16px rgba(31,45,61,.09);}
        .notification-meta {display:flex; flex-wrap:wrap; gap:8px; color:#6c757d; font-size:12px;}
        .notification-icon {width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f1ff;color:#0d6efd;font-size:20px;flex:0 0 42px;}
        .notification-icon.letter-icon {background:#f3ebf7;color:#6f42c1;}
        .school-notification .badge-purple {background:#f1f3f5 !important;color:#111827 !important;border:1px solid #d7dce1;font-weight:600;}
        .notification-filter-btn {font-size:16px; padding:8px 16px; line-height:1.4;}
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
                        <h2 class="main-content-title tx-24 mg-b-5">اعلان‌های مدرسه و نامه‌های اختصاصی
                            <?php if ($unreadCount > 0): ?><span class="badge badge-danger align-middle"><?= (int)$unreadCount ?> جدید</span><?php endif; ?>
                        </h2>
                        <p class="text-muted mb-0">اعلان‌های مجاز سیستم و لیست نامه‌های اختصاصی همکاران در این بخش قرار دارد.</p>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                            <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                            <button class="btn btn-outline-primary" name="mark_all" value="1">خواندن همه اعلان‌ها (<?= $unreadCount ?>)</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card custom-card mb-3">
                    <div class="card-body d-flex flex-wrap" style="gap:8px">
                        <a class="btn notification-filter-btn <?= $selectedType === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php') ?>">همه موارد</a>
                        <a class="btn notification-filter-btn <?= $selectedType === 'special_letters' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php?type=special_letters') ?>">نامه‌های خاص</a>
                        <a class="btn notification-filter-btn <?= $selectedType === 'sent_letters' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php?type=sent_letters') ?>">نامه‌های ارسالی من</a>
                        <a class="btn notification-filter-btn <?= $selectedType === 'announcement' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php?type=announcement') ?>">اطلاعیه‌ها</a>
                        <a class="btn notification-filter-btn <?= $selectedType === 'circular' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php?type=circular') ?>">بخشنامه‌ها</a>
                        <?php foreach ($allowedTypes as $type): ?>
                            <?php if (in_array($type, ['announcement', 'circular'], true)) continue; ?>
                            <a class="btn notification-filter-btn <?= $selectedType === $type ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('admin/notifications/school_notifications.php?type=' . rawurlencode($type)) ?>"><?= htmlspecialchars($catalog[$type]['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!SchoolNotificationsReady()): ?>
                    <div class="alert alert-warning">ساختار دیتابیس اعلان‌های مدرسه هنوز نصب نشده است. migration مربوط به اعلان‌ها را اجرا کنید.</div>
                <?php else: ?>
                    
                    <?php if ($notifications === [] && $specialLetters === [] && $sentLetters === []): ?>
                        <div class="alert alert-light text-center py-5">در این بخش اعلانی برای شما وجود ندارد.</div>
                    <?php endif; ?>

                    <!-- نمایش نامه‌های اختصاصی دریافتی همکاران همراه با ویو (Modal) -->
                    <?php foreach ($specialLetters as $letter): 
                        $sender_stmt = $conn->prepare("SELECT username, fname, lname FROM dbadmin WHERE username = ? LIMIT 1");
                        $senderUsername = trim((string)($letter['sender_admin'] ?? ''));
                        $sender_stmt->execute([$senderUsername]);
                        $sender_info = $sender_stmt->fetch(PDO::FETCH_OBJ);
                        $senderName = ($sender_info && (!empty($sender_info->fname) || !empty($sender_info->lname))) 
                            ? trim($sender_info->fname . ' ' . $sender_info->lname) 
                            : ($senderUsername !== '' ? $senderUsername : 'نامشخص');
                    ?>
                        <div class="card custom-card school-notification letter-card <?= (int)$letter['is_read'] === 0 ? 'unread' : '' ?> mb-2" data-sort-time="<?= (int)strtotime((string)$letter['datecreate']) ?>" data-sort-id="<?= (int)$letter['id'] ?>">
                            <div class="card-body d-flex align-items-start" style="gap:14px">
                                <div class="notification-icon letter-icon"><i class="fe fe-mail"></i></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px">
                                        <div>
                                            <span class="badge badge-purple mb-1">نامه اختصاصی دریافتی</span>
                                            <button type="button" class="btn btn-link p-0 mb-1 text-black js-open-notification-view js-mark-notification-read"
                                                data-read-id="<?= (int)$letter['id'] ?>"
                                                data-view-title="<?= htmlspecialchars($letter['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-party-label="فرستنده"
                                                data-view-party="<?= htmlspecialchars($senderName . ' (' . $letter['sender_admin'] . ')', ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-date="<?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-priority="<?= htmlspecialchars((string)($letter['priority'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-body="<?= htmlspecialchars($letter['message'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-attachment="<?= htmlspecialchars(!empty($letter['attachment']) ? url($letter['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($letter['subject']) ?>
                                            </button>
                                        </div>
                                        <div>
                                            <?php if ((int)$letter['is_read'] === 0): ?>
                                                <span class="badge badge-danger">خوانده‌نشده</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">خوانده‌شده</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-muted"><?= mb_substr(strip_tags($letter['message']), 0, 120) ?>...</p>
                                    <div class="notification-meta">
                                        <span><i class="fe fe-send"></i> فرستنده: <strong><?= htmlspecialchars($senderName) ?></strong></span>
                                        <span><i class="fe fe-clock"></i> <?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa')) ?></span>
                                    </div>
                                    <div class="mt-3 d-flex" style="gap:8px">
                                        <!-- دکمه ویو (Modal) -->
                                        <button type="button" class="btn btn-sm btn-info js-open-notification-view js-mark-notification-read"
                                            data-read-id="<?= (int)$letter['id'] ?>"
                                            data-view-title="<?= htmlspecialchars($letter['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-party-label="فرستنده"
                                            data-view-party="<?= htmlspecialchars($senderName . ' (' . $letter['sender_admin'] . ')', ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-date="<?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-priority="<?= htmlspecialchars((string)($letter['priority'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-body="<?= htmlspecialchars($letter['message'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-attachment="<?= htmlspecialchars(!empty($letter['attachment']) ? url($letter['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fe fe-eye ml-1"></i> مشاهده ویو کامل
                                        </button>

                                        <?php if ((int)$letter['is_read'] === 0): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                                                <input type="hidden" name="mark_letter_read_id" value="<?= (int)$letter['id'] ?>">
                                                <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                                                <button class="btn btn-sm btn-outline-secondary">علامت‌گذاری به عنوان خوانده‌شده</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal مربوط به نامه دریافتی -->
                        <div class="modal fade" id="letterModal<?= $letter['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">مشاهده نامه: <?= htmlspecialchars($letter['subject']) ?></h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>فرستنده:</strong> <?= htmlspecialchars($senderName) ?> (<?= htmlspecialchars($letter['sender_admin']) ?>)</p>
                                        <p><strong>اولویت:</strong> <?= htmlspecialchars((string)($letter['priority'] ?? 'عادی'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p><strong>تاریخ ایجاد:</strong> <?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa')) ?></p>
                                        <hr>
                                        <div class="p-3 bg-light rounded mb-3" style="white-space: pre-line;"><?= htmlspecialchars($letter['message']) ?></div>
                                        <?php if (!empty($letter['attachment'])): ?>
                                            <p><i class="fe fe-paperclip"></i> <strong>پیوست:</strong> <a href="<?= htmlspecialchars(url($letter['attachment'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">دانلود / مشاهده فایل پیوست</a></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- نمایش نامه‌های ارسالی من همراه با ویو (Modal) -->
                    <?php foreach ($sentLetters as $letter): 
                        $receiverUsername = trim((string)($letter['target_username'] ?? ''));
                        $rec_stmt = $conn->prepare("SELECT username, fname, lname FROM dbadmin WHERE username = ? LIMIT 1");
                        $rec_stmt->execute([$receiverUsername]);
                        $rec_info = $rec_stmt->fetch(PDO::FETCH_OBJ);
                        $receiverName = ($rec_info && (!empty($rec_info->fname) || !empty($rec_info->lname))) 
                            ? trim($rec_info->fname . ' ' . $rec_info->lname) 
                            : ($receiverUsername !== '' ? $receiverUsername : 'نامشخص');
                    ?>
                        <div class="card custom-card school-notification letter-card mb-2" data-sort-time="<?= (int)strtotime((string)$letter['datecreate']) ?>" data-sort-id="<?= (int)$letter['id'] ?>">
                            <div class="card-body d-flex align-items-start" style="gap:14px">
                                <div class="notification-icon letter-icon" style="background:#e2f0d9; color:#385723;"><i class="fe fe-send"></i></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px">
                                        <div>
                                            <span class="badge badge-success mb-1">نامه ارسالی شما</span>
                                            <button type="button" class="btn btn-link p-0 mb-1 text-black js-open-notification-view"
                                                data-view-title="<?= htmlspecialchars($letter['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-party-label="گیرنده"
                                                data-view-party="<?= htmlspecialchars($receiverName . ($receiverUsername !== '' ? ' (' . $receiverUsername . ')' : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-date="<?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-priority="<?= htmlspecialchars((string)($letter['priority'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-body="<?= htmlspecialchars($letter['message'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-attachment="<?= htmlspecialchars(!empty($letter['attachment']) ? url($letter['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($letter['subject']) ?>
                                            </button>
                                        </div>
                                        <div>
                                            <?php if ((int)$letter['is_read'] === 1): ?>
                                                <span class="badge badge-success">توسط گیرنده خوانده شده</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning text-dark">هنوز خوانده نشده</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-muted"><?= mb_substr(strip_tags($letter['message']), 0, 120) ?>...</p>
                                    <div class="notification-meta">
                                        <span><i class="fe fe-user"></i> گیرنده: <strong><?= htmlspecialchars((string)$receiverName, ENT_QUOTES, 'UTF-8') ?></strong></span>
                                        <span><i class="fe fe-clock"></i> <?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa')) ?></span>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-info js-open-notification-view"
                                            data-view-title="<?= htmlspecialchars($letter['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-party-label="گیرنده"
                                            data-view-party="<?= htmlspecialchars((string)$receiverName . ' (' . (string)($letter['target_username'] ?? '') . ')', ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-date="<?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-priority="<?= htmlspecialchars((string)($letter['priority'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-body="<?= htmlspecialchars($letter['message'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-attachment="<?= htmlspecialchars(!empty($letter['attachment']) ? url($letter['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fe fe-eye ml-1"></i> مشاهده ویو کامل
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal مربوط به نامه ارسالی -->
                        <div class="modal fade" id="sentModal<?= $letter['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">مشاهده نامه ارسالی: <?= htmlspecialchars($letter['subject']) ?></h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>گیرنده:</strong> <?= htmlspecialchars((string)$receiverName, ENT_QUOTES, 'UTF-8') ?><?= $receiverUsername !== '' ? ' (' . htmlspecialchars($receiverUsername, ENT_QUOTES, 'UTF-8') . ')' : '' ?></p>
                                        <p><strong>اولویت:</strong> <?= htmlspecialchars((string)($letter['priority'] ?? 'عادی'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <p><strong>تاریخ ارسال:</strong> <?= htmlspecialchars(toJalali($letter['datecreate'], 'Y/m/d H:i', 'fa')) ?></p>
                                        <hr>
                                        <div class="p-3 bg-light rounded mb-3" style="white-space: pre-line;"><?= htmlspecialchars($letter['message']) ?></div>
                                        <?php if (!empty($letter['attachment'])): ?>
                                            <p><i class="fe fe-paperclip"></i> <strong>پیوست:</strong> <a href="<?= htmlspecialchars(url($letter['attachment'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">دانلود / مشاهده فایل پیوست</a></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- نمایش اعلان‌ها و بخشنامه‌های عمومی همراه با ویو (Modal) -->
                    <?php foreach ($notifications as $notification):
                        $meta = $catalog[$notification['event_type']] ?? ['title' => $notification['event_type'], 'icon' => 'fe-bell'];
                        $target = trim((string)($notification['target_url'] ?? ''));
                        $actorName = trim((string)($notification['actor_name'] ?? '')) ?: trim((string)($notification['actor_username'] ?? ''));
                    ?>
                        <div class="card custom-card school-notification <?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?> mb-2" data-sort-time="<?= (int)strtotime((string)$notification['created_at']) ?>" data-sort-id="<?= (int)$notification['id'] ?>">
                            <div class="card-body d-flex align-items-start" style="gap:14px">
                                <div class="notification-icon"><i class="fe <?= htmlspecialchars($meta['icon']) ?>"></i></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px">
                                        <div>
                                            <span class="badge badge-light mb-1"><?= htmlspecialchars($meta['title']) ?></span>
                                            <button type="button" class="btn btn-link p-0 mb-1 text-black js-open-notification-view js-mark-notification-read"
                                                data-read-id="<?= (int)$notification['id'] ?>"
                                                data-view-title="<?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-party-label="فرستنده"
                                                data-view-party="<?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-date="<?= htmlspecialchars(toJalali($notification['created_at'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-body="<?= htmlspecialchars($notification['body'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-view-attachment="<?= htmlspecialchars(!empty($notification['attachment']) ? url($notification['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </button>
                                        </div>
                                        <?php if ((int)$notification['is_read'] === 0): ?><span class="badge badge-primary">جدید</span><?php endif; ?>
                                    </div>
                                    <?php if (!empty($notification['body'])): ?>
                                        <p class="mb-2 text-muted"><?= mb_substr(strip_tags($notification['body']), 0, 120) ?>...</p>
                                    <?php endif; ?>
                                    <div class="notification-meta">
                                        <?php if ($actorName !== ''): ?><span><i class="fe fe-user"></i> <?= htmlspecialchars($actorName) ?></span><?php endif; ?>
                                        <span><i class="fe fe-clock"></i> <?= htmlspecialchars(toJalali($notification['created_at'], 'Y/m/d H:i', 'fa')) ?></span>
                                    </div>
                                    <div class="mt-3 d-flex" style="gap:8px">
                                        <!-- دکمه ویو (Modal) برای اطلاعیه و بخشنامه -->
                                        <button type="button" class="btn btn-sm btn-info js-open-notification-view js-mark-notification-read"
                                            data-read-id="<?= (int)$notification['id'] ?>"
                                            data-view-title="<?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-party-label="فرستنده"
                                            data-view-party="<?= htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-date="<?= htmlspecialchars(toJalali($notification['created_at'], 'Y/m/d H:i', 'fa'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-body="<?= htmlspecialchars($notification['body'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            data-view-attachment="<?= htmlspecialchars(!empty($notification['attachment']) ? url($notification['attachment']) : '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fe fe-eye ml-1"></i> مشاهده ویو کامل
                                        </button>

                                        <?php if ($target !== '' && strpos($target, 'admin/') === 0): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(url($target)) ?>">مشاهده جزئیات</a>
                                        <?php endif; ?>
                                        <?php if ((int)$notification['is_read'] === 0): ?>
                                            <form method="post">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['school_notification_csrf']) ?>">
                                                <input type="hidden" name="mark_read_id" value="<?= (int)$notification['id'] ?>">
                                                <input type="hidden" name="return_type" value="<?= htmlspecialchars($selectedType) ?>">
                                                <button class="btn btn-sm btn-outline-secondary">خواندم</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal مربوط به اطلاعیه و بخشنامه عمومی -->
                        <div class="modal fade" id="notifModal<?= $notification['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?= htmlspecialchars($meta['title']) ?>: <?= htmlspecialchars($notification['title']) ?></h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>تاریخ انتشار:</strong> <?= htmlspecialchars(toJalali($notification['created_at'], 'Y/m/d H:i', 'fa')) ?></p>
                                        <?php if ($actorName !== ''): ?>
                                            <p><strong>ارسال‌کننده:</strong> <?= htmlspecialchars($actorName) ?></p>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="p-3 bg-light rounded mb-3" style="white-space: pre-line;"><?= htmlspecialchars($notification['body']) ?></div>
                                        <?php if (!empty($notification['attachment'])): ?>
                                            <p><i class="fe fe-paperclip"></i> <strong>پیوست:</strong>
                                                <a href="<?= htmlspecialchars(url($notification['attachment'])) ?>" target="_blank" class="btn btn-sm btn-outline-primary">دانلود / مشاهده فایل پیوست</a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div id="notificationDetailModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="notificationDetailTitle" class="modal-title"></h5>
                                <button type="button" class="close js-close-notification-view" aria-label="بستن">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p><strong id="notificationDetailPartyLabel">فرستنده:</strong> <span id="notificationDetailParty"></span></p>
                                <p><strong>تاریخ:</strong> <span id="notificationDetailDate"></span></p>
                                <p id="notificationDetailPriority" style="display:none;"><strong>اولویت:</strong> <span id="notificationDetailPriorityValue"></span></p>
                                <hr>
                                <div id="notificationDetailBody" class="p-3 bg-light rounded mb-3" style="white-space:pre-line;"></div>
                                <p id="notificationDetailAttachment" style="display:none;">
                                    <i class="fe fe-paperclip"></i> <strong>پیوست:</strong>
                                    <a id="notificationDetailAttachmentLink" target="_blank" class="btn btn-sm btn-outline-primary">دانلود / مشاهده فایل پیوست</a>
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary js-close-notification-view">بستن</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../footers.php'; ?>
<script>
var notificationDetailModal = document.getElementById('notificationDetailModal');
var notificationBackdrop;

function closeNotificationView() {
    notificationDetailModal.classList.remove('show');
    notificationDetailModal.style.display = 'none';
    notificationDetailModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    notificationBackdrop?.remove();
}

document.querySelectorAll('.js-open-notification-view').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('notificationDetailTitle').textContent = button.dataset.viewTitle || '';
        document.getElementById('notificationDetailPartyLabel').textContent = (button.dataset.viewPartyLabel || 'فرستنده') + ':';
        document.getElementById('notificationDetailParty').textContent = button.dataset.viewParty || '-';
        document.getElementById('notificationDetailDate').textContent = button.dataset.viewDate || '-';
        var priority = document.getElementById('notificationDetailPriority');
        var priorityValue = document.getElementById('notificationDetailPriorityValue');
        if (button.dataset.viewPriority) {
            priorityValue.textContent = button.dataset.viewPriority;
            priority.style.display = 'block';
        } else {
            priorityValue.textContent = '';
            priority.style.display = 'none';
        }
        document.getElementById('notificationDetailBody').textContent = button.dataset.viewBody || '';

        var attachment = document.getElementById('notificationDetailAttachment');
        var attachmentLink = document.getElementById('notificationDetailAttachmentLink');
        if (button.dataset.viewAttachment) {
            attachmentLink.href = button.dataset.viewAttachment;
            attachment.style.display = 'block';
        } else {
            attachment.style.display = 'none';
            attachmentLink.removeAttribute('href');
        }

        notificationDetailModal.classList.add('show');
        notificationDetailModal.style.display = 'block';
        notificationDetailModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        notificationBackdrop = document.createElement('div');
        notificationBackdrop.className = 'modal-backdrop fade show';
        notificationBackdrop.addEventListener('click', closeNotificationView);
        document.body.appendChild(notificationBackdrop);

    });
});

document.querySelectorAll('.js-close-notification-view').forEach(function (button) {
    button.addEventListener('click', closeNotificationView);
});

// در حالت «همه موارد»، نامه‌ها و اطلاعیه‌ها باید در یک ترتیب زمانی مشترک دیده شوند.
<?php if ($selectedType === ''): ?>
(function () {
    var cards = Array.from(document.querySelectorAll('.school-notification[data-sort-time]'));
    if (!cards.length) return;
    var container = cards[0].parentElement;
    cards.sort(function (a, b) {
        var timeDiff = Number(b.dataset.sortTime) - Number(a.dataset.sortTime);
        return timeDiff || (Number(b.dataset.sortId) - Number(a.dataset.sortId));
    });
    cards.forEach(function (card) { container.appendChild(card); });
})();
<?php endif; ?>
</script>
</body>
</html>
