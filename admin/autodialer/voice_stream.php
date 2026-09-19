<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (!isset($_SESSION['admin'])) { http_response_code(403); exit('Forbidden'); }
if (!permision('autodialer')) { http_response_code(403); exit('No access'); }

$filename = trim((string)($_GET['f'] ?? ''));

// strict validation: filename only
if (
    $filename === '' ||
    strpos($filename, '..') !== false ||
    strpos($filename, '/') !== false ||
    strpos($filename, '\\') !== false
) {
    http_response_code(400);
    exit('Bad filename');
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['wav', 'mp3'], true)) {
    http_response_code(400);
    exit('Bad type');
}

$path = __DIR__ . '/announcements/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$ctype = ($ext === 'mp3') ? 'audio/mpeg' : 'audio/wav';
header('Content-Type: ' . $ctype);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Accept-Ranges: bytes');

readfile($path);
exit;
