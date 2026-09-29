<?php
// Shared by document forms; no database operations or automatic output.
function DocumentFormCsrfToken(): string
{
    if (empty($_SESSION['document_form_csrf'])) {
        $_SESSION['document_form_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['document_form_csrf'];
}

function DocumentRequireCsrf(): void
{
    $token = $_POST['document_csrf'] ?? null;
    if (!is_string($token) || !hash_equals(DocumentFormCsrfToken(), $token)) {
        http_response_code(403);
        exit('درخواست نامعتبر است. صفحه را تازه‌سازی کنید و دوباره تلاش کنید.');
    }
}

function DocumentSaveAttachment(string $folder): string
{
    if (!in_array($folder, ['announcements', 'special_letters'], true)) {
        throw new InvalidArgumentException('Invalid attachment folder.');
    }
    $file = $_FILES['attachment'] ?? null;
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return '';
    if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
        || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('فایل پیوست بارگذاری نشد.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size <= 0 || $size > 4 * 1024 * 1024) {
        throw new RuntimeException('حجم فایل باید بیشتر از صفر و حداکثر ۴ مگابایت باشد.');
    }
    $allowed = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    ];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$extension]) || $allowed[$extension] !== $mime) {
        throw new RuntimeException('فقط فایل PDF یا تصویر معتبر JPG، PNG و WEBP مجاز است.');
    }
    if ($extension !== 'pdf' && @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('فایل تصویر معتبر نیست.');
    }
    $relativeDirectory = 'uploads/' . $folder . '/';
    $directory = dirname(__DIR__, 2) . '/' . $relativeDirectory;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('امکان ایجاد پوشه پیوست وجود ندارد.');
    }
    $filename = bin2hex(random_bytes(24)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . $filename)) {
        throw new RuntimeException('ذخیره فایل پیوست انجام نشد.');
    }
    return $relativeDirectory . $filename;
}
