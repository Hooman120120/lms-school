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
// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('../admin/login.php'));
    die();
}
if (!Permision('permission_news_notif')) {
    header('location:' . url("admin/index.php"));
    die();
}

// ------------------------------------------------------------------------
// Role mapping for display (same as news page)
// ------------------------------------------------------------------------
$audienceLabels = [
    '0'   => 'مدیریت',
    '1'   => 'پشتیبان',
    '2'   => 'مدیرمیانی',
    '3'   => 'استاد',
    '6'   => 'کارشناس فروش',
    '51'  => 'معاونت اجرایی',
    '52'  => 'معاونت آموزشی',
    '53'  => 'معاونت فروش',
    '54'  => 'معاونت فنی',
    '00'  => 'کاربران',
    '000' => 'همه کارمندان'
];

// ------------------------------------------------------------------------
// Handle success messages from redirects
// ------------------------------------------------------------------------
$msg = '';
if (isset($_GET['inserted']) && $_GET['inserted'] == 1) {
    $msg = '<div class="alert alert-success mt-3" role="alert">اعلان با موفقیت ثبت شد.</div>';
}
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $msg = '<div class="alert alert-success mt-3" role="alert">اعلان با موفقیت به‌روزرسانی شد.</div>';
}
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $msg = '<div class="alert alert-success mt-3" role="alert">اعلان مورد نظر با موفقیت حذف شد.</div>';
}

// ------------------------------------------------------------------------
// Image upload function (kept as is, but note path uses uploads/notifications/)
// ------------------------------------------------------------------------
function uploadNotificationImage($file, $old_image_path = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $old_image_path;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jfif'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    $max_size = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $max_size) {
        return false;
    }

    // Absolute filesystem path (no "../../" in stored path)
    $upload_dir_fs = __DIR__ . '/../../uploads/notifications/';
    if (!is_dir($upload_dir_fs)) {
        mkdir($upload_dir_fs, 0755, true);
    }

    // Web‑relative path (what gets stored in DB)
    $web_path = 'uploads/notifications/';
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'notif_' . uniqid() . '.' . $ext;
    $destination_fs = $upload_dir_fs . $filename;
    $destination_web = $web_path . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination_fs)) {
        if ($old_image_path && file_exists(__DIR__ . '/../../' . $old_image_path)) {
            unlink(__DIR__ . '/../../' . $old_image_path);
        }
        return $destination_web;
    }
    return false;
}

// ------------------------------------------------------------------------
// ۱. عملیات ثبت اعلان جدید (Create) با عکس
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notif'])) {
    $text = trim($_POST['notif_text']);
    $audience = trim($_POST['audience']);

    if (!empty($text)) {
        $image_path = uploadNotificationImage($_FILES['notif_image']);
        if ($image_path === false && $_FILES['notif_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در آپلود تصویر! فرمت مجاز: jpg, png, gif, webp (حداکثر ۵ مگابایت)</div>';
        } else {
            $sql_insert = "INSERT INTO `notif` (`text`, `creator`, `audience`, `image_path`) VALUES (:text, :creator, :audience, :image_path)";
            $stmt_insert = $conn->prepare($sql_insert);
            $result = $stmt_insert->execute([
                ':text' => $text,
                ':creator' => $_SESSION['admin']['user'],
                ':audience' => $audience,
                ':image_path' => $image_path
            ]);
            if ($result) {
                // Redirect to avoid resubmission on refresh
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?inserted=1');
                die();
            } else {
                $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در ثبت اعلان! لطفا دوباره تلاش کنید.</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-warning mt-3" role="alert">متن اعلان نمی‌تواند خالی باشد.</div>';
    }
}

// ------------------------------------------------------------------------
// ۲. عملیات ویرایش اعلان (Update)
// ------------------------------------------------------------------------
$edit_notif = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sql_edit = "SELECT * FROM `notif` WHERE `id` = :id";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->execute([':id' => $edit_id]);
    $edit_notif = $stmt_edit->fetch(PDO::FETCH_OBJ);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notif'])) {
    $notif_id = intval($_POST['notif_id']);
    $text = trim($_POST['notif_text']);
    $audience = trim($_POST['audience']);

    if (!empty($text)) {
        $sql_old = "SELECT `image_path` FROM `notif` WHERE `id` = :id";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->execute([':id' => $notif_id]);
        $old_image = $stmt_old->fetchColumn();

        $new_image = uploadNotificationImage($_FILES['notif_image'], $old_image);
        if ($new_image === false && $_FILES['notif_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در آپلود تصویر جدید!</div>';
        } else {
            $sql_update = "UPDATE `notif` SET `text` = :text, `audience` = :audience, `image_path` = :image_path WHERE `id` = :id";
            $stmt_update = $conn->prepare($sql_update);
            $result = $stmt_update->execute([
                ':text' => $text,
                ':audience' => $audience,
                ':image_path' => $new_image,
                ':id' => $notif_id
            ]);
            if ($result) {
                // Redirect to avoid resubmission and clear edit mode
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?updated=1');
                die();
            } else {
                $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در به‌روزرسانی اعلان!</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-warning mt-3" role="alert">متن اعلان نمی‌تواند خالی باشد.</div>';
    }
}

// ------------------------------------------------------------------------
// ۳. عملیات حذف اعلان (Delete) + حذف فایل تصویر (already has redirect)
// ------------------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $sql_get_img = "SELECT `image_path` FROM `notif` WHERE `id` = :id";
    $stmt_get_img = $conn->prepare($sql_get_img);
    $stmt_get_img->execute([':id' => $del_id]);
    $img_path = $stmt_get_img->fetchColumn();

    $sql_delete = "DELETE FROM `notif` WHERE `id` = :id";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([':id' => $del_id]);

    if ($img_path) {
        $abs_img_path = __DIR__ . '/../' . $img_path;
        if (file_exists($abs_img_path)) {
            unlink($abs_img_path);
        }
    }
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?deleted=1');
    die();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>مدیریت اعلان‌ها</title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <?php echo $msg; ?>

            <!-- فرم ثبت اعلان جدید (با آپلود عکس و نقش‌های جدید) -->
            <div class="row row-sm mt-4">
                <div class="col-lg-12 col-md-12">
                    <div class="card custom-card shadow-sm">
                        <div class="card-header border-bottom-0 custom-card-header">
                            <h6 class="main-content-label mb-0 d-flex align-items-center" style="gap:10px;">

                                ثبت اعلان جدید

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید اطلاعیه جدیدی برای کاربران سامانه ثبت کنید. با ایجاد اعلان، پیام مورد نظر در تابلو اعلانات نمایش داده شده و کاربران می‌توانند از اطلاعیه‌ها، تغییرات، زمان‌بندی‌ها و سایر پیام‌های مهم مطلع شوند."
                                      aria-label="راهنمای ثبت اعلان جدید">

            <i class="fe fe-info fs-4"></i>

        </span>

                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="audience" class="form-label fw-bold">مخاطب اعلان</label>
                                        <select class="form-select" id="audience" name="audience" required>
                                            <?php foreach ($audienceLabels as $key => $label): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-9 mb-3">
                                        <label for="notif_text" class="form-label fw-bold">متن پیام</label>
                                        <textarea class="form-control" id="notif_text" name="notif_text" rows="3" placeholder="متن اعلان خود را اینجا بنویسید..." required></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="notif_image" class="form-label fw-bold">تصویر (اختیاری)</label>
                                        <input type="file" class="form-control" id="notif_image" name="notif_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                        <small class="text-muted">فرمت‌های مجاز: jpg, png, gif, webp | حداکثر ۵ مگابایت</small>
                                    </div>
                                </div>
                                <button type="submit" name="submit_notif" class="btn btn-primary px-4">ثبت و انتشار اعلان</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $sql_fetch = "SELECT * FROM `notif` ORDER BY `id` DESC";
            $stmt_fetch = $conn->prepare($sql_fetch);
            $stmt_fetch->execute();
            $notifs = $stmt_fetch->fetchAll(PDO::FETCH_OBJ);
            ?>

            <div class="row row-sm mt-4">
                <div class="col-lg-12 col-md-12">
                    <div class="card custom-card shadow-sm">
                        <div class="card-header border-bottom-0 custom-card-header">
                            <h6 class="main-content-label mb-0 d-flex align-items-center" style="gap:10px;">

                                لیست اعلان‌های ثبت شده

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش تمامی اعلان‌های ثبت‌شده در سامانه نمایش داده می‌شود. از طریق این قسمت می‌توانید اطلاعیه‌های قبلی را مشاهده، ویرایش یا حذف کرده و وضعیت اطلاع‌رسانی‌های منتشرشده را مدیریت کنید."
                                      aria-label="راهنمای لیست اعلان‌های ثبت شده">

            <i class="fe fe-info fs-4"></i>

        </span>

                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example1" class="table text-md-nowrap table-striped table-bordered text-center align-middle">
                                    <thead class="table-dark">
                                    <tr>
                                        <th class="wd-5p">#</th>
                                        <th class="wd-10p">تصویر</th>
                                        <th class="wd-10p">مخاطب</th>
                                        <th class="wd-45p text-right">متن اعلان</th>
                                        <th class="wd-15p">تاریخ ایجاد</th>
                                        <th class="wd-15p">عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($notifs) > 0): ?>
                                        <?php $counter = 1; foreach ($notifs as $notif):
                                            $date_show = $notif->created_at;
                                            $image_html = '';
                                            if (!empty($notif->image_path)) {
                                                // Root‑relative URL (starts from web root)
                                                $web_url = '/' . $notif->image_path;
                                                // Absolute filesystem path for file_exists() – assumes document root is the project root
                                                $absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $notif->image_path;
                                                if (file_exists($absolute_path)) {
                                                    $image_html = '<a href="' . htmlspecialchars($web_url) . '" target="_blank">
                        <img src="' . htmlspecialchars($web_url) . '" width="50" height="50" style="object-fit: cover; border-radius: 5px;" class="img-thumbnail">
                      </a>';
                                                } else {
                                                    $image_html = '<span class="text-muted">فایل تصویر یافت نشد</span>';
                                                }
                                            } else {
                                                $image_html = '<span class="text-muted">بدون تصویر</span>';
                                            }
                                            // نمایش برچسب مخاطب بر اساس نقش
                                            $audienceKey = (string) $notif->audience;
                                            $audienceLabel = isset($audienceLabels[$audienceKey]) ? $audienceLabels[$audienceKey] : 'نامشخص';
                                            // رنگ بندی خاص برای '000' (همه مدیران)
                                            $badgeClass = ($audienceKey === '000') ? 'badge bg-dark' : (($audienceKey === '00') ? 'badge bg-secondary' : 'badge bg-info');
                                            ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo $image_html; ?></td>
                                                <td><span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($audienceLabel); ?></span></td>
                                                <td class="text-right" style="white-space: normal; line-height: 1.8;">
                                                    <?php echo nl2br(htmlspecialchars($notif->text)); ?>
                                                </td>
                                                <td dir="ltr"><?php echo $date_show; ?></td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $notif->id; ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $notif->id; ?>">ویرایش</a>
                                                    <a href="?delete_id=<?php echo $notif->id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این اعلان اطمینان دارید؟');">حذف</a>
                                                </td>
                                            </tr>

                                            <!-- مودال ویرایش برای هر اعلان -->
                                            <div class="modal fade" id="editModal<?php echo $notif->id; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">ویرایش اعلان</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                                        </div>
                                                        <form action="" method="POST" enctype="multipart/form-data">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="notif_id" value="<?php echo $notif->id; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">مخاطب</label>
                                                                    <select name="audience" class="form-select" required>
                                                                        <?php foreach ($audienceLabels as $key => $label): ?>
                                                                            <option value="<?php echo $key; ?>" <?php echo ($notif->audience == $key) ? 'selected' : ''; ?>>
                                                                                <?php echo $label; ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">متن اعلان</label>
                                                                    <textarea name="notif_text" class="form-control" rows="4" required><?php echo htmlspecialchars($notif->text); ?></textarea>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">تصویر فعلی</label><br>
                                                                    <?php
                                                                    $abs_img = !empty($notif->image_path) ? __DIR__ . '/../' . $notif->image_path : '';
                                                                    if (!empty($notif->image_path) && file_exists($abs_img)):
                                                                        $web_img = '../' . $notif->image_path;
                                                                        ?>
                                                                        <img src="<?php echo htmlspecialchars($web_img); ?>" style="max-width: 150px; max-height: 150px;" class="border rounded mb-2">
                                                                        <br>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">تصویری وجود ندارد</span>
                                                                    <?php endif; ?>
                                                                    <label class="form-label mt-2">بارگذاری تصویر جدید (اختیاری)</label>
                                                                    <input type="file" name="notif_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                                                    <small class="text-muted">با انتخاب تصویر جدید، تصویر قبلی جایگزین خواهد شد.</small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                                <button type="submit" name="update_notif" class="btn btn-primary">ذخیره تغییرات</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">هیچ اعلانی یافت نشد.</td></tr>
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

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>