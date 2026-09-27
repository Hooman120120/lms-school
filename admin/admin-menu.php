<?php
$crossPanelHelper = __DIR__ . '/../inc/cross_panel.php';
if (!function_exists('cross_panel_secret') && is_file($crossPanelHelper)) {
    require_once $crossPanelHelper;
}
// ✅ Stronger guard: make sure the logged-in admin has a username
if (empty($_SESSION['admin'])) {
    header('location: login.php');
    exit;
}
$currentUser = (string)$_SESSION['admin']['user'];

// session role (optional)
$sessionRole = $_SESSION['admin']['role'] ?? null;

// ✅ Fetch adminrole + permissions ONCE
$stmt = $conn->prepare("
    SELECT
        adminrole AS role,
        COALESCE(administrator, 0)       AS administrator,
        COALESCE(permision_viewalert, 0)  AS perm_viewalert,
        COALESCE(permision_viewemploy, 0) AS perm_viewemploy,
        COALESCE(permision_suporter, 0)   AS perm_supporter,
        COALESCE(installment_permission, 0)   AS perm_installment
    FROM dbadmin
    WHERE username = ?
    LIMIT 1
");
$stmt->execute([$currentUser]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// ✅ Normalize role to int exactly once (safe default = 0)
// Keep session override if it exists
$adminRole = ($sessionRole !== null)
    ? (int)$sessionRole
    : (int)($row['role'] ?? 0);

// ✅ permissions
$isManager = !empty($row['perm_viewalert']);
$canSeeAll = $isManager && !empty($row['perm_viewemploy']);
$isOperator = !empty($row['perm_supporter']);
$administrator = (int)($row['administrator'] ?? 0);
$installment_manager = (int)($row['perm_installment'] ?? 0);

$adminteachernotif = countTeacherManagementticket($currentUser, $administrator);
$suspReqCount = countSuspensionRequestsNeedAction($currentUser, $isManager, $canSeeAll);
$reportCount = countReportThreadsVisible($currentUser, $isManager, $canSeeAll);
$teacherNotifCount = countTeacherTicketsNeedAction($currentUser, (int)$adminRole, (int)$administrator);
$studentOpChangeCount = countStudentOperatorChanged($currentUser, (int)$administrator);
$countNewStudent = countNewStudent($currentUser, (int)$administrator);
$countNewStudentticket = countNewStudentticket($currentUser, $administrator);
$notifTotalCount = (int)$reportCount + (int)$teacherNotifCount + (int)$studentOpChangeCount + (int)$countNewStudent;
$operatorNotifTotalCount = (int)$reportCount + (int)$studentOpChangeCount + (int)$countNewStudent;
$administratorNotifcount = (int)$reportCount + (int)$studentOpChangeCount;
$Countsus = countSuspensionReviewedUnseen($currentUser, (int)$administrator);
$Countnoop = countCancelOpReviewedUnseen($currentUser, (int)$administrator);
$countInstallmentOverdue = countInstallmentOverdue($currentUser, (int)$installment_manager);
$countPendingTicketReferrals = countPendingTicketReferrals($currentUser);
$countPendinghomework = countPendingHomeworkAdmin();
$schoolNotificationTypes = SchoolNotificationAllowedTypes(SchoolStaffRoleKey($currentUser));
$schoolNotificationCount = CountSchoolNotificationMenu($currentUser);
if (AdminHasAnyCurrentTreePermission()) {
    $schoolNotificationTypes = array_values(array_filter($schoolNotificationTypes, static fn(string $type): bool =>
        AdminHasMenuPermission('school-notifications.categories.' . $type)));
}


$role = (int)($_SESSION['admin']['adminrole'] ?? $adminRole);

// مدیر مدرسه از رنگ هویتی مدرسه استفاده می‌کند؛ آموزشگاه رنگ فعلی خود را نگه می‌دارد.
if ($role === 0 && inst_is('school')) $c = '#1E4C4C';
elseif ($role === 1) $c = '#138420';
elseif ($role === 2) $c = '#516d9b';
elseif ($role === 6) $c = '#ff8a00';
elseif ($role >= 51 && $role <= 59) $c = '#244370';
else                  $c = '#15233f';
?>

<style>
    body .main-sidebar-sticky {
        background: <?= $c ?> !important;
    }

    body .main-sidebar-body .nav-link::before {
        border-left: 20px solid <?= $c ?> !important;
    }

    body .main-sidebar-body .nav-item.active > .nav-link::after,
    body .main-sidebar-body .nav-item.active > .nav-link::before {
        border-left: 20px solid <?= $c ?> !important;
    }


    .menu-info-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        color: #6c757d;
        cursor: help;
        transition: color 0.2s;
    }

    .menu-info-trigger:hover {
        color: #0d6efd;
    }

    #menuInfoTooltip {
        position: fixed;
        z-index: 2147483647;
        display: block;
        box-sizing: border-box;
        width: 280px;
        max-width: calc(100vw - 20px);
        padding: 10px 12px;
        background-color: #1f2937;
        color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 5px 18px rgba(0, 0, 0, 0.25);
        font-size: 12px;
        line-height: 1.8;
        text-align: right;
        direction: rtl;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-50%);
        will-change: left, top, opacity;
        transition: opacity 0.15s ease, visibility 0.15s ease;
    }

    #menuInfoTooltip.show {
        opacity: 1;
        visibility: visible;
    }

    #menuInfoTooltip::after {
        content: "";
        position: absolute;
        top: 50%;
        border: 7px solid transparent;
        transform: translateY(-50%);
    }

    #menuInfoTooltip.place-left::after {
        left: 100%;
        border-left-color: #1f2937;
    }

    #menuInfoTooltip.place-right::after {
        right: 100%;
        border-right-color: #1f2937;
    }
</style>

<div class="main-sidebar main-sidebar-sticky side-menu">
    <div class="sidemenu-logo">
        <a class="main-logo" href="<?php echo url('admin/index.php') ?>">
            <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img desktop-logo" alt="لوگو">
            <img src="<?php echo url('assets/img/logo-white.png') ?>" class="header-brand-img icon-logo" alt="لوگو">
        </a>
    </div>
    <div class="main-sidebar-body">
        <ul class="nav">
            <li class="nav-header"><span class="nav-label" style="font-size: 15px; color: #CD964F;"><?= inst_is('institute') ? 'ویژه آموزشگاه' : 'ویژه مدارس' ?></span></li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo url('admin/index.php') ?>">
                    <span class="shape1"></span>
                    <span class="shape2"></span>
                    <i class="ti-home sidemenu-icon"></i>
                    <span class="sidemenu-label"
                        style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                        نمایه

                        <span class="menu-info-trigger"
                            data-tooltip="در این بخش نمای کلی سامانه نمایش داده می‌شود. از طریق نمایه می‌توانید وضعیت کلی سیستم، آمارهای مهم، اعلان‌ها، اخبار و تقویم اجرایی را در یک نگاه مشاهده کنید."
                            aria-label="راهنمای نمایه"
                            onclick="event.preventDefault(); event.stopPropagation();">

                            <i class="fe fe-info fs-4"></i>

                        </span>

                    </span>
                </a>
            </li>
            <?php if (Permision('permision_viewcall')) { ?>

                <li class="nav-item">
                    <a class="nav-link with-sub" href="#">
                        <span class="shape1"></span>
                        <span class="shape2"></span>
                        <i class="ti-headphone sidemenu-icon"></i>
                        <span class="sidemenu-label"
                            style="display:inline-flex; align-items:center; gap:4px; white-space:nowrap;">

                            <?= inst_is('institute') ? 'امور مشتریان' : 'روابط عمومی' ?>

                            <span class="menu-info-trigger"
                                data-tooltip="مدیریت جامع تماس ها ؛ در این بخش میتوانید گروه‌های تماس را تعریف و به کارشناسان تخصیص ‌دهید و بر تمامی گزارش‌های عملکرد و وضعیت تماس‌ها نظارت دقیق داشته باشید. همچنین می‌توانید با ابزار Auto Dialer کمپین‌های تماس گروهی را اجرا کنید."
                                aria-label="اطلاعات"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info"></i>
                            </span>

                        </span>
                        <i class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <?php if (AdminCanUsePermissionScope('public-relations.safe-call', ['permision_viewcall'])) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_call.php') ?>">تماس امن
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (AdminCanUsePermissionScope('public-relations.safe-sms', ['permision_viewcall'])) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_sms.php') ?>">پیامک امن
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>

            <?php if (AdminMenuCan('students', Permision('permision_viewstudent'))): ?>
                <li class="nav-item" style="margin-bottom: 5px;">
                    <a class="nav-link with-sub" href="#">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="si si-graduation sidemenu-icon"></i>
                        <span class="sidemenu-label"
                            style="display:inline-flex;
                                 align-items:center;
                                 gap:12px;"> <?php echo m('menu.students') ?>

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('menu.students_tooltip') ?>"
                                aria-label="<?php echo m('menu.students_aria') ?>"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i class="angle fe fe-chevron-left"></i>
                    </a>

                    <ul class="nav-sub">

                        <!-- دانش آموزان قطعی -->
                        <?php if (inst_is('school') || AdminMenuCan('students.definitive', true)): ?>
                        <?php if (inst_is('institute')): ?>
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                    style="font-size:16px; color: #79f57d;"><?php echo m('menu.students_definitive') ?></span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;"> <!-- فاصله زیرمنو -->
                        <?php endif; ?>
                                <?php if (AdminMenuCan('students.definitive.register', $adminRole !== 6)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/add.php') ?>">ثبت نام</a>
                                    </li>
                                <?php endif; ?>
                                <!-- <//?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<//?= url('admin/Student/add-group.php') ?>">ثبت نام گروهی </a>
                                    </li>
                                <//?php endif; ?> -->
                                <?php if (AdminMenuCan('students.definitive.enrolment', true)): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/addcourse.php') ?>"><?php echo m('menu.student_register_course') ?></a>
                                </li>
                                <?php endif; ?>
                                <?php if (inst_is('school') && AdminMenuCan('students.temporary.enrolment', $adminRole !== 2)): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/democourse/addstudent.php') ?>"><?= m('menu.register_democourse_student') ?></a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminMenuCan('students.definitive.edit', true)): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-absolute.php') ?>"><?= inst_is('school') ? 'ویرایش اطلاعات' : 'ویرایش اطلاعات قطعی' ?></a>
                                </li>
                                <?php endif; ?>

                                <?php if ($adminRole == 1 || AdminHasAnyCurrentTreePermission()) : ?>

                                    <?php if (AdminMenuCan('students.definitive.my-students', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/mystudent.php') ?>">
                                            <?php echo m('menu.students_individual') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (inst_is('institute') && AdminMenuCan('students.definitive.my-courses', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/mypersonalcourse.php') ?>">
                                            <?php echo m('menu.students_per_course') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminMenuCan('students.definitive.cancelled-support', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/canceled_student_list.php') ?>">
                                            <?php echo m('menu.students_canceled_support') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminMenuCan('students.definitive.suspended-support', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/suspension_list.php') ?>">
                                            <?php echo m('menu.students_suspended_list') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                <?php endif; ?>


                                <?php if ($adminRole !== 2 || AdminHasAnyCurrentTreePermission()): ?>
                                    <?php if (AdminMenuCan('students.definitive.course-report', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/Student/registration-Course-absolute.php') ?>">گزارش
                                            تفکیکی</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (inst_is('school') && AdminMenuCan('students.temporary.report', $adminRole !== 2)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/registration-Course-experimental.php') ?>">گزارش تفکیکی فوق برنامه</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (AdminMenuCan('students.definitive.inactive', true)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/unactive-student.php') ?>">غیرفعال‌ها</a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminMenuCan('students.definitive.cancelled', Permision('permision_reportstudent'))): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/Student/canceled-student.php') ?>">انصرافی ها</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (AdminMenuCan('students.definitive.suspended', Permision('permision_reportstudent'))): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/Student/suspended-student.php') ?>">
                                                تعلیقی ها
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (AdminMenuCan('students.definitive.cancelled-operator', Permision('permision_reportstudent'))): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/Student/canceled-op-student.php') ?>">لغو پشتیبانی
                                                شده ها</a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                        <?php if (inst_is('institute')): ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- دانش آموزان آزمایشی -->
                        <?php if (inst_is('institute') && AdminMenuCan('students.temporary', true)): ?>
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                    style="font-size:16px; color: #79f57d;"><?php echo m('menu.students_temporary') ?></span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;">
                                <?php if (AdminMenuCan('students.temporary.register', true)): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/experimental-add.php') ?>">ثبت
                                        نام

                                        موقت</a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminMenuCan('students.temporary.enrolment', $adminRole !== 2)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/democourse/addstudent.php') ?>"><?php echo m('menu.register_democourse_student') ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if (AdminMenuCan('students.temporary.edit', true)): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-experimental.php') ?>">ویرایش
                                        اطلاعات موقت</a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminMenuCan('students.temporary.report', $adminRole !== 2)): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/Student/registration-Course-experimental.php') ?>">گزارش
                                            تفکیکی</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                    </ul>
                </li>
            <?php endif; ?>


            <?php
            $execMenuIsAdmin = Permision('administrator');
            $execMenuCanViewAcademic = ((int)$adminRole === 3) || SchoolStaffRoleKey($currentUser) !== '';
            if (AdminMenuCan('executive', $execMenuIsAdmin || $execMenuCanViewAcademic)) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-calendar  sidemenu-icon"></i>
                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            امور اجرایی

                            <span class="menu-info-trigger"
                                data-tooltip="در این بخش ابزارهای اجرایی سامانه در اختیار مدیران قرار دارد. از طریق این قسمت می‌توانید تقویم اجرایی را مدیریت کرده، اطلاعیه‌های روزانه و اخبار سامانه را منتشر یا ویرایش کنید و اطلاع‌رسانی‌های مورد نیاز کاربران را مدیریت نمایید."
                                aria-label="راهنمای امور اجرایی"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>

                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <?php if (AdminMenuCan('executive.calendar', $execMenuIsAdmin)) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/panel/calendar_panel.php') ?>">تقویم
                                    اجرایی</a>
                            </li>
                        <?php } ?>
                        <?php if (AdminMenuCan('executive.academic-calendar', true)): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/panel/academic-calendar.php') ?>">تقویم
                                آموزشی</a>
                        </li>
                        <?php endif; ?>
                        <?php if (AdminMenuCan('executive.daily-notices', $execMenuIsAdmin)) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/notifications/daily_notifications.php') ?>">تابلو
                                    اعلان روزانه
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (AdminMenuCan('executive.news', $execMenuIsAdmin)) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/notifications/news_panel.php') ?>">تابلو
                                    اخبار</a>
                            </li>
                        <?php } ?>
                        <!--                    <li class="nav-sub-item">-->
                        <!--                        <a class="nav-sub-link" href="--><?php //echo url('admin/events-panel.php') 
                                                                                        ?><!--">تابلو رویداد</a>-->
                        <!--                    </li>-->
                    </ul>
                </li>
            <?php } ?>

            <?php if (AdminMenuCan('courses', true)): ?>
            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                        class="si si-organization sidemenu-icon"></i>

                    <span class="sidemenu-label"
                        style="display:inline-flex;
                             align-items:center;
                             gap:12px;"> <?php echo m('menu.courses') ?>

                        <span class="menu-info-trigger"
                            data-tooltip="<?php echo m('menu.courses_tooltip') ?>"
                            aria-label="راهنمای مدیریت محصولات"
                            onclick="event.preventDefault(); event.stopPropagation();">

                            <i class="fe fe-info fs-4"></i>
                        </span>

                    </span>
                    <i
                        class="angle fe fe-chevron-left"></i>
                </a>

                <ul class="nav-sub ">
                    <?php if (AdminMenuCan('courses.main', true)): ?>
                    <li class="nav-item show">
                        <a class="nav with-sub" href="#"><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label" style="font-size:16px; color: #79f57d;"><?php echo m('menu.courses_sub') ?></span>
                        </a>

                        <ul class="nav-sub">
                            <?php if ($adminRole !== 2): ?>
                                <?php if (AdminMenuCan('courses.main.create', Permision('permision_viewcourse'))) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?php echo url('admin/course/add-Course.php') ?>"><?php echo m('menu.course_create') ?></a>
                                    </li>
                                <?php } ?>
                                <?php if (AdminMenuCan('courses.main.demo-create', true)): ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link"
                                        href="<?php echo url('admin/democourse/add-democourse.php') ?>"><?php echo m('menu.democourse_create') ?></a>
                                </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (AdminMenuCan('courses.main.active', true)): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>"><?php echo m('menu.active_courses') ?></a>
                            </li>
                            <?php endif; ?>

                            <?php if (AdminMenuCan('courses.main.demo-active', true)): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link"
                                    href="<?php echo url('admin/democourse/list-democourse.php?paye=all') ?>"><?php echo m('menu.active_democourses') ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($adminRole !== 2): ?>
                                <?php if (AdminMenuCan('courses.main.archive', Permision('permision_viewcourse'))) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                            href="<?php echo url('admin/course/unactive-course.php') ?>">
                                            بایگانی سال‌های قبل</a>
                                    </li>
                                <?php } ?>
                                <?php if (AdminMenuCan('courses.main.discount', Permision('permision_discount'))) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                            href="<?php echo url('admin/course/add-discount.php') ?>"> ایجاد
                                            کد تخفیف</a>
                                    </li>
                                <?php } ?>
                            <?php endif; ?>

                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (AdminMenuCan('courses.handhug', true)): ?>
                    <li class="nav-item show">
                        <a class="nav with-sub" href=""><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label "
                                style="font-size:16px; color: #79f57d;">"بغل دستی ها"</span></a>

                        <ul class="nav-sub">
                            <?php if (AdminMenuCan('courses.handhug.create', true)): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link " href="<?php echo url('admin/handhug/add-handhug.php') ?>"> ساخت
                                    محصول "بغل دستی ها"</a>
                            </li>
                            <?php endif; ?>
                            <?php if (AdminMenuCan('courses.handhug.manage', true)): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link "
                                    href="<?php echo url('admin/handhug/handhug-management.php') ?>"> <?php echo m('menu.handhug_active') ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if (AdminMenuCan('courses.handhug.archive', true)): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link "
                                    href="<?php echo url('admin/handhug/disabled-handhugs.php') ?>"> بایگانی سال‌های قبل
                                    "بغل دستی ها"</a>
                            </li>
                            <?php endif; ?>
                            <!--<li class="nav-sub-item">-->
                            <!--    <a class="nav-sub-link " href="#">   ثبت گروه های بغل دستی موقت</a>-->
                            <!--</li>-->
                            <!--<li class="nav-sub-item">-->
                            <!--    <a class="nav-sub-link " href="#">   گروه های بغل دستی موقت</a>-->
                            <!--</li>-->

                            <!--<li class="nav-sub-item">-->
                            <!--    <a class="nav-sub-link " href="#">   ایجاد کد تخفیف</a>-->
                            <!--</li>-->
                            <!--<li class="nav-sub-item">-->
                            <!--    <a class="nav-sub-link " href="#">   آمار گزارشات</a>-->
                            <!--</li>-->


                        </ul>
                    </li>
                    <?php endif; ?>


                </ul>
            </li>

            <?php endif; ?>
            <?php if (inst_is('school') && (AdminHasAnyCurrentTreePermission()
                ? AdminHasMenuNavigationPermission('school-notifications')
                : $schoolNotificationTypes !== [])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('admin/notifications/school_notifications.php') ?>">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="fe fe-bell sidemenu-icon"></i>
                        <span class="sidemenu-label"><?php echo m('menu.school_notifications') ?></span>
                        <?php if ($schoolNotificationCount > 0): ?>
                            <span class="badge badge-warning side-badge mg-r-20"><?= (int)$schoolNotificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (inst_is('institute') && AdminMenuCan('institute-notifications', SchoolNotificationAllowedTypes() !== [])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('admin/notifications/institute_notifications.php') ?>">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="fe fe-bell sidemenu-icon"></i>
                        <span class="sidemenu-label">اعلان‌های آموزشگاه</span>
                        <?php $instituteNotificationCount = CountInstituteNotificationMenu(); ?>
                        <?php if ($instituteNotificationCount > 0): ?><span class="badge badge-warning side-badge mg-r-20"><?= (int)$instituteNotificationCount ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
            <!-- The institute notification entry above opens the shared notification screen. -->
            <?php if (false): ?>
                <!--main alert navigator-->
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="fe fe-bell sidemenu-icon"></i>
                        <span class="sidemenu-label"
                            style="display:inline-flex;
                             align-items:center;
                             gap:12px;">

                            اعلان‌ها

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('tooltip.notifications_menu') ?>"
                                aria-label="راهنمای مدیریت اعلان‌ها"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i class="angle fe fe-chevron-left"></i>
                        <?php if ($notifTotalCount > 0): ?>
                            <span class="badge badge-warning side-badge mg-r-20">*</span>
                        <?php endif; ?>
                    </a>

                    <ul class="nav-sub"> <!--                    main ul                  -->
                        <?php $allowed_users_management = [0];
                        if (in_array($_SESSION['admin']['adminrole'], $allowed_users_management)) { ?>
                            <li class="nav-item">
                                <a class="d-flex flex-row with-sub" href="#" style="height: 30px;">
                                    <span class="shape1"></span><span class="shape2"></span>
                                    <span class="sidemenu-label" style="font-size:16px; color: #79f57d; "> مدیریت </span>
                                    <?php if ($adminteachernotif > 0): ?>
                                        <span class="badge badge-warning"
                                            style="border-radius: 50px; display: flex; margin-right: 105px; height: 20px;">*</span>
                                    <?php endif; ?>
                                </a>
                                <ul class="nav-sub">
                                    <?php if (Permision('permision_viewalert')): ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/notif_rule_management.php') ?>">
                                                کنترل محدودیت اعلان ها
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if (Permision('permision_viewalert')): ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/manager_notif.php') ?>">
                                                اعلان مدیریت
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if (Permision('permision_viewalert')): ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/notification_view.php') ?>">
                                                اعلان اپراتور ها
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/notifications/admin_teacher_notif.php') ?>">
                                            <?php echo m('label.teacher_notifications') ?>
                                            <?php if ($adminteachernotif > 0): ?>
                                                <span class="badge badge-warning"
                                                    style="border-radius: 50px; display: flex; margin-right: 60px; height: 20px;"><?= (int)$adminteachernotif ?></span>
                                            <?php endif; ?>
                                            <span class="badge badge-light"></span>
                                        </a>
                                    </li>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/notifications/referred_tickets.php') ?>">
                                            ارجاع یافته
                                            <?php if ($countPendingTicketReferrals > 0): ?>
                                                <span class="badge badge-warning"
                                                    style="border-radius: 50px; display: flex; margin-right: 60px; height: 20px;"><?= (int)$countPendingTicketReferrals ?></span>
                                            <?php endif; ?>
                                            <span class="badge badge-light"></span>
                                        </a>
                                    </li>

                                </ul>
                            </li>
                        <?php } ?>
                        <?php $allowed_users_administrators = [0, 51, 52, 53, 54];
                        if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators)) { ?>
                            <li class="nav-item">
                                <a class="d-flex flex-row with-sub" href="#" style="height: 30px;">
                                    <span class="shape1"></span><span class="shape2"></span>
                                    <span class="sidemenu-label" style="font-size:16px; color: #79f57d;"> معاونت </span>
                                    <?php if ($administratorNotifcount > 0): ?>
                                        <span class="badge badge-warning"
                                            style="border-radius: 50px; display: flex; margin-right: 105px; height: 20px;">*</span>
                                    <?php endif; ?>
                                </a>
                                <ul class="nav-sub">
                                    <?php $allowed_users_administrators1 = [0, 51];
                                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators1)) { ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/administrator_notif.php?type=1') ?>">
                                                معاونت اجرایی
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <?php $allowed_users_administrators2 = [0, 51, 52];
                                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators2)) { ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/administrator_notif.php?type=2') ?>">
                                                معاونت آموزش
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <?php $allowed_users_administrators3 = [0, 51, 53, 52, 54];
                                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators3)) { ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/administrator_notif.php?type=3') ?>">
                                                معاونت فروش
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php } ?>
                                    <?php $allowed_users_administrators3 = [0, 51, 52, 54];
                                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_administrators3)) { ?>
                                        <li class="nav-sub-item">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/notifications/administrator_notif.php?type=4') ?>">
                                                معاونت فنی
                                                <span class="badge badge-light"></span>
                                            </a>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </li>
                        <?php } ?>
                        <?php $allowed_users_middle = [0, 2, 51, 52, 54];
                        if (in_array($_SESSION['admin']['adminrole'], $allowed_users_middle)) { ?>
                            <li class="nav-item">
                                <a class="d-flex flex-row with-sub" href="#" style="height: 30px;">
                                    <span class="shape1"></span><span class="shape2"></span>
                                    <span class="sidemenu-label"
                                        style="font-size:16px; color: #79f57d;"> معاونت میانی </span>
                                    <!--                            --><?php //if ($notifTotalCount > 0): 
                                                                        ?>
                                    <!--                                <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 87px; height: 20px;"></span>-->
                                    <!--                            --><?php //endif; 
                                                                        ?>
                                </a>
                                <ul class="nav-sub">
                                </ul>
                            </li>
                        <?php } ?>
                        <?php $allowed_users_sales = [0, 2, 51, 52, 54, 6];
                        if (in_array($_SESSION['admin']['adminrole'], $allowed_users_sales)) { ?>
                            <li class="nav-item">
                                <a class="d-flex flex-row with-sub" href="#">
                                    <span class="shape1"></span><span class="shape2" style="height: 30px;"></span>
                                    <span class="sidemenu-label" style="font-size:16px; color: #79f57d;  "> واحد فروش</span>
                                    <!--                            <i class="angle fe fe-chevron-left"></i>-->
                                    <!--                            --><?php //if ($notifTotalCount > 0): 
                                                                        ?>
                                    <!--                            <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 55px; height: 20px;"></span>-->
                                    <!--                            --><?php //endif; 
                                                                        ?>

                                </a>
                                <ul class="nav-sub">
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?= url('admin/notifications/notif_sales.php') ?>">
                                            لیست اعلان ها
                                            <span class="badge badge-light"></span>
                                        </a>
                                    </li>

                                </ul>
                            </li>
                        <?php } ?>
                        <?php $allowed_users_operator = [0, 1, 2, 51, 52, 54];
                        if (in_array($_SESSION['admin']['adminrole'], $allowed_users_operator)) { ?>
                            <li class="nav-item">
                                <a class="d-flex flex-row with-sub" href="#" style="height: 30px;">
                                    <span class="shape1"></span><span class="shape2"></span>
                                    <span class="sidemenu-label"
                                        style="font-size:16px; color: #79f57d; "> واحد پشتیبانی </span>
                                </a>
                                <ul class="nav-sub">
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?= url('admin/notifications/operator_notif.php') ?>">
                                            لیست اعلان ها
                                            <?php if ($teacherNotifCount > 0): ?>
                                                <span class="badge badge-warning mg-r-20"><?= (int)$teacherNotifCount ?></span>
                                            <?php endif; ?>
                                            <span class="badge badge-light"></span>
                                        </a>
                                    </li>
                                    <!--                                            <li class="nav-sub-item">-->
                                    <!--                                                <a class="nav-sub-link" href="-->
                                    <? //= url('admin/notifications/teachers_notif.php') 
                                    ?><!--">-->
                                    <!--                                                        تیکت اساتید-->
                                    <!--                                                    --><?php //if ($teacherNotifCount > 0): 
                                                                                                ?>
                                    <!--                                                        <span class="badge badge-warning mg-r-20">-->
                                    <? //= (int)$teacherNotifCount 
                                    ?><!--</span>-->
                                    <!--                                                    --><?php //endif; 
                                                                                                ?>
                                    <!--                                                    <span class="badge badge-light"></span>-->
                                    <!--                                                </a>-->
                                    <!--                                            </li>-->

                                    <!--                                                <li class="nav-sub-item">-->
                                    <!--                                                    <a class="nav-sub-link" href="-->
                                    <? //= url('admin/notifications/studentoperator_changes_notif.php') 
                                    ?><!--">-->
                                    <!--                                                        --><? //= ((int)$administrator === 1) ? 'دانش آموزان تغییر پشتیبان یافته' : 'لیست دانش آموزان تغییر پشتیبان یافته به شما' 
                                                                                                    ?>
                                    <!--                                                        --><?php //if ($studentOpChangeCount > 0): 
                                                                                                    ?>
                                    <!--                                                            <span class="badge badge-warning pr-2">-->
                                    <? //= (int)$studentOpChangeCount 
                                    ?><!--</span>-->
                                    <!--                                                        --><?php //endif; 
                                                                                                    ?>
                                    <!--                                                        <span class="badge badge-light"></span>-->
                                    <!--                                                    </a>-->
                                    <!--                                                </li>-->
                                    <!---->
                                    <!--                                                <li class="nav-sub-item">-->
                                    <!--                                                    <a class="nav-sub-link" href="-->
                                    <? //= url('admin/notifications/new_students.php') 
                                    ?><!--">-->
                                    <!--                                                        --><? //= ((int)$administrator === 1) ? 'دانش آموزان با پشتیبان جدید' : 'لیست دانش آموزان جدید شما' 
                                                                                                    ?>
                                    <!---->
                                    <!--                                                        --><?php //if ($countNewStudent > 0): 
                                                                                                    ?>
                                    <!--                                                            <span class="badge badge-warning pr-2">-->
                                    <? //= (int)$countNewStudent 
                                    ?><!--</span>-->
                                    <!--                                                        --><?php //endif; 
                                                                                                    ?>
                                    <!---->
                                    <!--                                                        <span class="badge badge-light"></span>-->
                                    <!--                                                    </a>-->
                                    <!--                                                </li>-->
                                    <!---->
                                    <!--                                                <li class="nav-sub-item">-->
                                    <!--                                                    <a class="nav-sub-link" href="-->
                                    <? //= url('admin/notifications/new_student_tickets.php') 
                                    ?><!--">-->
                                    <!--                                                     تیکت دانش آموزان جدید-->
                                    <!--                                                        <span class="badge badge-warning pr-2"></span>-->
                                    <!--                                                        <span class="badge badge-light"></span>-->
                                    <!--                                                    </a>-->
                                    <!--                                                </li>-->
                                    <!--                                            <li class="nav-sub-item">-->
                                    <!--                                                <a class="nav-sub-link" href="#">-->
                                    <!--                                                    اعلان اقساط-->
                                    <!--                                                    <span class="badge badge-warning pr-2"></span>-->
                                    <!--                                                    <span class="badge badge-light"></span>-->
                                    <!--                                                </a>-->
                                    <!--                                            </li>-->


                                </ul>
                            </li>
                        <?php } ?>

                        <!--                    <li class="nav-item">-->
                        <!--                        <a class="d-flex flex-row with-sub" href="#" style="height: 30px;" >-->
                        <!--                            <span class="shape1"></span><span class="shape2"></span>-->
                        <!--                            <span class="sidemenu-label" style="color: white; font-size:17px;  "> واحد آموزش</span>-->
                        <!--                             --><?php //if ($teacherNotifCount > 0): 
                                                            ?>
                        <!--                                 <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 80px; height: 20px;">-->
                        <? //= (int)$teacherNotifCount 
                        ?><!--</span>-->
                        <!--                             --><?php //endif; 
                                                            ?>
                        <!--                        </a>-->
                        <!--                    </li>-->

                    </ul>
                </li>
            <?php endif; ?>
            <?php if (Permision('permision_viewsupport')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="si si-speech  sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                                 align-items:center;
                                 gap:12px;">

                            تیکت‌ها

                            <span class="menu-info-trigger"
                                data-tooltip="مدیریت و پیگیری تیکت‌های سامانه؛ در این بخش می‌توانید تیکت‌های منتظر ارجاع، صندوق ورودی جدید، تیکت‌های اورژانسی، کارتابل تیکت‌های باز و در صورت داشتن دسترسی، کارتابل همه تیکت‌ها را مشاهده و مدیریت کنید. همچنین امکان بررسی، ارجاع، پاسخگویی و پیگیری وضعیت تیکت‌ها از این قسمت فراهم است."
                                aria-label="راهنمای مدیریت تیکت‌ها"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i><?php $count1 = countCheckNotreffer() + countCheckinbox() + countCheckemergency();
                                                                    if ($count1 > 0) { ?> <span
                                class="badge badge-warning side-badge mg-r-20"><?php echo $count1; ?></span><?php } ?>
                    </a>
                    <ul class="nav-sub">
                        <?php if (Permision('permision_refer')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tickets/Refer.php') ?>"> منتظر
                                    ارجاع <?php if (countCheckNotreffer() > 0) { ?><span
                                            class="badge badge-warning mg-r-20 "><?php echo countCheckNotreffer(); ?> </span><?php } ?>
                                    <span class="badge badge-light "></span></a>
                            </li>
                        <?php } ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Tickets/inbox.php') ?>"> صندوق ورودی
                                جدید <?php if (countCheckinbox() > 0) { ?><span
                                        class="badge badge-warning mg-r-20 "><?php echo countCheckinbox(); ?> </span><?php } ?>
                                <span class="badge badge-light "></span></a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Tickets/emergency.php') ?>"> تیکت های
                                اورژانسی <?php if (countCheckemergency() > 0) { ?> <span
                                        class="badge badge-warning mg-r-20 "><?php echo countCheckemergency(); ?> </span><?php } ?>
                                <span class="badge badge-light "></span></a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Tickets/openTicket.php') ?>"> کارتابل
                                تیکت های باز <span class="badge badge-light "></span></a>
                        </li>
                        <?php if (Permision('permision_allticket')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tickets/allticket.php') ?>"> کارتابل
                                    همه تیکت ها </a>
                            </li>
                        <?php } ?>


                    </ul>
                </li>
            <?php } ?>
            <?php if (Permision('permision_viewdocument')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-paperclip t sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            مستندات

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('menu.docs_tooltip') ?>"
                                aria-label="راهنمای مدیریت مستندات"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>

                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i><?php $count2 = countCheckDoc();
                                                                    if ($count2 > 0) { ?><span
                                class="badge badge-warning side-badge "><?php echo $count2; ?></span><?php } ?>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Doc/newdoc.php') ?>"> تازه ها <span
                                    class="badge badge-light "></span></a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Doc/searchdoc.php') ?>">جستجوی
                                مستندات </a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Doc/announcements.php') ?>">
                                اطلاعیه‌ها و بخشنامه‌ها
                            </a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Doc/special-letters.php') ?>">
                                نامه‌های اختصاصی
                            </a>
                        </li>


                    </ul>
                </li>
            <?php } ?>

            <?php if (Permision('permision_viewemploy') || Permision('permission_staff_view')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="si si-people t sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            همکاران

                            <span class="menu-info-trigger"
                                data-tooltip="در این بخش می‌توانید اطلاعات همکاران سامانه را مدیریت کنید. امکان ثبت همکار جدید، مشاهده و ویرایش اطلاعات همکاران، تعیین نقش‌ها و سطح دسترسی آن‌ها و مدیریت وضعیت کاربران سازمان از طریق این قسمت فراهم است."
                                aria-label="راهنمای مدیریت همکاران"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>

                        <i
                            class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <?php if (Permision('permission_staff_view')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/staff-directory.php') ?>">مشاهده همکاران</a>
                            </li>
                        <?php } ?>

                        <?php if (Permision('permision_viewemploy')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/listadmin.php') ?>">مدیریت همکاران</a>
                            </li>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/listteacher.php') ?>">مشاهده
                                    <?php echo t('teacher_pl') ?> </a>

                            </li>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/addemploy.php') ?>"> ثبت همکار
                                    جدید </a>
                            </li>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/addteacher.php') ?>"> <?php echo m('page.add_teacher') ?> </a>
                            </li>
                        <?php } ?>

                    </ul>
                </li>

            <?php } ?>

            <?php if (Permision('permission_assigned_tasks')) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('admin/tasks/assigned-tasks.php') ?>">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="fe fe-check-square sidemenu-icon"></i>
                        <span class="sidemenu-label">کارهای محول‌شده</span>
                    </a>
                </li>
            <?php } ?>

            <?php if (Permision('permision_viewcollague')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-link  sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            معرفان

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('menu.referrer_tooltip') ?>"
                                aria-label="راهنمای مدیریت معرفان"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>

                        <i
                            class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/add-collague.php') ?>">
                                ثبت معرف
                            </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/edit-collague.php') ?>">
                                ویرایش اطلاعات معرف
                            </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/unactive-collague.php') ?>">
                                معرفان غیرفعال
                            </a>
                        </li>
                        <?php if (Permision('permision_reportcollague')) { ?>
                            <!--                            <li class="nav-sub-item">-->
                            <!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Collague/report-collague.php') 
                                                                                                    ?><!--">گزارش-->
                            <!--                                    آماری همکاران</a>-->
                            <!--                            </li>-->
                        <?php } ?>
                        <!--                        --><?php //if (Permision('permision_reportcollague')) { 
                                                        ?>
                        <!--                            <li class="nav-sub-item">-->
                        <!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Collague/partnerspanel.php') 
                                                                                                ?><!--">آمار-->
                        <!--                                    دانش آموزان خارج از هنرآموزش</a>-->
                        <!--                            </li>-->
                        <!--                        --><?php //} 
                                                        ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/sub-collague.php') ?>">
                                زیرمجموعه های ثبت شده معرفان
                            </a>
                        </li>


                    </ul>
                </li>
            <?php } ?>


            <?php if (AdminHasAnyCurrentTreePermission() && AdminHasMenuNavigationPermission('reports')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="fe fe-radio sidemenu-icon"></i><span class="sidemenu-label">آمار و گزارشات</span><i class="angle fe fe-chevron-left"></i></a>
                    <ul class="nav-sub">
                        <?php if (AdminHasMenuNavigationPermission('reports.students')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/report/students.php') ?>"><?= m('menu.students') ?></a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('reports.staff')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/report/colleague.php') ?>">همکاران</a></li><?php endif; ?>
                    </ul>
                </li>
            <?php } elseif (!AdminHasAnyCurrentTreePermission() && Permision('excel_permission')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-radio sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            آمار و گزارشات

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('menu.reports_tooltip') ?>"
                                aria-label="راهنمای آمار و گزارشات"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/report/students.php') ?>"> <?php echo m('menu.students') ?>
                            </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/report/colleague.php') ?>">همکاران
                                <span class="badge badge-light "></span></a>
                        </li>
                    </ul>
                </li>
            <?php } ?>
            <?php if (($adminRole == 2) or (Permision('administrator'))) : ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="si si-badge t sidemenu-icon"></i> <!-- approvals / verified -->
                        </i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            تایید تکالیف

                            <span class="menu-info-trigger"
                                data-tooltip="<?php echo m('tooltip.homework_review') ?>"
                                aria-label="راهنمای تایید تکالیف"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i>
                        <?php if ($countPendinghomework > 0): ?>
                            <span class="badge badge-warning side-badge mg-r-20"><?php echo $countPendinghomework; ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/homework_check.php') ?>">تایید
                                تکالیف
                                <?php if ($countPendinghomework > 0): ?>
                                    <span class="badge badge-warning"
                                        style="border-radius: 50px; display: flex; margin-right: 60px; height: 20px;"><?= (int)$countPendinghomework ?></span>
                                <?php endif; ?>
                            </a>

                        </li>

                    </ul>
                </li>

            <?php endif ?>
            <?php if (AdminHasAnyCurrentTreePermission() && AdminHasMenuNavigationPermission('finance')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="fe fe-database sidemenu-icon"></i><span class="sidemenu-label">امور مالی</span><i class="angle fe fe-chevron-left"></i></a>
                    <ul class="nav-sub">
                        <?php if (AdminHasMenuNavigationPermission('finance.pending')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/active-factor.php') ?>">فاکتورهای منتظر تأیید</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.unpaid')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/factor-notpay.php') ?>">فاکتورهای پرداخت‌نشده</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.returned')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/returned.php') ?>">فاکتورهای مستردشده</a></li><?php endif; ?>
                        <?php if (inst_feature('wallet') && AdminHasMenuNavigationPermission('finance.wallet')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/waitingWallet.php') ?>">شارژهای کیف پول منتظر تأیید</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.referrer-commission')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/finance/referrer-commission.php') ?>">کارمزد معرف</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.cards')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/setting/credit-card.php') ?>">افزودن شماره کارت</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.statement')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/SearchBillView.php') ?>">صورتحساب‌ها</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.advanced-report')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/advanced-report.php') ?>">گزارش مالی پیشرفته</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.installments')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/payment.php') ?>">موارد اقساطی</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.monthly-payments')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/finance/monthly-payments.php') ?>">واریز ماهانه همکاران</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.staff-documents')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/employ/staff-documents.php') ?>">مستندات همکاران</a></li><?php endif; ?>
                        <?php if (AdminHasMenuNavigationPermission('finance.insurance')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/employ/insurance.php') ?>">بیمه افراد</a></li><?php endif; ?>
                    </ul>
                </li>
            <?php } elseif (!AdminHasAnyCurrentTreePermission() && (Permision('permision_viewbill') || Permision('permission_monthly_payment') || Permision('permission_staff_documents') || Permision('permission_insurance'))) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-database  sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            امور مالی

                            <span class="menu-info-trigger"
                                data-tooltip="در این بخش می‌توانید فرآیندهای مالی سامانه را مدیریت و پیگیری کنید. امکاناتی مانند بررسی و تأیید فاکتورها، مشاهده فاکتورهای پرداخت‌نشده، مدیریت فاکتورهای مستردشده، تأیید تراکنش‌های کیف پول، مشاهده صورتحساب‌ها، گزارش‌های مالی و پیگیری موارد قسطی از طریق این قسمت انجام می‌شود."
                                aria-label="راهنمای امور مالی"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i
                            class="angle fe fe-chevron-left"></i><?php $count = Permision('permision_viewbill') ? countCheckfactor() + (inst_feature('wallet') ? countCheckWallet() : 0) : 0;
                                                                    if ($count > 0) { ?> <span
                                class="badge badge-warning side-badge "><?php echo $count; ?></span><?php } ?></a>

                    <ul class="nav-sub">
                        <?php if (Permision('permision_viewbill')) { ?>
                            <?php if (Permision('permision_actfactor')) { ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link" href="<?php echo url('admin/Bill/active-factor.php') ?>">منتظر تایید
                                        فاکتور <?php if (countCheckfactor() > 0) { ?><span
                                                class="badge badge-warning mg-r-20"><?php echo countCheckfactor(); ?> </span><?php } ?>
                                    </a>
                                </li>
                            <?php } ?>

                            <?php if (Permision('permision_notpay')) { ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link" href="<?php echo url('admin/Bill/factor-notpay.php') ?>"> فاکتورهای
                                        پرداخت نشده </a>
                                </li>
                                <?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?php echo url('admin/Bill/returned.php') ?>"> فاکتورهای
                                            مسترد
                                            شده </a>
                                    </li>
                                <?php endif; ?>
                            <?php } ?>

                            <?php if (inst_feature('wallet') && Permision('permision_actwallet')) { ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link" href="<?php echo url('admin/Bill/waitingWallet.php') ?>">منتظر تایید
                                        کیف پول <?php if (countCheckWallet() > 0) { ?><span
                                                class="badge badge-warning mg-r-20 "><?php echo countCheckWallet(); ?> </span><?php } ?>
                                    </a>
                                </li>
                            <?php } ?>

                            <?php if ($adminRole !== 2 && Permision('permision_bill')): ?>

                                <li class="nav-sub-item">
                                    <a class="nav-sub-link" href="<?php echo url('admin/Bill/SearchBillView.php') ?>">صورتحساب
                                        ها</a>
                                </li>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link" href="<?php echo url('admin/Bill/advanced-report.php') ?>">گزارش
                                        پیشرفته</a>
                                </li>
                            <?php endif; ?>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Bill/payment.php') ?>"> موارد قسطی
                                </a>
                            </li>
                        <?php } ?>

                        <?php if (Permision('permision_viewbill')) { ?>
                            <li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/finance/referrer-commission.php') ?>">کارمزد معرف</a></li>
                        <?php } ?>

                        <?php if (Permision('permission_monthly_payment')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/finance/monthly-payments.php') ?>">واریز ماهانه همکاران</a>
                            </li>
                        <?php } ?>

                        <?php if (Permision('permission_staff_documents')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/staff-documents.php') ?>">مستندات همکاران</a>
                            </li>
                        <?php } ?>

                        <?php if (Permision('permission_insurance')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/insurance.php') ?>">بیمه افراد</a>
                            </li>
                        <?php } ?>

                        <?php if (Permision('permision_viewbill')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/setting/credit-card.php') ?>">افزودن شماره کارت</a>
                            </li>
                        <?php } ?>


                    </ul>
                </li>
            <?php } ?>
            

        </ul>
        <hr>
        <ul class="nav" style="margin-left: 10px; display:none;" aria-hidden="true">
            <li class="text-center text-white">
                <div class="d-flex align-items-center" style="gap:12px;">

                    <div class="dropdown dropup flex-grow-1">

                        <button aria-expanded="false"
                            aria-haspopup="true"
                            class="btn ripple btn-block btn-info dropdown-toggle w-100"
                            data-bs-toggle="dropdown"
                            type="button"
                            style="font-size:16px;">

                            <i class="fe fe-hash"></i>چشم انداز کلی
                        </button>

                        <div class="dropdown-menu tx-13">
                            <?php $usesPermissionTree = AdminHasAnyCurrentTreePermission();
                            if ($usesPermissionTree
                                ? AdminHasMenuNavigationPermission('dashboard.summary.overview.desktop')
                                : in_array((int)$_SESSION['admin']['adminrole'], [1, 6, 52], true)) { ?>
                                <a class="dropdown-item" href="<?php echo url('admin/panel/desktop.php') ?>">میزکار
                                    من</a>
                            <?php } ?>

                            <?php if (Permision('permision_manage_suporter')) { ?>
                                <a class="dropdown-item" href="<?php echo url('admin/panel/manager.php') ?>">داشبورد</a>
                            <?php } ?>

                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-success" href="">گزارش بروزرسانی</a>
                        </div>

                    </div>

                    <span class="menu-info-trigger flex-shrink-0"
                        data-tooltip="<?php echo m('menu.mysystem_tooltip') ?>"
                        aria-label="راهنمای سامانه من">
                        <i class="fe fe-info fs-4"></i>
                    </span>

                </div>
                <div class="mt-3">نسخه سامانه 2.7.4</div>
            </li>

        </ul>
    </div>
</div>

<script>
    (function () {
        if (window.adminInfoTooltipInitialized) return;
        window.adminInfoTooltipInitialized = true;

        var tooltip = document.getElementById('menuInfoTooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'menuInfoTooltip';
            tooltip.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltip);
        }
        var activeTrigger = null;

        function closestTrigger(target) {
            while (target && target !== document) {
                if (target.nodeType === 1 && target.classList.contains('menu-info-trigger') && target.hasAttribute('data-tooltip')) return target;
                target = target.parentNode;
            }
            return null;
        }
        function showTooltip(trigger) {
            var message = trigger.getAttribute('data-tooltip');
            if (!message) return;
            activeTrigger = trigger;
            tooltip.textContent = message;
            tooltip.classList.remove('place-left', 'place-right');
            tooltip.classList.add('show');
            var triggerRect = trigger.getBoundingClientRect();
            var tooltipRect = tooltip.getBoundingClientRect();
            var margin = 10;
            var left = triggerRect.left - tooltipRect.width - margin;
            var placement = 'place-left';
            if (left < margin) { left = triggerRect.right + margin; placement = 'place-right'; }
            left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));
            var halfHeight = tooltipRect.height / 2;
            var top = triggerRect.top + (triggerRect.height / 2);
            top = Math.max(halfHeight + margin, Math.min(top, window.innerHeight - halfHeight - margin));
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
            tooltip.classList.add(placement);
        }
        function hideTooltip(trigger) {
            if (trigger && activeTrigger && trigger !== activeTrigger) return;
            activeTrigger = null;
            tooltip.classList.remove('show', 'place-left', 'place-right');
        }
        document.addEventListener('pointerover', function (event) {
            var trigger = closestTrigger(event.target);
            if (trigger && (!event.relatedTarget || !trigger.contains(event.relatedTarget))) showTooltip(trigger);
        });
        document.addEventListener('pointerout', function (event) {
            var trigger = closestTrigger(event.target);
            if (trigger && (!event.relatedTarget || !trigger.contains(event.relatedTarget))) hideTooltip(trigger);
        });
        document.addEventListener('focusin', function (event) { var trigger = closestTrigger(event.target); if (trigger) showTooltip(trigger); });
        document.addEventListener('focusout', function (event) { var trigger = closestTrigger(event.target); if (trigger) hideTooltip(trigger); });
        window.addEventListener('scroll', function () { hideTooltip(); }, true);
        window.addEventListener('resize', function () { hideTooltip(); });
    })();
</script>
