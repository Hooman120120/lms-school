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
    exit(m('perm.teacher_only_page'));
}

RequireTeacherPermission('teacher_perm_content', url('admin/course/Active-course.php'));

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
if (!$classId || !TeacherHasClassAccess((int)$classId)) {
    http_response_code(403);
    exit('شما به این کلاس دسترسی ندارید.');
}

$classStmt = $conn->prepare('
    SELECT c.id, c.name, c.codecourse, cr.coursename
    FROM classes c
    INNER JOIN codtbl_course cr ON cr.codecourse = c.codecourse
    WHERE c.id = ?
    LIMIT 1
');
$classStmt->execute([(int)$classId]);
$class = $classStmt->fetch(PDO::FETCH_OBJ);
if (!$class) {
    http_response_code(404);
    exit('کلاس یافت نشد.');
}

$darsStmt = $conn->prepare('SELECT d.id, d.dars FROM class_dars cd INNER JOIN dbdars d ON d.id = cd.dars_id WHERE cd.class_id = ? AND d.codecourse = ? ORDER BY d.id ASC');
$darsStmt->execute([(int)$classId, (int)$class->codecourse]);
$darsRows = $darsStmt->fetchAll(PDO::FETCH_OBJ);
$assignedDarsIds = TeacherAssignedDarsIds((int)$classId);
if ($assignedDarsIds !== []) {
    $darsRows = array_values(array_filter($darsRows, static function ($dars) use ($assignedDarsIds) {
        return in_array((int)$dars->id, $assignedDarsIds, true);
    }));
}

$lessonStmt = $conn->prepare('
    SELECT DISTINCT l.id, l.dars_id, l.name
    FROM dblesson l
    LEFT JOIN class_lesson cl ON cl.lesson_id = l.id AND cl.class_id = ?
    LEFT JOIN dbmovie m ON m.lesson_id = l.id
    LEFT JOIN class_movie cm ON cm.movie_id = m.id AND cm.class_id = ?
    LEFT JOIN dbaddon a ON a.lesson_id = l.id
    LEFT JOIN addon_class ac ON ac.addon_id = a.id AND ac.class_id = ?
    WHERE l.codecourse = ?
      AND (cl.class_id IS NOT NULL OR cm.class_id IS NOT NULL OR ac.class_id IS NOT NULL)
    ORDER BY l.id ASC
');
$lessonStmt->execute([(int)$classId, (int)$classId, (int)$classId, (int)$class->codecourse]);
$lessonRows = $lessonStmt->fetchAll(PDO::FETCH_OBJ);
$lessonsByDars = [];
foreach ($lessonRows as $lesson) {
    $lessonsByDars[(string)$lesson->dars_id][] = [
        'id' => (int)$lesson->id,
        'name' => (string)$lesson->name,
    ];
}

$message = '';
$error = '';
$videoMaxBytes = 500 * 1024 * 1024;
$documentMaxBytes = 25 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['token'], $_POST['token']) || !hash_equals((string)$_SESSION['token'], (string)$_POST['token'])) {
            throw new RuntimeException('اعتبار فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.');
        }

        $contentKind = (string)($_POST['content_kind'] ?? '');
        $sourceType = (string)($_POST['source_type'] ?? '');
        $title = trim((string)($_POST['title'] ?? ''));
        $darsId = (int)($_POST['dars_id'] ?? 0);
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        if (!TeacherHasDarsAccess((int)$classId, $darsId)) {
            throw new RuntimeException('این درس به شما تخصیص داده نشده است.');
        }
        if (!in_array($contentKind, ['movie', 'addon'], true) || !in_array($sourceType, ['link', 'upload'], true)) {
            throw new RuntimeException('نوع محتوا یا روش ثبت نامعتبر است.');
        }
        $titleLimit = $contentKind === 'movie' ? 255 : 100;
        if ($title === '' || mb_strlen($title, 'UTF-8') > $titleLimit) {
            throw new RuntimeException('عنوان محتوا خالی یا بیش از حد طولانی است.');
        }

        $validLessonStmt = $conn->prepare('
            SELECT 1
            FROM dblesson l
            INNER JOIN dbdars d ON d.id = l.dars_id AND d.codecourse = l.codecourse
            INNER JOIN class_dars cd ON cd.dars_id = d.id AND cd.class_id = ?
            WHERE l.id = ? AND l.dars_id = ? AND l.codecourse = ?
            LIMIT 1
        ');
        $validLessonStmt->execute([(int)$classId, $lessonId, $darsId, (int)$class->codecourse]);
        if (!$validLessonStmt->fetchColumn()) {
            throw new RuntimeException('درس یا سرفصل انتخاب‌شده معتبر نیست.');
        }

        $contentUrl = '';
        $contentType = '';
        $host = '';
        $storedPath = null;

        if ($sourceType === 'link') {
            $contentUrl = trim((string)($_POST['external_url'] ?? ''));
            if (!filter_var($contentUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('لینک واردشده معتبر نیست.');
            }
            $scheme = strtolower((string)parse_url($contentUrl, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new RuntimeException('فقط لینک‌های HTTP و HTTPS مجاز هستند.');
            }
            $linkedExtension = strtolower(pathinfo((string)parse_url($contentUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $linkedTypes = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'zip' => 'application/zip',
            ];
            $contentType = $contentKind === 'movie'
                ? 'video/mp4'
                : ($linkedTypes[$linkedExtension] ?? 'application/octet-stream');
            $host = mb_substr((string)parse_url($contentUrl, PHP_URL_HOST), 0, 50, 'UTF-8');
        } else {
            $upload = $_FILES['content_file'] ?? null;
            if (!$upload || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('فایل به‌درستی دریافت نشد.');
            }

            $maxBytes = $contentKind === 'movie' ? $videoMaxBytes : $documentMaxBytes;
            if ((int)$upload['size'] <= 0 || (int)$upload['size'] > $maxBytes) {
                throw new RuntimeException($contentKind === 'movie'
                    ? 'حجم فیلم باید حداکثر ۵۰۰ مگابایت باشد.'
                    : 'حجم جزوه باید حداکثر ۲۵ مگابایت باشد.');
            }

            $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
            $allowed = $contentKind === 'movie'
                ? [
                    'mp4' => ['video/mp4'],
                    'webm' => ['video/webm'],
                    'mov' => ['video/quicktime'],
                    'mkv' => ['video/x-matroska'],
                ]
                : [
                    'pdf' => ['application/pdf'],
                    'doc' => ['application/msword'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    'ppt' => ['application/vnd.ms-powerpoint'],
                    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                    'jpg' => ['image/jpeg'],
                    'jpeg' => ['image/jpeg'],
                    'png' => ['image/png'],
                    'zip' => ['application/zip', 'application/x-zip-compressed'],
                ];
            if (!isset($allowed[$extension])) {
                throw new RuntimeException('پسوند فایل انتخاب‌شده مجاز نیست.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file((string)$upload['tmp_name']);
            if (!in_array($mime, $allowed[$extension], true)) {
                throw new RuntimeException('نوع واقعی فایل با پسوند آن مطابقت ندارد.');
            }

            $relativeDirectory = 'uploads/teacher-content/' . (int)$class->codecourse;
            $absoluteDirectory = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
                throw new RuntimeException('امکان ایجاد پوشه بارگذاری وجود ندارد.');
            }
            $fileName = bin2hex(random_bytes(20)) . '.' . $extension;
            $storedPath = $absoluteDirectory . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file((string)$upload['tmp_name'], $storedPath)) {
                throw new RuntimeException('ذخیره فایل روی سرور انجام نشد.');
            }
            $contentUrl = url($relativeDirectory . '/' . $fileName);
            $contentType = $mime;
            $host = 'local-upload';
        }

        $conn->beginTransaction();
        $classLessonCheck = $conn->prepare('SELECT 1 FROM class_lesson WHERE class_id = ? AND lesson_id = ? LIMIT 1');
        $classLessonCheck->execute([(int)$classId, $lessonId]);
        if (!$classLessonCheck->fetchColumn()) {
            $classLessonInsert = $conn->prepare('INSERT INTO class_lesson (class_id, lesson_id) VALUES (?, ?)');
            $classLessonInsert->execute([(int)$classId, $lessonId]);
        }

        $nowDate = date('Y-m-d');
        $nowTime = date('H:i:s');
        $username = (string)$_SESSION['admin']['user'];
        if ($contentKind === 'movie') {
            $insert = $conn->prepare('
                INSERT INTO dbmovie
                    (lesson_id, dars_id, codecourse, filmname, typefilm, linkfilm, host, useradder, datecreate, timecreate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insert->execute([$lessonId, $darsId, (int)$class->codecourse, $title, $contentType, $contentUrl, $host, $username, $nowDate, $nowTime]);
            $contentId = (int)$conn->lastInsertId();
            $map = $conn->prepare('INSERT INTO class_movie (class_id, movie_id) VALUES (?, ?)');
        } else {
            $insert = $conn->prepare('
                INSERT INTO dbaddon
                    (codecourse, dars_id, lesson_id, docname, typedoc, linkdoc, host, useradder, datecreate, timecreate)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insert->execute([(int)$class->codecourse, $darsId, $lessonId, $title, $contentType, $contentUrl, $host, $username, $nowDate, $nowTime]);
            $contentId = (int)$conn->lastInsertId();
            $map = $conn->prepare('INSERT INTO addon_class (class_id, addon_id) VALUES (?, ?)');
        }
        $map->execute([(int)$classId, $contentId]);
        $conn->commit();
        $storedPath = null;
        $message = $contentKind === 'movie' ? 'فیلم با موفقیت ثبت شد.' : 'جزوه با موفقیت ثبت شد.';
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if (!empty($storedPath) && is_file($storedPath)) {
            unlink($storedPath);
        }
        $error = $exception->getMessage();
    }
}

$token = bin2hex(random_bytes(32));
$_SESSION['token'] = $token;
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>افزودن فیلم یا جزوه | <?= __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="page-header">
                    <div>
                        <h2 class="main-content-title tx-24">افزودن فیلم یا جزوه</h2>
                        <p class="text-muted mb-0">
                            پایه <?= htmlspecialchars($class->coursename) ?> — کلاس <?= htmlspecialchars($class->name) ?>
                        </p>
                    </div>
                    <a class="btn btn-outline-primary" href="class-detail.php?class_id=<?= (int)$classId ?>">بازگشت به محتوای کلاس</a>
                </div>

                <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <div class="card custom-card">
                    <div class="card-body">
                        <?php if (!$darsRows || !$lessonRows): ?>
                            <div class="alert alert-warning mb-0">
                                ابتدا برای این پایه درس و سرفصل تعریف کنید؛ سپس امکان افزودن محتوا فعال می‌شود.
                            </div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data" data-parsley-validate="">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">نوع محتوا</label>
                                        <select class="form-control" name="content_kind" id="content-kind" required>
                                            <option value="movie">فیلم</option>
                                            <option value="addon">جزوه</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">عنوان محتوا</label>
                                        <input class="form-control" name="title" required maxlength="255">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">درس</label>
                                        <select class="form-control" name="dars_id" id="upload-dars" required>
                                            <option value="">انتخاب درس</option>
                                            <?php foreach ($darsRows as $dars): ?>
                                                <option value="<?= (int)$dars->id ?>"><?= htmlspecialchars($dars->dars) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">سرفصل</label>
                                        <select class="form-control" name="lesson_id" id="upload-lesson" required disabled>
                                            <option value="">ابتدا درس را انتخاب کنید</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label d-block">روش افزودن محتوا</label>
                                    <label class="ml-4"><input type="radio" name="source_type" value="link" checked> ثبت لینک</label>
                                    <label><input type="radio" name="source_type" value="upload"> بارگذاری فایل</label>
                                </div>
                                <div id="link-source" class="mb-3">
                                    <label class="form-label">لینک مستقیم محتوا</label>
                                    <input type="url" class="form-control" name="external_url" placeholder="https://example.com/file">
                                </div>
                                <div id="file-source" class="mb-3 d-none">
                                    <label class="form-label">انتخاب فایل</label>
                                    <input type="file" class="form-control" name="content_file" id="content-file">
                                    <small class="text-muted" id="upload-help">فیلم: MP4، WebM، MOV یا MKV — حداکثر ۵۰۰ مگابایت</small>
                                </div>
                                <button class="btn btn-success" type="submit"><i class="fe fe-upload"></i> ثبت محتوا</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../footers.php'; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lessonsByDars = <?= json_encode($lessonsByDars, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const dars = document.getElementById('upload-dars');
    const lesson = document.getElementById('upload-lesson');
    const kind = document.getElementById('content-kind');
    const file = document.getElementById('content-file');
    const help = document.getElementById('upload-help');
    if (dars && lesson) {
        dars.addEventListener('change', function () {
            lesson.innerHTML = '<option value="">انتخاب سرفصل</option>';
            (lessonsByDars[this.value] || []).forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                lesson.appendChild(option);
            });
            lesson.disabled = !this.value;
        });
    }
    document.querySelectorAll('input[name="source_type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const upload = this.value === 'upload';
            document.getElementById('link-source').classList.toggle('d-none', upload);
            document.getElementById('file-source').classList.toggle('d-none', !upload);
        });
    });
    if (kind) {
        kind.addEventListener('change', function () {
            if (this.value === 'movie') {
                file.accept = '.mp4,.webm,.mov,.mkv';
                help.textContent = 'فیلم: MP4، WebM، MOV یا MKV — حداکثر ۵۰۰ مگابایت';
            } else {
                file.accept = '.pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.zip';
                help.textContent = 'جزوه: PDF، Word، PowerPoint، تصویر یا ZIP — حداکثر ۲۵ مگابایت';
            }
        });
        kind.dispatchEvent(new Event('change'));
    }
});
</script>
</body>
</html>
