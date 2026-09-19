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

//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

if (!permision('autodialer')){
    echo "دسترسی ندارید.";
    die();
}
// End SESSION HIJACKING
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}


// POST -> Redirect -> GET (prevents resubmission on refresh)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $actor = (string)($_SESSION['admin']['user'] ?? 'unknown');

    // ADD / EDIT (single modal)
    if (isset($_POST['blacklist_submit'])) {

        $mode = trim((string)($_POST['mode'] ?? 'add')); // add|edit
        $id = (int)($_POST['id'] ?? 0);

        $phone = trim((string)($_POST['phone'] ?? ''));
        $phone = preg_replace('/\s+/', '', $phone);
        $description = trim((string)($_POST['description'] ?? ''));

        if ($phone === '') {
            $_SESSION['flash_error'] = 'شماره الزامی است.';
            autodialerLog($actor, 'blacklist.' . $mode . '.validation_fail', 'blacklist');
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }

        if ($mode === 'edit' && $id <= 0) {
            $_SESSION['flash_error'] = 'شناسه نامعتبر است.';
            autodialerLog($actor, 'blacklist.edit.validation_fail ' . $phone, 'blacklist');
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Duplicate check (unique phone)
        $exclude = ($mode === 'edit') ? $id : null;
        if (autodialerBlacklistExists($phone, $exclude)) {
            $_SESSION['flash_error'] = 'این شماره قبلاً در بلک‌لیست ثبت شده است.';
            autodialerLog($actor, 'blacklist.' . $mode . '.duplicate ' . $phone, 'blacklist');
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }

        $ok = false;
        if ($mode === 'edit') {
            $ok = autodialerBlacklistUpdate($id, $phone, $description);
        } else {
            $ok = autodialerBlacklistAdd($phone, $description, $actor);
        }

        if ($ok) {
            $_SESSION['flash_success'] = ($mode === 'edit') ? 'ویرایش انجام شد.' : 'به بلک‌لیست اضافه شد.';
            autodialerLog($actor, 'blacklist.' . $mode . ' ' . $phone, 'blacklist');
        } else {
            $_SESSION['flash_error'] = ($mode === 'edit') ? 'خطا در ویرایش.' : 'خطا در افزودن.';
            autodialerLog($actor, 'blacklist.' . $mode . '.failed ' . $phone, 'blacklist');
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // DELETE
    if (isset($_POST['blacklist_delete_submit'])) {
        $id = (int)($_POST['id'] ?? 0);
        $phone = trim((string)($_POST['phone'] ?? ''));
        $phone = preg_replace('/\s+/', '', $phone);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'شناسه نامعتبر است.';
            autodialerLog($actor, 'blacklist.delete.validation_fail ' . $phone, 'blacklist');
        } else {
            $ok = autodialerBlacklistDelete($id);

            if ($ok) {
                $_SESSION['flash_success'] = 'حذف شد.';
                autodialerLog($actor, 'blacklist.delete ' . $phone, 'blacklist');
            } else {
                $_SESSION['flash_error'] = 'خطا در حذف.';
                autodialerLog($actor, 'blacklist.delete.failed ' . $phone, 'blacklist');
            }
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
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
    <!-- Title -->
    <title> blacklist |<?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->

<!-- Page -->
<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <!-- Main Content-->
    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <div class="row row-sm safe-call-menu">
                    <?php
                    $q = trim((string)($_GET['q'] ?? ''));
                    $rows = autodialerBlacklistList($q);
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
                            <div class="card-header d-flex flex-row col-12 flex-wrap">
                                <div class="card-title font-weight-bold fs-5 col-6 ">لیست سیاه autodialer</div>
                                <div class="mb-3 d-flex gap-2 col-6 flex-row-reverse ">
                                    <button type="button" class="btn btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalBlacklist"
                                            data-mode="add">
                                        افزودن به بلک‌ لیست
                                    </button>
                                </div>
                                <p class="col-12">شماره هایی که نباید با آنها تماس گرفته شود را با دلیل اینجا قرار دهید.</p>
                                <p class="col-12 bg-danger badge-pill text-white"> خیلی مهم :** حتما اگر شماره جدیدی اضافه کردین گروه های مربوطه به اون شماره را آپدیت کنید</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                        <thead>
                                        <tr>
                                            <th style="width:60px;">#</th>
                                            <th>شماره</th>
                                            <th>توضیحات</th>
                                            <th>ایجاد کننده</th>
                                            <th>تاریخ</th>
                                            <th>عملیات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($rows)): ?>
                                            <?php $i=1; foreach ($rows as $r): ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td style="direction:ltr; text-align:left;">
                                                        <?php echo htmlspecialchars((string)($r['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($r['created_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <?php
                                                        $dt = (string)($r['created_at'] ?? '');
                                                        if ($dt !== '') {
                                                            $parts = explode(' ', $dt);
                                                            $datePart = $parts[0] ?? '';
                                                            $timePart = $parts[1] ?? '';

                                                            $d = explode('-', $datePart);
                                                            $jy = '';
                                                            if (count($d) === 3) {
                                                                [$gy, $gm, $gd] = array_map('intval', $d);
                                                                $j = gregorian_to_jalali($gy, $gm, $gd);
                                                                // $j is usually [jy, jm, jd]
                                                                if (is_array($j) && count($j) === 3) {
                                                                    $jy = sprintf('%04d-%02d-%02d', $j[0], $j[1], $j[2]);
                                                                }
                                                            }

                                                            echo htmlspecialchars(trim($jy ), ENT_QUOTES, 'UTF-8');
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-sm btn-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalBlacklist"
                                                                data-mode="edit"
                                                                data-id="<?php echo (int)$r['id']; ?>"
                                                                data-phone="<?php echo htmlspecialchars((string)$r['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-description="<?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                            ویرایش
                                                        </button>

                                                        <button type="button"
                                                                class="btn btn-sm btn-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalBlacklistDelete"
                                                                data-id="<?php echo (int)$r['id']; ?>"
                                                                data-phone="<?php echo htmlspecialchars((string)$r['phone'], ENT_QUOTES, 'UTF-8'); ?>">
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

                </div>

            </div>
        </div>
    </div>
    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

</div>
<div class="modal fade" id="modalBlacklist" tabindex="-1" aria-labelledby="modalBlacklistLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="">
            <input type="hidden" name="mode" id="blacklist_mode" value="add">
            <input type="hidden" name="id" id="blacklist_id" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBlacklistLabel">افزودن به بلک‌لیست</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label>شماره</label>
                        <input type="text" name="phone" id="blacklist_phone" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>توضیحات</label>
                        <textarea name="description" id="blacklist_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                    <button type="submit" name="blacklist_submit" value="1" id="blacklist_submit_btn" class="btn btn-primary">ثبت</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalBlacklistDelete" tabindex="-1" aria-labelledby="modalBlacklistDeleteLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="">
            <input type="hidden" name="id" id="blacklist_delete_id" value="">
            <input type="hidden" name="phone" id="blacklist_delete_phone" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBlacklistDeleteLabel">حذف از بلک‌لیست</h5>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    آیا از حذف این شماره مطمئن هستید؟
                    <div class="mt-2" style="direction:ltr; text-align:left;">
                        <strong id="blacklist_delete_phone_show"></strong>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">لغو</button>
                    <button type="submit" name="blacklist_delete_submit" value="1" class="btn btn-danger">حذف</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Add/Edit modal
        var modal = document.getElementById('modalBlacklist');
        if (modal) {
            modal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                var mode = (btn && btn.getAttribute('data-mode')) ? btn.getAttribute('data-mode') : 'add';

                document.getElementById('blacklist_mode').value = mode;

                var title = document.getElementById('modalBlacklistLabel');
                var submitBtn = document.getElementById('blacklist_submit_btn');

                if (mode === 'edit') {
                    document.getElementById('blacklist_id').value = btn.getAttribute('data-id') || '';
                    document.getElementById('blacklist_phone').value = btn.getAttribute('data-phone') || '';
                    document.getElementById('blacklist_description').value = btn.getAttribute('data-description') || '';

                    title.textContent = 'ویرایش بلک‌لیست';
                    submitBtn.textContent = 'ذخیره تغییرات';
                } else {
                    document.getElementById('blacklist_id').value = '';
                    document.getElementById('blacklist_phone').value = '';
                    document.getElementById('blacklist_description').value = '';

                    title.textContent = 'افزودن به بلک‌لیست';
                    submitBtn.textContent = 'ثبت';
                }
            });
        }

        // Delete modal
        var delModal = document.getElementById('modalBlacklistDelete');
        if (delModal) {
            delModal.addEventListener('show.bs.modal', function (event) {
                var btn = event.relatedTarget;
                var id = btn.getAttribute('data-id') || '';
                var phone = btn.getAttribute('data-phone') || '';

                document.getElementById('blacklist_delete_id').value = id;
                document.getElementById('blacklist_delete_phone').value = phone;
                document.getElementById('blacklist_delete_phone_show').textContent = phone;
            });
        }

    });
</script>


</body>
</html>
