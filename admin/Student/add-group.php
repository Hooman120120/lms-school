<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';

include_once '../../inc/bulk_upload.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
//* End Check Session JUST ONE Session
$stmt = $conn->prepare("
    SELECT bu.id, bu.uploaded_at, bu.uploaded_by, bu.filename,bu.deleted_at,
           COUNT(dp.idpersonal) AS inserted
    FROM bulk_uploads bu
    LEFT JOIN dbpersonal dp ON dp.bulk_upload_id = bu.id
    GROUP BY bu.id
    ORDER BY bu.uploaded_at DESC
");
$stmt->execute();
$bulkUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Read session report into local variables (if exists)
$bulk_inserted = 0;
$bulk_updated  = 0;
$bulk_skipped  = 0;
$bulk_errors   = [];

if (!empty($_SESSION['bulk_signup_report'])) { // <- match the key
    $bulk_inserted = $_SESSION['bulk_signup_report']['inserted'] ?? 0;
    $bulk_updated  = $_SESSION['bulk_signup_report']['updated']  ?? 0;
    $bulk_skipped  = $_SESSION['bulk_signup_report']['skipped']  ?? 0;
    $bulk_errors   = $_SESSION['bulk_signup_report']['errors']   ?? [];
    unset($_SESSION['bulk_signup_report']);
}

if (isset($_GET['download'])) {
    $sql = "SELECT * FROM bulk_uploads WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['download']]);
    $bulk = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$bulk) {
        die("رکورد یافت نشد");
    }

    $file = $_SERVER['DOCUMENT_ROOT'] . $bulk->file_path;
    $path = realpath($file);

    if ($path === false || !file_exists($path)) {
        die("فایل یافت نشد یا مسیر اشتباه است: " . htmlspecialchars($file));
    }

    $mimeType = mime_content_type($path);

    if (!file_exists($path)) {
        die("فایل یافت نشد");
    }

    // هدرهای دانلود
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> <?php echo m('page.bulk_add_student') ?> | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
    <!-- Loader -->
    <!--<div id="global-loader">-->
    <!--    <img src="--><?php //echo url('assets/img/media/loader.gif') 
                            ?><!--" class="loader-img" alt="درحال بارگذاری">-->
    <!--</div>-->
    <!-- End Loader -->
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
                    <?php
                    if (Permision('permision_viewstudent')) {
                        if (Permision('permision_addstudent')) {
                    ?>
                            <!-- Row -->

                            <div class="row row-sm">
                                <div class="col-lg-12">
                                    <div class="card custom-card">
                                        <div class="card-body">
                                            <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                                                <div class="card custom-card pt-0 pb-0" style="background-color: lightgreen">
                                                    <div class="card-header border-bottom-0 pb-1">
                                                        <label class="main-content-label mb-2 pt-1">ثبت نام دسته جمعی</label>
                                                        <p class="tx-12 mb-0 text-muted font-weight-bolder"><?php echo m('label.bulk_add_excel') ?></p>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" enctype="multipart/form-data">
                                                            <div class="form-group">
                                                                <label for="fileUpload">انتخاب فایل</label>
                                                                <input type="file" name="excel_file" accept=".xlsx,.xlsm" required>
                                                                <small class="form-text text-muted font-weight-bolder">(.xlsm)فرمت‌های مجاز: اکسل</small>
                                                            </div>
                                                            <button type="submit" name="upload" class="btn btn-primary">آپلود و ثبت نام</button>
                                                        </form>
                                                        <hr>
                                                        <p class="header-message">
                                                            طبق فرمت ذیل، فایل خود را آپلود نمایید<br>
                                                        </p>
                                                        <ol style="text-align: right; direction: rtl;">
                                                            <li>بعد از دانلود فایل، راست کلیک کنید و بر روی گزینه <strong>Properties</strong> کلیک نمایید.</li>
                                                            <li>در تب <strong>General</strong> پایین پنجره، بخش <strong>Unblock</strong> را تیک بزنید.</li>
                                                            <li>دکمه <strong>OK</strong> را کلیک کنید و پنجره را ببندید.</li>
                                                            <li>حال میتوانید از تنظیمات ماکروی فایل مربوطه استفاده کنید.</li>
                                                        </ol>
                                                        <p>
                                                            <a href="<?php echo url('assets/doc/group-add.xlsm') ?>" class="btn btn-success ripple"> دانلود فایل نمونه</a>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- REPORT CARD: only visible if session report exists -->
                                            <?php if ($bulk_inserted || $bulk_updated || $bulk_skipped || !empty($bulk_errors)): ?>
                                                <div class="col-xxl-12 col-xl-12 col-md-12 col-lg-12">
                                                    <div class="card custom-card bg-light">
                                                        <div class="card-header border-bottom-0 pb-1">
                                                            <label class="main-content-label mb-2 pt-1">گزارش ثبت نام دسته جمعی</label>
                                                        </div>
                                                        <div class="card-body">
                                                            <p>افزوده شده: <?= $bulk_inserted ?></p>
                                                            <p>به‌روزرسانی شده: <?= $bulk_updated ?></p>
                                                            <p>نادیده گرفته شده: <?= $bulk_skipped ?></p>

                                                            <?php if (!empty($bulk_errors)): ?>
                                                                <div class="mt-3">
                                                                    <strong>خطاها:</strong>
                                                                    <ul class="text-danger">
                                                                        <?php foreach ($bulk_errors as $err): ?>
                                                                            <li><?= $err ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>شناسه</th>
                                                        <th>زمان آپلود</th>
                                                        <th>آپلود شده توسط</th>
                                                        <th>فایل</th>
                                                        <th>موفق</th>
                                                        <th>اقدامات</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($bulkUploads as $upload): ?>
                                                        <tr>
                                                            <td><?= $upload['id'] ?></td>
                                                            <td><?= !empty($upload['uploaded_at']) ? gregorianDateTimeToJalali($upload['uploaded_at']) : '-' ?></td>
                                                            <td><?= $upload['uploaded_by'] ?></td>
                                                            <td><?= $upload['filename'] ?></td>
                                                            <td><?= $upload['inserted'] ?></td>
                                                            <td class="d-flex flex-row gap-1">
                                                                <?php if ($upload['deleted_at']): ?>
                                                                    <span class="badge bg-danger">حذف شده</span>
                                                                <?php else: ?>
                                                                    <form method="POST" onsubmit="return confirmDelete(this);">
                                                                        <input type="hidden" name="delete_bulk_id" value="<?= $upload['id'] ?>">
                                                                        <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <?php if (!empty($upload['errors'])): ?>
                                                                    <ul>
                                                                        <?php foreach ($upload['errors'] as $err): ?>
                                                                            <li><?= $err ?></li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                <?php endif; ?>
                                                                <a class="btn btn-success btn-sm text-white" href="?download=<?= $upload['id'] ?>">دانلود فایل</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Row -->
                        <?php } else { ?>
                            <div class="card-body">

                                <div class="alert alert-danger" role="alert">
                                    <span class="alert-inner--icon"><i class="fe fe-alert-triangle"></i></span>

                                    شما مجوز لازم برای مشاهده این بخش را ندارید، با مدیر سامانه تماس بگیرید
                                </div>
                                <div style="text-align: center; padding-top: 20px">
                                    <img src="../../assets/img/media/alert.png">

                                </div>
                            </div>
                    <?php }
                    } ?>
                </div>
            </div>
        </div>
        <!-- End Row -->


        <!-- Main Footer-->
        <?php include '../../footers.php'; ?>
        <!--End Footer-->

        <script>
            function confirmDelete(form) {
                let input = prompt("برای تایید حذف، عبارت DELETE را وارد کنید:");
                if (input === "DELETE") {
                    return true;
                }
                alert("حذف لغو شد. برای حذف باید دقیقا DELETE تایپ کنید.");
                return false;
            }
        </script>

</body>

</html>