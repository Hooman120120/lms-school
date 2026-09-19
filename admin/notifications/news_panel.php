<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// ------------------------------------------------------------------------
// Handle Flash Messages (Post/Redirect/Get)
// ------------------------------------------------------------------------
$msg = '';
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
} elseif (isset($_GET['success'])) {
    if ($_GET['success'] == 'insert') {
        $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">خبر با موفقیت ثبت شد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } elseif ($_GET['success'] == 'update') {
        $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">خبر با موفقیت ویرایش شد.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } elseif ($_GET['success'] == 'status') {
        $msg = '<div class="alert alert-success alert-dismissible fade show mt-3" role="alert">وضعیت خبر با موفقیت تغییر یافت.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// ------------------------------------------------------------------------
// Role mapping for display
// ------------------------------------------------------------------------
$audienceLabels = [
    '0'   => 'مدیریت',
    '1'   => 'پشتیبان',
    '2'   => 'مدیرمیانی',
    '3'   => t('role_teacher'),
    '6'   => 'کارشناس فروش',
    '51'  => 'معاونت اجرایی',
    '52'  => 'معاونت آموزشی',
    '53'  => 'معاونت فروش',
    '54'  => 'معاونت فنی',
    '00'  => 'دانش آموزان',
    '000' => 'همه کاربران'
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

    $upload_dir_fs = __DIR__ . '/../../uploads/news/';
    if (!is_dir($upload_dir_fs)) {
        mkdir($upload_dir_fs, 0755, true);
    }

    $web_path = 'uploads/news/';
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'news_' . uniqid() . '.' . $ext;
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
// Quick toggle status action
// ------------------------------------------------------------------------
if (isset($_GET['toggle_status_id'])) {
    $toggle_id = (int) $_GET['toggle_status_id'];
    $stmt = $conn->prepare("UPDATE news SET status = IF(status = 1, 0, 1) WHERE id = :id");
    $stmt->execute([':id' => $toggle_id]);

    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=status');
    die();
}

// ------------------------------------------------------------------------
// Save / Edit news (with news_audience support)
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_news'])) {

    // Prevent duplicate submission using form tokens
    if (!isset($_POST['form_token']) || !isset($_SESSION['form_token']) || $_POST['form_token'] !== $_SESSION['form_token']) {
        $_SESSION['flash_msg'] = '<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">فرم قبلاً ارسال شده است یا معتبر نیست.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }

    // Invalidate the used token immediately
    unset($_SESSION['form_token']);

    $text = trim($_POST['news_text']);
    $status = (int) $_POST['news_status'];
    $audiences = isset($_POST['audience']) && is_array($_POST['audience']) ? $_POST['audience'] : [];
    $news_id = (int) $_POST['news_id'];

    if (empty($text) || empty($audiences)) {
        $_SESSION['flash_msg'] = '<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">متن خبر و حداقل یک مخاطب باید انتخاب شود.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }

    $old_image = null;
    if ($news_id > 0) {
        $stmt_old = $conn->prepare("SELECT image_path FROM news WHERE id = :id");
        $stmt_old->execute([':id' => $news_id]);
        $old_image = $stmt_old->fetchColumn();
    }

    $uploaded_image = uploadNewsImage($_FILES['news_image'], $old_image);
    if ($uploaded_image === false && $_FILES['news_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $_SESSION['flash_msg'] = '<div class="alert alert-danger mt-3">فرمت تصویر نامعتبر یا حجم بیش از ۵ مگابایت!</div>';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }

    $conn->beginTransaction();
    try {
        if ($news_id > 0) {
            $sql = "UPDATE news SET text = :text, status = :status, image_path = :image_path WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':text' => $text,
                ':status' => $status,
                ':image_path' => $uploaded_image,
                ':id' => $news_id
            ]);

            $stmt_del = $conn->prepare("DELETE FROM news_audience WHERE news_id = :news_id");
            $stmt_del->execute([':news_id' => $news_id]);

            $stmt_ins = $conn->prepare("INSERT INTO news_audience (news_id, audience) VALUES (:news_id, :audience)");
            foreach ($audiences as $aud) {
                $stmt_ins->execute([
                    ':news_id' => $news_id,
                    ':audience' => trim($aud)
                ]);
            }

            $conn->commit();
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=update');
            die();
        } else {
            $sql = "INSERT INTO news (text, status, creator, image_path) VALUES (:text, :status, :creator, :image_path)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':text' => $text,
                ':status' => $status,
                ':creator' => $_SESSION['admin']['user'],
                ':image_path' => $uploaded_image
            ]);
            $inserted_id = $conn->lastInsertId();

            $stmt_ins = $conn->prepare("INSERT INTO news_audience (news_id, audience) VALUES (:news_id, :audience)");
            foreach ($audiences as $aud) {
                $stmt_ins->execute([
                    ':news_id' => $inserted_id,
                    ':audience' => trim($aud)
                ]);
            }

            $conn->commit();
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=insert');
            die();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['flash_msg'] = '<div class="alert alert-danger mt-3">خطا در ذخیره‌سازی خبر! لطفا دوباره تلاش کنید.</div>';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }
}

// Generate new form token for display rendering
$_SESSION['form_token'] = bin2hex(random_bytes(32));

// ------------------------------------------------------------------------
// Delete news (with image & audience records)
// ------------------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];

    $stmt_img = $conn->prepare("SELECT image_path FROM news WHERE id = :id");
    $stmt_img->execute([':id' => $del_id]);
    $img_path = $stmt_img->fetchColumn();

    $conn->beginTransaction();
    try {
        $stmt_del_aud = $conn->prepare("DELETE FROM news_audience WHERE news_id = :id");
        $stmt_del_aud->execute([':id' => $del_id]);

        $stmt_del_news = $conn->prepare("DELETE FROM news WHERE id = :id");
        $stmt_del_news->execute([':id' => $del_id]);

        $conn->commit();

        if ($img_path && file_exists(__DIR__ . '/../../' . $img_path)) {
            unlink(__DIR__ . '/../../' . $img_path);
        }
    } catch (Exception $e) {
        $conn->rollBack();
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
$edit_selected_audiences = [];
if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM news WHERE id = :id");
    $stmt->execute([':id' => $edit_id]);
    $edit_news = $stmt->fetch(PDO::FETCH_OBJ);

    if ($edit_news) {
        $stmt_aud = $conn->prepare("SELECT audience FROM news_audience WHERE news_id = :news_id");
        $stmt_aud->execute([':news_id' => $edit_id]);
        $edit_selected_audiences = array_map('strval', $stmt_aud->fetchAll(PDO::FETCH_COLUMN));
    }
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

            <!-- Form -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold"><?php echo $edit_news ? 'ویرایش خبر' : 'ثبت خبر جدید'; ?></h6>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>">
                                <input type="hidden" name="news_id" value="<?php echo $edit_news ? $edit_news->id : '0'; ?>">

                                <div class="mb-3">
                                    <label for="news_text" class="form-label">متن خبر</label>
                                    <textarea class="form-control" id="news_text" name="news_text" rows="4" required><?php echo $edit_news ? htmlspecialchars($edit_news->text) : ''; ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <div class="accordion" id="campaignSelectAccordion">
                                            <div class="accordion-item mt-2">
                                                <h2 class="accordion-header" id="headingCampaignGroups">
                                                    <button class="accordion-button collapsed" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#collapseCampaignGroups"
                                                            aria-expanded="false" aria-controls="collapseCampaignGroups">
                                                        انتخاب مخاطبین <span class="ms-2 text-muted small audience-count-label">(0)</span>
                                                    </button>
                                                </h2>
                                                <div id="collapseCampaignGroups" class="accordion-collapse collapse"
                                                     aria-labelledby="headingCampaignGroups" data-bs-parent="#campaignSelectAccordion">
                                                    <div class="accordion-body">
                                                        <div class="mb-3">
                                                            <input type="text" class="form-control form-control-sm campaign-group-search" placeholder="جستجوی گروه...">
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2 campaign-group-list">
                                                            <?php foreach ($audienceLabels as $key => $label):
                                                                $is_checked = in_array((string)$key, $edit_selected_audiences, true);
                                                                $active_class = $is_checked ? ' active btn-primary' : ' btn-outline-primary';
                                                                ?>
                                                                <label class="campaign-group-pill btn btn-sm mb-0<?php echo $active_class; ?>">
                                                                    <input type="checkbox" name="audience[]" value="<?php echo $key; ?>" class="d-none" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="news_status" class="form-label">وضعیت نمایش</label>
                                        <select class="form-select" id="news_status" name="news_status">
                                            <option value="1" <?php echo ($edit_news && $edit_news->status == 1) ? 'selected' : (! $edit_news ? 'selected' : ''); ?>>فعال (نمایش)</option>
                                            <option value="0" <?php echo ($edit_news && $edit_news->status == 0) ? 'selected' : ''; ?>>مخفی (عدم نمایش)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3">
                                        <label for="news_image" class="form-label">تصویر (اختیاری)</label>
                                        <input type="file" class="form-control" id="news_image" name="news_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                        <small class="text-muted">فرمت‌های مجاز: jpg, png, gif, webp | حداکثر ۵ مگابایت</small>
                                        <?php if ($edit_news && !empty($edit_news->image_path) && file_exists(__DIR__ . '/../../' . $edit_news->image_path)): ?>
                                            <div class="mt-2">
                                                <img src="../../<?php echo htmlspecialchars($edit_news->image_path); ?>" style="height: 60px;" class="border rounded">
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

            <!-- News list -->
            <?php
            $stmt = $conn->prepare("SELECT * FROM news ORDER BY id DESC");
            $stmt->execute();
            $news_list = $stmt->fetchAll(PDO::FETCH_OBJ);

            $news_audiences = [];
            if (!empty($news_list)) {
                $news_ids = array_map(function($n) { return $n->id; }, $news_list);
                $in_clause = implode(',', array_fill(0, count($news_ids), '?'));
                $stmt_auds = $conn->prepare("SELECT news_id, audience FROM news_audience WHERE news_id IN ($in_clause)");
                $stmt_auds->execute($news_ids);
                while ($row = $stmt_auds->fetch(PDO::FETCH_ASSOC)) {
                    $news_audiences[$row['news_id']][] = $row['audience'];
                }
            }
            ?>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <p class="fw-bold mb-0">لیست اخبار ثبت شده</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered text-center align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 10%;">تصویر</th>
                                        <th style="width: 32%;" class="text-end">متن خبر</th>
                                        <th style="width: 15%;">مخاطبان</th>
                                        <th style="width: 8%;">وضعیت</th>
                                        <th style="width: 12%;">تاریخ</th>
                                        <th style="width: 18%;">عملیات</th>
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

                                            $item_audiences = $news_audiences[$news->id] ?? [];
                                            $aud_badges = [];
                                            foreach ($item_audiences as $audKey) {
                                                $lbl = $audienceLabels[$audKey] ?? 'نامشخص';
                                                $badgeClass = ($audKey === '00') ? 'badge bg-secondary m-1' : 'badge bg-info m-1';
                                                $aud_badges[] = '<span class="' . $badgeClass . '">' . htmlspecialchars($lbl) . '</span>';
                                            }
                                            $aud_html = !empty($aud_badges) ? implode(' ', $aud_badges) : '<span class="text-muted">بدون مخاطب</span>';

                                            $thumb = '';
                                            if (!empty($news->image_path)) {
                                                $web_url = '../../' . ltrim($news->image_path, '/');
                                                $abs_path = __DIR__ . '/../../' . $news->image_path;

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
                                                <td><?php echo $aud_html; ?></td>
                                                <td>
                                                    <?php echo ($news->status == 1) ? '<span class="badge bg-success">فعال</span>' : '<span class="badge bg-warning text-dark">مخفی</span>'; ?>
                                                </td>
                                                <td dir="ltr">
                                                    <?php echo $date_show . $update_show; ?>
                                                </td>
                                                <td>
                                                    <?php if ($news->status == 1): ?>
                                                        <a href="?toggle_status_id=<?php echo $news->id; ?>" class="btn btn-sm btn-warning mb-1" title="مخفی‌سازی">مخفی‌سازی</a>
                                                    <?php else: ?>
                                                        <a href="?toggle_status_id=<?php echo $news->id; ?>" class="btn btn-sm btn-success mb-1" title="فعال‌سازی">فعال‌سازی</a>
                                                    <?php endif; ?>
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
        // Modal image viewer
        var modal = document.getElementById('imageModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function(event) {
                var trigger = event.relatedTarget;
                var imgUrl = trigger.getAttribute('data-image-src');
                document.getElementById('modalImage').src = imgUrl;
            });
        }

        // Audience selection logic
        const accordion = document.getElementById('campaignSelectAccordion');
        if (accordion) {
            const countLabel = accordion.querySelector('.audience-count-label');
            const searchInput = accordion.querySelector('.campaign-group-search');
            const pills = accordion.querySelectorAll('.campaign-group-pill');

            function updateAudienceState() {
                const allUsersCheckbox = accordion.querySelector('input[value="000"]');
                const isAllSelected = allUsersCheckbox && allUsersCheckbox.checked;
                const filter = searchInput ? searchInput.value.trim().toLowerCase() : '';

                pills.forEach(pill => {
                    const checkbox = pill.querySelector('input[type="checkbox"]');
                    if (!checkbox) return;

                    const isAllPill = checkbox.value === '000';

                    if (isAllSelected) {
                        if (isAllPill) {
                            pill.style.display = '';
                            pill.classList.add('active', 'btn-primary');
                            pill.classList.remove('btn-outline-primary');
                        } else {
                            // Hide and uncheck all other audience options
                            pill.style.display = 'none';
                            checkbox.checked = false;
                            pill.classList.remove('active', 'btn-primary');
                            pill.classList.add('btn-outline-primary');
                        }
                    } else {
                        // Update pill button active states
                        if (checkbox.checked) {
                            pill.classList.add('active', 'btn-primary');
                            pill.classList.remove('btn-outline-primary');
                        } else {
                            pill.classList.remove('active', 'btn-primary');
                            pill.classList.add('btn-outline-primary');
                        }

                        // Apply search filter
                        const text = pill.textContent.trim().toLowerCase();
                        if (!filter || text.includes(filter)) {
                            pill.style.display = '';
                        } else {
                            pill.style.display = 'none';
                        }
                    }
                });

                // Update badge count label
                const selectedCount = accordion.querySelectorAll('.campaign-group-pill input[type="checkbox"]:checked').length;
                if (countLabel) {
                    countLabel.textContent = `(${selectedCount})`;
                }
            }

            pills.forEach(pill => {
                const checkbox = pill.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.addEventListener('change', updateAudienceState);
                }
            });

            if (searchInput) {
                searchInput.addEventListener('input', updateAudienceState);
            }

            updateAudienceState();
        }
    });
</script>
</body>
</html>