<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';

if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
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
if ((int)($_SESSION['admin']['adminrole'] ?? -1) !== 3) {
    http_response_code(403);
    exit(m('perm.teacher_only_path'));
}

RequireTeacherPermission('teacher_perm_content', url('admin/course/Active-course.php'));

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$contentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = (string)($_GET['type'] ?? '');
if (!$classId || !$contentId || !in_array($type, ['movie', 'addon'], true)) {
    http_response_code(400);
    exit('درخواست محتوای آموزشی نامعتبر است.');
}
if (!TeacherHasClassAccess((int)$classId)) {
    http_response_code(403);
    exit('شما به محتوای این کلاس دسترسی ندارید.');
}

if ($type === 'movie') {
    $stmt = $conn->prepare('
        SELECT m.linkfilm AS content_url
        FROM class_movie cm
        INNER JOIN classes c ON c.id = cm.class_id
        INNER JOIN dbmovie m ON m.id = cm.movie_id AND m.codecourse = c.codecourse
        WHERE cm.class_id = ? AND cm.movie_id = ?
        LIMIT 1
    ');
} else {
    $stmt = $conn->prepare('
        SELECT a.linkdoc AS content_url
        FROM addon_class ac
        INNER JOIN classes c ON c.id = ac.class_id
        INNER JOIN dbaddon a ON a.id = ac.addon_id AND a.codecourse = c.codecourse
        WHERE ac.class_id = ? AND ac.addon_id = ?
        LIMIT 1
    ');
}
$stmt->execute([(int)$classId, (int)$contentId]);
$contentUrl = trim((string)$stmt->fetchColumn());

if ($contentUrl === '' || !filter_var($contentUrl, FILTER_VALIDATE_URL)) {
    http_response_code(404);
    exit('لینک محتوای آموزشی معتبر نیست یا محتوا به این کلاس تخصیص داده نشده است.');
}
$scheme = strtolower((string)parse_url($contentUrl, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'], true)) {
    http_response_code(400);
    exit('نشانی محتوای آموزشی مجاز نیست.');
}

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Location: ' . $contentUrl, true, 302);
exit;
