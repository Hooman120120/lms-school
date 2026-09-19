<?php
include_once '../dbconfig.php';

if (!isset($_GET['fileChat'])) {
    die("فایلی مشخص نشده");
}
$id = intval($_GET['fileChat']);
$fetch = 'SELECT * FROM teacher_ticket_messages WHERE id = ?';
$stmt = $conn->prepare($fetch);
$stmt->execute([$id]);
$message = $stmt->fetch(PDO::FETCH_OBJ);

$file = $_SERVER['DOCUMENT_ROOT'] . $message->file_path;
$path = realpath($file);

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

?>