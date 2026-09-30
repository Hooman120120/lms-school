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
$isAdministrator = AdminCan('notifications.all-records');

// --- fetch changed operator students (last 7 days, not null) ---
if ($isAdministrator) {
    $sql = "
        SELECT 
    dbpersonal.*, 
    dbcourse_demo.*,
    (SELECT MAX(datestart) 
     FROM tbl_attendanced 
     WHERE tbl_attendanced.codecourse = dbcourse_demo.codecourse
    ) AS last_meeting_date,
    (SELECT status 
     FROM dbattendance 
     WHERE dbattendance.username = dbpersonal.codemeli 
     AND dbattendance.coding = (
         SELECT id 
         FROM tbl_attendanced 
         WHERE codecourse = dbcourse_demo.codecourse 
         ORDER BY datestart DESC LIMIT 1
     )
     LIMIT 1
    ) AS last_meeting_status          
    FROM dbpersonal
    INNER JOIN dbcourse_demo ON dbpersonal.codemeli = dbcourse_demo.username
    WHERE CHAR_LENGTH(dbpersonal.codemeli) = 11
    AND (
    -- Condition 1: Select if no records exist at all
    NOT EXISTS (SELECT 1 FROM user_account_status WHERE username = dbpersonal.codemeli)
    OR 
    -- Condition 2: Select if the most recent record does NOT have status = 1
    (SELECT status FROM user_account_status 
     WHERE username = dbpersonal.codemeli 
     ORDER BY id DESC LIMIT 1) != 1
    )
    GROUP BY dbpersonal.codemeli
    ";
    $q = $conn->prepare($sql);
    $q->execute();
} else {
    $sql = "
        SELECT 
    dbpersonal.*, 
    dbcourse_demo.*,
    (SELECT MAX(datestart) 
     FROM tbl_attendanced 
     WHERE tbl_attendanced.codecourse = dbcourse_demo.codecourse
    ) AS last_meeting_date,
    (SELECT status 
     FROM dbattendance 
     WHERE dbattendance.username = dbpersonal.codemeli 
     AND dbattendance.coding = (
         SELECT id 
         FROM tbl_attendanced 
         WHERE codecourse = dbcourse_demo.codecourse 
         ORDER BY datestart DESC LIMIT 1
     )
     LIMIT 1
    ) AS last_meeting_status
    FROM dbpersonal
    INNER JOIN dbcourse_demo ON dbpersonal.codemeli = dbcourse_demo.username
    WHERE CHAR_LENGTH(dbpersonal.codemeli) = 11
    AND dbpersonal.creator = ?
        AND (
    -- Condition 1: Select if no records exist at all
    NOT EXISTS (SELECT 1 FROM user_account_status WHERE username = dbpersonal.codemeli)
    OR 
    -- Condition 2: Select if the most recent record does NOT have status = 1
    (SELECT status FROM user_account_status 
     WHERE username = dbpersonal.codemeli 
     ORDER BY id DESC LIMIT 1) != 1
    )
    GROUP BY dbpersonal.codemeli
    ";
    $q = $conn->prepare($sql);
    $q->execute([$_SESSION['admin']['user']]);
}

$learners = $q->fetchAll(PDO::FETCH_OBJ);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
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
                                            لیست دانش آموزان موقت فعال که حداقل در یک جلسه حاضر بوده اند
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example1" class="table table-bordered mb-0">
                                        <thead>
                                        <tr>
                                            <th>ورود</th>
                                            <th>شماره تماس</th>
                                            <th>نام</th>
                                            <th>نام خانوادگی</th>
                                            <?php if ($isAdministrator): ?>
                                                <th>کارشناس ثبت کننده</th>
                                            <?php endif; ?>
                                            <th>تاریخ برگزاری آخرین جلسه</th>
                                            <th>مدت زمان حاضری در آخرین جلسه</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($learners)): ?>
                                            <?php $i = 1;
                                            foreach ($learners as $learner): ?>
                                                <tr>
                                                    <td class="wd-15">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open(
                                                                     '../panel/suporting.php?Id=<?php echo rawurlencode($learner->username); ?>',
                                                                     'پشتیبانی',
                                                                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                     )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($learner->codemeli ?? $learner->username); ?></td>
                                                    <td><?php echo htmlspecialchars($learner->fname ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($learner->lname ?? ''); ?></td>
                                                    <?php if ($isAdministrator): ?>
                                                        <td><?php echo htmlspecialchars($learner->useradder ?? ''); ?></td>
                                                    <?php endif; ?>
                                                    <td>
                                                        <?php
                                                        if ($learner->last_meeting_date) {
                                                            $date_parts = explode('-', $learner->last_meeting_date);
                                                            $jalali = gregorian_to_jalali($date_parts[0], $date_parts[1], $date_parts[2]);
                                                            echo implode('/', $jalali);
                                                        } else {
                                                            echo 'جلسه‌ای یافت نشد';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($learner->last_meeting_status): ?>
                                                            <p class="badge badge-<?php echo ($learner->last_meeting_status != 'غایب') ? 'success' : 'danger'; ?>">
                                                                <?php echo htmlspecialchars($learner->last_meeting_status); ?>
                                                            </p>
                                                        <?php else: ?>
                                                            <p class="badge badge-secondary">جلسه برگزار نشده</p>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?php echo $isAdministrator ? 7 : 6; ?>"
                                                    class="text-center">
                                                    موردی یافت نشد
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div
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
