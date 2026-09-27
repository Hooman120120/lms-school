<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

// بررسی نشست ورود (ادمین یا کاربر)
if (!isset($_SESSION['admin']) && !isset($_SESSION['user'])) {
    header('location: ../login.php');
    exit;
}

// استخراج دقیق نام کاربری متنی از سشن (برای جلوگیری از خطای Array to string conversion)
if (isset($_SESSION['user'])) {
    $current_user = is_array($_SESSION['user']) ? ($_SESSION['user']['username'] ?? $_SESSION['user']['user'] ?? '') : $_SESSION['user'];
} else {
    $current_user = is_array($_SESSION['admin']) ? ($_SESSION['admin']['user'] ?? $_SESSION['admin']['username'] ?? '') : $_SESSION['admin'];
}

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page_size = 5;
$announcements_page = max(1, (int)($_GET['announcements_page'] ?? 1));
$letters_page = max(1, (int)($_GET['letters_page'] ?? 1));

// ۱. گزارش اطلاعیه‌ها و بخشنامه‌های ارسال شده از سامانهٔ اعلان‌ها
try {
    $sql = "SELECT n.*, CONCAT(COALESCE(a.fname,''), ' ', COALESCE(a.lname,'')) AS recipient_name
        FROM school_notifications n LEFT JOIN dbadmin a ON a.username = n.recipient_username
        WHERE n.actor_username = :author AND n.event_type IN ('announcement','circular')";
    $params = ['author' => $current_user];
    if ($search_query !== '') { $sql .= " AND (n.title LIKE :q OR n.body LIKE :q OR n.recipient_username LIKE :q)"; $params['q'] = '%' . $search_query . '%'; }
    $stmt_ann = $conn->prepare($sql . " ORDER BY n.created_at DESC, n.id DESC");
    $stmt_ann->execute($params);
    $announcements_list = $stmt_ann->fetchAll(PDO::FETCH_OBJ) ?: [];
} catch (PDOException $e) {
    $announcements_list = [];
}

// ۲. گزارش نامه‌های خاص ارسال شده از همان جدول اعلان‌ها
try {
    $sql = "SELECT n.*, CONCAT(COALESCE(a.fname,''), ' ', COALESCE(a.lname,'')) AS recipient_name
        FROM school_notifications n LEFT JOIN dbadmin a ON a.username = n.recipient_username
        WHERE n.actor_username = :sender AND n.event_type = 'special_letter'";
    $params = ['sender' => $current_user];
    if ($search_query !== '') { $sql .= " AND (n.title LIKE :q OR n.body LIKE :q OR n.recipient_username LIKE :q)"; $params['q'] = '%' . $search_query . '%'; }
    $stmt_spec = $conn->prepare($sql . " ORDER BY n.created_at DESC, n.id DESC");
    $stmt_spec->execute($params);
    $special_letters_list = $stmt_spec->fetchAll(PDO::FETCH_OBJ) ?: [];
} catch (PDOException $e) {
    $special_letters_list = [];
}

$announcements_total_pages = max(1, (int)ceil(count($announcements_list) / $page_size));
$letters_total_pages = max(1, (int)ceil(count($special_letters_list) / $page_size));
$announcements_page = min($announcements_page, $announcements_total_pages);
$letters_page = min($letters_page, $letters_total_pages);
$announcements_offset = ($announcements_page - 1) * $page_size;
$letters_offset = ($letters_page - 1) * $page_size;
$announcements_page_items = array_slice($announcements_list, $announcements_offset, $page_size);
$letters_page_items = array_slice($special_letters_list, $letters_offset, $page_size);
$pagination_query = $search_query !== '' ? '&q=' . urlencode($search_query) : '';

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>اسناد و مدارک ارسالی شما | <?php echo defined('__TITLE') ? __TITLE : 'پنل مدیریت'; ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                
                <!-- فرم جستجو -->
                <div class="row row-sm mt-4">
                    <div class="col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <h6 class="main-content-label mb-3">جستجو در اسناد ارسالی شما</h6>
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-9">
                                        <input type="text" name="q" class="form-control" placeholder="شماره نامه، عنوان یا موضوع را وارد کنید..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fe fe-search me-2"></i> جستجو
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- بخش دوگانه: نمایش سوابق ارسالی همکار جاری -->
                <div class="row row-sm">
                    
                    <!-- باکس اول: اطلاعیه‌ها و بخشنامه‌ها -->
                    <div class="col-xl-6 col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="main-content-label m-0">اطلاعیه‌ها و بخشنامه‌های ارسالی شما</h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>عنوان</th>
                                                <th>نوع</th>
                                                <th>گیرنده</th>
                                                <th>وضعیت</th>
                                                <th>تاریخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($announcements_page_items)): ?>
                                                <?php foreach ($announcements_page_items as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item->title ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $item->event_type === 'announcement' ? 'badge-success' : 'badge-primary'; ?>">
                                                                <?php echo $item->event_type === 'announcement' ? 'اطلاعیه' : 'بخشنامه'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(trim(($item->recipient_name ?? '') . ' (' . ($item->recipient_username ?? '') . ')'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo $item->status === 'published' ? 'منتشر شده' : 'پیش‌نویس'; ?></td>
                                                        <td><?php echo htmlspecialchars($item->created_at ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">شما هنوز اطلاعیه یا بخشنامه‌ای ثبت نکرده‌اید.</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                        <?php if ($announcements_total_pages > 1): ?>
                                            <nav class="mt-3" aria-label="صفحه‌بندی اطلاعیه‌ها"><ul class="pagination justify-content-center mb-0">
                                                <li class="page-item <?= $announcements_page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?announcements_page=<?= max(1, $announcements_page - 1) ?>&letters_page=<?= $letters_page ?><?= $pagination_query ?>" aria-label="صفحه قبل">‹ قبلی</a></li>
                                                <?php for ($page = 1; $page <= $announcements_total_pages; $page++): ?>
                                                    <li class="page-item <?= $page === $announcements_page ? 'active' : '' ?>"><a class="page-link" href="?announcements_page=<?= $page ?>&letters_page=<?= $letters_page ?><?= $pagination_query ?>"><?= $page ?></a></li>
                                                <?php endfor; ?>
                                                <li class="page-item <?= $announcements_page >= $announcements_total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?announcements_page=<?= min($announcements_total_pages, $announcements_page + 1) ?>&letters_page=<?= $letters_page ?><?= $pagination_query ?>" aria-label="صفحه بعد">بعدی ›</a></li>
                                            </ul></nav>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- باکس دوم: نامه‌های خاص -->
                    <div class="col-xl-6 col-lg-12">
                        <div class="card custom-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="main-content-label m-0">نامه‌های خاص ارسالی شما</h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table text-nowrap text-md-nowrap table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>موضوع</th>
                                                <th>گیرنده</th>
                                                <th>اولویت</th>
                                                <th>تاریخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($letters_page_items)): ?>
                                                <?php foreach ($letters_page_items as $letter): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($letter->title ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars(trim(($letter->recipient_name ?? '') . ' (' . ($letter->recipient_username ?? '') . ')'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($letter->priority ?? 'عادی', ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars($letter->created_at ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">شما هنوز نامه خاصی ثبت نکرده‌اید.</td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                        <?php if ($letters_total_pages > 1): ?>
                                            <nav class="mt-3" aria-label="صفحه‌بندی نامه‌های خاص"><ul class="pagination justify-content-center mb-0">
                                                <li class="page-item <?= $letters_page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?announcements_page=<?= $announcements_page ?>&letters_page=<?= max(1, $letters_page - 1) ?><?= $pagination_query ?>" aria-label="صفحه قبل">‹ قبلی</a></li>
                                                <?php for ($page = 1; $page <= $letters_total_pages; $page++): ?>
                                                    <li class="page-item <?= $page === $letters_page ? 'active' : '' ?>"><a class="page-link" href="?announcements_page=<?= $announcements_page ?>&letters_page=<?= $page ?><?= $pagination_query ?>"><?= $page ?></a></li>
                                                <?php endfor; ?>
                                                <li class="page-item <?= $letters_page >= $letters_total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?announcements_page=<?= $announcements_page ?>&letters_page=<?= min($letters_total_pages, $letters_page + 1) ?><?= $pagination_query ?>" aria-label="صفحه بعد">بعدی ›</a></li>
                                            </ul></nav>
                                        <?php endif; ?>
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
</body>
</html>
