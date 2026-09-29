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


$currentUserRole = isset($_SESSION['admin']['adminrole']) ? (int)$_SESSION['admin']['adminrole'] : -1;
$currentUsername = $_SESSION['admin']['user'];

$stAd = $conn->prepare("SELECT idadmin FROM dbadmin WHERE username = ? LIMIT 1");
$stAd->execute([$currentUsername]);
$currAdmin = $stAd->fetch(PDO::FETCH_ASSOC);
$currAdminId = $currAdmin ? $currAdmin['idadmin'] : 0;

$viewTitle = '';
if ($currentUserRole === 0) {
    $viewTitle = 'مدیریت';
} elseif ($currentUserRole === 52) {
    $viewTitle = 'معاونت آموزش';
} else {
    die(); // اگر نقش کاربر مجاز نیست
}

$ticketTypes = [
    'student_suspension_requests' => 'تعلیق',
    'cancel_student_operator_request' => 'لغو پشتیبان'
];

// -------------------------------------------------------------
// پردازش فرم اقدام (ثبت وضعیت و آپدیت جداول مربوطه)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_action'])) {
    AdminRequire('notifications.referred.act');
    if ($currentUserRole === 0 || $currentUserRole === 52) { // اجازه اقدام به مدیریت و معاونت
        $referral_id = (int)$_POST['referral_id'];

        // تبدیل وضعیت به عدد صحیح تا با ساختار tinyint دیتابیس همخوانی داشته باشد
        $status = isset($_POST['status']) && $_POST['status'] !== '' ? (int)$_POST['status'] : null;
        $referee_comment = trim($_POST['referee_comment'] ?? '');

        if ($status !== null) {
            try {
                $conn->beginTransaction();

                // 1. آپدیت جدول ارجاعات همراه با تاریخ آپدیت
                $updQuery = "UPDATE ticket_referrals 
                             SET status = ?, referee_comment = ?, updated_at = CURRENT_TIMESTAMP 
                             WHERE id = ? AND referred_to_id = ?";
                $updStmt = $conn->prepare($updQuery);
                $isUpdated = $updStmt->execute([$status, $referee_comment, $referral_id, $currAdminId]);

// بررسی اجرای موفق کوئری به جای rowCount
                if ($isUpdated) {

                    // 2. واکشی اطلاعات ارجاع برای پیدا کردن تیکت اصلی
                    $refInfoStmt = $conn->prepare("SELECT ticket_id, ticket_type FROM ticket_referrals WHERE id = ? LIMIT 1");
                    $refInfoStmt->execute([$referral_id]);
                    $refInfo = $refInfoStmt->fetch(PDO::FETCH_ASSOC);

                    if ($refInfo) {
                        // --- حالت اول: تیکت تعلیق ---
                        if ($refInfo['ticket_type'] === 'student_suspension_requests') {
                            $ticketStmt = $conn->prepare("SELECT student_username FROM student_suspension_requests WHERE id = ? LIMIT 1");
                            $ticketStmt->execute([$refInfo['ticket_id']]);
                            $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

                            if ($ticket && !empty($ticket['student_username'])) {
                                $student_codemeli = $ticket['student_username'];

                                if ($status === 1) { // تایید
                                    $dbPersStmt = $conn->prepare("UPDATE dbpersonal SET suspension_mode = 1, suspension_reason = ? WHERE codemeli = ?");
                                    $dbPersStmt->execute([$referee_comment, $student_codemeli]);
                                    hf_control_release($conn, hf_control_login_id($student_codemeli), true);
                                } elseif ($status === 0) { // رد
                                    $response = hf_control_unsuspend_student($conn, $student_codemeli);
                                    if (isset($response['allowed']) && !$response['allowed']) {
                                        throw new RuntimeException(hf_control_denial_message($response));
                                    }
                                }
                            }
                        }
                        // --- حالت دوم: تیکت لغو پشتیبان ---
                        elseif ($refInfo['ticket_type'] === 'cancel_student_operator_request') {
                            $ticketStmt = $conn->prepare("SELECT student_username, requested_by FROM cancel_student_operator_request WHERE id = ? LIMIT 1");
                            $ticketStmt->execute([$refInfo['ticket_id']]);
                            $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

                            if ($ticket && !empty($ticket['student_username'])) {
                                $studentUsername = $ticket['student_username'];
                                $requestedBy = $ticket['requested_by'];

                                if ($status === 1) { // تایید
                                    $dbCourseStmt = $conn->prepare("
                                        UPDATE dbcourse
                                        SET has_operator = 0,
                                            suporter = NULL
                                        WHERE username = ?
                                    ");
                                    $dbCourseStmt->execute([$studentUsername]);
                                } elseif ($status === 0) { // رد
                                    $dbCourseStmt = $conn->prepare("
                                        UPDATE dbcourse
                                        SET suporter = ?,
                                            has_operator = 1
                                        WHERE username = ?
                                    ");
                                    $dbCourseStmt->execute([$requestedBy, $studentUsername]);
                                }

                                // (اختیاری) اگر می‌خواهید وضعیت تیکت اصلی هم آپدیت شود، می‌توانید اینجا کوئری آپدیت cancel_student_operator_request را هم اضافه کنید
                                // مانند: UPDATE cancel_student_operator_request SET status = ? WHERE id = ?
                            }
                        }
                    }
                } else {
                    throw new Exception("عملیات ناموفق بود. مجاز به انجام این کار نیستید.");
                }

                // ثبت نهایی تمام تغییرات
                $conn->commit();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;

            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
            }
        }
    }
}

// -------------------------------------------------------------
// واکشی لیست تیکت‌ها جهت نمایش
// -------------------------------------------------------------
$filterType = $_GET['ticket_type'] ?? '';

$query = "
    SELECT tr.*, 
           rb.fname AS ref_by_fname, rb.lname AS ref_by_lname,
           rt.fname AS ref_to_fname, rt.lname AS ref_to_lname
    FROM ticket_referrals tr
    LEFT JOIN dbadmin rb ON tr.referred_by_id = rb.idadmin
    LEFT JOIN dbadmin rt ON tr.referred_to_id = rt.idadmin
    WHERE tr.referred_to_id = ? AND tr.status IS NULL
";
$params = [$currAdminId];

if (array_key_exists($filterType, $ticketTypes)) {
    $query .= " AND tr.ticket_type = ?";
    $params[] = $filterType;
}

$query .= " ORDER BY (tr.status IS NULL) DESC, tr.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);



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
    <title>تیکت های ارجاعی | <?php echo __TITLE ?></title>
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

                <div class="page-header">
                    <div>
                        <h2 class="main-content-title tx-24 mg-b-5">تیکت‌های ارجاعی به شما (<?php echo htmlspecialchars($viewTitle); ?>)</h2>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a class="badge bg-info rounded-pill px-3 py-2" href="<?php echo url('admin/notifications/referral_history.php') ?>">سوابق تعلیق / لغو</a>

                        <a class="nav-link p-0" data-bs-toggle="tooltip" href="#" title="در این صفحه تیکت هایی که به شما ارجاع داده شده را مشاهده میکنید. اگر ارجاعی تعلیق را تایید کنید دانش آموز تعلیق میشود و در اگر رد کنید حتی اگر ارجاع دهنده آن را تایید کرده باشد آن دانش آموز تعلیق نمیشود و بلعکس. برای لغو پشتیبانی اگر آن را تایید کنید پشتیبانی آن دانش آموز لغو میشود اما اگر آن را لغو کنید حتی اگر ارجاع دهنده آن را تایید کرده باشد دانش آموز به پشتیبانی که درخواست لغو پشتیبانی را داده بر میگردد و بلعکس.">
                            <i class="fe fe-info fs-4" style="color: gray;" title=""></i>
                        </a>
                    </div>

                </div>

                <div class="card custom-card">
                    <div class="card-body">
                        <form method="GET" action="" class="row align-items-end">
                            <div class="col-md-4">
                                <label for="ticket_type" class="form-label">فیلتر بر اساس نوع درخواست:</label>
                                <select name="ticket_type" id="ticket_type" class="form-control">
                                    <option value="">-- همه درخواست‌ها --</option>
                                    <?php foreach ($ticketTypes as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($filterType === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">اعمال فیلتر</button>
                            </div>
                            <?php if (!empty($filterType)): ?>
                                <div class="col-md-2 mt-3 mt-md-0">
                                    <a href="?" class="btn btn-outline-danger w-100">حذف فیلتر</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card custom-card mt-3">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="example1" class="table table-bordered table-striped mg-b-0 text-center">
                                <thead class="table-secondary">
                                <tr>
                                    <th>مشاهده</th>
                                    <th>نوع درخواست</th>
                                    <th>ارجاع دهنده</th>
                                    <th>توضیحات ارجاع</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ارجاع</th>
                                    <th>عملیات</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $modalsHtml = '';

                                if (empty($referrals)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">هیچ تیکت ارجاعی یافت نشد.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($referrals as $ref):

                                        $origInfo = ['fullname' => 'ناشناس', 'username' => 'نامشخص', 'reason' => 'ثبت نشده'];

                                        if ($ref['ticket_type'] == 'student_suspension_requests') {
                                            $stOrig = $conn->prepare("SELECT s.fname, s.lname, s.codemeli, t.requested_reason FROM student_suspension_requests t LEFT JOIN dbpersonal s ON t.student_username = s.codemeli WHERE t.id = ? LIMIT 1");
                                            $stOrig->execute([$ref['ticket_id']]);
                                            $data = $stOrig->fetch(PDO::FETCH_ASSOC);
                                            if($data){
                                                $origInfo['fullname'] = $data['fname'] . ' ' . $data['lname'];
                                                $origInfo['username'] = $data['codemeli'];
                                                $origInfo['reason'] = $data['requested_reason'];
                                            }
                                        }
                                        elseif ($ref['ticket_type'] == 'cancel_student_operator_request') {
                                            // واکشی اطلاعات مربوط به لغو پشتیبان برای مودال
                                            $stOrig = $conn->prepare("SELECT s.fname, s.lname, s.codemeli, t.requested_mode, t.requested_by FROM cancel_student_operator_request t LEFT JOIN dbpersonal s ON t.student_username = s.codemeli WHERE t.id = ? LIMIT 1");
                                            $stOrig->execute([$ref['ticket_id']]);
                                            $data = $stOrig->fetch(PDO::FETCH_ASSOC);
                                            if($data){
                                                $origInfo['fullname'] = trim($data['fname'] . ' ' . $data['lname']);
                                                $origInfo['username'] = $data['codemeli'];
                                                $reqModeTxt = ((int)$data['requested_mode'] === 1) ? 'لغو پشتیبانی' : 'فعال‌سازی مجدد پشتیبانی';
                                                $origInfo['reason'] = "نوع درخواست: " . $reqModeTxt . "\nدرخواست دهنده: " . $data['requested_by'];
                                            }
                                        }
                                        ?>

                                        <tr>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#modalInfo<?php echo $ref['id']; ?>">
                                                    <i class="fe fe-eye"></i> اصلی
                                                </button>
                                            </td>
                                            <td><span class="badge bg-dark p-2"><?php echo isset($ticketTypes[$ref['ticket_type']]) ? $ticketTypes[$ref['ticket_type']] : $ref['ticket_type']; ?></span></td>
                                            <td><?php echo htmlspecialchars(($ref['ref_by_fname'] ?? '') . ' ' . ($ref['ref_by_lname'] ?? '')); ?></td>

                                            <td style="max-width: 200px; white-space: normal;">
                                                <?php echo !empty($ref['referrer_comment']) ? htmlspecialchars($ref['referrer_comment']) : '-'; ?>
                                            </td>

                                            <td>
                                                <?php
                                                if ($ref['status'] === null) echo '<span class="badge bg-warning p-2 text-dark">در انتظار</span>';
                                                elseif ($ref['status'] == 1) echo '<span class="badge bg-success p-2">انجام شد</span>';
                                                elseif ($ref['status'] == 0) echo '<span class="badge bg-danger p-2">انجام نشد</span>';
                                                ?>
                                            </td>
                                            <td dir="ltr"><?php echo jdate('Y/m/d H:i', strtotime($ref['created_at'])); ?></td>

                                            <td>
                                                <?php if ($currentUserRole === 0 || $currentUserRole === 52): ?>
                                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAction<?php echo $ref['id']; ?>">
                                                        اقدام
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <?php ob_start(); ?>
                                        <div class="modal fade" id="modalInfo<?php echo $ref['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">اطلاعات تیکت اصلی (شناسه: <?php echo $ref['ticket_id']; ?>)</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-right">
                                                        <p><strong>نام و نام خانوادگی:</strong> <?php echo htmlspecialchars($origInfo['fullname']); ?></p>
                                                        <p><strong>نام کاربری:</strong> <span dir="ltr"><?php echo htmlspecialchars($origInfo['username']); ?></span></p>
                                                        <p><strong>جزئیات / علت درخواست:</strong><br> <?php echo nl2br(htmlspecialchars($origInfo['reason'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($currentUserRole === 0 || $currentUserRole === 52): ?>
                                        <div class="modal fade" id="modalAction<?php echo $ref['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">ثبت نتیجه اقدام</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-right">
                                                            <input type="hidden" name="referral_id" value="<?php echo $ref['id']; ?>">

                                                            <div class="form-group">
                                                                <label class="form-label">وضعیت کار: <span class="text-danger">*</span></label>
                                                                <select name="status" class="form-control" required>
                                                                    <option value="">انتخاب کنید...</option>
                                                                    <option value="1" <?php echo ($ref['status'] === 1) ? 'selected' : ''; ?>>تایید</option>
                                                                    <option value="0" <?php echo ($ref['status'] === 0) ? 'selected' : ''; ?>>رد</option>
                                                                </select>
                                                            </div>

                                                            <div class="form-group mt-3">
                                                                <label class="form-label">توضیحات :</label>
                                                                <textarea name="referee_comment" class="form-control" rows="4" required><?php echo htmlspecialchars($ref['referee_comment'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                            <button type="submit" name="submit_action" class="btn btn-success">ثبت اطلاعات</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                        <?php
                                        $modalsHtml .= ob_get_clean();
                                    endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php echo $modalsHtml; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
    <?php include '../../footers.php'; ?>

</div>
</body>
</html>
