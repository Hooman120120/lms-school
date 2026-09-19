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

$msg = '';

// ------------------------------------------------------------------------
// Handle success message from redirect after POST
// ------------------------------------------------------------------------
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'insert') {
        $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">خبر با موفقیت ثبت شد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } elseif ($_GET['success'] == 'update') {
        $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">خبر با موفقیت ویرایش شد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// ------------------------------------------------------------------------
// Role mapping for display
// ------------------------------------------------------------------------
$audienceLabels = [
    '0'  => 'مدیریت',
    '1'  => 'پشتیبان',
    '2'  => 'مدیرمیانی',
    '3'  => 'استاد',
    '6'  => 'کارشناس فروش',
    '51' => 'معاونت اجرایی',
    '52' => 'معاونت آموزشی',
    '53' => 'معاونت فروش',
    '54' => 'معاونت فنی',
    '00' => 'کاربران',
    '000' => 'همه کارمندان'
];

// ------------------------------------------------------------------------
// Image upload function
// ------------------------------------------------------------------------
function uploadNewsImage($file, $old_image_path = null) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $old_image_path;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        return false;
    }
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return false;
    }

    // Absolute filesystem path (no "../../" in stored path)
    $upload_dir_fs = __DIR__ . '/../../uploads/news/';
    if (!is_dir($upload_dir_fs)) {
        mkdir($upload_dir_fs, 0755, true);
    }

    // Web‑relative path (what gets stored in DB)
    $web_path = 'uploads/news/';
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'news_' . uniqid() . '.' . $ext;
    $destination_fs = $upload_dir_fs . $filename;
    $destination_web = $web_path . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination_fs)) {
        if ($old_image_path && file_exists(__DIR__ . '/../../' . $old_image_path)) {
            unlink(__DIR__ . '/../../' . $old_image_path);
        }
        return $destination_web;   // store clean path like "uploads/news/news_xxx.jpg"
    }
    return false;
}

// ------------------------------------------------------------------------
// Save / Edit news (with image)
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_news'])) {
    $text = trim($_POST['news_text']);
    $status = (int) $_POST['news_status'];
    $audience = trim($_POST['news_audience']);
    $news_id = (int) $_POST['news_id'];

    if (empty($text)) {
        $msg = '<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">متن خبر نمی‌تواند خالی باشد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } else {
        $old_image = null;
        if ($news_id > 0) {
            $stmt_old = $conn->prepare("SELECT image_path FROM news WHERE id = :id");
            $stmt_old->execute([':id' => $news_id]);
            $old_image = $stmt_old->fetchColumn();
        }

        $uploaded_image = uploadNewsImage($_FILES['news_image'], $old_image);
        if ($uploaded_image === false && $_FILES['news_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = '<div class="alert alert-danger mt-3">فرمت تصویر نامعتبر یا حجم بیش از ۵ مگابایت!</div>';
        } else {
            if ($news_id > 0) {
                // Update
                $sql = "UPDATE news SET text = :text, status = :status, audience = :audience, image_path = :image_path WHERE id = :id";
                $params = [':text' => $text, ':status' => $status, ':audience' => $audience, ':image_path' => $uploaded_image, ':id' => $news_id];
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute($params);
                if ($result) {
                    // Redirect to avoid resubmission on refresh
                    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=update');
                    die();
                } else {
                    $msg = '<div class="alert alert-danger mt-3">خطا در ویرایش خبر!</div>';
                }
            } else {
                // Insert new
                $sql = "INSERT INTO news (text, status, audience, creator, image_path) VALUES (:text, :status, :audience, :creator, :image_path)";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([
                    ':text' => $text,
                    ':status' => $status,
                    ':audience' => $audience,
                    ':creator' => $_SESSION['admin']['user'],
                    ':image_path' => $uploaded_image
                ]);
                if ($result) {
                    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=insert');
                    die();
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $db_error = isset($errorInfo[2]) ? $errorInfo[2] : 'خطای نامشخص';
                    $msg = '<div class="alert alert-danger mt-3">خطا در ثبت خبر! جزئیات: ' . htmlspecialchars($db_error) . '</div>';
                }
            }
        }
    }
}

// ------------------------------------------------------------------------
// Delete news (with image)
// ------------------------------------------------------------------------

if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];
    $stmt_img = $conn->prepare("SELECT image_path FROM news WHERE id = :id");
    $stmt_img->execute([':id' => $del_id]);
    $img_path = $stmt_img->fetchColumn();

    $sql = "DELETE FROM news WHERE id = :id";
    $params = [':id' => $del_id];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($img_path && file_exists($img_path)) {
        unlink($img_path);
    }

    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?deleted=1');
    die();
}
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">خبر مورد نظر با موفقیت حذف شد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ------------------------------------------------------------------------
// Get data for editing
// ------------------------------------------------------------------------
$edit_news = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    $sql = "SELECT * FROM news WHERE id = :id";
    $params = [':id' => $edit_id];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $edit_news = $stmt->fetch(PDO::FETCH_OBJ);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>مدیریت اخبار</title>
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

            <!-- Form with new audience options -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold d-flex align-items-center" style="gap:10px;">

                                <?php echo $edit_news ? 'ویرایش خبر' : 'ثبت خبر جدید'; ?>

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید خبر جدیدی برای سامانه ثبت کنید یا اخبار قبلاً ثبت‌شده را ویرایش نمایید. اخبار منتشرشده از طریق تابلو اخبار در اختیار کاربران قرار گرفته و برای اطلاع‌رسانی رویدادها، اطلاعیه‌ها و مطالب مهم مجموعه استفاده می‌شوند."
                                      aria-label="راهنمای ثبت و ویرایش خبر">

            <i class="fe fe-info fs-4"></i>

        </span>

                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="news_id" value="<?php echo $edit_news ? $edit_news->id : '0'; ?>">

                                <div class="mb-3">
                                    <label for="news_text" class="form-label">متن خبر</label>
                                    <textarea class="form-control" id="news_text" name="news_text" rows="4" required><?php echo $edit_news ? htmlspecialchars($edit_news->text) : ''; ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="news_audience" class="form-label">مخاطب خبر</label>
                                        <select class="form-select" id="news_audience" name="news_audience">
                                            <option value="0" <?php echo ($edit_news && $edit_news->audience == '0') ? 'selected' : ''; ?>>مدیریت</option>
                                            <option value="1" <?php echo ($edit_news && $edit_news->audience == '1') ? 'selected' : ''; ?>>پشتیبان</option>
                                            <option value="2" <?php echo ($edit_news && $edit_news->audience == '2') ? 'selected' : ''; ?>>مدیرمیانی</option>
                                            <option value="3" <?php echo ($edit_news && $edit_news->audience == '3') ? 'selected' : ''; ?>>استاد</option>
                                            <option value="6" <?php echo ($edit_news && $edit_news->audience == '6') ? 'selected' : ''; ?>>کارشناس فروش</option>
                                            <option value="51" <?php echo ($edit_news && $edit_news->audience == '51') ? 'selected' : ''; ?>>معاونت اجرایی</option>
                                            <option value="52" <?php echo ($edit_news && $edit_news->audience == '52') ? 'selected' : ''; ?>>معاونت آموزشی</option>
                                            <option value="53" <?php echo ($edit_news && $edit_news->audience == '53') ? 'selected' : ''; ?>>معاونت فروش</option>
                                            <option value="54" <?php echo ($edit_news && $edit_news->audience == '54') ? 'selected' : ''; ?>>معاونت فنی</option>
                                            <option value="00" <?php echo ($edit_news && $edit_news->audience == '00') ? 'selected' : ''; ?>>کاربران</option>
                                            <option value="000" <?php echo ($edit_news && $edit_news->audience == '000') ? 'selected' : ''; ?>>همه کارمندان</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="news_status" class="form-label">وضعیت نمایش</label>
                                        <select class="form-select" id="news_status" name="news_status">
                                            <option value="1" <?php echo ($edit_news && $edit_news->status == 1) ? 'selected' : ''; ?>>فعال (نمایش)</option>
                                            <option value="0" <?php echo ($edit_news && $edit_news->status == 0) ? 'selected' : ''; ?>>مخفی (عدم نمایش)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="news_image" class="form-label">تصویر (اختیاری)</label>
                                        <input type="file" class="form-control" id="news_image" name="news_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                        <small class="text-muted">فرمت‌های مجاز: jpg, png, gif, webp | حداکثر ۵ مگابایت</small>
                                        <?php if ($edit_news && !empty($edit_news->image_path) && file_exists($edit_news->image_path)): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo htmlspecialchars($edit_news->image_path); ?>" style="height: 60px;" class="border rounded">
                                                <span class="text-muted small">(تصویر فعلی)</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <button type="submit" name="submit_news" class="btn <?php echo $edit_news ? 'btn-success' : 'btn-primary'; ?>">
                                    <i class="fa <?php echo $edit_news ? 'fa-save' : 'fa-plus'; ?>"></i>
                                    <?php echo $edit_news ? 'ذخیره تغییرات' : 'ثبت خبر'; ?>
                                </button>
                                <?php if ($edit_news): ?>
                                    <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-secondary">انصراف</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- News list with updated audience labels -->
            <?php
            $stmt = $conn->prepare("SELECT * FROM news ORDER BY id DESC");
            $stmt->execute();
            $news_list = $stmt->fetchAll(PDO::FETCH_OBJ);
            ?>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <p class="fw-bold mb-0 d-flex align-items-center" style="gap:10px;">

                                لیست اخبار ثبت شده

                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش تمامی اخبار ثبت‌شده سامانه نمایش داده می‌شود. از طریق این قسمت می‌توانید اخبار منتشرشده را مشاهده، ویرایش یا حذف کرده و محتوای خبری مجموعه را مدیریت نمایید."
                                      aria-label="راهنمای لیست اخبار ثبت شده">

            <i class="fe fe-info fs-4"></i>

        </span>

                            </p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered text-center align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 10%;">تصویر</th>
                                        <th style="width: 40%;" class="text-end">متن خبر</th>
                                        <th style="width: 10%;">مخاطب</th>
                                        <th style="width: 8%;">وضعیت</th>
                                        <th style="width: 14%;">تاریخ</th>
                                        <th style="width: 13%;">عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($news_list) > 0): ?>
                                        <?php $counter = 1; foreach ($news_list as $news):
                                            $date_show = $news->created_at;
                                            $update_show = '';
                                            if (function_exists('jdate')) {
                                                $date_show = jdate('Y/m/d - H:i', strtotime($news->created_at));
                                                if ($news->updated_at) {
                                                    $update_show = '<br><small class="text-muted">بروزرسانی: '.jdate('Y/m/d - H:i', strtotime($news->updated_at)).'</small>';
                                                }
                                            } elseif ($news->updated_at) {
                                                $update_show = '<br><small class="text-muted">بروزرسانی: '.$news->updated_at.'</small>';
                                            }
                                            // Determine audience label
                                            $audienceKey = (string) $news->audience;
                                            $audienceLabel = isset($audienceLabels[$audienceKey]) ? $audienceLabels[$audienceKey] : 'نامشخص';
                                            $badgeClass = ($audienceKey === '00') ? 'badge bg-secondary' : 'badge bg-info';
                                            // Image thumb
                                            $thumb = '';
                                            if (!empty($news->image_path)) {
                                                $raw_path = $news->image_path;

                                                // Normalize the stored path: remove leading '../' or './' and ensure it starts with 'uploads/'
                                                $clean_path = preg_replace('#^\.\./\.\./#', '', $raw_path);
                                                $clean_path = ltrim($clean_path, './');

                                                $web_url = (strpos($news->image_path, '../../') === 0) ? $news->image_path : '../../' . $news->image_path;

                                                // Absolute filesystem path for file_exists() check
                                                $abs_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $clean_path;

                                                if (file_exists($abs_path)) {
                                                    $thumb = '<div style="width: 60px; height: 60px; margin:0 auto; overflow:hidden; border-radius:5px; cursor:pointer;" 
                       data-bs-toggle="modal" data-bs-target="#imageModal" 
                       data-image-src="'.htmlspecialchars($web_url).'">
                    <img src="'.htmlspecialchars($web_url).'" 
                         style="width:100%; height:100%; object-fit:cover;">
                  </div>';
                                                } else {
                                                    $thumb = '<span class="text-muted">فایل تصویر یافت نشد</span>';
                                                }
                                            } else {
                                                $thumb = '<span class="text-muted">بدون تصویر</span>';
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo $thumb; ?></td>
                                                <td class="text-end" style="white-space: normal; word-wrap: break-word;">
                                                    <?php echo nl2br(htmlspecialchars($news->text)); ?>
                                                </td>
                                                <td><span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($audienceLabel); ?></span></td>
                                                <td>
                                                    <?php echo ($news->status == 1) ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-warning text-dark">مخفی</span>'; ?>
                                                </td>
                                                <td dir="ltr">
                                                    <?php echo $date_show . $update_show; ?>
                                                </td>
                                                <td>
                                                    <a href="?edit_id=<?php echo $news->id; ?>" class="btn btn-sm btn-info mb-1">ویرایش</a>
                                                    <a href="?delete_id=<?php echo $news->id; ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('آیا از حذف این خبر اطمینان دارید؟');">حذف</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">هیچ خبری یافت نشد.</td></tr>
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

<!-- Modal for large image -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تصویر خبر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="تصویر بزرگ" style="max-width:100%; max-height:80vh;">
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('imageModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function(event) {
                var trigger = event.relatedTarget;
                var imgUrl = trigger.getAttribute('data-image-src');
                document.getElementById('modalImage').src = imgUrl;
            });
        }
    });
</script>
</body>
</html>