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
$sessionUser = $_SESSION['admin']['user'];
$urlType = trim($_GET['type'] ?? '');
$isImpersonate = ($_SESSION['admin']['can_impersonate']) == 1;
//$allowedTypes = [2, 1];
//$check = $conn->query("SELECT CURDATE() AS today")->fetch(PDO::FETCH_ASSOC);


$sqlReqs = 'SELECT change_expert_requests.*, dbpersonal.fname, dbpersonal.lname, dbpersonal.datecreated FROM change_expert_requests INNER JOIN dbpersonal ON change_expert_requests.username = dbpersonal.codemeli WHERE status = ?';
$stmtReqs = $conn->prepare($sqlReqs);
$stmtReqs->execute(['pending']);
$reqs = $stmtReqs->fetchAll(PDO::FETCH_OBJ);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {

    $username = trim($_POST['codemeli'] ?? '');
    $reqId = $_POST['req_id'];

    if ($username !== '' && preg_match('/^\d{11}$/', $username)) {

        $reason = trim($_POST['reason'] ?? '');

        $chk = $conn->prepare("
            UPDATE change_expert_requests SET status = ?, reject_reason = ? WHERE id = ? 
        ");
        $chk->execute(['rejected', $reason, $reqId]);

        $_SESSION['message'] = 'کاربر رد شد';

    } else {
        $_SESSION['message'] = 'شناسه کاربری نامعتبر است';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['change-expert'])) {
    $suggestedAdmin = $_POST['new_admin_id'];
    $username = $_POST['codemeli'];
    $reqId = $_POST['req_id'];
    $sql = 'UPDATE dbpersonal SET creator = ? WHERE codemeli = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$suggestedAdmin, $username]);
    $sqll = 'UPDATE change_expert_requests SET status = ? WHERE id = ?';
    $stmtt = $conn->prepare($sqll);
    $stmtt->execute(['accepted', $reqId]);
    $_SESSION['change_success'] = 'تغییر کارشناس با موفقیت انجام شد';
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

if (isset($_GET['ajax_get_admins'])) {
    $codemeli = $_GET['codemeli'];

    // Your logic: Find the person who originally requested the change
    $sql_req = "SELECT requested_by FROM change_expert_requests WHERE username = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1";
    $stmt_req = $conn->prepare($sql_req);
    $stmt_req->execute([$codemeli]);
    $requester = $stmt_req->fetchColumn();

    // Fetch all admins
    $sql_admins = 'SELECT * FROM dbadmin WHERE adminrole = 6';
    $stmt_admins = $conn->prepare($sql_admins);
    $stmt_admins->execute();
    $admins = $stmt_admins->fetchAll(PDO::FETCH_OBJ);

    // Generate the HTML options
    echo '<option value="">انتخاب کنید...</option>';
    foreach ($admins as $admin) {
        $selected = ($admin->username == $requester) ? '(کارشناس قبلی)' : '';
        echo "<option value='{$admin->username}'>{$admin->username}{$selected}</option>";
    }
    exit; // IMPORTANT: Stop the rest of the page from rendering in the AJAX response
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
    <title> پایه های فعال | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>
<body class="main-body leftmenu">
<!-- Loader -->
<!-- <div id="global-loader">
<img src="<//?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div> -->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <?php include_once '../menu.php';
    include_once '../header.php';
    ?>
    <!-- Main Content-->
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
                    <?php if (isset($_SESSION['change_success'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['change_success'] ?>
                        </div>
                        <?php unset($_SESSION['change_success']);
                    } ?>
                    <?php if (isset($_SESSION['message'])) { ?>
                        <div class="alert alert-success" role="alert">
                            <span class="alert-inner--icon"><i class=" mdi mdi-asterisk"></i></span>
                            <button aria-label="بستن" class="close" data-bs-dismiss="alert" type="button">
                                <span aria-hidden="true">×</span>
                            </button>
                            <?= $_SESSION['message'] ?>
                        </div>
                        <?php unset($_SESSION['message']);
                    } ?>
                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">
                            <div class="col-lg-12">
                                <div class="card custom-card">
                                    <div class="card-body">
                                        <p>لیست درخواست های تغییر کارشناس (منتظر تایید)</p>
                                        <div class="table">
                                            <table class="table table-bordered table-hover text-center align-middle"
                                                   id="example1">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>کارشناس درخواست داده</th>
                                                    <th>نام دانش آموز</th>
                                                    <th>شماره تماس</th>
                                                    <th>تاریخ ثبت نام</th>
                                                    <th>کارشناس پیشنهادی</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($reqs)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="8">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php foreach ($reqs as $u) {
                                                        $jalaliCreated = '';
                                                        if (!empty($u->datecreated) && strpos($u->datecreated, '-') !== false) {
                                                            list($gy, $gm, $gd) = explode('-', $u->datecreated);
                                                            $jalaliCreated = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                        }
                                                        ?>
                                                        <tr class="align-middle text-center">
                                                            <td>
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                     onclick="window.open('../panel/suporting.php?Id=<?= htmlspecialchars($u->username) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                    <i class="fa fa-key"></i>
                                                                </div>
                                                            </td>
                                                            <td><?= htmlspecialchars($u->requested_by) ?></td>
                                                            <td><?= htmlspecialchars($u->fname . ' ' . $u->lname) ?></td>
                                                            <td><?= htmlspecialchars($u->username) ?></td>
                                                            <td><?= htmlspecialchars($jalaliCreated) ?></td>
                                                            <td><?= htmlspecialchars($u->suggested_admin) ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-outline-primary dropdown-toggle"
                                                                            type="button" data-bs-toggle="dropdown"
                                                                            aria-expanded="false">
                                                                        اقدام <i
                                                                            class="bi bi-caret-down-fill ms-1"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-end text-start">

                                                                        <button type="button"
                                                                                class="dropdown-item text-primary"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#transferModal"
                                                                                data-id="<?= htmlspecialchars($u->id) ?>"
                                                                                data-codemeli="<?= htmlspecialchars($u->username) ?>">
                                                                            قبول درخواست
                                                                        </button>

                                                                        <button type="button"
                                                                                class="dropdown-item text-danger"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#rejectModal"
                                                                                data-id="<?= htmlspecialchars($u->id) ?>"
                                                                                data-codemeli="<?= htmlspecialchars($u->username) ?>">
                                                                            رد درخواست
                                                                        </button>
                                                                    </div>
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
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">رد کردن درخواست</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="codemeli" id="rejectCodemeli" value="">
                <input type="hidden" name="req_id" id="rejectId" value="">

                <label class="form-label">دلیل رد کردن</label>
                <textarea name="reason" class="form-control" rows="4" required
                          placeholder="مثلاً: اطلاعات ناقص / عدم پاسخگویی / ..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="submit" name="reject" class="btn btn-danger">ثبت رد کردن</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تغییر کارشناس فروش</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="codemeli" id="modal_codemeli">
                    <input type="hidden" name="req_id" id="modal_req">

                    <div class="mb-3">
                        <label class="form-label">کارشناس نهایی :</label>
                        <select name="new_admin_id" id="admin_select" class="form-select" required>
                            <option value="">در حال بارگذاری...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="change-expert" class="btn btn-primary">تغییر کارشناس</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var codemeli = button.getAttribute('data-codemeli');
            var id = button.getAttribute('data-id');
            document.getElementById('rejectCodemeli').value = codemeli;
            document.getElementById('rejectId').value = id;
        });
    });

    document.getElementById('transferModal').addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var codemeli = button.getAttribute('data-codemeli');
        var id = button.getAttribute('data-id');

        document.getElementById('modal_codemeli').value = codemeli;
        document.getElementById('modal_req').value = id;

        var currentUrl = window.location.pathname;

        fetch(currentUrl + '?ajax_get_admins=1&codemeli=' + codemeli)
            .then(response => response.text())
            .then(data => {
                document.getElementById('admin_select').innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('admin_select').innerHTML = '<option>خطا در بارگذاری</option>';
            });
    });
</script>

</body>
</html>
