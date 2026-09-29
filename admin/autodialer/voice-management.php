<?php
include_once '../../dbconfig.php';
include_once '../../inc/function_login.php';
include_once '../../inc/function.php';
include_once '../../inc/jdf.php';
include_once 'autodialer_functions.php';

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

if (!AdminCanSee('public-relations.safe-call.autodialer.voices')) {
    echo "دسترسی ندارید.";
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_voice') {
    AdminRequire('public-relations.safe-call.autodialer.voices.create');

    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    $error = null;

    // For Fix A, we require the file upload (because we need Simotel to generate the final filename)
    if (empty($_FILES['voice_file']['name']) || !is_uploaded_file($_FILES['voice_file']['tmp_name'])) {
        $error = 'فایل انتخاب نشده است.';
    }

    if ($error === null) {

        $tmp  = (string)$_FILES['voice_file']['tmp_name'];
        $orig = (string)$_FILES['voice_file']['name'];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['wav', 'mp3'];

        if (!in_array($ext, $allowed, true)) {
            $error = 'فرمت فایل مجاز نیست (wav/mp3).';
        } elseif ($name === '') {
            $error = 'نام الزامی است.';
        } else {

            // 1) Upload to Simotel FIRST to get final sanitized filename
            $up = autodialerAnnouncementsUpload($tmp, $orig);

            if (empty($up['ok']) || empty($up['filename'])) {
                $msg = 'آپلود به سیموتل ناموفق بود. کد: ' . ($up['httpcode'] ?? 0);
                if (!empty($up['error'])) $msg .= ' | ' . $up['error'];

                autodialerLog($_SESSION['admin']['user'], 'upload failed ' . $name, 'announcement');
                $_SESSION['flash_error'] = $msg;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            $simotelFilename = (string)$up['filename'];

            // 2) Save local copy using the SAME Simotel filename (so admin listen works)
            $uploadDir = __DIR__ . '/announcements/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

            if (!move_uploaded_file($tmp, $uploadDir . $simotelFilename)) {
                $msg = 'ذخیره فایل در سرور سایت ناموفق بود.';
                autodialerLog($_SESSION['admin']['user'], 'local save failed ' . $name, 'announcement');
                $_SESSION['flash_error'] = $msg;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            // 3) Add announcement record in Simotel using the returned filename
            $res = autodialerAnnouncementsAdd($name, $simotelFilename, $desc);

            if (!empty($res['ok'])) {
                autodialerLog($_SESSION['admin']['user'], 'add ' . $name, 'announcement');

                $_SESSION['flash_success'] = 'پیام صوتی با موفقیت ثبت شد.';
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $msg = 'خطا در ثبت پیام صوتی. کد: ' . ($res['httpcode'] ?? 0);
                if (!empty($res['error'])) $msg .= ' | ' . $res['error'];

                autodialerLog($_SESSION['admin']['user'], 'add failed ' . $name, 'announcement');

                $_SESSION['flash_error'] = $msg;
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    // validation error
    $_SESSION['flash_error'] = $error;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_voice') {
    AdminRequire('public-relations.safe-call.autodialer.voices.delete');

    $id = trim((string)($_POST['id'] ?? ''));
    $filename = trim((string)($_POST['filename'] ?? ''));

    if ($id === '') {
        $_SESSION['flash_error'] = 'شناسه نامعتبر است.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $res = autodialerAnnouncementsRemove($id);

    if (!empty($res['ok'])) {

        // delete local file too (best effort)
        if ($filename !== '' &&
            strpos($filename, '..') === false &&
            strpos($filename, '/') === false &&
            strpos($filename, '\\') === false
        ) {
            $localPath = __DIR__ . '/announcements/' . $filename;
            if (is_file($localPath)) {
                @unlink($localPath);
            }
        }

        autodialerLog($_SESSION['admin']['user'], 'delete ' . $id, 'announcement');
        $_SESSION['flash_success'] = 'با موفقیت حذف شد.';
    } else {
        $msg = 'خطا در حذف. کد: ' . ($res['httpcode'] ?? 0);
        if (!empty($res['error'])) $msg .= ' | ' . $res['error'];
        autodialerLog($_SESSION['admin']['user'], 'delete failed ' . $id, 'announcement');
        $_SESSION['flash_error'] = $msg;
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// Optional server-side search by querystring (?q=...)
$q = trim((string)($_GET['q'] ?? ''));

// Fetch announcements (voices)
$voices = autodialerAnnouncementsSearch($q);
if (!is_array($voices)) $voices = [];
usort($voices, function ($a, $b) {
    $da = (string)($a->date ?? '');
    $db = (string)($b->date ?? '');

    $ta = strtotime($da) ?: 0;
    $tb = strtotime($db) ?: 0;

    return $tb <=> $ta; // DESC
});
include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>مدیریت پیام‌های صوتی | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">


<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm safe-call-menu">
                    <?php if (!empty($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_success']); ?>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_error']); ?>
                    <?php endif; ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap align-items-center">
                                <div class="card-title mb-0 fs-5 font-weight-bold col-12 col-md-6 pb-2">
                                    لیست پیام‌های صوتی (Announcements)
                                </div>
                                <div class="col-12 col-md-6 text-md-left">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVoiceModal">
                                        افزودن پیام صوتی
                                    </button>
                                </div>
                                <p class="col-12">فایل های صوتی که برای campaign ها قرار گرفته میشود را اینجا آپلود کنید.</p>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>نام</th>
                                            <th>توضیحات</th>
                                            <th>تاریخ</th>
                                            <th>پخش / دانلود / اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($voices)): ?>
                                            <?php $i = 1; foreach ($voices as $v):
                                                $id = (string)($v->_id ?? ($v->id ?? ''));
                                                $name = (string)($v->name ?? '');
                                                $desc = (string)($v->description ?? ($v->desc ?? ''));
                                                $createdAt = (string)($v->date ?? ($v->date ?? ''));
                                                $filename = (string)($v->filename ?? $v->path ?? '');
                                                $localPath = __DIR__ . '/announcements/' . $filename;
                                                $hasLocal  = ($filename !== '' && is_file($localPath));
                                                ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td class="v-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="v-desc"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td class="v-date"><?php echo htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php
                                                    $audioUrl = 'voice_stream.php?f=' . urlencode($filename);
                                                    ?>
                                                    <td>
                                                        <?php if ($filename === ''): ?>
                                                            <span class="badge bg-secondary">بدون فایل</span>
                                                        <?php elseif (!$hasLocal): ?>
                                                        <div class="d-flex flex-row  justify-content-around ">
                                                            <div class="mt-2 text-muted small">
                                                                فایل برای پخش در دسترس نیست
                                                            </div>
                                                            <div class="d-flex align-items-center justify-items-center">
                                                                <form method="post" onsubmit="return confirm('حذف شود؟ این عملیات قابل بازگشت نیست.');" class="d-inline">
                                                                    <input type="hidden" name="action" value="delete_voice">
                                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <?php else: ?>

                                                            <?php $audioUrl = 'voice_stream.php?f=' . urlencode($filename); ?>
                                                            <div class="d-flex flex-row justify-content-around">
                                                                <div>
                                                                <audio controls preload="none" style="max-width:400px">
                                                                    <source src="<?php echo htmlspecialchars($audioUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                                </audio>
                                                                </div>
                                                                <div class="d-flex align-items-center justify-items-center">
                                                                <form method="post" onsubmit="return confirm('حذف شود؟ این عملیات قابل بازگشت نیست.');" class="d-inline">
                                                                    <input type="hidden" name="action" value="delete_voice">
                                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                                                </form>
                                                                </div>
                                                            </div>


                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">موردی یافت نشد</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                        </div>
                    </div>

                </div><!-- row -->
            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div>

<div class="modal fade" id="addVoiceModal" tabindex="-1" aria-labelledby="addVoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVoiceModalLabel">افزودن پیام صوتی</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="add_voice">

                <div class="mb-3">
                    <label class="form-label">نام</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">توضیحات</label>
                    <input type="text" name="description" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">آپلود فایل </label>
                    <input type="file" name="voice_file" class="form-control" accept=".wav,.mp3">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <button type="submit" class="btn btn-primary">ثبت</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
