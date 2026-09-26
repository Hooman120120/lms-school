<?php

include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';


// ---------------------------------------------------------
// بررسی IP در Blacklist
// ---------------------------------------------------------
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}


// ---------------------------------------------------------
// بررسی لاگین بودن ادمین
// ---------------------------------------------------------
if (!isset($_SESSION['admin'])) {
    header('location: ../login.php');
    die();
}


// ---------------------------------------------------------
// Protecting against SESSION Hijacking
// ---------------------------------------------------------
if (
    $_SESSION['admin']['ip'] !== GetIp()
    and
    $_SESSION['admin']['os'] != GetOs()
) {
    session_unset();
    header('location: ../login.php');
    die();
}


// ---------------------------------------------------------
// Check Session JUST ONE Session
// ---------------------------------------------------------
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}


// ---------------------------------------------------------
// Access to documents section
// ---------------------------------------------------------
if (!Permision('permision_viewdocument')) {
    header('location: ' . url('admin/index.php'));
    die();
}

$currentUser = (string)($_SESSION['admin']['user'] ?? '');
$schoolRoleKey = SchoolStaffRoleKey($currentUser);
$canManageAllAnnouncements = false;
$announcementOwnerClause = ' AND (recipient_username = ? OR actor_username = ?)';


// ---------------------------------------------------------
// حذف اطلاعیه
// ---------------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_announcement'])
) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM school_notifications
                WHERE id = ? AND event_type IN ('announcement', 'circular'){$announcementOwnerClause}");
            $deleteParams = [$id, $currentUser, $currentUser];
            $stmt->execute($deleteParams);
            $deleted = $stmt->rowCount() > 0;
            $_SESSION['announcement_notice'] = [
                'type' => $deleted ? 'success' : 'error',
                'message' => $deleted ? 'اطلاعیه با موفقیت حذف شد.' : 'اطلاعیه پیدا نشد یا قبلاً حذف شده است.'
            ];
        } catch (Throwable $e) {
            error_log('Announcement delete failed: ' . $e->getMessage());
            $_SESSION['announcement_notice'] = ['type' => 'error', 'message' => 'حذف اطلاعیه انجام نشد.'];
        }
    }
    header('Location: announcements.php');
    exit;
}


// ---------------------------------------------------------
// انتشار / لغو انتشار
// ---------------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_publish'])
) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $conn->prepare("SELECT status
                FROM school_notifications
                WHERE id = ? AND event_type IN ('announcement', 'circular'){$announcementOwnerClause}
                LIMIT 1");
            $toggleParams = [$id, $currentUser, $currentUser];
            $stmt->execute($toggleParams);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($announcement) {
                if ($announcement['status'] === 'published') {
                    $update = $conn->prepare("UPDATE school_notifications SET status = 'draft', published_at = NULL WHERE id = ?{$announcementOwnerClause}");
                    $update->execute([$id, $currentUser, $currentUser]);
                    $noticeMessage = 'اطلاعیه به پیش‌نویس منتقل شد.';
                } else {
                    $update = $conn->prepare("UPDATE school_notifications SET status = 'published', published_at = NOW() WHERE id = ?{$announcementOwnerClause}");
                    $update->execute([$id, $currentUser, $currentUser]);
                    $noticeMessage = 'اطلاعیه با موفقیت منتشر شد.';
                }
                $_SESSION['announcement_notice'] = ['type' => 'success', 'message' => $noticeMessage];
            } else {
                $_SESSION['announcement_notice'] = ['type' => 'error', 'message' => 'اطلاعیه پیدا نشد.'];
            }
        } catch (Throwable $e) {
            error_log('Announcement status update failed: ' . $e->getMessage());
            $_SESSION['announcement_notice'] = ['type' => 'error', 'message' => 'تغییر وضعیت اطلاعیه انجام نشد.'];
        }
    }
    header('Location: announcements.php');
    exit;
}


// ---------------------------------------------------------
// فیلترها و دریافت لیست اطلاعیه‌ها
// ---------------------------------------------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type   = isset($_GET['type'])   ? trim($_GET['type'])   : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = ["event_type IN ('announcement', 'circular')"];
$params = [];

// نمایش اطلاعیه‌های ارسال شده توسط کاربر یا دریافت شده برای کاربر
$where[] = "(actor_username = ? OR recipient_username = ?)";
$params[] = $currentUser;
$params[] = $currentUser;

if ($search !== '') {
    $where[] = "(title LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type !== '') {
    $where[] = "event_type = ?";
    $params[] = $type;
}

if ($status !== '') {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

try {
    $stmt = $conn->prepare("SELECT * FROM school_notifications WHERE {$whereClause} ORDER BY id DESC");
    $stmt->execute($params);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Fetch announcements failed: ' . $e->getMessage());
    $announcements = [];
}

$announcementNotice = $_SESSION['announcement_notice'] ?? null;
unset($_SESSION['announcement_notice']);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="اطلاعیه ها و بخشنامه ها">
    <meta name="author" content="hamsepar.ir">
    <title>اطلاعیه‌ها و بخشنامه‌ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .announcement-page-header {margin-bottom:24px;}
        .announcement-page-header .breadcrumb {margin-bottom:0;}
        .announcement-create-btn {padding:10px 18px;font-size:15px;box-shadow:0 4px 12px rgba(13,110,253,.18);}
        .announcement-section-card {border:1px solid #e6eaf0;box-shadow:0 4px 14px rgba(31,45,61,.05);}
        .announcement-section-card .card-header {background:#fbfcfe;padding:18px 20px;}
        .announcement-section-card .card-title {display:flex;align-items:center;gap:8px;font-size:16px;}
        .announcement-section-card .card-title i {color:#0d6efd;font-size:18px;}
        .announcement-filter-form {background:#f7f9fc;border:1px solid #edf0f4;border-radius:8px;padding:16px;}
        .announcement-filter-form .form-label {font-size:13px;color:#46505c;margin-bottom:6px;}
        .announcement-notice {position:fixed;top:24px;right:24px;z-index:1055;display:flex;align-items:center;gap:12px;min-width:300px;max-width:430px;padding:14px 16px;background:#fff;border:1px solid #d7eee0;border-right:4px solid #198754;border-radius:10px;color:#245b38;box-shadow:0 10px 28px rgba(31,45,61,.18);animation:announcementNoticeIn .25s ease-out;}
        .announcement-notice.error {border-color:#f2d5d8;border-right-color:#dc3545;color:#842029;}
        .announcement-notice-icon {width:32px;height:32px;display:flex;align-items:center;justify-content:center;flex:0 0 32px;border-radius:50%;background:#e8f6ed;color:#198754;font-size:17px;}
        .announcement-notice.error .announcement-notice-icon {background:#fbeaec;color:#dc3545;}
        .announcement-notice-message {flex:1;font-size:14px;font-weight:600;line-height:1.7;}
        .announcement-notice-close {border:0;background:transparent;color:inherit;opacity:.55;font-size:20px;line-height:1;padding:0;cursor:pointer;}
        .announcement-notice-close:hover {opacity:1;}
        @keyframes announcementNoticeIn {from {opacity:0;transform:translateY(-10px);} to {opacity:1;transform:translateY(0);}}
        .announcement-confirm-backdrop {position:fixed;inset:0;z-index:1050;background:rgba(20,28,38,.42);display:none;align-items:center;justify-content:center;padding:20px;}
        .announcement-confirm-backdrop.is-visible {display:flex;}
        .announcement-confirm {width:min(420px,100%);background:#fff;border-radius:10px;box-shadow:0 12px 35px rgba(0,0,0,.2);padding:24px;}
        .announcement-confirm h5 {margin-bottom:8px;}
        @media (max-width:767px) {
            .announcement-page-header {align-items:flex-start !important;gap:14px;}
            .announcement-page-header > div:last-child {width:100%;}
            .announcement-create-btn {width:100%;}
        }
    </style>
</head>
<body class="main-body leftmenu">

<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- سربرگ صفحه -->
                <div class="page-header my-3 d-flex justify-content-between align-items-center announcement-page-header">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">اطلاعیه‌ها و بخشنامه‌ها</h2>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">مستندات</a></li>
                            <li class="breadcrumb-item active" aria-current="page">اطلاعیه‌ها و بخشنامه‌ها</li>
                        </ol>
                    </div>
                    <div>
                        <a href="announcement-create.php" class="btn btn-primary ripple announcement-create-btn">
                            <i class="fe fe-plus me-1"></i> ایجاد اطلاعیه جدید
                        </a>
                    </div>
                </div>

                <?php if (is_array($announcementNotice) && !empty($announcementNotice['message'])): ?>
                    <div id="announcementNotice" class="announcement-notice <?= ($announcementNotice['type'] ?? 'success') === 'error' ? 'error' : '' ?>" role="status">
                        <span class="announcement-notice-icon"><i class="fe <?= ($announcementNotice['type'] ?? 'success') === 'error' ? 'fe-alert-circle' : 'fe-check-circle' ?>"></i></span>
                        <span class="announcement-notice-message"><?= htmlspecialchars($announcementNotice['message'], ENT_QUOTES, 'UTF-8') ?></span>
                        <button type="button" class="announcement-notice-close" aria-label="بستن" onclick="document.getElementById('announcementNotice')?.remove()">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- فیلتر و جستجو -->
                <div class="row row-sm mb-4">
                    <div class="col-lg-12">
                        <div class="card custom-card announcement-section-card">
                            <div class="card-header border-bottom-0 pb-0">
                                <h6 class="main-content-label mb-1 card-title"><i class="fe fe-sliders"></i> فیلتر و جستجو</h6>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="announcement-filter-form">
                                    <div class="row row-sm">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label font-weight-bold">جستجو:</label>
                                            <input type="text" name="search" class="form-control" placeholder="عنوان یا متن اطلاعیه..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label font-weight-bold">نوع:</label>
                                            <select name="type" class="form-control">
                                                <option value="">همه</option>
                                                <option value="announcement" <?= $type === 'announcement' ? 'selected' : '' ?>>اطلاعیه</option>
                                                <option value="circular" <?= $type === 'circular' ? 'selected' : '' ?>>بخشنامه</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label font-weight-bold">وضعیت:</label>
                                            <select name="status" class="form-control">
                                                <option value="">همه</option>
                                                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary ripple w-100">
                                                <i class="fe fe-search me-1"></i> جستجو
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول نمایش لیست اطلاعیه‌ها -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card announcement-section-card">
                            <div class="card-header border-bottom-0">
                                <h6 class="main-content-label mb-0 card-title"><i class="fe fe-list"></i> لیست اطلاعیه‌ها و بخشنامه‌ها</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered text-nowrap text-center">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>عنوان</th>
                                                <th>نوع</th>
                                                <th>گیرنده</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ ایجاد</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($announcements)): ?>
                                                <?php foreach ($announcements as $index => $row): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td>
                                                            <?php if (($row['event_type'] ?? '') === 'circular'): ?>
                                                                <span class="badge bg-info">بخشنامه</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary">اطلاعیه</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($row['recipient_username'] ?? 'همه', ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td>
                                                            <?php if (($row['status'] ?? '') === 'published'): ?>
                                                                <span class="badge bg-success">منتشر شده</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">پیش‌نویس</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= isset($row['created_at']) ? jdate('Y/m/d H:i', strtotime($row['created_at'])) : '-' ?></td>
                                                        <td>
                                                            <!-- انتشار / لغو انتشار -->
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                <button type="submit" name="toggle_publish" class="btn btn-sm btn-outline-<?= ($row['status'] ?? '') === 'published' ? 'warning' : 'success' ?>">
                                                                    <?= ($row['status'] ?? '') === 'published' ? 'لغو انتشار' : 'انتشار' ?>
                                                                </button>
                                                            </form>

                                                            <!-- حذف -->
                                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('آیا از حذف این مورد اطمینان دارید؟');">
                                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                <button type="submit" name="delete_announcement" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fe fe-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-muted">هیچ اطلاعیه یا بخشنامه‌ای یافت نشد.</td>
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
</div>

<div id="announcementConfirmBackdrop" class="announcement-confirm-backdrop" aria-hidden="true">
    <div class="announcement-confirm" role="dialog" aria-modal="true" aria-labelledby="announcementConfirmTitle">
        <h5 id="announcementConfirmTitle">تأیید عملیات</h5>
        <p id="announcementConfirmMessage" class="text-muted mb-4"></p>
        <div class="d-flex justify-content-end" style="gap:8px;">
            <button type="button" class="btn btn-light" id="announcementConfirmCancel">انصراف</button>
            <button type="button" class="btn btn-danger" id="announcementConfirmSubmit">تأیید</button>
        </div>
    </div>
</div>
<script>
setTimeout(function () {
    document.getElementById('announcementNotice')?.remove();
}, 5000);
</script>
</body>
</html>