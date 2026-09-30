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

// Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

if (!AdminCanSee('public-relations.safe-call.autodialer.contacts')) {
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


function autodialerApiOk(string $apiOut): bool
{
    $decoded = json_decode($apiOut, true);
    if (is_array($decoded)) {
        if (isset($decoded['success'])) return (bool)$decoded['success'];
        if (isset($decoded['ok'])) return (bool)$decoded['ok'];
        if (isset($decoded['status']) && in_array($decoded['status'], ['ok','success'], true)) return true;
        // if it's valid JSON but no known flag, assume ok (adjust if your API differs)
        return true;
    }
    return trim($apiOut) !== '';
}

// ---------------------------
// Handle REMOVE contact POST
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_contact_submit'])) {
    AdminRequire('public-relations.safe-call.autodialer.contacts.delete');

    $id     = trim((string)($_POST['_id'] ?? ''));
    $number = trim((string)($_POST['number'] ?? '')); // for logging context
    $numberClean = preg_replace('/\s+/', '', $number);

    $actor = (string)($_SESSION['admin']['user'] ?? 'unknown');

    if ($id === '') {
        $_SESSION['flash_error'] = 'شناسه مخاطب نامعتبر است.';
        autodialerLogLocal($actor, 'contact.remove.validation_fail ' . $numberClean, 'contact');
    } else {
        ob_start();
        autodialerContactsRemove($id); // your function (echoes)
        $apiOut = ob_get_clean();

        $ok = autodialerApiOk($apiOut);

        if ($ok) {
            $_SESSION['flash_success'] = 'مخاطب حذف شد.';
            autodialerlog($actor, 'contact.remove ' . $numberClean, 'contact');
        } else {
            $_SESSION['flash_error'] = 'خطا در حذف مخاطب.';
            autodialerlog($actor, 'contact.remove.failed ' . $numberClean, 'contact');
        }
    }

    // PRG
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// ---------------------------
// Handle ADD / EDIT contact POST
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    AdminRequire('public-relations.safe-call.autodialer.contacts.create');

    $mode = trim((string)($_POST['mode'] ?? 'add')); // add|edit
    $id   = trim((string)($_POST['_id'] ?? ''));

    $name        = trim((string)($_POST['name'] ?? ''));
    $number      = trim((string)($_POST['number'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    $actor = (string)($_SESSION['admin']['user'] ?? 'unknown');
    $numberClean = preg_replace('/\s+/', '', $number);

    if ($name === '' || $number === '' || ($mode === 'edit' && $id === '')) {
        $_SESSION['flash_error'] = 'اطلاعات وارد شده ناقص است.';
        autodialerlog($actor, 'contact.' . $mode . '.validation_fail ' . $numberClean, 'contact');
    } else {
        ob_start();

        if ($mode === 'edit') {
            autodialerContactsUpdate($id, $name, $number, $description); // echoes
        } else {
            autodialerContactsAdd($name, $number, $description); // echoes
        }

        $apiOut = ob_get_clean();
        $ok = autodialerApiOk($apiOut);

        if ($ok) {
            $_SESSION['flash_success'] = ($mode === 'edit') ? 'مخاطب ویرایش شد.' : 'مخاطب اضافه شد.';
            autodialerlog($actor, 'contact.' . $mode . ' ' . $numberClean, 'contact');
        } else {
            $_SESSION['flash_error'] = ($mode === 'edit') ? 'خطا در ویرایش مخاطب.' : 'خطا در افزودن مخاطب.';
            autodialerlog($actor, 'contact.' . $mode . '.failed ' . $numberClean, 'contact');
        }
    }

    // PRG
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

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
    <title> contacts |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">

<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm safe-call-menu">
                    <?php
                    // You can increase count if you want
                    $contacts = autodialerContactsSearch(0, 2000);
                    ?>

                    <div class="col-12 mb-3">
                        <?php if (!empty($_SESSION['flash_success'])): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php unset($_SESSION['flash_success']); ?>
                        <?php endif; ?>

                        <?php if (!empty($_SESSION['flash_error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php unset($_SESSION['flash_error']); ?>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <div class="card">

                            <div class="card-header d-flex flex-row justify-content-between align-content-center col-12 flex-wrap">
                                <div class="card-title mb-0 col-6 font-weight-bold fs-5">لیست مخاطبین</div>
                                <div class="col-6 d-flex flex-row-reverse">
                                    <button type="button" class="btn btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalAddContact">
                                        افزودن مخاطب
                                    </button>
                                </div>
                                <p class="col-12 mb-0 mt-2">برای تغییرات در شماره هایی که برای تست قرار داده میشود.</p>

                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>نام</th>
                                            <th>شماره</th>
                                            <th>توضیحات</th>
                                            <th>تاریخ</th>
                                            <th>عملیات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($contacts)): ?>
                                            <?php $i=1; foreach ($contacts as $c): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td><?php echo htmlspecialchars((string)($c->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($c->number ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($c->description ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($c->date ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-sm btn-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalAddContact"
                                                                data-mode="edit"
                                                                data-id="<?php echo htmlspecialchars((string)($c->_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-name="<?php echo htmlspecialchars((string)($c->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-number="<?php echo htmlspecialchars((string)($c->number ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-description="<?php echo htmlspecialchars((string)($c->description ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                            ویرایش
                                                        </button>

                                                        <button type="button"
                                                                class="btn btn-sm btn-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalRemoveContact"
                                                                data-id="<?php echo htmlspecialchars((string)($c->_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-number="<?php echo htmlspecialchars((string)($c->number ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-name="<?php echo htmlspecialchars((string)($c->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                            حذف
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">موردی یافت نشد</td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- /row -->

            </div>
        </div>
    </div>

    <?php include '../../footers.php'; ?>
</div><!-- /page -->


<!-- Remove Contact Modal -->
<div class="modal fade" id="modalRemoveContact" tabindex="-1" aria-labelledby="modalRemoveContactLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="">
            <input type="hidden" name="_id" id="remove_contact_id" value="">
            <input type="hidden" name="number" id="remove_contact_number" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRemoveContactLabel">حذف مخاطب</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    آیا از حذف مخاطب زیر مطمئن هستید؟
                    <div class="mt-2 d-flex justify-content-between">
                        <strong id="remove_contact_name_show"></strong>
                        <div style="direction:ltr; text-align:left;" id="remove_contact_number_show"></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                    <button type="submit" name="remove_contact_submit" value="1" class="btn btn-danger">حذف</button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Add/Edit Contact Modal -->
<div class="modal fade" id="modalAddContact" tabindex="-1" aria-labelledby="modalAddContactLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="">
            <input type="hidden" name="mode" id="contact_mode" value="add">
            <input type="hidden" name="_id" id="contact_id" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddContactLabel">افزودن مخاطب</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>نام</label>
                        <input type="text" name="name" id="contact_name" class="form-control" required>
                    </div>

                    <div class="form-group mb-2">
                        <label>شماره</label>
                        <input type="text" name="number" id="contact_number" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" id="contact_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="submit" name="contact_submit" value="1" id="contact_submit_btn" class="btn btn-primary">ثبت</button>
                </div>
            </div>
        </form>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Add/Edit modal: fill values if edit button clicked
        const modalAdd = document.getElementById('modalAddContact');
        if (modalAdd) {
            modalAdd.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                const mode = btn?.getAttribute('data-mode') || 'add';

                const id = btn?.getAttribute('data-id') || '';
                const name = btn?.getAttribute('data-name') || '';
                const number = btn?.getAttribute('data-number') || '';
                const description = btn?.getAttribute('data-description') || '';

                document.getElementById('contact_mode').value = mode;
                document.getElementById('contact_id').value = (mode === 'edit') ? id : '';
                document.getElementById('contact_name').value = (mode === 'edit') ? name : '';
                document.getElementById('contact_number').value = (mode === 'edit') ? number : '';
                document.getElementById('contact_description').value = (mode === 'edit') ? description : '';

                document.getElementById('modalAddContactLabel').textContent = (mode === 'edit') ? 'ویرایش مخاطب' : 'افزودن مخاطب';
                document.getElementById('contact_submit_btn').textContent = (mode === 'edit') ? 'ذخیره' : 'ثبت';
            });

            // When closed, reset to add mode (clean UX)
            modalAdd.addEventListener('hidden.bs.modal', function () {
                document.getElementById('contact_mode').value = 'add';
                document.getElementById('contact_id').value = '';
                document.getElementById('contact_name').value = '';
                document.getElementById('contact_number').value = '';
                document.getElementById('contact_description').value = '';
                document.getElementById('modalAddContactLabel').textContent = 'افزودن مخاطب';
                document.getElementById('contact_submit_btn').textContent = 'ثبت';
            });
        }

        // Remove modal: fill hidden inputs for POST
        const modalRemove = document.getElementById('modalRemoveContact');
        if (modalRemove) {
            modalRemove.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                const id = btn?.getAttribute('data-id') || '';
                const number = btn?.getAttribute('data-number') || '';
                const name = btn?.getAttribute('data-name') || '';

                document.getElementById('remove_contact_id').value = id;
                document.getElementById('remove_contact_number').value = number;

                document.getElementById('remove_contact_name_show').textContent = name;
                document.getElementById('remove_contact_number_show').textContent = number;
            });
        }

    });
</script>

</body>
</html>
