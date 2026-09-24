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

// Protecting against SESSION Hijacking
if ($_SESSION['admin']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}

// Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('admin/login.php'));
    die();
}

$sessionUser = $_SESSION['admin']['user'];
$isImpersonate = ((int)($_SESSION['admin']['can_impersonate'] ?? 0) === 1);

$type = (int)($_GET['type'] ?? 1);
$allowedTypes = [1]; // we are ignoring type2 like you said
if (!in_array($type, $allowedTypes, true)) {
    echo 'دسترسی ندارید';
    exit;
}

$users = [];
$isadmin = 0;
if (
    $_SESSION['admin']['adminrole'] == 53 ||
    $_SESSION['admin']['adminrole'] == 0  ||
    $_SESSION['admin']['adminrole'] == 52 ||
    $_SESSION['admin']['adminrole'] == 51
) {
    $isadmin = 1;
}


//if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
//
//    $codemeli  = trim($_POST['codemeli'] ?? '');
//    $createdBy = $_SESSION['admin']['user'] ?? 'unknown';
//
//    if ($codemeli !== '' && preg_match('/^\d{11}$/', $codemeli)) {
//
//        $status = 0; // ✅ restored / returned
//        $reason = trim($_POST['reason'] ?? '');
//
//        $chk = $conn->prepare("
//            SELECT 1
//            FROM user_account_status
//            WHERE username = ? AND status = ? AND reason = ?
//            ORDER BY created_at DESC, id DESC
//            LIMIT 1
//        ");
//        $chk->execute([$codemeli, $status, $reason]);
//
//        if (!$chk->fetchColumn()) {
//            $stmt = $conn->prepare("
//                INSERT INTO user_account_status (username, status, reason, created_by, created_at)
//                VALUES (?, ?, ?, ?, NOW())
//            ");
//            $stmt->execute([$codemeli, $status, $reason, $createdBy]);
//        }
//
//        $_SESSION['message'] = 'کاربر برگردانده شد';
//
//    } else {
//        $_SESSION['message'] = 'شناسه کاربری نامعتبر است';
//    }
//
//    header("Location: " . $_SERVER['PHP_SELF'] . "?type=" . urlencode($type));
//    exit;
//}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_restore') {

    $codemeli  = trim($_POST['codemeli'] ?? '');
    $createdBy = $_SESSION['admin']['user'] ?? 'unknown';
    $reason    = trim($_POST['reason'] ?? '');

    if ($codemeli !== '' && preg_match('/^\d{11}$/', $codemeli)) {

        // prevent duplicate open ticket for same student + same type
        $dup = $conn->prepare("
            SELECT id
            FROM admin_tickets
            WHERE type = ?
              AND student_username = ?
              AND ticket_status = 0
            ORDER BY id DESC
            LIMIT 1
        ");
        $dup->execute(['reactivate_temp_user', $codemeli]);

        if (!$dup->fetchColumn()) {

            $stmt = $conn->prepare("
                INSERT INTO admin_tickets
                    (type, subject, ticket_creator, student_username, ticket_status)
                VALUES
                    (?, ?, ?, ?, 0)
            ");

            $subject = ($reason !== '') ? $reason : null;

            $stmt->execute([
                'reactivate_temp_user',
                $subject,
                $createdBy,
                $codemeli
            ]);

            $_SESSION['message'] = 'درخواست برگرداندن ثبت شد';

        } else {
            $_SESSION['message'] = 'برای این کاربر یک درخواست باز وجود دارد';
        }

    } else {
        $_SESSION['message'] = 'شناسه کاربری نامعتبر است';
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?type=" . urlencode($type));
    exit;
}


if ($type === 1) {
    if ($isadmin == 1) {
        $listSql = "
    SELECT
        uas.id,
        uas.username,
        uas.status,
        uas.reason,
        uas.created_by,
        uas.created_at,
        p.fname,
        p.lname,
        t.ticket_status AS reactivate_ticket_status
    FROM user_account_status uas
    INNER JOIN (
        SELECT username, MAX(id) AS max_id
        FROM user_account_status
        GROUP BY username
    ) last
        ON last.username = uas.username AND last.max_id = uas.id
    LEFT JOIN dbpersonal p
        ON p.codemeli = uas.username
    LEFT JOIN admin_tickets t
  ON t.id = (
      SELECT at2.id
      FROM admin_tickets at2
      WHERE at2.student_username = uas.username
        AND at2.type = 'reactivate_temp_user'
      ORDER BY at2.id DESC
      LIMIT 1
  )
    WHERE uas.status = 1
    ORDER BY uas.id DESC
";
        $stmt = $conn->prepare($listSql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    } else {
        $listSql = "
    SELECT
        uas.id,
        uas.username,
        uas.status,
        uas.reason,
        uas.created_by,
        uas.created_at,
        p.fname,
        p.lname
    FROM user_account_status uas
    INNER JOIN (
        SELECT username, MAX(id) AS max_id
        FROM user_account_status
        GROUP BY username
    ) last
        ON last.username = uas.username AND last.max_id = uas.id
    LEFT JOIN dbpersonal p
        ON p.codemeli = uas.username
    WHERE uas.status = 1
      AND uas.created_by = ?
    ORDER BY uas.id DESC
";
        $stmt = $conn->prepare($listSql);
        $stmt->execute([$sessionUser]);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);

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
    <title> وضعیت کاربران | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">
                <div style="padding:10px;">
                    <style>
                        .custom-tabs .nav-link {
                            border: none;
                            border-radius: 50px;
                            margin-right: 10px;
                            padding: 10px 25px;
                            font-weight: bold;
                            color: #555;
                            background: linear-gradient(145deg, #f0f0f0, #e0e0e0);
                            transition: all 0.3s ease;
                            box-shadow: 3px 3px 8px rgba(0, 0, 0, 0.1);
                        }
                        .custom-tabs .nav-link:hover {
                            background: linear-gradient(145deg, #4e73df, #1cc88a);
                            color: #fff;
                            transform: translateY(-3px);
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2);
                        }
                        .custom-tabs .nav-link.active {
                            background: linear-gradient(145deg, #1cc88a, #36b9cc);
                            color: #fff;
                            font-size: 1.05rem;
                            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.25);
                        }
                    </style>
                </div>

                <div class="row row-sm">
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php } ?>

                    <div class="tab-content custom-tab-pane">
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">
                            <p class="fs-6 font-weight-bold"> لیست وضعیت کاربران </p>

                            <div class="col-lg-12">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <div class="table">
                                            <table class="table table-bordered table-hover text-center align-middle" id="example1">
                                                <thead>
                                                <tr>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>کد ملی / تلفن</th>
                                                    <th>وضعیت</th>
                                                    <th>دلیل</th>
                                                    <th>ثبت کننده</th>
                                                        <th>اقدام</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($users)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="8">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php foreach ($users as $u) {
                                                        $fullName = trim(($u->fname ?? '') . ' ' . ($u->lname ?? ''));
                                                        if ($fullName === '') $fullName = '-';

                                                        $ticketStatus = isset($u->reactivate_ticket_status) ? (int)$u->reactivate_ticket_status : null;
                                                        $hasActiveTicket = in_array($ticketStatus, [0, 3], true); // open or ongoing

                                                        $statusText = ((int)$u->status === 1) ? 'رد شده' : 'نامشخص';
                                                        ?>
                                                        <tr class="align-middle text-center">
                                                            <td><?= htmlspecialchars($fullName) ?></td>
                                                            <td><?= htmlspecialchars($u->username) ?></td>
                                                            <td><?= htmlspecialchars($statusText) ?></td>
                                                            <td><?= htmlspecialchars($u->reason ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($u->created_by ?? '-') ?></td>

                                                                <td>
                                                                    <div class="dropdown">
                                                                        <?php if (!$hasActiveTicket) { ?>
                                                                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                                اقدام
                                                                            </button>
                                                                            <!--                                                                    <div class="dropdown-menu dropdown-menu-end text-start">-->
                                                                            <!--                                                                        <button type="button"-->
                                                                            <!--                                                                                class="dropdown-item text-warning"-->
                                                                            <!--                                                                                data-bs-toggle="modal"-->
                                                                            <!--                                                                                data-bs-target="#restoreModal"-->
                                                                            <!--                                                                                data-codemeli="--><?php //= htmlspecialchars($u->username) ?><!--">-->
                                                                            <!--                                                                            برگرداندن-->
                                                                            <!--                                                                        </button>-->
                                                                            <!--                                                                    </div>-->
                                                                            <div class="dropdown-menu dropdown-menu-end text-start">
                                                                                <button type="button"
                                                                                        class="dropdown-item text-warning"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#restoreModal"
                                                                                        data-codemeli="<?= htmlspecialchars($u->username) ?>">
                                                                                    درخواست برگرداندن
                                                                                </button>
                                                                            </div>
                                                                        <?php } else { ?>
                                                                            <button class="btn btn-pink dropdown-toggle disabled" type="button" data-bs-toggle="dropdown" aria-expanded="false" >
                                                                                در حال بررسی
                                                                            </button>
                                                                        <?php } ?>
                                                                    </div>

                                                                </td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- tab pane -->
                    </div><!-- tab content -->

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../footers.php'; ?>

<!-- <div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">-->
<!--    <div class="modal-dialog">-->
<!--        <form method="post" action="" class="modal-content">-->
<!--            <div class="modal-header">-->
<!--                <h5 class="modal-title">برگرداندن دانش آموز</h5>-->
<!--                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>-->
<!--            </div>-->
<!---->
<!--            <div class="modal-body">-->
<!--                <input type="hidden" name="action" value="restore">-->
<!--                <input type="hidden" name="codemeli" id="restoreCodemeli" value="">-->
<!--                <input type="hidden" name="type" value="--><?//= htmlspecialchars($type) ?><!--">-->
<!---->
<!--                <label class="form-label">دلیل برگرداندن</label>-->
<!--                <textarea name="reason" class="form-control" rows="4" required-->
<!--                          placeholder="مثلاً: رفع مشکل / اشتباه ثبت شده / ..."></textarea>-->
<!--            </div>-->
<!---->
<!--            <div class="modal-footer">-->
<!--                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>-->
<!--                <button type="submit" class="btn btn-warning">ثبت برگرداندن</button>-->
<!--            </div>-->
<!--        </form>-->
<!--    </div>-->
<!--</div>-->

<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">برگرداندن دانش آموز</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="request_restore">
                <input type="hidden" name="codemeli" id="restoreCodemeli" value="">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

                <label class="form-label">دلیل برگرداندن</label>
                <textarea name="reason" class="form-control" rows="4" required
                          placeholder="مثلاً: رفع مشکل / اشتباه ثبت شده / ..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" class="btn btn-warning">ثبت برگرداندن</button>
            </div>
        </form>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        var restoreModal = document.getElementById('restoreModal');
        restoreModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var codemeli = button.getAttribute('data-codemeli');
            document.getElementById('restoreCodemeli').value = codemeli;
        });
    });
</script>



</body>
</html>
