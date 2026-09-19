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
if (!Permision('permision_manage_suporter') and $_SESSION['admin']['user'] != 'op213') {
    header('location: ' . url('admin/index.php'));
    die();
}
//* End Check Session JUST ONE Session

$rule = $conn->query("
    SELECT period_days, min_calls, min_talk_time
    FROM notification_rules
    WHERE active=1
    ORDER BY created_at DESC, id DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$periodDays = max(1, (int)($rule['period_days'] ?? 1));
$MIN_CALLS = isset($rule['min_calls']) && $rule['min_calls'] !== null ? (int)$rule['min_calls'] : 5;
$MIN_TALK = isset($rule['min_talk_time']) && $rule['min_talk_time'] !== null ? (int)$rule['min_talk_time'] : 300;

// 🔹 Date range from rule (period_days)
$startDay = date('Y-m-d', strtotime('-' . ($periodDays - 1) . ' days'));
$endDay = date('Y-m-d');

if (isset($_POST['supporter_excel'])) {
    $op = isset($_GET['Op']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['Op']) ? $_GET['Op'] : '';
    $_SESSION['supporter_excel'] = array(
        "Op" => $op
    );
    header('Location: ../Bill/execel.php');
    exit;
}


if (!isset($dateno)) {
    $todayGregorian = date('Y-m-d');
    list($jy, $jm, $jd) = gregorian_to_jalali(date('Y'), date('m'), date('d'));
    $dateno = "$jy/$jm/$jd";
}

if (!function_exists('secToHMS')) {
    function secToHMS(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
if (isset($_POST['assign_operator'])) {
    // 1. ابتدا همه دانش آموزان این اپراتور را میگیریم (بدون شرط داشتن پرداختی)
    $sql = '
        SELECT dbcourse.*, codtbl_course.*, dbfactor.* 
        FROM dbcourse 
        INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse 
        INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor 
        WHERE codtbl_course.activecourse = 1 
          AND dbcourse.activityuser = 1 
          AND dbcourse.suporter = ? 
          AND dbfactor.status = ? 
        GROUP BY dbcourse.username
    ';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_POST['thisOp'], 'موفق']);
    $allStudents = $stmt->fetchAll(PDO::FETCH_OBJ);

    $studentsThatPay = []; // آرایه برای ذخیره کسانی که پرداختی دارند

    // 2. حالا با همان منطق جدول، پرداختی ها را محاسبه میکنیم
    foreach ($allStudents as $ret) {
        $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
        $wer = $conn->prepare($swr);
        $wer->execute([1, 1, $ret->username]);
        $recive = $wer->fetchAll(PDO::FETCH_OBJ);

        $total = [];
        $factorCheck = [];

        if ($wer->rowCount() > 0) {
            foreach ($recive as $rey) {
                if (!in_array($rey->numfactor, $factorCheck)) {
                    $factor = queryFactor($rey->numfactor); // تابع شما برای گرفتن فاکتور
                    if($factor) {
                        $sum = (float)$factor->wallet + (float)$factor->factorfee;
                        $total[] = $sum;
                    }
                    $factorCheck[] = $rey->numfactor;
                }
            }
        }

        $sumtoto = array_sum($total);

        // 3. اگر جمع پرداختی بیشتر از صفر بود، به آرایه نهایی اضافه‌اش میکنیم
        if ($sumtoto > 0) {
            // برای اطمینان از صحت، مبلغ محاسبه شده را هم موقتاً به آبجکت اضافه میکنیم
            $ret->calculated_total_payment = $sumtoto;
            $studentsThatPay[] = $ret;
        }
    }



    // 4. عملیات تخصیص به کارشناس فروش
    $salesOperator = $_POST['sales_operator'];
    $assignedCount = 0;

    if (!empty($salesOperator) && count($studentsThatPay) > 0) {

        // آماده‌سازی کوئری INSERT بیرون از حلقه برای سرعت بالاتر
        $insertSql = "INSERT INTO sales_followup (username, sales_admin, total_previous_payment, assign_date, status) 
                  VALUES (?, ?, ?, ?, 'در انتظار تماس')";
        $stmtInsert = $conn->prepare($insertSql);

        foreach ($studentsThatPay as $student) {
            $studentUsername = $student->username;
            $totalPayment = $student->calculated_total_payment;
            date_default_timezone_set('Asia/Tehran');
            $currentDate = date('Y-m-d H:i:s');

            // اجرای کوئری برای هر دانش‌آموز
            $stmtInsert->execute([$studentUsername, $salesOperator, $totalPayment, $currentDate]);

            $assignedCount++;
        }

        // پیام موفقیت و ریدایرکت
        echo "<script>
            alert('تعداد $assignedCount کاربر با موفقیت به کارشناس فروش ($salesOperator) تخصیص داده شد.');
            window.location.href = window.location.href; 
          </script>";
        exit;

    } elseif (empty($salesOperator)) {
        echo "<script>alert('خطا: لطفا یک کارشناس فروش انتخاب کنید.');</script>";
    } else {
        echo "<script>alert('کاربری با شرط پرداختی برای این اپراتور یافت نشد.');</script>";
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
    <!-- Title -->
    <title>گزارش نفرات پشتیبان | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--    <div id="global-loader">-->
<!--        <img src="-->
<?php //echo url('assets/img/media/loader.gif') ?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--    </div>-->
<!-- End Loader -->
<!-- Page -->
<div class="page">
    <div class="container-fluid">
        <div class="inner-body">
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="main-content-label mb-0">
                                            گزارش تعداد دانش آموزان اپراتور
                                            <?php
                                            $admin = retAdmin($_GET['Op']);
                                            echo $admin->dakheli;
                                            ?>
                                            <span class="text-primary"></span>
                                        </h6>

                                        <?php
                                        $currentUser = $_SESSION['admin']['user'];
                                        if (PermisionUser('excel_permission', $currentUser)):
                                            $op = isset($_GET['Op']) && preg_match('/^[a-zA-Z0-9]+$/', $_GET['Op']) ? $_GET['Op'] : '';
                                            if (empty($op)) {
                                                echo '<p class="text-danger mb-0">خطا: مقدار Op نامعتبر یا وجود ندارد.</p>';
                                            } else {
                                                ?>
                                                <form action="" method="post" class="mb-0">
                                                    <input type="hidden" name="supporter_excel" value="1">
                                                    <input type="hidden" name="Op" value="<?= htmlspecialchars($op) ?>">
                                                    <button type="submit" class="btn btn-success ms-2">
                                                        <i class="fa fa-file-excel-o"></i> دانلود اکسل
                                                    </button>
                                                </form>
                                                <?php
                                            }
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <div class="alert alert-info mb-3">
                                            قانون فعال: حداقل تماس <?= $MIN_CALLS ?> | حداقل زمان
                                            مکالمه <?= $MIN_TALK ?> ثانیه | بازه <?= $periodDays ?> روز اخیر
                                        </div>
                                        <form action="" method="POST">
                                            <div class="row mb-3 align-items-end">
                                                <div class="col-md-5 col-sm-8">
                                                    <div class="form-group mb-0">
                                                        <label for="sales_operator" class="mb-2"
                                                               style="font-weight: bold;">
                                                            تخصیص دانش آموزان دارای پرداخت برای فروش مجدد
                                                        </label>
                                                        <select class="form-control select2" id="sales_operator"
                                                                name="sales_operator" required>
                                                            <option value="">-- انتخاب اپراتور --</option>
                                                            <?php
                                                            $sql = "SELECT * FROM dbadmin WHERE adminrole = 6 AND adminactive = 1";
                                                            $stmt = $conn->prepare($sql);
                                                            $stmt->execute();
                                                            $sellExperts = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                            foreach ($sellExperts as $admin) {
                                                                ?>
                                                                <option value="<?= $admin->username ?>"><?= $admin->username ?></option>
                                                            <?php } ?>
                                                        </select>
                                                        <input type="hidden" name="thisOp" value="<?= $_GET['Op'] ?>">
                                                    </div>
                                                </div>

                                                <div class="col-md-2 col-sm-4">
                                                    <button type="submit" name="assign_operator"
                                                            class="btn btn-primary btn-block">
                                                        ثبت و تخصیص
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <table class="table text-nowrap text-md-nowrap table-bordered" id="example1">
                                            <thead>
                                            <tr>
                                                <th>ردیف</th>
                                                <th>ورود</th>
                                                <th>کد ملی</th>
                                                <th>نام و نام خانوادگی</th>
                                                <th>نام دوره</th>
                                                <th>اولین دوره</th>
                                                <th>زمان مکالمه</th>
                                                <th>آزمون</th>
                                                <th>آخرین پشتیبانی</th>
                                                <th>پشتیبانی بعدی</th>
                                                <th>نوع</th>
                                                <th>رنگ</th>
                                                <th>پرداختی</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $rule = $conn->query("
                                                SELECT period_days, min_calls, min_talk_time
                                                FROM notification_rules
                                                WHERE active=1
                                                ORDER BY created_at DESC, id DESC
                                                LIMIT 1
                                            ")->fetch(PDO::FETCH_ASSOC);

                                            $periodDays = max(1, (int)($rule['period_days'] ?? 1));
                                            $MIN_CALLS = isset($rule['min_calls']) && $rule['min_calls'] !== null ? (int)$rule['min_calls'] : 5;
                                            $MIN_TALK = isset($rule['min_talk_time']) && $rule['min_talk_time'] !== null ? (int)$rule['min_talk_time'] : 300;

                                            $startDay = date('Y-m-d', strtotime('-' . ($periodDays - 1) . ' days'));
                                            $endDay = date('Y-m-d');

                                            // 🔹 Fetch operator's students
                                            $sql = '
                                                SELECT * FROM dbcourse 
                                                INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse 
                                                INNER JOIN dbfactor ON dbcourse.numfactor = dbfactor.idfactor 
                                                WHERE codtbl_course.activecourse = 1 
                                                  AND dbcourse.activityuser = 1 
                                                  AND dbcourse.suporter = ? 
                                                  AND dbfactor.status = ? 
                                                GROUP BY dbcourse.username
                                            ';
                                            $query = $conn->prepare($sql);
                                            $query->execute([$_GET['Op'], 'موفق']);
                                            $result = $query->fetchAll(PDO::FETCH_OBJ);
                                            $id = 1;

                                            // 🔹 Iterate students
                                            if ($query->rowCount() > 0) {
                                                foreach ($result as $ret) {

                                                    // ────────────────────────────────
                                                    // 🔹 Get call stats
                                                    // ────────────────────────────────
                                                    $statsQuery = $conn->prepare("
                                                                    SELECT 
                                                                        total_calls,
                                                                        unanswered_calls,
                                                                        total_talk_time
                                                                    FROM simotel_calls_data
                                                                    WHERE codemeli = ?
                                                                      AND operator_username = ?
                                                              AND snapshot_day = (
                                                                  SELECT MAX(snapshot_day)
                                                                  FROM simotel_calls_data
                                                                  WHERE operator_username = ?
                                                              )
                                                        ");
                                                    $statsQuery->execute([$ret->username, $_GET['Op'], $_GET['Op']]);
                                                    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

                                                    $totalCalls = (int)($stats['total_calls'] ?? 0);
                                                    $unansweredCalls = (int)($stats['unanswered_calls'] ?? 0);
                                                    $totalTalkTime = (int)($stats['total_talk_time'] ?? 0);

                                                    $ratioPercent = $totalCalls > 0 ? round(($unansweredCalls / $totalCalls) * 100, 1) : 0.0;

                                                    // ────────────────────────────────
                                                    // 🔹 Determine coloring logic
                                                    // ────────────────────────────────
                                                    $lowTalkTime = ($totalTalkTime < $MIN_TALK);
                                                    $lowCalls = ($totalCalls < $MIN_CALLS);
                                                    $colorRow = ($totalCalls > 0 && ($unansweredCalls / $totalCalls) >= 0.7);

                                                    if ($lowTalkTime || $lowCalls) {
                                                        $rowClass = 'bg-danger';
                                                        $rowStyle = 'background-color: #ff9999 !important; color: white;';
                                                    } elseif ($colorRow) {
                                                        $rowClass = 'bg-danger-subtle';
                                                        $rowStyle = 'background-color: #f8caca !important; color: #6b0505;';
                                                    } else {
                                                        $rowClass = '';
                                                        $rowStyle = '';
                                                    }

                                                    $personal = retPerson($ret->username);
                                                    $talkTimeDisplay = '<span title="' . number_format($totalTalkTime) . ' ثانیه">' . secToHMS($totalTalkTime) . '</span>';
                                                    $firstlogin = retdblogin($ret->username);

                                                    ?>
                                                    <tr class="<?php echo $rowClass; ?>"
                                                        style="<?php echo $rowStyle; ?>">
                                                        <td class="w-5"><?php echo $id ?></td>
                                                        <td class="w-auto">
                                                            <div class="btn btn-sm ripple btn-info btn-icon"
                                                                 onclick="window.open('suporting.php?Id='+'<?php echo $ret->username ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                <i class="fa fa-key"></i>
                                                            </div>
                                                        </td>
                                                        <td class="w-auto"><?php echo $ret->username; ?></td>
                                                        <?php
                                                        $personal = retPerson($ret->username);
                                                        ?>
                                                        <td class="w-auto">
                                                            <?php echo htmlspecialchars($personal->fname . ' ' . $personal->lname) ?>
                                                            <div class="small text-muted">
                                                                کل تماس‌ها: <?= $totalCalls ?> |
                                                                بدون پاسخ: <?= $unansweredCalls ?> |
                                                                نسبت: <?= $ratioPercent ?>%
                                                            </div>
                                                        </td>

                                                        <?php $dore = getCourses($ret->username);
                                                        $dore = implode(' || ', $dore);
                                                        $displayDore = mb_strlen($dore) > 50 ? mb_substr($dore, 0, 50) . '...' : $dore;
                                                        ?>
                                                        <td title="<?= $dore ?>"
                                                            style="width:50%;"><?php echo $displayDore ?></td>
                                                        <td class="wd-15"><?php if ($firstlogin->firstlogin == 1) {
                                                                echo '<span class="badge badge-success-light">بله</span>';
                                                            } else {
                                                                echo '<span class="badge badge-warning-light">خیــر</span>';
                                                            } ?></td>
                                                        <!-- زمان مکالمه column -->
                                                        <td class="w-auto"><?php echo $talkTimeDisplay ?></td>

                                                        <?php
                                                        $quiz = getTblQuizColor($ret->codecourse, $ret->username);
                                                        ?>
                                                        <td class="w-auto"><?= $quiz[0] ?></td>

                                                        <?php
                                                        $suport = getsuport_user($ret->username);
                                                        if ($suport) {
                                                            $last = implode('/', gregorian_to_jalali(...explode('-', $suport['lastdate'])));
                                                            $after = implode('/', gregorian_to_jalali(...explode('-', $suport['afterdate'])));
                                                            if ($suport['typesupport'] == 1) {
                                                                $type = '<span class="badge badge-success">ویـژه</span>';
                                                            } elseif ($suport['typesupport'] == 2) {
                                                                $type = '<span class="badge badge-primary">ویژه فعال</span>';
                                                            } else {
                                                                $type = 'عــادی';
                                                            }
                                                        } else {
                                                            $last = 'ندارد';
                                                            $after = 'ندارد';
                                                            $type = 'عادی';
                                                        }
                                                        ?>
                                                        <td><?php echo $last ?> </td>
                                                        <td><?php echo $after ?></td>
                                                        <td><?php echo $type ?></td>

                                                        <?php
                                                        switch ($ret->color) {
                                                            case 0:
                                                                $color = 'قرمز';
                                                                break;
                                                            case 1:
                                                                $color = 'نارنجی';
                                                                break;
                                                            case 2:
                                                                $color = 'زرد';
                                                                break;
                                                            case 3:
                                                                $color = 'سبز';
                                                                break;
                                                            default:
                                                                $color = '';
                                                        }
                                                        ?>
                                                        <td class="w-auto"><?php echo $color; ?></td>

                                                        <?php
                                                        $swr = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse WHERE codtbl_course.activecourse=? AND dbcourse.activityuser=? AND dbcourse.username =?';
                                                        $wer = $conn->prepare($swr);
                                                        $wer->execute([1, 1, $ret->username]);
                                                        $recive = $wer->fetchAll(PDO::FETCH_OBJ);
                                                        $total = [];
                                                        $factorCheck = [];
                                                        if ($wer->rowCount() > 0) {
                                                            foreach ($recive as $rey) {
                                                                if (!in_array($rey->numfactor, $factorCheck)) {
                                                                    $factor = queryFactor($rey->numfactor);
                                                                    $sum = $factor->wallet + $factor->factorfee;
                                                                    $total[] = $sum;
                                                                    $factorCheck[] = $rey->numfactor;
                                                                }
                                                            }
                                                        }
                                                        $sumtoto = array_sum($total);
                                                        ?>
                                                        <td class="w-auto"><?php echo number_format($sumtoto) ?></td>

                                                    </tr>
                                                    <?php
                                                    $id++;
                                                } // end foreach
                                            } // end if rowCount > 0
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

    <!-- Main Footer-->
    <?php include '../../footers.php'; ?>
    <!--End Footer-->


</body>

</html>