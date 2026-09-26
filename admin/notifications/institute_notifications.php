<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
if (!isset($_SESSION['admin'])) {
    header('Location: ../login.php');
    exit;
}
if (!inst_is('institute')) {
    http_response_code(404);
    exit;
}
EnforceAdminPageTreePermission();
$notificationLinks = InstituteNotificationLinks();
if ($notificationLinks === []) {
    http_response_code(403);
    exit('دسترسی بخش‌های اعلان‌های آموزشگاه فعال نشده است.');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اعلان‌های آموزشگاه | <?= __TITLE ?></title>
    <?php include '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include '../menu.php'; include '../header.php'; ?>
    <div class="main-content side-content pt-0"><div class="container-fluid">
        <h2 class="main-content-title tx-24 mb-4">اعلان‌های آموزشگاه</h2>
        <div class="row">
            <?php foreach ($notificationLinks as $entry): ?>
                <div class="col-md-6 col-xl-4 mb-3">
                    <a class="card custom-card p-4" href="<?= htmlspecialchars(url('admin/notifications/' . $entry[2]), ENT_QUOTES, 'UTF-8') ?>">
                        <span><i class="fe fe-bell ml-2"></i><?= htmlspecialchars($entry[1], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</div>
<?php include '../../footers.php'; ?>
</body>
</html>
