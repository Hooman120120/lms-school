<?php
include_once '../dbconfig.php';
include_once '../inc/function_login.php';
include_once '../inc/function.php';
include_once '../inc/jdf.php';
if (CheckBlacklist_ip(GetIp())) {
    include_once '../../blackList.html';
    exit;
}
if (!isset($_SESSION['auth'])) {
    header('location: ../login.php');
    die();
}
//Protecting against SESSION Hijaking
if ($_SESSION['auth']['ip'] !== GetIp() and $_SESSION['admin']['os'] != GetOs()) {
    session_unset();
    header('location: ../login.php');
    die();
}
//* Start Check Session JUST ONE Session
$re = sessionid($_SESSION['auth']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('login.php'));
    die();
}

function getStatusStyle($status)
{
    switch ($status) {
        case 'حاضر':
            return ['cls' => 'success', 'icon' => 'fa-check-circle'];
        case 'غایب':
            return ['cls' => 'danger', 'icon' => 'fa-times-circle'];
        case 'دیر':
            return ['cls' => 'warning', 'icon' => 'fa-exclamation-circle'];
        default:
            return ['cls' => 'secondary', 'icon' => 'fa-clock-o'];
    }
}

// Check if this is an AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_attendance' && isset($_POST['group_id'])) {
    $sessionId = $_POST['session_id'];

    // Fetch students for the selected session
//    $sql = 'SELECT dbattendance.*, dbpersonal.* FROM dbattendance INNER JOIN dbpersonal ON dbattendance.username = dbpersonal.codemeli WHERE coding = ?';
    $sql = "SELECT 
    dbpersonal.*, 
    dbcourse.*,
    COALESCE(dbattendance.status, 'غایب') AS status, -- Automatically sets 'غایب' if record is missing
    dbattendance.coding
FROM dbcourse
INNER JOIN dbpersonal ON dbpersonal.codemeli = dbcourse.username
LEFT JOIN dbattendance ON dbcourse.username = dbattendance.username 
    AND dbattendance.coding = ?  -- Condition moved HERE
WHERE dbcourse.group_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sessionId, $_POST['group_id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON and stop further execution
    header('Content-Type: application/json');
    echo json_encode($members);
    exit;
}

$groupSql = 'SELECT * FROM handhug_groups WHERE id = ?';
$groupStmt = $conn->prepare($groupSql);
$groupStmt->execute([$_GET['group']]);
$group = $groupStmt->fetch(PDO::FETCH_OBJ);

$sql = 'SELECT * FROM tbl_attendanced WHERE codecourse = ? AND group_id = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$group->codecourse, $_GET['group']]);
$sessions = $stmt->fetchAll(PDO::FETCH_OBJ);


//$sessions = [
//    'جلسه اول' => [
//        ['name' => 'نیما کیانوش', 'status' => 'حاضر'],
//        ['name' => 'یه دانش آموز غایب', 'status' => 'غایب'],
//        ['name' => 'یه دانش آموز با وضعیت دگیر', 'status' => 'وضعیت دیگر']
//    ],
//    'جلسه دوم' => [
//        ['name' => 'یه دانش آموز حاضر', 'status' => 'غایب'],
//        ['name' => 'یه دانش آموز غایب', 'status' => 'حاضر'],
//        ['name' => 'یه دانش آموز با وضعیت دیگه', 'status' => 'وضعیت دیگه']
//    ]
//];

$firstDate = array_key_first($sessions);
//* End Check Session JUST ONE Session
include_once '../temp.html';
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
    <title> میزکار من | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        :root {
            --primary-soft: rgba(0, 102, 204, 0.1);
            --success-soft: rgba(40, 167, 69, 0.1);
            --danger-soft: rgba(220, 53, 69, 0.1);
            --warning-soft: rgba(255, 193, 7, 0.1);
        }

        .shadow-card {
            box-shadow: 0 0.75rem 1.5rem rgba(18, 38, 63, 0.03);
            border: 1px solid #f0f2f5;
        }

        /* Soft Badges */
        .bg-success-soft {
            background-color: var(--success-soft);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .bg-danger-soft {
            background-color: var(--danger-soft);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .bg-warning-soft {
            background-color: var(--warning-soft);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .bg-secondary-soft {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        /* List Item Hover */
        .member-item {
            transition: all 0.2s ease-in-out;
            border-radius: 8px !important;
            margin-bottom: 5px;
            border: 1px solid transparent;
        }

        .member-item:hover {
            background-color: #ffffff !important;
            border-color: #e0e6ed;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .avatar-title {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Modern Scrollbar */
        #memberList::-webkit-scrollbar {
            width: 5px;
        }

        #memberList::-webkit-scrollbar-thumb {
            background: #dcdcdc;
            border-radius: 10px;
        }
    </style>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<div id="global-loader">
    <img src="<?php echo url('assets/img/media/loader.gif') ?>" class="loader-img" alt="درحال بارگذاری">
</div>
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
                <div class="card shadow-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="rounded-circle bg-info-transparent text-info d-flex align-items-center justify-content-center shadow-sm"
                                 style="width:55px;height:55px; background: rgba(1, 184, 255, 0.1);">
                                <i class="fa fa-calendar-check-o fa-lg"></i>
                            </div>
                            <div class="ms-3 mr-3">
                                <h5 class="mb-0 fw-bold">حضور و غیاب جلسات</h5>
                                <p class="text-muted small mb-0">انتخاب جلسه برای مشاهده وضعیت اعضا</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">انتخاب جلسه آموزشی:</label>
                            <select id="sessionSelect" class="form-select form-select-lg shadow-sm border-0 bg-light">
                                <?php if (!empty($sessions)) { ?>
                                    <option>انتخاب کنید</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session->id ?>"><?= $session->classname ?></option>
                                    <?php endforeach;
                                } else { ?>
                                    <option value="none">کلاسی برگزار نشده است</option>
                                <?php } ?>
                            </select>
                        </div>

                        <div id="memberList" class="list-group list-group-flush flex-grow-1 overflow-auto pe-2"
                             style="max-height:450px;">
                            <div class="text-center p-5 text-muted">لطفاً یک جلسه را انتخاب کنید</div>
                        </div>

                        <!--                        <div class="mt-4">-->
                        <!--                            <button id="detailsBtn" class="btn btn-primary w-100 shadow-sm py-2">-->
                        <!--                                <i class="fa fa-bar-chart me-2"></i> مشاهده تحلیل کلی جلسه-->
                        <!--                            </button>-->
                        <!--                        </div>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="sessionDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h6 class="modal-title">جزئیات جلسه</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3 text-center">
                        <span id="sessionTitle" class="badge bg-info p-2"></span>
                    </div>

                    <ul id="sessionStats" class="list-group mb-3"></ul>

                    <div id="sessionMembers" class="list-group"></div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        بستن
                    </button>
                </div>

            </div>
        </div>
    </div>


    <?php include '../footers.php'; ?>
    <script>
        //const sessions = <?php //echo json_encode($sessions, JSON_UNESCAPED_UNICODE); ?>//;
        //
        //function getStatusConfig(status) {
        //    let config = {cls: 'secondary', icon: 'fa-clock-o'};
        //    if (status === 'حاضر') config = {cls: 'success', icon: 'fa-check-circle'};
        //    if (status === 'غایب') config = {cls: 'danger', icon: 'fa-times-circle'};
        //    if (status === 'دیر') config = {cls: 'warning', icon: 'fa-exclamation-circle'};
        //    return config;
        //}
        //
        //function renderMembers(date) {
        //    const memberList = document.getElementById('memberList');
        //    memberList.innerHTML = '';
        //
        //    sessions[date].forEach(m => {
        //        const config = getStatusConfig(m.status);
        //        const firstLetter = m.name.charAt(0);
        //
        //        memberList.innerHTML += `
        //    <div class="list-group-item d-flex align-items-center p-3 member-item shadow-none">
        //        <div class="avatar-sm ms-3">
        //            <div class="avatar-title rounded-circle bg-light text-primary border">${firstLetter}</div>
        //        </div>
        //        <div class="flex-grow-1">
        //            <h6 class="mb-0 fw-bold">${m.name}</h6>
        //            <span class="text-${config.cls} small"><i class="fa ${config.icon} me-1"></i> ${m.status}</span>
        //        </div>
        //        <div class="d-flex align-items-center gap-2">
        //            <span class="badge rounded-pill bg-${config.cls}-soft px-3 py-2">${m.status}</span>
        //            <button onclick="showStudentInfo('${m.name}')" class="btn btn-icon btn-light rounded-circle shadow-sm">
        //                <i class="fa fa-chevron-left text-primary"></i>
        //            </button>
        //        </div>
        //    </div>`;
        //    });
        //}
        //
        //document.getElementById('sessionSelect').addEventListener('change', e => renderMembers(e.target.value));

        document.getElementById('sessionSelect').addEventListener('change', function () {
            const sessionId = this.value;
            const memberList = document.getElementById('memberList');

            if (sessionId === 'none' || !sessionId) return;

            // Show loading state
            memberList.innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">در حال بارگذاری لیست حضور و غیاب...</p>
                </div>`;

            // Create Form Data for POST
            const formData = new FormData();
            formData.append('action', 'get_attendance');
            formData.append('session_id', sessionId);
            formData.append('group_id', <?= $_GET['group'] ?>);

            // Send POST request to the SAME page
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    memberList.innerHTML = '';

                    if (data.length === 0) {
                        memberList.innerHTML = '<div class="alert alert-light text-center">لیست حضور و غیاب برای این جلسه ثبت نشده است.</div>';
                        return;
                    }

                    data.forEach(m => {
                        const config = getStatusConfig(m.status);
                        const firstLetter = m.name ? m.name.charAt(0) : '?';

                        const itemHtml = `
            <div class="list-group-item d-flex align-items-center p-3 member-item shadow-none border mb-2">
                <div class="flex-grow-1">
                    <h6 class="mb-0 fw-bold">${m.fname + ' ' + m.lname}</h6>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill bg-${config.cls}-soft px-3 py-2">
                        <i class="fa ${config.icon} me-1"></i> ${m.status != 'غایب' ? 'حاضر' : 'غایب'}
                    </span>
                    <a href="member-info.php?user=${m.codemeli}" class="btn btn-icon btn-light rounded-circle shadow-sm">
                       <i class="fa fa-chevron-left text-primary"></i>
                   </a>
                </div>
            </div>`;
                        memberList.insertAdjacentHTML('beforeend', itemHtml);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    memberList.innerHTML = '<div class="alert alert-danger">خطا در برقراری ارتباط با سرور.</div>';
                });
        });

        // Helper for status styles
        function getStatusConfig(status) {
            switch (status) {
                case 'غایب':
                    return {cls: 'danger', icon: 'fa-times-circle'};
                default:
                    return {cls: 'success', icon: 'fa-check-circle'};
            }
        }
    </script>


    <!--End Footer-->
</body>

</html>