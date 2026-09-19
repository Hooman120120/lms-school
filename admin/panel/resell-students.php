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
$check = $conn->query("SELECT CURDATE() AS today")->fetch(PDO::FETCH_ASSOC);
$users = [];
$isadmin = 0;
if ($_SESSION['admin']['adminrole'] == 53 or $_SESSION['admin']['adminrole'] == 0 or $_SESSION['admin']['adminrole'] == 52 or $_SESSION['admin']['adminrole'] == 51) {
    $isadmin = 1;
}
if ($isadmin) {
    if ($_GET['status'] == 'all') {
        $sql = 'SELECT * FROM sales_followup WHERE status = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['در انتظار تماس']);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    } else {
        $sql = 'SELECT * FROM sales_followup WHERE status = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['فروش موفق']);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    }
} else {
    if ($_GET['status'] == 'all') {
        $sql = 'SELECT * FROM sales_followup WHERE status = ? AND sales_admin = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['در انتظار تماس', $_SESSION['admin']['user']]);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    } else {
        $sql = 'SELECT * FROM sales_followup WHERE status = ? AND sales_admin = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute(['فروش موفق', $_SESSION['admin']['user']]);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    date_default_timezone_set("Asia/Tehran");
    $sql = "UPDATE sales_followup SET status = ?, updated_at = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['فروش موفق', date("Y-m-d H:i:s"), $_POST['verify_user']]);

    $_SESSION['message'] = 'کاربر موردنظر با موفقیت تائید شد';

    header("Location: " . $_SERVER['PHP_SELF'] . "?status=all");
    exit;
}

if (isset($_POST['reject'])) {
    date_default_timezone_set("Asia/Tehran");
    $sql = "UPDATE sales_followup SET status = ?, updated_at = ?, followup_note = ? WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['ناموفق', date("Y-m-d H:i:s"), $_POST['reason'], $_POST['codemeli']]);
    $_SESSION['message'] = 'کاربر موردنظر با موفقیت رد شد';

    header("Location: " . $_SERVER['PHP_SELF'] . "?status=all");
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
    <!-- Title -->
    <title> دوره های فعال | <?php echo __TITLE ?></title>
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
                                        <div class="table">
                                            <?php if ($_GET['status'] == 'all') { ?>
                                                <p class="text-center">اگر فروش مجدد موفقیت آمیز بود، ابتدا دانش آموز را
                                                    ثبت
                                                    دوره و سپس در فاکتور تائید نمایید، سپس دکمه تائید را بزنید.</p>
                                            <?php } ?>
                                            <table class="table table-bordered table-hover text-center align-middle"
                                                   id="example1">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <?php if ($isadmin == 1) { ?>
                                                        <th>کارشناس</th>
                                                    <?php } ?>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>کد ملی</th>
                                                    <th>تاریخ ثبت نام</th>
                                                    <?php if ($_GET['status'] == 'all') { ?>
                                                        <th>اقدامات</th>
                                                    <?php } ?>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($users)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="8">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php foreach ($users as $u) {
                                                        $sql = 'SELECT fname, lname, datecreated FROM dbpersonal WHERE codemeli = ?';
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->execute([$u->username]);
                                                        $name = $stmt->fetch(PDO::FETCH_OBJ);
                                                        $u->full_name = $name->fname . ' ' . $name->lname;
                                                        $u->datecreated = $name->datecreated;
                                                        $priorityText = 'مشخص نشده';
                                                        $dateText = 'مشخص نشده';

                                                        if (!empty($u->datesupport)) {
                                                            $dateText = $u->datesupport; // expected YYYY-MM-DD
                                                        }

                                                        // datecreated -> jalali
                                                        $jalaliCreated = '';
                                                        if (!empty($u->datecreated) && strpos($u->datecreated, '-') !== false) {
                                                            list($gy, $gm, $gd) = explode('-', $u->datecreated);
                                                            $jalaliCreated = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                        }
                                                        // datesupport -> jalali (if valid)
                                                        $jalaliSupport = $dateText;
                                                        if ($dateText !== 'مشخص نشده' && strpos($dateText, '-') !== false) {
                                                            list($gy2, $gm2, $gd2) = explode('-', $dateText);
                                                            $jalaliSupport = implode('/', gregorian_to_jalali((int)$gy2, (int)$gm2, (int)$gd2));
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                     onclick="window.open('suporting.php?Id=<?= htmlspecialchars($u->username) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                    <i class="fa fa-key"></i>
                                                                </div>
                                                            </td>
                                                            <?php if ($isadmin == 1) { ?>
                                                                <td><?= htmlspecialchars($u->creator_name ?: ($u->creator_id ?? '-')) ?></td>
                                                            <?php } ?>
                                                            <td><?= htmlspecialchars($u->full_name) ?></td>
                                                            <td><?= htmlspecialchars($u->username) ?></td>
                                                            <td><?= htmlspecialchars($jalaliCreated) ?></td>
                                                            <?php if ($_GET['status'] == 'all') { ?>
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-outline-primary dropdown-toggle"
                                                                                type="button" data-bs-toggle="dropdown"
                                                                                aria-expanded="false">
                                                                            اقدام <i
                                                                                    class="bi bi-caret-down-fill ms-1"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu dropdown-menu-end text-start">
                                                                            <form action="" method="post">
                                                                                <input type="hidden" name="verify_user"
                                                                                       value="<?= $u->username ?>">
                                                                                <button type="submit" name="verify"
                                                                                        class="dropdown-item text-success"
                                                                                        onclick="return confirm('آیا از این اقدام اطمینان دارید ؟');">
                                                                                    تائید
                                                                                </button>
                                                                            </form>

                                                                            <button type="button"
                                                                                    class="dropdown-item text-danger"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#rejectModal"
                                                                                    data-codemeli="<?= htmlspecialchars($u->username) ?>">
                                                                                رد
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            <?php } ?>
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
                <h5 class="modal-title">رد کردن دانش آموز</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="codemeli" id="rejectCodemeli" value="">

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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var rejectModal = document.getElementById('rejectModal');
        rejectModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var codemeli = button.getAttribute('data-codemeli');
            document.getElementById('rejectCodemeli').value = codemeli;
        });
    });
</script>

</body>
</html>
