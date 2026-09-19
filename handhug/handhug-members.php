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
$groupSql = 'SELECT * FROM handhug_groups WHERE id = ?';
$groupStmt = $conn->prepare($groupSql);
$groupStmt->execute([$_GET['group']]);
$group = $groupStmt->fetch(PDO::FETCH_OBJ);

$countMembersSql = 'SELECT COUNT(*) AS countMembers FROM dbcourse WHERE codecourse = ? AND group_id = ?';
$countMembersStmt = $conn->prepare($countMembersSql);
$countMembersStmt->execute([$group->codecourse, $_GET['group']]);
$countMembers = $countMembersStmt->fetch(PDO::FETCH_OBJ);

if (isset($_POST['get_user_profile']) && isset($_POST['username'])) {
    $username = $_POST['username'];
    $person = retPerson($username);

    if ($person) {
        $response = [
            'success' => true,
            'data' => [
                'person' => [
                    'fname' => $person->fname,
                    'lname' => $person->lname,
                    'username' => $person->codemeli,
                    'mobile' => $person->tel1 ?? 'ثبت نشده'
                ]
            ]
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'کاربر یافت نشد'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

//* End Check Session JUST ONE Session
include_once '../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="LMS hamsepar">
    <meta name="author" content="hamsepar.ir">
    <!-- Title -->
    <title> میزکار من | <?php echo __TITLE ?></title>
    <?php include_once '../inc/link.php'; ?>
    <style>
        .profile-initials {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-weight: bold;
        }

        .modal-avatar {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            font-size: 24px;
            margin: 0 auto 15px;
        }
    </style>
</head>

<body class="main-body leftmenu">
<!-- Loader -->
<!--<div id="global-loader">-->
<!--    <img src="--><?php //echo url('assets/img/media/loader.gif')
?><!--" class="loader-img" alt="درحال بارگذاری">-->
<!--</div>-->
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

            <!-- ===== Group Header ===== -->
            <div class="card mb-4">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <div>
                        <h5 class="mb-1">مشاهده اعضای گروه <?= $group->group_name ?></h5>
                        <small class="text-muted"><?= $countMembers->countMembers - 1 ?> عضو</small>
                    </div>
                </div>
            </div>

            <!-- ===== Members ===== -->
            <div class="row">
                <?php
                $sql = 'SELECT username FROM dbcourse WHERE codecourse = ? AND group_id = ?';
                $stmt = $conn->prepare($sql);
                $stmt->execute([$group->codecourse, $_GET['group']]);
                $members = $stmt->fetchAll(PDO::FETCH_OBJ);
                foreach ($members as $member) {
                    if ($member->username == $_SESSION['auth']['user']) {
                        continue;
                    }
                    $person = retPerson($member->username);
                    ?>
                    <!-- Member Card -->
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <div class="card h-100 d-flex flex-column">
                            <div class="card-body d-flex flex-column">
                                <!-- Top -->
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                         style="width:50px;height:50px;">
                                        profile
                                    </div>
                                    <div class="ml-3 mr-3">
                                        <strong><?= $person->fname . ' ' . $person->lname ?></strong>
                                    </div>
                                </div>

                                <!-- Progress -->
                                <div class="mb-4">
                                    <small class="text-muted">میزان مشارکت</small>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-info" style="width:50%"></div>
                                    </div>
                                </div>

                                <!-- دکمه با mt-auto -->
                                <div class="mt-auto text-center">
                                    <button class="btn btn-sm btn-outline-secondary d-inline-block px-4 btn-prof" data-username="<?= htmlspecialchars($member->username) ?>">
                                        پروفایل
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>


            </div>
        </div>
        <!-- ===== Modal Profile ===== -->
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <!-- Modal Header -->
                    <div class="modal-header">
                        <h5 class="modal-title" id="profileModalLabel">پروفایل عضو</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body text-center">
                        <!-- Avatar -->
                        <div id="modalAvatar" class="modal-avatar bg-primary">
                            <!-- اول حروف نام اینجا لود می‌شود -->
                        </div>

                        <!-- Name & Info -->
                        <h5 id="modalFullName" class="mb-1">در حال بارگذاری...</h5>
                        <small id="modalUsername" class="text-muted d-block mb-3">در حال بارگذاری...</small>

                        <!-- Contact Info -->
                        <div class="mb-4 text-center">
                            <div class="mb-2">
                                <i class="fas fa-user me-2"></i>
                                <span id="modalName">در حال بارگذاری...</span>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-id-card me-2"></i>
                                <span id="modalUserID">در حال بارگذاری...</span>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2"></i>
                                <span id="modalPhone">در حال بارگذاری...</span>
                            </div>
                        </div>

                        <!-- Note: اطلاعات حضور و غیاب و مشارکت سخت کد شده باقی می‌ماند -->
                        <div class="mb-3 text-start">
                            <strong>حضور و غیاب:</strong>
                            <ul class="list-group list-group-flush mt-2">
                                <li class="list-group-item d-flex justify-content-between">
                                    جلسه 1
                                    <span class="badge bg-success">حاضر</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    جلسه 2
                                    <span class="badge bg-danger">غایب</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    جلسه 3
                                    <span class="badge bg-warning text-dark">دیر</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Participation (سخت کد شده) -->
                        <div class="mb-2 text-start">
                            <small class="text-muted">میزان مشارکت</small>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar bg-info" style="width:50%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">بستن</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php include '../footers.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // هندلر کلیک روی دکمه پروفایل
            document.querySelectorAll('.btn-prof').forEach(btn => {
                btn.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');

                    // نمایش مودال با اطلاعات اولیه
                    const modal = new bootstrap.Modal(document.getElementById('profileModal'));

                    // نمایش مودال
                    modal.show();

                    // درخواست AJAX برای دریافت اطلاعات کاربر
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'get_user_profile=1&username=' + encodeURIComponent(username)
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data.person) {
                                const person = data.data.person;

                                // تولید اول حروف نام برای آواتار
                                let initials = '';
                                if (person.fname) {
                                    initials += person.fname.charAt(0);
                                }
                                if (person.lname) {
                                    initials += person.lname.charAt(0);
                                }
                                initials = (initials || 'U').toUpperCase();

                                // پر کردن اطلاعات در مودال
                                document.getElementById('modalAvatar').textContent = initials;
                                document.getElementById('modalFullName').textContent = person.fname + ' ' + person.lname;
                                document.getElementById('modalUsername').textContent = 'شناسه: ' + person.username;
                                document.getElementById('modalName').textContent = person.fname + ' ' + person.lname;
                                document.getElementById('modalUserID').textContent = person.username;
                                document.getElementById('modalPhone').textContent = person.mobile;
                            } else {
                                // در صورت خطا
                                document.getElementById('modalFullName').textContent = 'خطا در دریافت اطلاعات';
                                document.getElementById('modalUsername').textContent = 'لطفا مجددا تلاش کنید';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('modalFullName').textContent = 'خطا در ارتباط با سرور';
                        });
                });
            });
        });
    </script>
    <!--End Footer-->
</body>

</html>