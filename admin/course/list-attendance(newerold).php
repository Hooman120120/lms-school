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
if (!isset($_GET['code'])) {
    header('location: ' . url('admin/index.php'));
    die();
}
$ids = intval($_GET['code']);


include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS Hamsepar Produce">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> لیست حضورغیاب دانش آموزان | <?php echo __TITLE ?></title>
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
    <div class="container-fluid">
        <div class="inner-body" >
            <div class="row row-sm" >
                <div class="col-lg-12" >
                    <?php $general = rettblattendance($ids); ?>
                    <?php   if (Permision('permision_viewcourse')) { ?>
                    <div class="card custom-card">  <!-- for manager -->
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div>
                                        <h6 class="main-content-label mb-1">گرارش حضورغیاب پایه : <span
                                                    class="text-primary"><?php echo $general->classname ?></span></h6>
                                        <p class="text-muted card-sub-title"> کد پایه :<span
                                                    class="text-primary"><?php echo $general->codecourse ?></span>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <table  class="table text-nowrap text-md-nowrap table-bordered example1">
                                        <thead>
                                        <tr>
                                            <th>ردیف</th>
                                            <th>کد ملی</th>
                                            <th>نام و نام خانوادگی</th>
                                            <th>زمان حضور</th>
                                            <th>وضعیت</th>
                                            <th>پشتیبان</th>
                                            <th>ورود</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $sql = 'SELECT * FROM dbattendance WHERE coding=? ';
                                        $query = $conn->prepare($sql);
                                        $query->execute([$ids]);
                                        $list = $query->fetchAll(PDO::FETCH_OBJ);
                                        $id = 1;

                                        if ($query->rowCount() > 0) {
                                            foreach ($list as $ret) {
                                                ?>
                                                <tr <?php echo ($ret->status == 'غایب') ? 'class="bg-danger"' : 'class="bg-success"'; ?>>
                                                    <td class="wd-30"><?php echo $id; ?></td>
                                                    <td class="wd-30"><?php echo $ret->username; ?></td>
                                                    <td><?php $person = retPerson($ret->username); echo $person->fname . ' ' . $person->lname; ?></td>
                                                    <td class="wd-30"><?php echo $ret->timehozor; ?></td>
                                                    <td class="wd-30"><?php echo ($ret->status == 'غایب') ? 'غایب' : 'حاضر'; ?></td>

                                                    <?php
                                                    $sql = 'SELECT * FROM dbcourse WHERE codecourse=? AND username=? ';
                                                    $query1 = $conn->prepare($sql);
                                                    $query1->execute([$general->codecourse, $ret->username]);
                                                    $sup = $query1->fetch();
                                                    ?>
                                                    <td class="wd-30"><?php echo ($sup['suporter'] == NULL) ? 'ندارد' : $sup['suporter']; ?></td>
                                                    <td class="wd-30">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open(
                                                                     '<?php echo url('admin/panel/suporting.php'); ?>?Id=<?php echo urlencode($ret->username); ?>',
                                                                     'پشتیبانی',
                                                                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                     )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                                $id++;
                                            }
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php   if (!Permision('permision_viewcourse')) { ?>
                    <div class="card custom-card" >  <!-- for supporter (operator) -->
                        <div class="card-body" >
                            <div class="row">
                                <div class="col-12" >
                                    <div>
                                        <?php $general = rettblattendance($ids); ?>
                                        <h6 class="main-content-label mb-1">گرارش حضورغیاب پایه : <span
                                                    class="text-primary"><?php echo $general->classname ?></span></h6>
                                        <p class="text-muted card-sub-title"> کد پایه :<span
                                                    class="text-primary"><?php echo $general->codecourse ?></span>
                                        </p>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12">
                                        <table id="attendanceTable" class=" table text-nowrap text-md-nowrap table-bordered attendance-table example1">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>کد ملی</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>زمان حضور</th>
                                                <th>وضعیت</th>
                                                <th>پشتیبان</th>
                                                <th>ورود</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            // ===== DEBUG SWITCH =====
                                            $DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';
                                            $h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

                                            $normalizeOp = function($op) {
                                                $op = trim((string)$op);
                                                if (preg_match('/^\d+$/', $op)) return 'op'.$op;
                                                return $op;
                                            };

                                            $rawOp = isset($_GET['Op']) && $_GET['Op'] !== '' ? trim($_GET['Op']) : trim($_SESSION['admin']['user'] ?? '');
                                            $currentOp1 = $normalizeOp($rawOp); // e.g. "op202"
                                            $currentOp2 = $rawOp;               // e.g. "202" or already "op202"

                                            $classId   = (int)$ids; // dbattendance.coding
                                            $courseCode = (int)($general->codecourse ?? 0); // the real course code from rettblattendance

                                            try { $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

                                            // Diagnostics
                                            $diag = [
                                                'class_id(coding)'                => $classId,
                                                'course_code(general->codecourse)'=> $courseCode,
                                                'rawOp'                           => $rawOp,
                                                'normalizedOp'                    => $currentOp1,
                                                'rawOp_fallback'                  => $currentOp2,
                                                'attendance_rows_for_class'       => null,
                                                'distinct_supporters_for_course'  => [],
                                                'attempt_used'                    => null,
                                                'attempt1_rows'                   => 0,
                                                'attempt2_rows'                   => 0,
                                                'sql_used'                        => null,
                                                'params_used'                     => null,
                                                'mismatch_samples'                => []
                                            ];

                                            try {
                                                $stmt = $conn->prepare("SELECT COUNT(*) FROM dbattendance WHERE coding = ?");
                                                $stmt->execute([$classId]);
                                                $diag['attendance_rows_for_class'] = (int)$stmt->fetchColumn();
                                            } catch (Throwable $e) {
                                                if ($DEBUG) echo '<div class="alert alert-danger">Count error: '.$h($e->getMessage()).'</div>';
                                            }
                                            try {
                                                $stmt = $conn->prepare("
                                                    SELECT DISTINCT d.suporter
                                                    FROM dbattendance a
                                                    JOIN dbcourse d
                                                      ON d.username = a.username
                                                     AND d.codecourse = ?
                                                    WHERE a.coding = ?
                                                  ");
                                                $stmt->execute([$courseCode, $classId]);
                                                $diag['distinct_supporters_for_course'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                                            } catch (Throwable $e) {
                                                if ($DEBUG) echo '<div class="alert alert-danger">Distinct supporters error: '.$h($e->getMessage()).'</div>';
                                            }

                                            $sql = "
                                                  SELECT a.id, a.coding, a.username, a.timehozor, a.status, d.suporter
                                                  FROM dbattendance a
                                                  JOIN dbcourse d
                                                    ON d.username   = a.username
                                                   AND d.codecourse = ?
                                                  WHERE a.coding = ?
                                                    AND d.suporter = ?
                                                  GROUP BY a.id
                                                  ORDER BY a.id ASC
                                                ";
                                            $diag['sql_used'] = trim($sql);

                                            $rows = [];
                                            try {
                                                $stmt = $conn->prepare($sql);
                                                $stmt->execute([$courseCode, $classId, $currentOp1]);
                                                $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                $diag['attempt1_rows'] = count($rows);
                                                if ($diag['attempt1_rows'] > 0) {
                                                    $diag['attempt_used'] = 'normalized';
                                                    $diag['params_used']  = [$courseCode, $classId, $currentOp1];
                                                } else {
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->execute([$courseCode, $classId, $currentOp2]);
                                                    $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                    $diag['attempt2_rows'] = count($rows);
                                                    if ($diag['attempt2_rows'] > 0) {
                                                        $diag['attempt_used'] = 'raw';
                                                        $diag['params_used']  = [$courseCode, $classId, $currentOp2];
                                                    }
                                                }
                                            } catch (Throwable $e) {
                                                if ($DEBUG) echo '<div class="alert alert-danger">Main query error: '.$h($e->getMessage()).'</div>';
                                                $rows = [];
                                            }

                                            if (!$rows && $diag['attendance_rows_for_class'] > 0) {
                                                try {
                                                    $stmt = $conn->prepare("
                                                      SELECT a.username, d.suporter
                                                      FROM dbattendance a
                                                      JOIN dbcourse d
                                                        ON d.username = a.username
                                                       AND d.codecourse = ?
                                                      WHERE a.coding = ?
                                                      LIMIT 15
                                                    ");
                                                    $stmt->execute([$courseCode, $classId]);
                                                    $diag['mismatch_samples'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                                } catch (Throwable $e) {
                                                    if ($DEBUG) echo '<div class="alert alert-danger">Mismatch probe error: '.$h($e->getMessage()).'</div>';
                                                }
                                            }

                                            // Render rows
                                            $i = 1;

                                            foreach ($rows as $ret):
                                                $person = null;
                                                try { $person = retPerson($ret->username); } catch (Throwable $e) { $person = (object)[]; }
                                                $fullName = trim(($person->fname ?? '').' '.($person->lname ?? ''));
                                                ?>
                                                <tr class="<?= ($ret->status === 'غایب') ? 'bg-danger' : 'bg-success' ?>"
                                                    data-username="<?= $h($ret->username) ?>">
                                                    <td class="wd-30"><?= $i ?></td>
                                                    <td class="wd-30"><?= $h($ret->username) ?></td>
                                                    <td><?= $h($fullName) ?></td>
                                                    <td class="wd-30"><?= $h($ret->timehozor) ?></td>
                                                    <td class="wd-30"><?= ($ret->status === 'غایب') ? 'غایب' : 'حاضر' ?></td>
                                                    <td class="wd-30"><?= $ret->suporter ? $h($ret->suporter) : 'ندارد' ?></td>
                                                    <td class="wd-30">
                                                        <div class="btn btn-sm ripple btn-info btn-icon"
                                                             onclick="window.open(
                                                                     '<?php echo url('admin/panel/suporting.php'); ?>?Id=<?php echo urlencode($ret->username); ?>',
                                                                     'پشتیبانی',
                                                                     'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                                     )">
                                                            <i class="fa fa-key"></i>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                                $i++;
                                            endforeach;

                                            if ($i === 1) {
                                                echo '<tr><td colspan="6" class="text-center text-muted">موردی یافت نشد.</td></tr>';
                                            }
                                            ?>
                                            </tbody>
                                        </table>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>


                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tables = Array.from(document.querySelectorAll('table.attendance-table'));
            tables.forEach(function (table) {

                if (!table || !table.tBodies.length) return;

                const tbody = table.tBodies[0];
                const seen = new Set();
                Array.from(tbody.rows).forEach(function (tr) {
                    let key = (tr.dataset.username || '').trim();
                    if (!key && tr.cells && tr.cells.length > 1) {
                        key = (tr.cells[1].textContent || '').trim();
                    }
                    key = key.toLowerCase();

                    if (!key) return;
                    if (seen.has(key)) {
                        tr.remove();
                    } else {
                        seen.add(key);
                    }
                });
            });
        });

        $(document).ready(function() {
            $('.example1').DataTable({
                language: {
                    searchPlaceholder: "جستجو کردن...",
                    sSearch: "",
                    lengthMenu: "_MENU_ موارد/صفحه"
                },
                responsive: true
            });
        });
    </script>

</body>
</html>