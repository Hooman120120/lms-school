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

// نام کاربری اپراتور فعلی
$operatorUser = $_SESSION['admin']['user'];

// ---------------------------------------------------------------------------
// 1. لیست دانش آموزان تعلیقیِ این اپراتور
// شرط اصلی: p.suspension_mode = 1 (دانش‌آموز واقعاً معلق است)
// شرط اپراتور: آخرین درخواست تعلیقِ تایید شده (status = 1 و requested_mode = 1) توسط این اپراتور باشد
// ---------------------------------------------------------------------------
$sqlSuspended = "
    SELECT p.codemeli, p.fname, p.lname, p.tel1, p.suspension_reason, req.created_at
    FROM dbpersonal p
    INNER JOIN (
        SELECT s1.*
        FROM student_suspension_requests s1
        INNER JOIN (
            SELECT student_username, MAX(created_at) as max_date
            FROM student_suspension_requests
            WHERE requested_mode = 1 AND status = 1
            GROUP BY student_username
        ) s2 ON s1.student_username = s2.student_username AND s1.created_at = s2.max_date
    ) req ON p.codemeli = req.student_username
    WHERE p.suspension_mode = 1 
      AND req.requested_by = ?
    ORDER BY p.lname ASC
";
// استفاده از متغیر کانکشن شما که در نمونه ارسال کردید ($conn یا $db)
$stmtSuspended = $conn->prepare($sqlSuspended);
$stmtSuspended->execute([$operatorUser]);
$mySuspendedStudents = $stmtSuspended->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// 2. لیست دانش آموزان لغو پشتیبانی شدهِ این اپراتور
// شرط اصلی: c.has_operator = 0 (دانش‌آموز واقعاً بدون اپراتور است)
// شرط اپراتور: آخرین درخواست لغو تایید شده (status = 1 و requested_mode = 1) توسط این اپراتور باشد
// ---------------------------------------------------------------------------
$sqlCanceled = "
    SELECT p.codemeli, p.fname, p.lname, p.tel1, req.created_at
    FROM dbpersonal p
    INNER JOIN dbcourse c ON p.codemeli = c.username
    INNER JOIN (
        SELECT c1.*
        FROM cancel_student_operator_request c1
        INNER JOIN (
            SELECT student_username, MAX(created_at) as max_date
            FROM cancel_student_operator_request
            WHERE requested_mode = 1 AND status = 1
            GROUP BY student_username
        ) c2 ON c1.student_username = c2.student_username AND c1.created_at = c2.max_date
    ) req ON p.codemeli = req.student_username
    WHERE c.has_operator = 0 
      AND req.requested_by = ?
    GROUP BY p.codemeli
    ORDER BY p.lname ASC
";
$stmtCanceled = $conn->prepare($sqlCanceled);
$stmtCanceled->execute([$operatorUser]);
$myCanceledStudents = $stmtCanceled->fetchAll(PDO::FETCH_ASSOC);



include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title>لیست دانش آموزان من | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .course-pill.active { box-shadow: 0 0 0 .15rem rgba(13,110,253,.25); }
    </style>
</head>

<body class="main-body leftmenu">
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>

<div class="page">
    <?php
    include_once '../menu.php';
    include_once '../header.php';
    var_dump($operatorUser);
    ?>

    <div class="main-content side-content pt-0">
        <div class="container-fluid">
            <div class="inner-body">

                <!-- Page Header -->
                <!-- End Page Header -->

                <!-- Card 1: Suspended Students -->
                <div class="card custom-card">
                    <div class="card-header border-bottom-0 pb-0">
                        <div>
                            <div class="d-flex">
                                <label class="main-content-label my-auto pt-2">دانش آموزان تعلیق‌شده توسط من</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" id="example1">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th style="width: 150px; min-width: 150px;">دانش‌آموز</th>
                                    <th>کد ملی</th>
                                    <th style="width: 250px; min-width: 250px;">علت تعلیق</th>
                                    <th>تاریخ تعلیق</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($mySuspendedStudents)) : ?>
                                    <tr><td colspan="7" class="text-center">موردی یافت نشد.</td></tr>
                                <?php else : ?>
                                    <?php $i = 1; foreach ($mySuspendedStudents as $s) : ?>
                                        <tr>
                                            <td>
                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                     onclick="window.open('../panel/suporting.php?Id='+'<?php echo $s['codemeli'] ?>','پشتیبانی'
                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                    <i class="fa fa-key"></i>
                                                </div>
                                            </td>
                                            <td style="max-width: 150px; white-space: normal; word-wrap: break-word;">
                                                <?php echo htmlspecialchars(trim(($s['fname'] ?? '') . ' ' . ($s['lname'] ?? ''))); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($s['codemeli']); ?></td>
                                            <td style="max-width: 250px; white-space: normal; word-wrap: break-word;">
                                                <?php echo htmlspecialchars($s['suspension_reason']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($s['created_at'])) {
                                                    $parts = explode(' ', $s['created_at']);
                                                    $d = explode('-', $parts[0]);
                                                    $j = gregorian_to_jalali((int)$d[0], (int)$d[1], (int)$d[2]);
                                                    echo implode('/', $j) . ' ' . ($parts[1] ?? '');
                                                } else { echo '-'; }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Canceled Students -->
                <div class="card custom-card mt-4">
                    <div class="card-header border-bottom-0 pb-0">
                        <div>
                            <div class="d-flex">
                                <label class="main-content-label my-auto pt-2">دانش آموزان لغو پشتیبانی شده توسط من</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0" id="example5">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th style="width: 150px; min-width: 150px;">دانش‌آموز</th>
                                    <th>کد ملی</th>
                                    <th>تاریخ لغو</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($myCanceledStudents)) : ?>
                                    <tr><td colspan="7" class="text-center">موردی یافت نشد.</td></tr>
                                <?php else : ?>
                                    <?php $i = 1; foreach ($myCanceledStudents as $c) : ?>
                                        <tr>
                                            <td>
                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                     onclick="window.open('../panel/suporting.php?Id='+'<?php echo $c['codemeli'] ?>','پشتیبانی'
                                                             ,'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0' )">
                                                    <i class="fa fa-key"></i>
                                                </div>
                                            </td>
                                            <td style="max-width: 150px; white-space: normal; word-wrap: break-word;">
                                                <?php echo htmlspecialchars(trim(($c['fname'] ?? '') . ' ' . ($c['lname'] ?? ''))); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($c['codemeli']); ?></td>
                                            <td>
                                                <?php
                                                if (!empty($c['created_at'])) {
                                                    $parts = explode(' ', $c['created_at']);
                                                    $d = explode('-', $parts[0]);
                                                    $j = gregorian_to_jalali((int)$d[0], (int)$d[1], (int)$d[2]);
                                                    echo implode('/', $j) . ' ' . ($parts[1] ?? '');
                                                } else { echo '-'; }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include '../../footers.php'; ?>
    </div>
    <script>
        const table = $("#example5").DataTable({
            stateSave: true,
            language: {
                searchPlaceholder: "جستجو کردن...",
                sSearch: "",
                lengthMenu: "_MENU_ موارد/صفحه"
            },
            drawCallback: function(settings) {
                let titleColumnIndex = -1;

                // 1. Find the index of the "عنوان" column dynamically
                $(this.api().table().header()).find('th').each(function(index) {
                    if ($(this).text().trim() === 'عنوان') {
                        titleColumnIndex = index;
                        return false; // Exit loop
                    }
                });

                if (titleColumnIndex !== -1) {
                    // 2. Apply the wrapping styles to the <a> tags in the specific column index
                    const columnIndexSelector = `:nth-child(${titleColumnIndex + 1}) a`;

                    $(this.api().table().body()).find('tr td' + columnIndexSelector).css({
                        'display': 'block',
                        'word-break': 'break-word',
                        'overflow-wrap': 'break-word',
                        'white-space': 'normal'
                    });
                }
            }
        });
    </script>

</body>
</html>
