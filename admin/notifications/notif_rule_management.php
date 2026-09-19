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
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}
$schoolRoleKey = SchoolStaffRoleKey((string)$_SESSION['admin']['user']);
if (!Permision('permision_viewalert') && !in_array($schoolRoleKey, ['manager', 'general_deputy'], true)) {
    header('location: ' . url('admin/index.php'));
    die();
}
if (isset($_POST['save_rule'])) {
    $id = $_POST['id'] ?? null;
    $rule_name = trim($_POST['rule_name']);
    $description = trim($_POST['description']);
    $period_days = (int)$_POST['period_days'];
    $min_calls = $_POST['min_calls'] !== '' ? (int)$_POST['min_calls'] : null;
    $min_talk_time = $_POST['min_talk_time'] !== '' ? (int)$_POST['min_talk_time'] : null;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($active === 1) {
        $conn->exec("UPDATE notification_rules SET active = 0");
    }

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("
            UPDATE notification_rules 
            SET rule_name=?, description=?, period_days=?, min_calls=?, min_talk_time=?, active=? 
            WHERE id=?
        ");
        $stmt->execute([$rule_name, $description, $period_days, $min_calls, $min_talk_time, $active, $id]);
        $_SESSION['message'] = "✅ قانون با موفقیت بروزرسانی شد.";
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO notification_rules (rule_name, description, period_days, min_calls, min_talk_time, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$rule_name, $description, $period_days, $min_calls, $min_talk_time, $active]);
        $_SESSION['message'] = "✅ قانون جدید با موفقیت اضافه شد.";
    }

    header("Location: notif_rule_management.php");
    exit;
}


if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("UPDATE notification_rules SET active = 0 WHERE id=?")->execute([$id]);
    $_SESSION['message'] = "⚠️ قانون غیرفعال شد.";

    header("Location: notif_rule_management.php");
    exit;
}


if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    // Get current status
    $stmt = $conn->prepare("SELECT active FROM notification_rules WHERE id = ?");
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();

    // Flip it
    $newStatus = $currentStatus ? 0 : 1;
    if ($newStatus === 1) {
        $conn->exec("UPDATE notification_rules SET active = 0");
    }
    $stmt = $conn->prepare("UPDATE notification_rules SET active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    $_SESSION['message'] = $newStatus ? "✅ قانون فعال شد." : "❌ قانون غیرفعال شد.";

    header("Location: notif_rule_management.php");
    exit;
}


if (isset($_POST['delete_rule']) && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM notification_rules WHERE id = ?");
    $stmt->execute([$deleteId]);

    $_SESSION['message'] = "✅ قانون با موفقیت حذف شد.";

    header("Location: notif_rule_management.php");
    exit;
}


$rules = $conn->query("SELECT * FROM notification_rules ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$editRule = null;

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>کنترل قوانین اعلان تماس مشاوران | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; ?>
    <?php include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card custom-card shadow-sm">
                            <div class="card-body">

                                <div class="d-flex align-items-center mb-4" style="gap: 12px;">

                                    <h4 class="mb-0">
                                         مدیریت قوانین اعلان تماس مشاوران
                                    </h4>

                                    <span class="menu-info-trigger"
                                          data-tooltip="در این بخش قوانین ارزیابی تماس مشاوران با دانش‌آموزان پایه‌های تخصیص‌یافته مدیریت می‌شود. حداقل تعداد تماس و حداقل مدت مکالمه قانون فعال، مبنای تولید اعلان تماس مشاور خواهد بود."
                                          aria-label="راهنمای مدیریت قوانین نوتیفیکیشن">

                                        <i class="fe fe-info fs-4"></i>
                                    </span>

                                </div>

                                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#ruleModal">
                                    ➕ افزودن / ویرایش قانون
                                </button>


                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert alert-info"><?= htmlspecialchars($_SESSION['message']) ?></div>
                                    <?php unset($_SESSION['message']); ?>
                                <?php endif; ?>


                                <div class="modal fade" id="ruleModal" tabindex="-1" aria-labelledby="ruleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content shadow-lg" style="border-radius:18px!important; overflow:hidden; border:0;">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="ruleModalLabel"> افزودن یا ویرایش قانون نوتیفیکیشن</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                                            </div>

                                            <div class="modal-body " >

                                                <form  method="post" class="mb-5">
                                                    <input type="hidden" name="id" value="<?= $editRule['id'] ?? '' ?>">

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label>نام قانون *</label>
                                                            <input type="text" name="rule_name" class="form-control" required
                                                                   value="<?= htmlspecialchars($editRule['rule_name'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label>بازه زمانی (روز) *</label>
                                                            <input type="number" name="period_days" class="form-control" required min="1"
                                                                   value="<?= htmlspecialchars($editRule['period_days'] ?? '') ?>">
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label>توضیحات</label>
                                                        <textarea name="description" class="form-control"><?= htmlspecialchars($editRule['description'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label>حداقل تعداد تماس</label>
                                                            <input type="number" name="min_calls" class="form-control"
                                                                   value="<?= htmlspecialchars($editRule['min_calls'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label>حداقل زمان مکالمه (ثانیه)</label>
                                                            <input type="number" name="min_talk_time" class="form-control"
                                                                   value="<?= htmlspecialchars($editRule['min_talk_time'] ?? '') ?>">
                                                        </div>
                                                    </div>

                                                    <style>
                                                        .no-pseudo-label::before,
                                                        .no-pseudo-label::after {
                                                            content: none !important;
                                                            display: none !important;
                                                        }
                                                    </style>

                                                    <div class="mb-3 form-check form-switch no-pseudo">
                                                        <input class="form-check-input" type="checkbox" id="active" name="active"
                                                            <?= (isset($editRule['active']) && $editRule['active']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label no-pseudo-label" for="active">فعال باشد</label>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="submit" name="save_rule" class="btn btn-success">
                                                            <?= $editRule ? 'ذخیره تغییرات' : 'ثبت قانون جدید' ?>
                                                        </button>
                                                        <?php if ($editRule): ?>
                                                            <a href="notif_rule_management.php" class="btn btn-secondary">لغو و افزودن جدید</a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">بستن</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered" id="example1">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>نام قانون</th>
                                            <th>بازه (روز)</th>
                                            <th>حداقل تماس</th>
                                            <th>حداقل مکالمه</th>
                                            <th>وضعیت</th>
                                            <th>اقدامات</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($rules as $r): ?>
                                            <tr>
                                                <td><?= $r['id'] ?></td>
                                                <td><?= htmlspecialchars($r['rule_name']) ?></td>
                                                <td><?= $r['period_days'] ?></td>
                                                <td><?= $r['min_calls'] ?></td>
                                                <td><?= $r['min_talk_time'] ?></td>
                                                <td><?= $r['active'] ? '✅ فعال' : '❌ غیرفعال' ?></td>
                                                <td>
                                                    <a href="#"
                                                       class="btn btn-warning btn-sm edit-btn"
                                                       data-bs-toggle="modal"
                                                       data-bs-target="#ruleModal"
                                                       data-id="<?= $r['id'] ?>"
                                                       data-name="<?= htmlspecialchars($r['rule_name']) ?>"
                                                       data-description="<?= htmlspecialchars($r['description']) ?>"
                                                       data-period="<?= $r['period_days'] ?>"
                                                       data-min-calls="<?= $r['min_calls'] ?>"
                                                       data-min-talk="<?= $r['min_talk_time'] ?>"
                                                       data-active="<?= $r['active'] ?>">
                                                        ویرایش
                                                    </a>
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این قانون را حذف کنید؟');">
                                                        <input type="hidden" name="delete_rule" value="1">
                                                        <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                    </form>

                                                    <a href="?toggle=<?= $r['id'] ?>"
                                                       class="btn <?= $r['active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm"
                                                       onclick="return confirm('آیا می‌خواهید این قانون را <?= $r['active'] ? 'غیرفعال' : 'فعال' ?> کنید؟')">
                                                        <?= $r['active'] ? 'غیرفعال کردن' : 'فعال‌سازی' ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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

    <?php include '../../footers.php'; ?>
</div>

</body>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const editButtons = document.querySelectorAll(".edit-btn");

        editButtons.forEach(button => {
            button.addEventListener("click", function () {
                document.querySelector("input[name='id']").value = this.dataset.id;
                document.querySelector("input[name='rule_name']").value = this.dataset.name;
                document.querySelector("textarea[name='description']").value = this.dataset.description;
                document.querySelector("input[name='period_days']").value = this.dataset.period;
                document.querySelector("input[name='min_calls']").value = this.dataset.minCalls || '';
                document.querySelector("input[name='min_talk_time']").value = this.dataset.minTalk || '';

                document.querySelector("input[name='active']").checked = this.dataset.active === "1";

                document.querySelector("button[name='save_rule']").innerText = "ذخیره تغییرات";
            });
        });
        document.getElementById('ruleModal').addEventListener('hidden.bs.modal', function () {
            this.querySelector("form").reset();
            document.querySelector("input[name='id']").value = "";
            document.querySelector("button[name='save_rule']").innerText = "ثبت قانون جدید";
        });
    });
</script>

</html>
