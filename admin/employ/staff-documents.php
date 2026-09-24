<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    exit;
}
if (($_SESSION['admin']['ip'] ?? '') !== GetIp() && ($_SESSION['admin']['os'] ?? '') != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
$sessionRow = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($sessionRow['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
if (!Permision('permission_staff_documents') && !Permision('permision_viewemploy')) {
    http_response_code(403);
    exit('مجوز مشاهده مستندات پرسنلی را ندارید.');
}
$rows = $conn->query("SELECT a.username,a.fname,a.lname,a.document_file,a.resume_file,a.identity_file,r.title role_title FROM dbadmin a LEFT JOIN school_admin_roles r ON r.id=a.school_role_id ORDER BY a.lname,a.fname")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$docs = ['documents' => 'مدارک', 'resume' => 'سوابق', 'identity' => 'شناسایی هویت'];

function staffDocumentUploadDate(string $storedName): string
{
    if ($storedName === '') {
        return '';
    }
    $storageDirectory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin_documents');
    $filePath = $storageDirectory !== false ? realpath($storageDirectory . DIRECTORY_SEPARATOR . basename($storedName)) : false;
    if ($storageDirectory === false || $filePath === false || stripos($filePath, $storageDirectory . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
        return '';
    }
    $modifiedAt = filemtime($filePath);
    return $modifiedAt ? toJalali(date('Y-m-d H:i:s', $modifiedAt), 'Y/m/d H:i', 'fa') : '';
}
?>
<!doctype html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>مستندات همکاران | <?= __TITLE ?></title><?php include '../link.php'; ?>
</head>

<body class="main-body leftmenu">
    <div class="page"><?php include '../menu.php';
                        include '../header.php'; ?><div class="main-content side-content pt-0">
            <div class="container-fluid">
                <div class="inner-body">
                    <div class="page-header">
                        <div>
                            <h2 class="main-content-title tx-24">مستندات همکاران</h2>
                            <p class="text-muted">مدارک، سوابق و فایل‌های شناسایی هویت</p>
                        </div>
                    </div>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>همکار</th>
                                            <th>سمت</th><?php foreach ($docs as $label): ?><th><?= $label ?></th><?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $i => $row): ?><tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars(trim($row['fname'] . ' ' . $row['lname'])) ?><small class="d-block text-muted"><?= htmlspecialchars($row['username']) ?></small></td>
                                                <td><?= htmlspecialchars($row['role_title'] ?: '-') ?></td>
                                                <?php foreach ($docs as $type => $label):
                                                    $column = $type === 'documents' ? 'document_file' : ($type === 'resume' ? 'resume_file' : 'identity_file');
                                                    $uploadDate = !empty($row[$column]) ? staffDocumentUploadDate((string)$row[$column]) : '';
                                                ?><td><?php if (!empty($row[$column])): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= url('admin/employ/download-document.php?user=' . rawurlencode($row['username']) . '&type=' . $type) ?>">مشاهده</a><?php if ($uploadDate): ?><small class="d-block text-muted mt-1">بارگذاری: <?= htmlspecialchars($uploadDate) ?></small><?php endif; ?><?php else: ?><span class="text-muted">ثبت نشده</span><?php endif; ?></td><?php endforeach; ?>
                                            </tr><?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><?php include '../../footers.php'; ?>
</body>

</html>
