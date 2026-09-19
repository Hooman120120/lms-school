<?php
date_default_timezone_set('Asia/Tehran');
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';

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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cuid'], $_POST['selected_codemeli'])) {
    header('Content-Type: application/json');
    $cuid = $_POST['cuid'];
    $selected_codemeli = $_POST['selected_codemeli'];

    try {
        $stmt = $conn->prepare("UPDATE calls SET codemeli = :codemeli WHERE cuid = :cuid AND codemeli = '0000000000'");
        $stmt->execute(['codemeli' => $selected_codemeli, 'cuid' => $cuid]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found or already updated.']);
        }
    } catch (PDOException $e) {
        // In production, log this error instead of echoing
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit; // Important: stop script execution after API call
}
// Fetch phone from URL
// Fetch parameters from URL
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$cuid = isset($_GET['cuid']) ? trim($_GET['cuid']) : '';

$users = [];
$callHistory = [];
$userType = 'unknown'; // 'single', 'multi', 'temp', 'unknown'

if (!empty($phone)) {
    // 1. Check dbpersonal for ALL matches (removed LIMIT 1)
    $stmt_p = $conn->prepare("SELECT fname, lname, paye, city, state, codemeli FROM dbpersonal WHERE ? IN (tel1, tel2, tel3, tel_home)");
    $stmt_p->execute([$phone]);
    $users = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) === 1) {
        $userType = 'single';
    } elseif (count($users) > 1) {
        $userType = 'multi';
    } else {
        // 2. Check temporary_personal if no main user found
        $stmt_t = $conn->prepare("SELECT first_name AS fname, last_name AS lname, city, state FROM temporary_personal WHERE phone = ? LIMIT 1");
        $stmt_t->execute([$phone]);
        $tempUser = $stmt_t->fetch(PDO::FETCH_ASSOC);

        if ($tempUser) {
            $users[] = $tempUser; // Add to users array
            $userType = 'temp';
        }
    }

    // 3. Fetch Call History
    $stmtHistory = $conn->prepare("SELECT * FROM calls WHERE remote_number = ? ORDER BY created_at DESC");
    $stmtHistory->execute([$phone]);
    $callHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['action']) && $_GET['action'] === 'check_active_call') {
    header('Content-Type: application/json');
    $check_cuid = isset($_GET['cuid']) ? trim($_GET['cuid']) : '';

    if (empty($check_cuid)) {
        echo json_encode(['status' => 'ended']);
        exit;
    }

    try {
        // Querying by unique CUID tracks both incoming and outgoing states instantly
        $stmt = $conn->prepare("SELECT 1 FROM active_calls WHERE cuid = ? LIMIT 1");
        $stmt->execute([$check_cuid]);
        $active = $stmt->fetchColumn();

        if ($active) {
            echo json_encode(['status' => 'active']);
        } else {
            echo json_encode(['status' => 'ended']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB error']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> تماس | <?php echo __TITLE ?></title>
    <?php include_once 'link.php'; ?>
    <!-- Enforce Bootstrap 5 -->
</head>

<body class="main-body leftmenu bg-light">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
<!-- End Loader -->

<!-- Page -->
<div class="page">
    <!-- Main Content-->
    <div class="main-content pt-4">
        <div class="container-fluid">

            <?php if(empty($phone)): ?>
                <div class="alert alert-warning text-center">شماره تماسی دریافت نشد.</div>
            <?php else: ?>

                <div class="row mb-4">
                    <div class="col-12 ">
                        <div class="card shadow-sm border-0 p-4">
                            <div class="card-header bg-white border-bottom-0 ">
                                <h4 class="card-title mb-0">
                                    اطلاعات تماس گیرنده
                                    <span class="text-primary dir-ltr d-inline-block ms-2"><?= htmlspecialchars($phone) ?></span>
                                </h4>
                            </div>
                            <div id="autoCloseAlert" class="alert alert-warning text-center d-none shadow-sm mt-3" role="alert">
                                <strong>تماس پایان یافت.</strong>
                                این صفحه تا <span id="countdownTimer" class="text-info fw-bold fs-5">60</span> ثانیه دیگر به طور خودکار بسته خواهد شد...
                            </div>

                            <div class="card-body">
                                <?php if ($userType === 'single' || $userType === 'temp'):
                                    $user = $users[0];
                                    ?>
                                    <div class="alert alert-<?php echo ($userType === 'single') ? 'success' : 'warning'; ?> d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="fs-5"><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></strong>
                                            (<?php echo ($userType === 'single') ? 'کاربر ثبت شده' : 'کاربر موقت'; ?>)
                                        </div>

                                        <?php if ($userType === 'single') {

                                            ?>
                                            <button class="btn btn-sm btn-info"
                                                    onclick="window.open('panel/suporting.php?Id=<?= htmlspecialchars($user['codemeli']) ?>',
                                                            'پشتیبانی',
                                                            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0')">
                                                <i class="fa fa-key"></i>
                                            </button>
                                        <?php } else { ?>
                                            <button class="btn btn-primary"
                                                    onclick="window.open(
                                                            'Student/experimental-add.php',
                                                            'ثبت موقت',
                                                            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                        )">
                                                <i class="text-white">ثبت موقت</i>
                                            </button>
                                        <?php } ?>
                                    </div>


                                    <div class="row g-3">
                                        <div class="col-md-4"><strong>استان:</strong> <?= htmlspecialchars($user['state']) ?></div>
                                        <div class="col-md-4"><strong>شهر:</strong> <?= htmlspecialchars($user['city']) ?></div>

                                        <?php if(isset($user['paye'])): ?>
                                            <div class="col-md-4">
                                                <strong>پایه:</strong>
                                                <?= (is_numeric($user['paye']) && (int)$user['paye'] > 12) ? 'فارغ‌التحصیل' : htmlspecialchars($user['paye']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                <?php elseif ($userType === 'multi'): ?>
                                    <div class="alert alert-warning">چندین کاربر با این شماره تلفن یافت شد...</div>

                                    <?php foreach($users as $user): ?>
                                        <div class="p-3 border rounded mb-2">
                                            <strong class="fs-6"><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></strong>
                                            <div class="text-muted small"> | شهر: <?= htmlspecialchars($user['city']) ?></div>
                                        </div>
                                    <?php endforeach; ?>

                                <?php else: ?>
                                    <div class="alert alert-secondary d-flex flex-row justify-content-between">
                                        <div>
                                            <h5 class="mb-0">اطلاعاتی یافت نشد</h5>
                                            <p class="mb-0 mt-2">ناشناس</p>
                                        </div>
                                        <div>
                                            <button class="btn btn-primary"
                                                    onclick="window.open(
                                                            'Student/experimental-add.php',
                                                            'ثبت موقت',
                                                            'width=1520,height=850,menubar=0,toolbar=0,resizable=0,left=100,top=50,status=0,scrollbars=0,location=0'
                                                        )">
                                                <i class="text-white">ثبت موقت</i>
                                            </button>
                                        </div>
                                    </div>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal for selecting user (Only rendered if needed) -->
                <?php if ($userType === 'multi'): ?>
                    <div class="modal fade" id="selectUserModal" tabindex="-1" aria-labelledby="selectUserModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="selectUserModalLabel">انتخاب کاربر صحیح</h5>
                                </div>
                                <div class="modal-body">
                                    <p>تماس به پایان رسید. لطفاً فرد صحیح را از بین گزینه‌های زیر انتخاب کنید.</p>

                                    <form id="selectUserForm">
                                        <input type="hidden" name="cuid" value="<?= htmlspecialchars($cuid) ?>">

                                        <div class="mb-3">
                                            <label for="selected_codemeli" class="form-label fw-bold">انتخاب کاربر:</label>
                                            <select class="form-select" name="selected_codemeli" id="selected_codemeli">
                                                <?php foreach ($users as $index => $user): ?>
                                                    <option value="<?= htmlspecialchars($user['codemeli']) ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?> (<?= htmlspecialchars($user['codemeli']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="saveUserSelection">ذخیره و بستن</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm p-4">
                            <div class="card-header bg-white pt-4 pb-3">
                                <h5 class="card-title mb-0">تاریخچه تماس ها</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($callHistory)): ?>
                                    <div class="table-responsive">
                                        <table id="example1" class="table table-hover table-striped align-middle">
                                            <thead class="table-light">
                                            <tr>
                                                <th>جهت</th>
                                                <th>داخلی</th>
                                                <th>وضعیت</th>
                                                <th>مدت مکالمه (ثانیه)</th>
                                                <th>تاریخ</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($callHistory as $call):
                                                $callTime = isset($call['created_at']) ? $call['created_at'] : null;
                                                $dirBadge = ($call['direction'] === 'in') ? '<span class="badge bg-primary">ورودی</span>' : '<span class="badge bg-secondary">خروجی</span>';
                                                $ansBadge = ($call['answered'] == 1) ? '<span class="badge bg-success">پاسخ داده شده</span>' : '<span class="badge bg-danger">بی پاسخ</span>';
                                                ?>
                                                <tr>
                                                    <td><?= $dirBadge ?></td>
                                                    <td>
                                                        <?= ($call['exten'] == '0') ? 'شماره مجموعه' : htmlspecialchars($call['exten']) ?>
                                                    </td>
                                                    <td><?= $ansBadge ?></td>
                                                    <td><?= htmlspecialchars($call['talk_seconds']) ?></td>
                                                    <td class="dir-ltr text-end">
                                                        <?php if ($callTime): ?>
                                                            <?= jdate('Y/m/d H:i', strtotime($callTime)) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 text-center text-muted">
                                        تاریخچه تماسی برای این شماره وجود ندارد.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userType = '<?= $userType ?>';
        const cuid = '<?= $cuid ?>';
        const phone = '<?= htmlspecialchars($phone) ?>';

        console.log('Script loaded. Phone:', phone, '| UserType:', userType, '| CUID:', cuid);

        let checkStatusInterval;

        function closeCurrentWindow() {
            window.open('', '_self', '');
            window.close();
        }

        if (phone) {
            console.log('Phone exists, starting polling interval...');
            checkStatusInterval = setInterval(checkActiveCall, 2000);
        } else {
            console.warn('No phone number found. Polling will not start.');
        }

        function checkActiveCall() {
            console.log('Checking active call status via CUID...');
            fetch(`?action=check_active_call&cuid=${encodeURIComponent(cuid)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Server response:', data);

                    if (data.status === 'ended') {
                        console.log('Call has ended. Stopping interval.');
                        clearInterval(checkStatusInterval);

                        if (userType === 'multi' && cuid) {
                            console.log('Triggering Multi-User Modal...');
                            const selectUserModalEl = document.getElementById('selectUserModal');
                            if (selectUserModalEl) {
                                const selectUserModal = new bootstrap.Modal(selectUserModalEl);
                                selectUserModal.show();
                            } else {
                                console.error('Modal element #selectUserModal not found in HTML!');
                            }
                        } else {
                            console.log('Triggering Countdown...');
                            triggerCountdown();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error polling active call:', error);
                    clearInterval(checkStatusInterval);
                });
        }

        function triggerCountdown() {
            const alertBox = document.getElementById('autoCloseAlert');
            const timerSpan = document.getElementById('countdownTimer');

            if (alertBox) alertBox.classList.remove('d-none');

            let timeLeft = 60;
            const countdownInterval = setInterval(function() {
                timeLeft--;
                if (timerSpan) timerSpan.innerText = timeLeft;

                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    console.log('Attempting to close window...');
                    closeCurrentWindow();
                }
            }, 1000);
        }

        // --- ADDED: CLICK HANDLER FOR SAVING MULTI-USER SELECTION ---
        const saveBtn = document.getElementById('saveUserSelection');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                const form = document.getElementById('selectUserForm');
                if (!form) return;

                const formData = new FormData(form);

                saveBtn.disabled = true;
                saveBtn.innerText = 'در حال ذخیره...';

                // Send the POST request to the top-level PHP processing logic
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Selection saved successfully!');

                            // Hide the modal cleanly using Bootstrap's API
                            const modalEl = document.getElementById('selectUserModal');
                            const modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            }

                            // Close the window since the operator job for this call is done
                            closeCurrentWindow();
                        } else {
                            alert('خطا در ثبت: ' + (data.message || 'خطای ناشناخته'));
                            saveBtn.disabled = false;
                            saveBtn.innerText = 'ذخیره و بستن';
                        }
                    })
                    .catch(error => {
                        console.error('Error saving user selection:', error);
                        alert('خطا در ارتباط با سرور.');
                        saveBtn.disabled = false;
                        saveBtn.innerText = 'ذخیره و بستن';
                    });
            });
        }
    });
</script>
<!-- Jquery js-->
<script src="<?php echo url('assets/plugins/jquery/jquery.min.js') ?>"></script>
<!-- Bootstrap js-->
<script src="<?php echo url('assets/plugins/bootstrap/js/popper.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/bootstrap/js/bootstrap-rtl.js') ?>"></script>
<!-- Perfect-scrollbar js -->
<script src="<?php echo url('assets/plugins/perfect-scrollbar/perfect-scrollbar.min-rtl.js') ?>"></script>
<!-- Internal Jquery-Ui js-->
<script src="<?php echo url('assets/plugins/jquery-ui/ui/widgets/datepicker.js') ?> "></script>
<!-- Sidemenu js -->
<script src="<?php echo url('assets/plugins/sidemenu/sidemenu-rtl.js')?>"></script>
<!-- Sidebar js -->
<script src="<?php echo url('assets/plugins/sidebar/sidebar-rtl.js') ?>"></script>
<!-- Peity js-->
<script src="<?php echo url('assets/plugins/peity/jquery.peity.min.js') ?>"></script>
<!-- Internal Ion-rangeslider js-->
<script src="<?php echo url('assets/plugins/ion-rangeslider/js/ion.rangeSlider.min.js') ?>"></script>
<!-- Internal Dashboard js-->
<script src="<?php echo url('assets/js/index.js') ?>"></script>
<!-- Internal Specturm-colorpicker js-->
<script src="<?php echo url('assets/plugins/spectrum-colorpicker/spectrum.js') ?>"></script>
<!-- Sticky js -->
<script src="<?php echo url('assets/js/sticky.js') ?>"></script>
<!-- Custom js -->
<script src="<?php echo url('assets/js/custom.js') ?>"></script>

<!-- Select2 js-->
<script src="<?php echo url('assets/plugins/select2/js/select2.min.js') ?>"></script>
<!-- Internal Form-elements js-->
<script src="<?php echo url('assets/js/advanced-form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/select2.js') ?>"></script>
<script src="<?php echo url('assets/plugins/prism/prism.js') ?>"></script>
<script src="<?php echo url('assets/js/form-validation.js') ?>"></script>
<script src="<?php echo url('assets/plugins/parsleyjs/parsley.min.js') ?>"></script>
<!-- Internal Jquery.maskedinput js-->
<script src="<?php echo url('assets/plugins/jquery.maskedinput/jquery.maskedinput.js') ?>"></script>
<!-- InternalSumoselect js-->
<script src="<?php echo url('assets/plugins/sumoselect/jquery.sumoselect.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/jquery.dataTables.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/dataTables.bootstrap4.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/dataTables.responsive.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/dataTables.buttons.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.bootstrap4.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/jszip.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/pdfmake.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/vfs_fonts.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.html5.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.print.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.colVis.min.js') ?>"></script>
<script src="<?php echo url('assets/js/table-data.js') ?>"></script>
<script src="<?php echo url('assets/js/circle-progress.min.js') ?>"></script>
<script src="<?php echo url('assets/js/chart-circle.js') ?>"></script>
<script src="<?php echo url('assets/plugins/chart/chart.js') ?>"></script>
<script src="<?php echo url('assets/plugins/jspdf/jspdf.umd.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/html2canvas/html2canvas.min.js') ?>"></script><!--End Footer-->
</body>
</html>
