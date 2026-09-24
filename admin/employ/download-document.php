<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (!isset($_SESSION['admin'])) {
    header('location: ' . url('admin/login.php'));
    exit;
}
if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
$session = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($session['session_id'] ?? null)) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    exit;
}
if (!Permision('permision_viewemploy') && !Permision('permission_staff_documents')) {
    http_response_code(403);
    exit('شما مجوز مشاهده مدارک پرسنلی را ندارید.');
}

$username = trim((string)($_GET['user'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$types = [
    'documents' => ['column' => 'document_file', 'label' => 'مدارک'],
    'resume' => ['column' => 'resume_file', 'label' => 'سوابق'],
    'identity' => ['column' => 'identity_file', 'label' => 'مدرک-هویتی'],
];
if ($username === '' || !isset($types[$type])) {
    http_response_code(400);
    exit('درخواست فایل نامعتبر است.');
}

$column = $types[$type]['column'];
$stmt = $conn->prepare("SELECT {$column} FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$storedName = basename((string)$stmt->fetchColumn());
if ($storedName === '') {
    http_response_code(404);
    exit('فایلی ثبت نشده است.');
}

$storageDirectory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin_documents');
$filePath = $storageDirectory !== false ? realpath($storageDirectory . DIRECTORY_SEPARATOR . $storedName) : false;
if ($filePath === false || $storageDirectory === false || strpos($filePath, $storageDirectory . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('فایل مورد نظر یافت نشد.');
}

$extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$downloadName = $types[$type]['label'] . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $username) . '.' . $extension;
$disposition = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) ? 'inline' : 'attachment';

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($filePath));
header("Content-Disposition: {$disposition}; filename=\"document.{$extension}\"; filename*=UTF-8''" . rawurlencode($downloadName));
readfile($filePath);
exit;
