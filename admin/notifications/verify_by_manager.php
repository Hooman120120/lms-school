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
$schoolRoleKey = SchoolStaffRoleKey((string)$sessionUser);
if (!Permision('permision_viewalert') && !Permision('permision_actfactor')
    && !in_array($schoolRoleKey, ['manager', 'general_deputy'], true)) {
    http_response_code(403);
    exit('دسترسی بررسی فاکتورهای منتظر تأیید برای سمت شما تعریف نشده است.');
}
$urlType = trim($_GET['type'] ?? '');
$isImpersonate = ($_SESSION['admin']['can_impersonate']) == 1;
//$allowedTypes = [2, 1];
//$check = $conn->query("SELECT CURDATE() AS today")->fetch(PDO::FETCH_ASSOC);


$sql = 'SELECT deposit_verifications.*, dbpersonal.fname, dbpersonal.lname, dbfactor.factorfee FROM deposit_verifications INNER JOIN dbpersonal ON deposit_verifications.username = dbpersonal.codemeli INNER JOIN dbfactor ON deposit_verifications.factor_id = dbfactor.idfactor WHERE deposit_verifications.status =  ?';
$stmt = $conn->prepare($sql);
$stmt->execute(['pending']);
$deposits = $stmt->fetchAll(PDO::FETCH_OBJ);

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

if (isset($_GET['verify'])) {

    $acceptSql = 'UPDATE deposit_verifications SET status = ? WHERE id = ?';
    $acceptStmt = $conn->prepare($acceptSql);
    $acceptStmt->execute(['accepted', $_GET['verify']]);

    $sql = 'SELECT * FROM deposit_verifications WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_GET['verify']]);
    $deposit = $stmt->fetch(PDO::FETCH_OBJ);

    $factor = queryFactor($deposit->factor_id);

    date_default_timezone_set("Asia/Tehran");
    $now = date('Y-m-d H:i:s');
    $datenow = date('Y-m-d');
    $timenow = date('H:i:s');
    $ip = GetIp();
    $os = GetOs();

    $detail = 'افزایش موجودی حساب از ' . $deposit->payment_method . ' برای فاکتور  ' . $factor->idfactor;
    $insert = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ref_id,ip,os,useractive,dateactive,timeactive,ipactive,osactive)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $query = $conn->prepare($insert);
    $query->execute([$deposit->username, $factor->factorfee, 'موفق', $datenow, $timenow, $detail, 1, $factor->idfactor, $deposit->ref_id, $ip, $os, $deposit->requested_by, $datenow, $timenow, $ip, $os]);
    //
    $fee = $factor->factorfee * -1;
    $dtailD = 'کاهش موجودی بواسطه فاکتور ' . $factor->idfactor;
    $insertD = 'INSERT INTO db_wallet(username,fee,status,datecreted,timecreated,detail_wallet,mode,factornum,ip,os,useractive,dateactive,timeactive,ipactive,osactive)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $queryD = $conn->prepare($insertD);
    $queryD->execute([$deposit->username, $fee, 'موفق', $datenow, $timenow, $dtailD, 0, $factor->idfactor, $ip, $os, $deposit->requested_by, $datenow, $timenow, $ip, $os]);
    //
    $wallet = $factor->wallet + $factor->factorfee;
    $total = 0;
    $update = 'UPDATE dbfactor SET wallet=?,factorfee=?, status=?,metod=?,dateresponse=?,timeresponse=? ,useractive=?,dateactive=?,timeactive=?,ipactive=?,osactive=? WHERE idfactor=? ';
    $qupdate = $conn->prepare($update);
    $qupdate->execute([$wallet, $total, 'موفق', $deposit->payment_method, $deposit->payment_date, $timenow, $deposit->requested_by, $datenow, $timenow, $ip, $os, $factor->idfactor]);
    //
    $courseA = 'UPDATE dbcourse SET activityuser=? WHERE numfactor=?';
    $queryA = $conn->prepare($courseA);
    $queryA->execute([1, $factor->idfactor]);
    // Register Quiz
    include_once '../../quizconfig.php';
    include_once '../../inc/function-quiz.php';
    $existingUid = checkUserquizByApi($factor->username);
    if (!$existingUid) {
        $person = retPerson($factor->username);
        $login = MD5(passUser($factor->username));
        CreateUserByApi(['aid' => 1, 'username' => $factor->username, 'password' => $login, 'active' => 1, 'expiration_date' => '3021-03-21', 'fname' => $person->fname, 'lname' => $person->lname, 'pass' => passUser($factor->username)]);
        // $inquiz = 'INSERT INTO `_users`(aid,uusername,upass,uactive,uexpiration_date,ufname,ulname) VALUES (?,?,?,?,?,?,?)';
        // $qin = $ponn->prepare($inquiz);
        // $qin->execute([1, $factor->username, $login, 1, '3021-03-21', $person->fname, $person->lname]);
    }
    $cou = 'SELECT dbcourse.*, codtbl_course.* FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse = codtbl_course.codecourse  WHERE dbcourse.numfactor=?';
    $qpo = $conn->prepare($cou);
    $qpo->execute([$factor->idfactor]);
    $rew = $qpo->fetchAll(PDO::FETCH_OBJ);

    $studentInfo = retPerson($factor->username);
    $studentFullName = $studentInfo
        ? trim((string)$studentInfo->fname . ' ' . (string)$studentInfo->lname)
        : (string)$factor->username;
    foreach ($rew as $activatedCourse) {
        CreateSchoolNotification(
            'new_student',
            'ثبت‌نام دانش‌آموز جدید: ' . $studentFullName,
            'ثبت‌نام دانش‌آموز پس از تأیید نهایی فاکتور در پایه «' . (string)$activatedCourse->coursename . '» فعال شد.',
            (int)($_SESSION['admin']['idadmin'] ?? 0),
            (string)($_SESSION['admin']['user'] ?? ''),
            (int)$activatedCourse->codecourse,
            !empty($activatedCourse->class_id) ? (int)$activatedCourse->class_id : null,
            (string)$factor->username,
            'admin/panel/suporting.php?user=' . rawurlencode((string)$factor->username)
                . '&course=' . rawurlencode((string)$activatedCourse->codecourse),
            'dbcourse_activation',
            $factor->idfactor . ':' . $factor->username . ':' . $activatedCourse->codecourse
        );
    }
//    foreach ($rew as $tbt) {
//        $existingUgumid = checkGroupAddByApi($tbt->codecourse, $tbt->username);
//        if (!$existingUgumid) {
//            $ugid = retIdGroupByApi($tbt->codecourse);
//            $uid = GetUserIdByApi($tbt->username);
//            AddUserToGroupByApi($ugid, $uid);
//            // $add = 'INSERT INTO `_ugroups_umembers`(ugid_parent,uid) VALUES (?,?) ';
//            // $qad = $ponn->prepare($add);
//            // $qad->execute([$ugid, $uid]);
//        }
//    }
    foreach ($rew as $tbt) {
        $uid = GetUserIdByApi($tbt->username);
        // باز کردن فایل لاگ برای دیباگ
        $logMsg = "\n--- بررسی فاکتور پایه: " . $tbt->id . " ---\n";

        // ۱. دریافت آیدی کلاس
        $class_id = isset($tbt->class_id) ? $tbt->class_id : null;
        $logMsg .= "Class ID: " . ($class_id ? $class_id : "NULL یا خالی") . "\n";

        if ($class_id) {
            // ثبت هم‌زمان عضویت پایه و زیرپایهٔ کلاس در OES.
            $enrollmentSync = syncStudentCourseClassToOes(
                (string) $tbt->username,
                (int) $tbt->codecourse,
                (int) $class_id
            );
            $logMsg .= "نتیجه همگام‌سازی پایه/زیرپایه: " . json_encode($enrollmentSync, JSON_UNESCAPED_UNICODE) . "\n";

            // ۲. دریافت ugid از دیتابیس
            $stmt = $conn->prepare("SELECT ugid FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $logMsg .= "نتیجه جستجوی کلاس در دیتابیس: " . ($class_info ? json_encode($class_info) : "کلاس پیدا نشد") . "\n";

            if ($class_info && !empty($class_info['ugid'])) {
                $ugid = $class_info['ugid'];
                $logMsg .= "UGID پیدا شد: " . $ugid . "\n";

                // ۳. گرفتن آیدی کاربر از سامانه آزمون
                
                $logMsg .= "آیدی کاربر در آزمون (UID): " . ($uid ? $uid : "پیدا نشد!") . "\n";
                if ($uid) {
                    // ۴. ثبت در گروه
                    $result = AddUserToGroupByApi($ugid, $uid);
                    $logMsg .= "نتیجه افزودن به گروه: " . json_encode($result) . "\n";
                } else {
                    $logMsg .= "خطا: کاربر در سامانه آزمون پیدا نشد، در نتیجه به گروه اضافه نمی‌شود.\n";
                }
            } else {
                $logMsg .= "خطا: UGID برای این کلاس در جدول classes خالی است.\n";
            }
        } else {
            $logMsg .= "خطا: کاربر هیچ کلاسی را انتخاب نکرده (class_id خالی است).\n";
        }
        
        $package_id = isset($tbt->package_id) ? $tbt->package_id : null;
        
        if ($package_id) {
            if ($uid) {
                $output = AddUserTestPaymentByApi($package_id, $uid);
            }
        }

        // ذخیره لاگ در فایل متنی در همان پوشه (موقتی برای پیدا کردن مشکل)
        file_put_contents('debug_log.txt', $logMsg, FILE_APPEND);
    }

    //            //
    if (ActivatePublic('actsms_success_card')) {
        //Start send sms
        $ret = retPerson($deposit->username);
        $text = number_format($factor->factorfee) . ';' . $factor->idfactor;
        SendSms($ret->tel1, $text, 171702, $deposit->username);
        //
        $sms = 'SELECT * FROM dbcourse INNER JOIN codtbl_course ON dbcourse.codecourse=codtbl_course.codecourse  WHERE dbcourse.numfactor=?';
        $qsm = $conn->prepare($sms);
        $qsm->execute([$factor->idfactor]);
        $result = $qsm->fetchAll(PDO::FETCH_OBJ);

        foreach ($result as $tes) {
            $actsms = retPerson($tes->username);
            $text = $actsms->fname . ';' . $actsms->lname . '; ' . $tes->coursename;
            SendSms($actsms->tel1, $text, 187461, $tes->username);
        }
        // end send SMS
    }

    $_SESSION['message'] = 'واریزی موردنظر تایید شد و پایه های مربوطه برای دانش آموز رزرو شدند';
    header('Location: verify_by_manager.php');
    die();
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
                                        <p>لیست فاکتور های منتظر تایید</p>
                                        <div class="table">
                                            <table class="table table-bordered table-hover text-center align-middle"
                                                   id="example1">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>کارشناس درخواست داده</th>
                                                    <th>کدملی</th>
                                                    <th>نام دانش آموز</th>
                                                    <th>شماره فاکتور</th>
                                                    <th>به حساب</th>
                                                    <th>مبلغ واریز</th>
                                                    <th>تاریخ واریز</th>
                                                    <th>روش واریز</th>
                                                    <th>شماره پیگیری</th>
                                                    <th>اقدامات</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($deposits)) { ?>
                                                    <tr class="align-middle text-center">
                                                        <td colspan="11">موردی یافت نشد</td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <?php foreach ($deposits as $deposit) {
                                                        $jalaliCreated = '';
                                                        if (!empty($deposit->payment_date) && strpos($deposit->payment_date, '-') !== false) {
                                                            list($gy, $gm, $gd) = explode('-', $deposit->payment_date);
                                                            $jalaliCreated = implode('/', gregorian_to_jalali((int)$gy, (int)$gm, (int)$gd));
                                                        }
                                                        ?>
                                                        <tr class="align-middle text-center">
                                                            <td>
                                                                <div class="btn btn-sm ripple btn-info btn-icon"
                                                                     onclick="window.open('../panel/suporting.php?Id=<?= htmlspecialchars($deposit->username) ?>','پشتیبانی','width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                                    <i class="fa fa-key"></i>
                                                                </div>
                                                            </td>
                                                            <td><?= htmlspecialchars($deposit->requested_by) ?></td>
                                                            <td><?= htmlspecialchars($deposit->username) ?></td>
                                                            <td><?= htmlspecialchars($deposit->fname . ' ' . $deposit->lname) ?></td>
                                                            <td><?= htmlspecialchars($deposit->factor_id) ?></td>
                                                            <td><?= htmlspecialchars($deposit->card) ?></td>
                                                            <td><?= htmlspecialchars(number_format($deposit->factorfee)) ?></td>
                                                            <td><?= htmlspecialchars($jalaliCreated) ?></td>
                                                            <td><?= htmlspecialchars($deposit->payment_method) ?></td>
                                                            <td><?= htmlspecialchars($deposit->ref_id) ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-outline-primary dropdown-toggle"
                                                                            type="button" data-bs-toggle="dropdown"
                                                                            aria-expanded="false">
                                                                        اقدام
                                                                        <i class="fas fa-caret-down"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-end text-start">
                                                                        <div class="dropdown-item text-success "
                                                                             style="cursor: pointer;"
                                                                             style="text-align: right"
                                                                             onclick="if(confirm('آیا اطمینان دارید ؟')) { window.location.href='verify_by_manager.php?verify=<?= $deposit->id ?>'; }">
                                                                            <i class="fe fe-check-square"></i> ثبت و
                                                                            تایید واریزی
                                                                        </div>
                                                                        <div class="dropdown-item "
                                                                             style="cursor: pointer;"
                                                                             style="text-align: right"
                                                                             onclick="window.open('../Bill/viewfactor.php?id='+'<?php echo $deposit->factor_id ?>','مشاهده فاکتور'
                                                                                     ,'width=600,height=900,menubar=0,toolbar=0,resizable=0,left=500,top=50,status=0,scrollbars=0,location=0' )">
                                                                            <i class="fe fe-search"></i>
                                                                            مشاهده فاکتور
                                                                        </div>
                                                                        <button class="dropdown-item" type="button">
                                                                            <i class="fe fe-file-text"></i>
                                                                            فیش‌های واریزی
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
                        <!--                        <select name="new_admin_id" class="form-select" required>-->
                        <!--                            <option value="">انتخاب کنید...</option>-->
                        <!--                            --><?php
                        //                            $sql = 'SELECT username FROM dbadmin WHERE adminrole = 6';
                        //                            $stmt = $conn->prepare($sql);
                        //                            $stmt->execute();
                        //                            $admins = $stmt->fetchAll(PDO::FETCH_OBJ);
                        //
                        //                            $sqll = 'SELECT requested_by FROM change_expert_requests WHERE '
                        //                            foreach ($admins as $admin) {
                        //
                        ?>
                        <!--                                <option value="--><?php //= $admin->username
                        ?><!--">-->
                        <?php //= $admin->username
                        ?><!--</option>-->
                        <!--                            --><?php //}
                        ?>
                        <!--                        </select>-->
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

    // document.getElementById('transferModal').addEventListener('show.bs.modal', function (event) {
    //     // Button that triggered the modal
    //     var button = event.relatedTarget;
    //     // Extract info from data-codemeli attribute
    //     var codemeli = button.getAttribute('data-codemeli');
    //     var id = button.getAttribute('data-id');
    //     // Update the modal's hidden input
    //     var modalInput = document.getElementById('modal_codemeli');
    //     var modalInput2 = document.getElementById('modal_req');
    //     modalInput.value = codemeli;
    //     modalInput2.value = id;
    // });

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
