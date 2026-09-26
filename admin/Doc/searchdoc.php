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

// ۱. دریافت اطلاعیه‌ها و بخشنامه‌های ارسال شده توسط همکار جاری
try {
    if (!empty($search_query)) {
        $stmt_ann = $conn->prepare("SELECT * FROM announcements WHERE author = :author AND title LIKE :q ORDER BY id DESC");
        $stmt_ann->execute([
            'author' => $current_user,
            'q' => '%' . $search_query . '%'
        ]);
    } else {
        $stmt_ann = $conn->prepare("SELECT * FROM announcements WHERE author = :author ORDER BY id DESC");
        $stmt_ann->execute(['author' => $current_user]);
    }
    $announcements_list = $stmt_ann->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $announcements_list = [];
}

// ۲. دریافت نامه‌های خاص ارسال شده توسط همکار جاری (استفاده از ستون درست sender_admin)
try {
    if (!empty($search_query)) {
        $stmt_spec = $conn->prepare("SELECT * FROM special_letters WHERE sender_admin = :sender AND (subject LIKE :q OR message LIKE :q) ORDER BY id DESC");
        $stmt_spec->execute([
            'sender' => $current_user,
            'q' => '%' . $search_query . '%'
        ]);
    } else {
        $stmt_spec = $conn->prepare("SELECT * FROM special_letters WHERE sender_admin = :sender ORDER BY id DESC");
        $stmt_spec->execute(['sender' => $current_user]);
    }
    $special_letters_list = $stmt_spec->fetchAll(PDO::FETCH_OBJ);
} catch (PDOException $e) {
    $special_letters_list = [];
}

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
                                                <th>تاریخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($announcements_list)): ?>
                                                <?php foreach ($announcements_list as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item->title ?? '-'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo (isset($item->type) && $item->type == 'announcement') ? 'badge-success' : 'badge-primary'; ?>">
                                                                <?php echo (isset($item->type) && $item->type == 'announcement') ? 'اطلاعیه' : 'بخشنامه'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($item->datecreate ?? $item->created_at ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">شما هنوز اطلاعیه یا بخشنامه‌ای ثبت نکرده‌اید.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
                                                <th>شماره نامه</th>
                                                <th>موضوع</th>
                                                <th>تاریخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($special_letters_list)): ?>
                                                <?php foreach ($special_letters_list as $letter): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($letter->letter_number ?? $letter->id ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($letter->subject ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($letter->datecreate ?? '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">شما هنوز نامه خاصی ثبت نکرده‌اید.</td>
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
</body>
</html>