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

$currentUser = $_SESSION['admin']['user'];
$adminRole   = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : 0;
//group info
$groupStmt = $conn->prepare("
    SELECT *
    FROM handhug_groups
    WHERE id = ?
");
$groupStmt->execute([ intval($_GET['group_id']) ]);
$groupinfo = $groupStmt->fetch(PDO::FETCH_OBJ);
$groupLeader = $groupinfo->leader_username ?? null;


//sar goroh info
$leaderStmt = $conn->prepare("
    SELECT fname , lname 
    FROM dbpersonal
    WHERE codemeli = ?
");
$leaderStmt->execute([ $groupinfo->leader_username ]);
$leaderinfo = $leaderStmt->fetch(PDO::FETCH_OBJ);

//users without group
$students = getUngroupedStudents($_GET['code']);
if (
    isset($_POST['username'], $_POST['token'], $_SESSION['token']) &&
    hash_equals($_SESSION['token'], $_POST['token'])
) {

    $sql = "
        UPDATE dbcourse
        SET group_id = ?
        WHERE username = ?
          AND codecourse = ?
          AND activityuser = 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        intval($_GET['group_id']),   // assign to THIS group
        $_POST['username'],          // selected student
        intval($_GET['code'])        // current handhug
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = 'دانش آموز با موفقیت به گروه اضافه شد';
    } else {
        $_SESSION['message'] = 'امکان افزودن دانش آموز وجود ندارد';
    }

    // prevent resubmit
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_GET['remove_user'], $_GET['group_id'], $_GET['code'])) {

    $username = $_GET['remove_user'];
    $groupId  = (int) $_GET['group_id'];
    $code     = (int) $_GET['code'];

    // 1. remove user from group
    $stmt = $conn->prepare("
        UPDATE dbcourse
        SET group_id = NULL
        WHERE username = ?
          AND codecourse = ?
          AND group_id = ?
    ");
    $stmt->execute([$username, $code, $groupId]);

    // 2. if user was leader, remove leader
    $stmt = $conn->prepare("
        UPDATE handhug_groups
        SET leader_username = NULL
        WHERE id = ?
          AND leader_username = ?
    ");
    $stmt->execute([$groupId, $username]);

    header("Location: ?code=$code&group_id=$groupId");
    exit;
}

if (isset($_GET['make_leader'], $_GET['group_id'], $_GET['code'])) {

    $username = $_GET['make_leader'];
    $groupId  = (int) $_GET['group_id'];
    $code     = (int) $_GET['code'];

    // safety: ensure user is in this group
    $check = $conn->prepare("
        SELECT 1
        FROM dbcourse
        WHERE username = ?
          AND codecourse = ?
          AND group_id = ?
        LIMIT 1
    ");
    $check->execute([$username, $code, $groupId]);

    if ($check->fetch()) {

        // promote to leader
        $stmt = $conn->prepare("
            UPDATE handhug_groups
            SET leader_username = ?
            WHERE id = ?
        ");
        $stmt->execute([$username, $groupId]);
    }

    header("Location: ?code=$code&group_id=$groupId");
    exit;
}

if (isset($_GET['remove_leader'], $_GET['group_id'], $_GET['code'])) {

    $username = $_GET['remove_leader'];
    $groupId  = (int) $_GET['group_id'];
    $code     = (int) $_GET['code'];

    $stmt = $conn->prepare("
        UPDATE handhug_groups
        SET leader_username = NULL
        WHERE id = ?
          AND leader_username = ?
    ");
    $stmt->execute([$groupId, $username]);

    header("Location: ?code=$code&group_id=$groupId");
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
    <?php
    include_once '../menu.php';
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
                    <ul class="nav nav-tabs mb-3 custom-tabs" id="courseTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active"  role="tab">
                                گروه
                                <?php echo $groupinfo->group_name  ?>
                            </button>
                        </li>
                    </ul>

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
                        <?php unset($_SESSION['message']);
                    } ?>


                    <div class="tab-content custom-tab-pane">
                        <!-- Type 1 tab -->
                        <div class="tab-pane fade show active" id="type1" role="tabpanel">
                            <div class="col-lg-12">
                                <div class="card custom-card p-2">
                                    <form method="POST">
                                        <label>انتخاب دانش آموز:</label>

                                        <select class="form-control select2" name="username" required>
                                            <option value="">-- انتخاب کنید --</option>
                                            <?php foreach ($students as $st): ?>
                                                <option value="<?= htmlspecialchars($st['username']) ?>">
                                                    <?= htmlspecialchars($st['fname']) . ' ' . htmlspecialchars($st['lname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php
                                        $hash = bin2hex(random_bytes(32));
                                        $_SESSION['token'] = $hash;
                                        ?>
                                        <input type="hidden" name="token" value="<?= $hash ?>">

                                        <button class="btn ripple btn-success mt-3" type="submit">
                                            ➕ افزودن
                                        </button>
                                    </form>

                                </div>
                                <div class="card custom-card">
                                    <td class="text-start ps-4">

                                        <span class="d-inline-flex align-items-center gap-2 px-3 py-2 fw-bold bg-opacity-10 shadow-sm">
                                                <i class="fe fe-shield" style="color: orange;"></i>
                                            <span class="badge  text-dark ms-2">
                                                سرگروه :
                                            </span>
                                            <?php if (isset($groupinfo->leader_username)){  ?>
                                            <?= htmlspecialchars($leaderinfo->fname . ' ' . $leaderinfo->lname) ?>
                                            <?php } else {  ?>
                                                فاقد سر گروه
                                            <?php  } ?>
                                        </span>
                                    </td>

                                    <div class="card-body">
                                        <div class="table">
                                            <table class="table table-bordered mg-b-0">
                                                <thead>
                                                <tr>
                                                    <th>اقدامات</th>
                                                    <th>نام و نام خانوادگی</th>
                                                    <th>کد ملی</th>
                                                    <th>وضعیت دانش آموز</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                function groupshow($conn) {

                                                    $sql = "
        SELECT
            dp.codemeli,
            dp.fname,
            dp.lname,
            dc.username,
            dc.activityuser
        FROM dbcourse dc
        INNER JOIN dbpersonal dp
            ON dc.username = dp.codemeli
        INNER JOIN handhug_groups hg
            ON dc.group_id = hg.id
        WHERE dc.codecourse = ?
          AND dc.group_id = ?
        ORDER BY dp.fname DESC
    ";

                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([
                                                        intval($_GET['code']),
                                                        intval($_GET['group_id'])
                                                    ]);

                                                    return $stmt->fetchAll(PDO::FETCH_OBJ);
                                                }

                                                $result = groupshow($conn);

                                                if (!empty($result)) {
                                                    foreach ($result as $res) { ?>
                                                        <tr class="align-middle text-center">
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button
                                                                            class="btn  btn-outline-primary dropdown-toggle"
                                                                            type="button"
                                                                            data-bs-toggle="dropdown"
                                                                            aria-expanded="false">
                                                                        اقدام
                                                                        <i class="bi bi-caret-down-fill ms-1"></i>
                                                                    </button>

                                                                    <div class="dropdown-menu dropdown-menu-end text-start">

                                                                        <!-- Remove user from group -->
                                                                        <a class="dropdown-item text-danger"
                                                                           href="?code=<?= intval($_GET['code']) ?>&remove_user=<?= $res->username ?>&group_id=<?= intval($_GET['group_id']) ?>"
                                                                           onclick="return confirm('آیا از حذف این دانش آموز از گروه مطمئن هستید؟')">
                                                                            <i class="bi bi-person-dash me-2"></i>
                                                                            حذف از گروه
                                                                        </a>

                                                                        <!-- Promote to leader -->
                                                                        <?php $isLeader = ($groupLeader == $res->username);  ?>


                                                                            <hr class="dropdown-divider">

                                                                            <?php if ($isLeader): ?>
                                                                                <!-- Demote leader -->
                                                                                <a class="dropdown-item text-secondary"
                                                                                   href="?code=<?= intval($_GET['code']) ?>&remove_leader=<?= $res->username ?>&group_id=<?= intval($_GET['group_id']) ?>"
                                                                                   onclick="return confirm('آیا از عزل این سرگروه مطمئن هستید؟')">
                                                                                    <i class="bi bi-person-x me-2"></i>
                                                                                    عزل از سرگروهی
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <!-- Promote to leader -->
                                                                                <a class="dropdown-item text-warning"
                                                                                   href="?code=<?= intval($_GET['code']) ?>&make_leader=<?= $res->username ?>&group_id=<?= intval($_GET['group_id']) ?>"
                                                                                   onclick="return confirm('آیا این دانش آموز به سرگروه ارتقا داده شود؟')">
                                                                                    <i class="bi bi-crown-fill me-2"></i>
                                                                                    ارتقا به سرگروهی
                                                                                </a>
                                                                            <?php endif; ?>

                                                                        </div>

                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?= htmlspecialchars($res->fname . ' ' . $res->lname) ?>
                                                            </td>
                                                            <td>
                                                                <?= htmlspecialchars($res->codemeli) ?>
                                                            </td>
                                                            <td>
                                                                <?php if ((int)$res->activityuser === 1) { ?>
                                                                    <span class="badge bg-success">فعال</span>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-danger">غیرفعال</span>
                                                                <?php } ?>
                                                            </td>
                                                        </tr>
                                                    <?php }
                                                } else {
                                                    echo '<tr><td colspan="4" class="text-center">هیچ دانش آموزی یافت نشد.</td></tr>';
                                                }
                                                ?>
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


</body>

</html>