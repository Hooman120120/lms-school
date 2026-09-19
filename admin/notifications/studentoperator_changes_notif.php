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

// Protecting against SESSION Hijaking
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

include_once '../../temp.html';

// --- administrator flag from dbadmin ---
$sq = 'SELECT administrator FROM dbadmin WHERE username = ? LIMIT 1';
$st = $conn->prepare($sq);
$st->execute([$_SESSION['admin']['user']]);
$adminRow = $st->fetch(PDO::FETCH_ASSOC);
$isAdministrator = !empty($adminRow) && (int)$adminRow['administrator'] === 1;

// --- fetch changed operator students (last 7 days, not null) ---
if ($isAdministrator) {
    $sql = "
        SELECT
            d.username,
            d.codecourse,
            d.suporter,
            d.suporter_updated_at,
            p.codemeli,
            p.fname,
            p.lname,
            c.coursename
        FROM dbcourse d
        INNER JOIN dbpersonal p ON p.codemeli = d.username
        INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
        WHERE d.suporter_updated_at IS NOT NULL
          AND d.suporter_updated_at >= (NOW() - INTERVAL 7 DAY)
        ORDER BY d.suporter_updated_at DESC
    ";
    $q = $conn->prepare($sql);
    $q->execute();
} else {
    $sql = "
        SELECT
            d.username,
            d.codecourse,
            d.suporter_updated_at,
            p.codemeli,
            p.fname,
            p.lname,
            c.coursename
        FROM dbcourse d
        INNER JOIN dbpersonal p ON p.codemeli = d.username
        INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
        WHERE d.suporter_updated_at IS NOT NULL
          AND d.suporter_updated_at >= (NOW() - INTERVAL 7 DAY)
          AND d.suporter = ?
        ORDER BY d.suporter_updated_at DESC
    ";
    $q = $conn->prepare($sql);
    $q->execute([$_SESSION['admin']['user']]);
}

$learners = $q->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <title>لیست تغییر اپراتور دانش آموزان | <?php echo __TITLE ?></title>
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
                    <div class="col-12">
                        <div class="card custom-card overflow-hidden">
                            <div class="card-header border-bottom-0 pb-0">
                                <div>
                                    <div class="d-flex">
                                        <label class="main-content-label my-auto pt-2">
                                            دانش آموزان تغییر پشتیبان یافته
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table table-bordered mb-0">
                                        <thead>
                                        <tr>
                                            <th>وضعیت</th>
                                            <th>ورود</th>
                                            <th>کدملی</th>
                                            <th>نام</th>
                                            <th>نام خانوادگی</th>
                                            <th>پایه</th>
                                            <?php if ($isAdministrator): ?>
                                                <th>اپراتور جدید</th>
                                            <?php endif; ?>
                                            <th>تاریخ تغییر</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($learners)): ?>
                                            <?php $i = 1; foreach ($learners as $learner): ?>
                                                <tr>
                                                    <td class="wd-15"> <p class="badge-danger p-1" style="border-radius: 5px; background-color: green;">جدید!</p></td>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open(
                                                                     'suporting.php?Id=<?php echo rawurlencode($learner->username); ?>',
                                                                     'پشتیبانی',
                                                                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                     )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($learner->codemeli ?? $learner->username); ?></td>
                                                    <td><?php echo htmlspecialchars($learner->fname ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($learner->lname ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($learner->coursename ?? ''); ?></td>

                                                    <?php if ($isAdministrator): ?>
                                                        <td><?php echo htmlspecialchars($learner->suporter ?? ''); ?></td>
                                                    <?php endif; ?>

                                                    <td>
                                                        <?php
                                                        // If you prefer Jalali:
                                                         echo jdate('Y/m/d', strtotime($learner->suporter_updated_at));
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo $isAdministrator ? 8 : 7; ?>" class="text-center">
                                                    موردی یافت نشد
                                                </td>
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

    <?php include '../../footers.php'; ?>
</div>
</body>
</html>
