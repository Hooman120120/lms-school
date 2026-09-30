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

// ------------------------------------------------------------------------
// Role mapping for display
// ------------------------------------------------------------------------
$schoolAudienceRoles = inst_is('school') ? DailyNotificationSchoolRoles() : [];
$audienceLabels = DailyNotificationAudienceLabels($schoolAudienceRoles);

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
// Image upload function
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

    $upload_dir_fs = __DIR__ . '/../../uploads/notifications/';
    if (!is_dir($upload_dir_fs)) {
        mkdir($upload_dir_fs, 0755, true);
    }

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
// ۱. عملیات ثبت اعلان جدید (Create)
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_notif'])) {
    AdminRequire('executive.daily-notices.create');
    $text = trim($_POST['notif_text']);
    $audiences = NormalizeDailyNotificationAudiences($_POST['audience'] ?? [], $audienceLabels);
    $expiration_raw = trim($_POST['expiration_date'] ?? '');

    // Using global function from function.php (true sets time to 23:59:59)
    $expiration_date = toGregorian($expiration_raw, true);

    if (!empty($text) && !empty($audiences)) {
        $image_path = uploadNotificationImage($_FILES['notif_image']);
        if ($image_path === false && $_FILES['notif_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در آپلود تصویر! فرمت مجاز: jpg, png, gif, webp (حداکثر ۵ مگابایت)</div>';
        } else {
            $conn->beginTransaction();
            try {
                $sql_insert = "INSERT INTO `notif` (`text`, `creator`, `image_path`, `expiration_date`) VALUES (:text, :creator, :image_path, :expiration_date)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->execute([
                    ':text' => $text,
                    ':creator' => $_SESSION['admin']['user'],
                    ':image_path' => $image_path,
                    ':expiration_date' => $expiration_date
                ]);
                $notif_id = $conn->lastInsertId();

                $sql_aud = "INSERT INTO `notif_audience` (`notif_id`, `audience`) VALUES (:notif_id, :audience)";
                $stmt_aud = $conn->prepare($sql_aud);
                foreach ($audiences as $aud) {
                    $stmt_aud->execute([
                        ':notif_id' => $notif_id,
                        ':audience' => trim($aud)
                    ]);
                }

                $conn->commit();
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?inserted=1');
                die();
            } catch (Exception $e) {
                $conn->rollBack();
                $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در ثبت اعلان! لطفا دوباره تلاش کنید.</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-warning mt-3" role="alert">متن اعلان و حداقل یک مخاطب باید انتخاب شود.</div>';
    }
}

// ------------------------------------------------------------------------
// ۲. عملیات ویرایش اعلان (Update)
// ------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notif'])) {
    AdminRequire('executive.daily-notices.edit');
    $notif_id = intval($_POST['notif_id']);
    $text = trim($_POST['notif_text']);
    $audiences = NormalizeDailyNotificationAudiences($_POST['audience'] ?? [], $audienceLabels);
    $expiration_raw = trim($_POST['expiration_date'] ?? '');

    // Using global function from function.php (true sets time to 23:59:59)
    $expiration_date = toGregorian($expiration_raw, true);

    if (!empty($text) && !empty($audiences)) {
        $sql_old = "SELECT `image_path` FROM `notif` WHERE `id` = :id";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->execute([':id' => $notif_id]);
        $old_image = $stmt_old->fetchColumn();

        $new_image = uploadNotificationImage($_FILES['notif_image'], $old_image);
        if ($new_image === false && $_FILES['notif_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در آپلود تصویر جدید!</div>';
        } else {
            $conn->beginTransaction();
            try {
                $sql_update = "UPDATE `notif` SET `text` = :text, `image_path` = :image_path, `expiration_date` = :expiration_date WHERE `id` = :id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->execute([
                    ':text' => $text,
                    ':image_path' => $new_image,
                    ':expiration_date' => $expiration_date,
                    ':id' => $notif_id
                ]);

                $sql_del_aud = "DELETE FROM `notif_audience` WHERE `notif_id` = :notif_id";
                $stmt_del_aud = $conn->prepare($sql_del_aud);
                $stmt_del_aud->execute([':notif_id' => $notif_id]);

                $sql_ins_aud = "INSERT INTO `notif_audience` (`notif_id`, `audience`) VALUES (:notif_id, :audience)";
                $stmt_ins_aud = $conn->prepare($sql_ins_aud);
                foreach ($audiences as $aud) {
                    $stmt_ins_aud->execute([
                        ':notif_id' => $notif_id,
                        ':audience' => trim($aud)
                    ]);
                }

                $conn->commit();
                header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?updated=1');
                die();
            } catch (Exception $e) {
                $conn->rollBack();
                $msg = '<div class="alert alert-danger mt-3" role="alert">خطا در به‌روزرسانی اعلان!</div>';
            }
        }
    } else {
        $msg = '<div class="alert alert-warning mt-3" role="alert">متن اعلان و حداقل یک مخاطب باید انتخاب شود.</div>';
    }
}

// ------------------------------------------------------------------------
// ۳. عملیات حذف اعلان (Delete)
// ------------------------------------------------------------------------
if (isset($_GET['delete_id'])) {
    AdminRequire('executive.daily-notices.edit');
    $del_id = intval($_GET['delete_id']);

    $sql_get_img = "SELECT `image_path` FROM `notif` WHERE `id` = :id";
    $stmt_get_img = $conn->prepare($sql_get_img);
    $stmt_get_img->execute([':id' => $del_id]);
    $img_path = $stmt_get_img->fetchColumn();

    $conn->beginTransaction();
    try {
        $sql_del_aud = "DELETE FROM `notif_audience` WHERE `notif_id` = :id";
        $stmt_del_aud = $conn->prepare($sql_del_aud);
        $stmt_del_aud->execute([':id' => $del_id]);

        $sql_delete = "DELETE FROM `notif` WHERE `id` = :id";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->execute([':id' => $del_id]);

        $conn->commit();

        if ($img_path) {
            $abs_img_path = __DIR__ . '/../' . $img_path;
            if (file_exists($abs_img_path)) {
                unlink($abs_img_path);
            }
        }
    } catch (Exception $e) {
        $conn->rollBack();
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
    <style>
        .campaign-group-pill {
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease-in-out;
        }
        .campaign-group-pill.active {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            border-color: #0d6efd !important;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.4);
        }
    </style>
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

            <!-- فرم ثبت اعلان جدید -->
            <div class="row row-sm mt-4">
                <div class="col-lg-12 col-md-12">
                    <div class="card custom-card shadow-sm">
                        <div class="card-header border-bottom-0 custom-card-header">
                            <h6 class="main-content-label mb-0 d-flex align-items-center" style="gap:10px;">
                                ثبت اعلان جدید
                                <span class="menu-info-trigger"
                                      data-tooltip="در این بخش می‌توانید اطلاعیه جدیدی برای کاربران سامانه ثبت کنید."
                                      aria-label="راهنمای ثبت اعلان جدید">
                                <i class="fe fe-info fs-4"></i>
                            </span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (AdminCan('executive.daily-notices.create')): ?>
                            <form action="" method="POST" enctype="multipart/form-data">
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
                                                            <?php foreach ($audienceLabels as $key => $label): ?>
                                                                <label class="campaign-group-pill btn btn-outline-primary btn-sm mb-0">
                                                                    <input type="checkbox" name="audience[]" value="<?php echo $key; ?>" class="d-none">
                                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- انتخاب تاریخ انقضا -->
                                    <div class="col-md-4 mb-3 align-content-center">
                                        <label class="form-label fw-bold">تاریخ انقضا (اختیاری)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                            <input class="form-control fc-datepicker" type="text" name="expiration_date" placeholder="مثال: ۱۴۰۵/۰۵/۱۹" autocomplete="off">
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $sql_fetch = "SELECT n.*, GROUP_CONCAT(na.audience) as audience_list 
                      FROM `notif` n 
                      LEFT JOIN `notif_audience` na ON n.id = na.notif_id 
                      GROUP BY n.id 
                      ORDER BY n.id DESC";
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
                                      data-tooltip="در این بخش تمامی اعلان‌های ثبت‌شده در سامانه نمایش داده می‌شود."
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
                                        <th class="wd-15p">مخاطبین</th>
                                        <th class="wd-35p text-right">متن اعلان</th>
                                        <th class="wd-10p">تاریخ انقضا</th>
                                        <th class="wd-15p">عملیات</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($notifs) > 0): ?>
                                        <?php $counter = 1; foreach ($notifs as $notif):
                                            $date_show = $notif->created_at;
                                            // Used global toJalali() function from function.php
                                            $exp_jalali = toJalali($notif->expiration_date);
                                            $image_html = '';
                                            if (!empty($notif->image_path)) {
                                                $web_url = '/' . $notif->image_path;
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

                                            $current_audiences = isset($notif->audience_list) && $notif->audience_list !== ''
                                                ? explode(',', $notif->audience_list)
                                                : (isset($notif->audience) ? [(string)$notif->audience] : []);
                                            $current_audiences = array_map('trim', $current_audiences);
                                            $current_audiences = DailyNotificationExpandLegacyAudiences($current_audiences, $schoolAudienceRoles);
                                            ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo $image_html; ?></td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                        <?php foreach ($current_audiences as $audKey):
                                                            $audKey = trim((string)$audKey);
                                                            $audienceLabel = isset($audienceLabels[$audKey]) ? $audienceLabels[$audKey] : 'نامشخص';
                                                            $badgeClass = ($audKey === '000') ? 'badge bg-dark' : (($audKey === '00') ? 'badge bg-secondary' : 'badge bg-info');
                                                            ?>
                                                            <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($audienceLabel); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                                <td class="text-right" style="white-space: normal; line-height: 1.8;">
                                                    <?php echo nl2br(htmlspecialchars($notif->text)); ?>
                                                </td>
                                                <td dir="ltr"><?php echo !empty($exp_jalali) ? htmlspecialchars($exp_jalali) : '<span class="text-muted">بدون انقضا</span>'; ?></td>
                                                <td>
                                                    <?php if (AdminCan('executive.daily-notices.edit')): ?>
                                                    <a href="?edit_id=<?php echo $notif->id; ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $notif->id; ?>">ویرایش</a>
                                                    <?php endif; ?>
                                                    <?php if (AdminCan('executive.daily-notices.edit')): ?>
                                                    <a href="?delete_id=<?php echo $notif->id; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این اعلان اطمینان دارید؟');">حذف</a>
                                                    <?php endif; ?>
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

                                                                <div class="row">
                                                                    <div class="col-md-8 mb-3">
                                                                        <div class="accordion" id="editAccordion<?php echo $notif->id; ?>">
                                                                            <div class="accordion-item mt-2">
                                                                                <h2 class="accordion-header" id="headingEdit<?php echo $notif->id; ?>">
                                                                                    <button class="accordion-button collapsed" type="button"
                                                                                            data-bs-toggle="collapse" data-bs-target="#collapseEdit<?php echo $notif->id; ?>"
                                                                                            aria-expanded="false" aria-controls="collapseEdit<?php echo $notif->id; ?>">
                                                                                        انتخاب مخاطبین <span class="ms-2 text-muted small audience-count-label">(<?php echo count($current_audiences); ?>)</span>
                                                                                    </button>
                                                                                </h2>
                                                                                <div id="collapseEdit<?php echo $notif->id; ?>" class="accordion-collapse collapse"
                                                                                     aria-labelledby="headingEdit<?php echo $notif->id; ?>" data-bs-parent="#editAccordion<?php echo $notif->id; ?>">
                                                                                    <div class="accordion-body">
                                                                                        <div class="mb-3">
                                                                                            <input type="text" class="form-control form-control-sm campaign-group-search" placeholder="جستجوی گروه...">
                                                                                        </div>
                                                                                        <div class="d-flex flex-wrap gap-2 campaign-group-list">
                                                                                            <?php foreach ($audienceLabels as $key => $label):
                                                                                                $isSelected = in_array((string)$key, $current_audiences, true);
                                                                                                ?>
                                                                                                <label class="campaign-group-pill btn btn-outline-primary btn-sm mb-0 <?php echo $isSelected ? 'active' : ''; ?>">
                                                                                                    <input type="checkbox" name="audience[]" value="<?php echo $key; ?>" class="d-none" <?php echo $isSelected ? 'checked' : ''; ?>>
                                                                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                                                </label>
                                                                                            <?php endforeach; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-md-4 mb-3 align-content-center">
                                                                        <label class="form-label fw-bold">تاریخ انقضا (اختیاری)</label>
                                                                        <div class="input-group">
                                                                            <span class="input-group-text"><i class="fe fe-calendar lh--9 op-6"></i></span>
                                                                            <input class="form-control fc-datepicker" type="text" name="expiration_date" value="<?php echo htmlspecialchars($exp_jalali); ?>" placeholder="مثال: ۱۴۰۵/۰۵/۱۹" autocomplete="off">
                                                                        </div>
                                                                    </div>
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
                                        <tr><td colspan="7" class="text-center text-muted py-4">هیچ اعلانی یافت نشد.</td></tr>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function updateAudienceState(accordionItem) {
            var activeCount = accordionItem.querySelectorAll('.campaign-group-pill.active').length;
            var countSpan = accordionItem.querySelector('.audience-count-label');
            if (countSpan) {
                countSpan.textContent = '(' + activeCount + ')';
            }
        }

        function syncPillVisibility(container) {
            var allUsersCheckbox = container.querySelector('input[value="000"]');
            var isAllUsersChecked = allUsersCheckbox && allUsersCheckbox.checked;
            var searchInput = container.querySelector('.campaign-group-search');
            var filter = searchInput ? searchInput.value.trim().toLowerCase() : '';

            var pills = container.querySelectorAll('.campaign-group-pill');
            pills.forEach(function(pill) {
                var checkbox = pill.querySelector('input[type="checkbox"]');
                var isAllUsersPill = checkbox && checkbox.value === '000';

                if (isAllUsersChecked) {
                    if (isAllUsersPill) {
                        pill.style.display = 'inline-block';
                    } else {
                        pill.style.display = 'none';
                        if (checkbox) checkbox.checked = false;
                        pill.classList.remove('active');
                    }
                } else {
                    var text = pill.textContent.trim().toLowerCase();
                    if (!filter || text.includes(filter)) {
                        pill.style.display = 'inline-block';
                    } else {
                        pill.style.display = 'none';
                    }
                }
            });
        }

        document.querySelectorAll('.campaign-group-pill').forEach(function(pill) {
            var checkbox = pill.querySelector('input[type="checkbox"]');

            if (checkbox && checkbox.checked) {
                pill.classList.add('active');
            }

            pill.addEventListener('click', function(e) {
                e.preventDefault();
                if (!checkbox) return;

                checkbox.checked = !checkbox.checked;
                pill.classList.toggle('active', checkbox.checked);

                var accordionBody = pill.closest('.accordion-body') || pill.closest('.accordion-item');
                if (accordionBody) {
                    syncPillVisibility(accordionBody);
                }

                var accordionItem = pill.closest('.accordion-item');
                if (accordionItem) {
                    updateAudienceState(accordionItem);
                }
            });
        });

        document.querySelectorAll('.campaign-group-search').forEach(function(searchInput) {
            searchInput.addEventListener('input', function() {
                var accordionBody = this.closest('.accordion-body') || this.closest('.accordion-item');
                if (accordionBody) {
                    syncPillVisibility(accordionBody);
                }
            });
        });

        document.querySelectorAll('.accordion-item').forEach(function(item) {
            syncPillVisibility(item);
            updateAudienceState(item);
        });
    });
</script>
</body>
</html>
