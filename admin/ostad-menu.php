<?php
$crossPanelHelper = __DIR__ . '/../inc/cross_panel.php';
if (!function_exists('cross_panel_secret') && is_file($crossPanelHelper)) {
    require_once $crossPanelHelper;
}
if (empty($_SESSION['admin'])) {
    header('location: login.php');
    exit;
}

$currentUser = (string)$_SESSION['admin']['user'];

$sessionRole = $_SESSION['admin']['role'] ?? null;
$stmt = $conn->prepare("SELECT adminrole AS role FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$currentUser]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$adminRole = ($sessionRole !== null)
    ? (int)$sessionRole
    : (int)($row['role'] ?? 0);

$isManager  = AdminCan(AdminNotificationCode('information.tickets.manage'));
$canSeeAll  = AdminCan(AdminNotificationCode('information.tickets.all'));
$isOperator = AdminCan(AdminNotificationCode('information.tickets.own'));
$administrator = AdminCan('notifications.all-records') ? 1 : 0;


$suspReqCount = countSuspensionRequestsNeedAction( $currentUser, $isManager, $canSeeAll);

$reportCount = countReportThreadsVisible( $currentUser, $isManager, $canSeeAll);

$teacherNotifCount = countTeacherTicketsNeedAction( $currentUser, (int)$adminRole, (int)$administrator);

$studentOpChangeCount = countStudentOperatorChanged( $currentUser, (int)$administrator);

$countNewStudent = countNewStudent( $currentUser, (int)$administrator);

$notifTotalCount = (int)$reportCount + (int)$teacherNotifCount + (int)$studentOpChangeCount + (int)$countNewStudent;
$schoolNotificationCount = CountSchoolNotifications($currentUser, true);
?>
<?php include_once __DIR__ . '/info-tooltip.php'; ?>
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

            <?php if (AdminCanSee('courses.main.active')): ?>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                        class="si si-organization sidemenu-icon"></i><span class="sidemenu-label"><?php echo m('menu.courses') ?></span><i
                        class="angle fe fe-chevron-left"></i></a>

                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>"> <?php echo m('menu.active_courses_short') ?></a>
                    </li>
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('admin/course/lesson-plans.php') ?>">طرح درس من</a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>


            <!--here-->
            <li class="nav-item">
                <a class="nav-link" href="<?= url(inst_is('school') ? 'admin/notifications/school_notifications.php' : 'admin/notifications/institute_notifications.php') ?>">
                    <span class="shape1"></span><span class="shape2"></span>
                    <i class="fe fe-bell sidemenu-icon"></i>
                    <span class="sidemenu-label" style="display:inline-flex; align-items:center; gap:12px;">
                        <?= htmlspecialchars(m('menu.school_notifications')) ?>
                        <span class="menu-info-trigger" tabindex="0"
                              data-tooltip="<?= htmlspecialchars(m('menu.school_notifications_tooltip'), ENT_QUOTES, 'UTF-8') ?>"
                              aria-label="<?= htmlspecialchars('راهنمای ' . m('menu.school_notifications'), ENT_QUOTES, 'UTF-8') ?>"
                              onclick="event.preventDefault(); event.stopPropagation();">
                            <i class="fe fe-info fs-4" aria-hidden="true"></i>
                        </span>
                    </span>
                    <?php if ($schoolNotificationCount > 0): ?>
                        <span class="badge badge-warning side-badge mg-r-20"><?= (int)$schoolNotificationCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php if (AdminCanSee('notifications.operator.teachers')): ?>
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
            <?php if (AdminCanSee('homework-approval')) : ?>
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

            <?php if (function_exists('cross_panel_targets')): ?>
                <?php foreach (cross_panel_targets() as $crossPanelType => $crossPanelLabel): ?>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="<?= htmlspecialchars(cross_panel_login_url($crossPanelType, (string) $_SESSION['admin']['user']), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="shape1"></span><span class="shape2"></span>
                            <i class="fe fe-external-link sidemenu-icon"></i>
                            <span class="sidemenu-label">ورود به <?= htmlspecialchars($crossPanelLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <hr>
    </div>
</div>
