<?php
if (empty($_SESSION['admin'])) {
    header('location: login.php');
    exit;
}

$currentUser = (string)$_SESSION['admin']['user'];

$sessionRole = $_SESSION['admin']['role'] ?? null;
$stmt = $conn->prepare("
    SELECT
        adminrole AS role,
        COALESCE(administrator, 0)       AS administrator,
        COALESCE(permision_viewalert, 0)  AS perm_viewalert,
        COALESCE(permision_viewemploy, 0) AS perm_viewemploy,
        COALESCE(permision_suporter, 0)   AS perm_supporter
    FROM dbadmin
    WHERE username = ?
    LIMIT 1
");
$stmt->execute([$currentUser]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$adminRole = ($sessionRole !== null)
    ? (int)$sessionRole
    : (int)($row['role'] ?? 0);

$isManager  = !empty($row['perm_viewalert']);
$canSeeAll  = $isManager && !empty($row['perm_viewemploy']);
$isOperator = !empty($row['perm_supporter']); // if you need it later
$administrator = (int)($row['administrator'] ?? 0);


$suspReqCount = countSuspensionRequestsNeedAction( $currentUser, $isManager, $canSeeAll);

$reportCount = countReportThreadsVisible( $currentUser, $isManager, $canSeeAll);

$teacherNotifCount = countTeacherTicketsNeedAction( $currentUser, (int)$adminRole, (int)$administrator);

$studentOpChangeCount = countStudentOperatorChanged( $currentUser, (int)$administrator);

$countNewStudent = countNewStudent( $currentUser, (int)$administrator);

$notifTotalCount = (int)$reportCount + (int)$teacherNotifCount + (int)$studentOpChangeCount + (int)$countNewStudent;
$schoolNotificationCount = CountSchoolNotifications($currentUser, true);
?>
<?php if ($adminRole == 3): ?>
    <style>
        .main-sidebar .nav-link::before,
        .main-sidebar .nav-link::after {
            content: none !important;
            display: none !important;
        }
    </style>
<?php endif ?>
<div class="main-sidebar main-sidebar-sticky side-menu"
     style="<?= ($adminRole === 3) ? 'background-color:#821CF9;' : '' ?>">
    <div class="sidemenu-logo">
        <a class="main-logo" href="<?php echo url('admin/index.php') ?>">
            <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img desktop-logo" alt="لوگو">
            <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img icon-logo" alt="لوگو">
        </a>
    </div>
    <div class="main-sidebar-body">
        <ul class="nav">
            <li class="nav-header"><span class="nav-label"> <?php echo m('label.teacher_panel') ?></span></li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo url('admin/index.php') ?>">
                    <span class="shape1"></span>
                    <span class="shape2"></span>
                    <i class="ti-home sidemenu-icon"></i>
                    <span class="sidemenu-label">نمایه</span>
                </a>
            </li>

            <?php if (TeacherPermission('teacher_perm_courses')): ?>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                        class="si si-organization sidemenu-icon"></i><span class="sidemenu-label">پایه ها</span><i
                        class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>"> پایه‌های
                            فعال</a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('admin/course/lesson-plans.php') ?>">طرح درس من</a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>


            <!--here-->
            <li class="nav-item">
                <a class="nav-link" href="<?= url('admin/notifications/school_notifications.php') ?>">
                    <span class="shape1"></span><span class="shape2"></span>
                    <i class="fe fe-bell sidemenu-icon"></i>
                    <span class="sidemenu-label">اعلان‌های مدرسه</span>
                    <?php if ($schoolNotificationCount > 0): ?>
                        <span class="badge badge-warning side-badge mg-r-20"><?= (int)$schoolNotificationCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if (TeacherPermission('teacher_perm_messages')): ?>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#">
                    <span class="shape1"></span><span class="shape2"></span>
                    <i class="fe fe-bell sidemenu-icon"></i>
                    <span class="sidemenu-label"> اعلان</span>
                    <i class="angle fe fe-chevron-left"></i>
                    <?php if ($notifTotalCount > 0): ?>
                        <span class="badge badge-warning side-badge mg-r-20"><?= (int)$notifTotalCount ?></span>
                    <?php endif; ?>
                </a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?= url('admin/notifications/teachers_notif.php') ?>">
                                ارسال پیام ها و فایل ها
                            <?php if ($teacherNotifCount > 0): ?>
                                <span class="badge badge-warning mg-r-20"><?= (int)$teacherNotifCount ?></span>
                            <?php endif; ?>
                            <span class="badge badge-light"></span>
                        </a>
                    </li>

                </ul>
            </li>
            <?php endif; ?>
            <?php if (($adminRole == 2) or (Permision('administrator'))) : ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="si si-badge t sidemenu-icon"></i> <!-- approvals / verified -->
                        </i><span class="sidemenu-label">تایید تکالیف</span><i
                            class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/homework_check.php') ?>"><?php echo m('action.approve_teacher_homeworks') ?></a>
                        </li>

                    </ul>
                </li>
            <?php endif ?>

        </ul>
        <hr>
    </div>
</div>
