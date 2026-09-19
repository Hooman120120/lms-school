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
    header('location: login.php');
    die();
}

if ($_SESSION['admin']['ip'] !== GetIp() && $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: login.php');
    die();
}

$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != ($re['session_id'] ?? '')) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

if (Permision('permision_suporter') && Permision('permision_viewcourse') == false) {
    header('location: panel/desktop.php');
    exit;
}

$operatorUsername = $re['username'] ?? ($_SESSION['admin']['user'] ?? '');

// adminrole from dbadmin
$adminrole = 0;
if ($operatorUsername !== '') {
    $st = $conn->prepare("SELECT adminrole FROM dbadmin WHERE username = ? LIMIT 1");
    $st->execute([$operatorUsername]);
    $adminrole = (int)($st->fetchColumn() ?: 0);
}
$isAdministrator = ($adminrole === 1);

// optional filter by codecourse
$filterCodecourse = null;
if (isset($_GET['code']) && ctype_digit((string)$_GET['code'])) {
    $filterCodecourse = (int)$_GET['code'];
}

// CSRF token
if (empty($_SESSION['token_hw_check'])) {
    $_SESSION['token_hw_check'] = bin2hex(random_bytes(32));
}
$token_hw_check = $_SESSION['token_hw_check'];

function deadline_jalali_date_only($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') return '—';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        $y  = (int)$m[1];
        $mo = (int)$m[2];
        $d  = (int)$m[3];

        if ($y >= 1200 && $y <= 1600) {
            return sprintf('%04d/%02d/%02d', $y, $mo, $d);
        }

        if (function_exists('gregorian_to_jalali')) {
            [$jy, $jm, $jd] = gregorian_to_jalali($y, $mo, $d);
            return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    $ts = strtotime($raw);
    if ($ts === false) return '—';

    $gy = (int)date('Y', $ts);
    $gm = (int)date('m', $ts);
    $gd = (int)date('d', $ts);

    if (function_exists('gregorian_to_jalali')) {
        [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    return date('Y-m-d', $ts);
}

/* =========================
   Handle Approve / Reject
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['token_hw_check'], $_SESSION['token_hw_check']) || $_POST['token_hw_check'] !== $_SESSION['token_hw_check']) {
        $_SESSION['message'] = 'نشست نامعتبر است. دوباره تلاش کنید.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $hw_id  = (int)($_POST['hw_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($hw_id <= 0) {
        $_SESSION['message'] = 'شناسه تکلیف نامعتبر است.';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Only allow operation on pending (so nobody overwrites someone else's result)
    if ($action === 'approve') {
        $st = $conn->prepare("
            UPDATE homework_admin
            SET status='approved', approved_by=?, updated_at=NOW()
            WHERE id=? AND status='pending'
            LIMIT 1
        ");
        $st->execute([$operatorUsername, $hw_id]);
        $_SESSION['message'] = '✅ تکلیف تایید شد.';
    }
    elseif ($action === 'reject') {
        $st = $conn->prepare("
            UPDATE homework_admin
            SET status='rejected', approved_by=?, updated_at=NOW()
            WHERE id=? AND status='pending'
            LIMIT 1
        ");
        $st->execute([$operatorUsername, $hw_id]);
        $_SESSION['message'] = '⛔ تکلیف رد شد.';
    }

    header('Location: ' . $_SERVER['REQUEST_URI']); // PRG
    exit;
}

/* =========================
   Fetch list for table
   ========================= */
$params = [];
$where  = [];

if ($filterCodecourse !== null) {
    $where[]  = "codecourse = ?";
    $params[] = $filterCodecourse;
}

if ($isAdministrator) {
    // admin sees all (no extra filter)
} else {
    // operator sees: pending + what THEY handled (approved/rejected)
    $where[]  = "(status='pending' OR approved_by=?)";
    $params[] = $operatorUsername;
}

$sql = "SELECT id, title, description, class_id, deadline, file_path, status, approved_by, created_at, updated_at
        FROM homework_admin";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>تایید تکالیف | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<div class="page">
    <?php include_once '../menu.php'; include_once '../header.php'; ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success" role="alert">
                        <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                            <span aria-hidden="true">×</span>
                        </button>
                        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <div class="card custom-card">
                    <div class="card-header border-bottom-0 pb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="main-content-label mb-1">تایید تکالیف</h6>
                            <div class="small text-muted">
                                شما: <b><?= htmlspecialchars($operatorUsername, ENT_QUOTES, 'UTF-8') ?></b>
                                <?php if ($filterCodecourse !== null): ?>
                                    — فیلتر درس: <b><?= (int)$filterCodecourse ?></b>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table text-nowrap text-md-nowrap table-bordered mg-b-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>عنوان</th>
                                    <th>توضیحات</th>
                                    <th>کد درس</th>
                                    <th>مهلت</th>
                                    <th>فایل</th>
                                    <th>وضعیت</th>
                                    <th>انجام شده توسط</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if ($rows): $i = 1; foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars((string)$r->title, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$r->description, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$r->class_id ?></td>
                                        <td>
                                            <?php
                                            $out = deadline_jalali_date_only($r->deadline);
                                            echo '<span style="direction:ltr; unicode-bidi:embed;">' . htmlspecialchars($out, ENT_QUOTES, 'UTF-8') . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r->file_path)): ?>
                                                <a href="<?= url($r->file_path) ?>" target="_blank" rel="noopener">دانلود</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $st = (string)$r->status;
                                            if ($st === 'pending') echo '<span class="badge bg-warning">در حال بررسی</span>';
                                            elseif ($st === 'approved') echo '<span class="badge bg-success">تایید شده</span>';
                                            else echo '<span class="badge bg-danger">رد شده</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?= !empty($r->approved_by) ? htmlspecialchars((string)$r->approved_by, ENT_QUOTES, 'UTF-8') : '—' ?>
                                        </td>
                                        <td>
                                            <?php if ($r->status === 'pending'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="token_hw_check" value="<?= htmlspecialchars($token_hw_check, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="hw_id" value="<?= (int)$r->id ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-success"
                                                            onclick="return confirm('این تکلیف تایید شود؟');">
                                                        تایید
                                                    </button>
                                                </form>

                                                <form method="post" class="d-inline ms-1">
                                                    <input type="hidden" name="token_hw_check" value="<?= htmlspecialchars($token_hw_check, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="hw_id" value="<?= (int)$r->id ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('این تکلیف رد شود؟');">
                                                        رد
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">درخواستی برای نمایش وجود ندارد.</td>
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

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
