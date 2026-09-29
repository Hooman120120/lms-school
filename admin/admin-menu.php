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
$stmt = $conn->prepare("SELECT adminrole AS role FROM dbadmin WHERE username = ? LIMIT 1");
$stmt->execute([$currentUser]);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// The admin's school position, shown in the sidebar like the OES «سمت» badge
$schoolRoleTitle = '';
try {
    $roleStmt = $conn->prepare('SELECT sr.title FROM dbadmin a JOIN school_admin_roles sr ON sr.id = a.school_role_id WHERE a.username = ? LIMIT 1');
    $roleStmt->execute([$currentUser]);
    $schoolRoleTitle = (string)$roleStmt->fetchColumn();
} catch (PDOException $e) {
    // older databases have no school roles yet
}

// ✅ Normalize role to int exactly once (safe default = 0)
// Keep session override if it exists
$adminRole = ($sessionRole !== null)
    ? (int)$sessionRole
    : (int)($row['role'] ?? 0);

// ✅ permissions
$isManager = AdminCan(AdminNotificationCode('information.tickets.manage'));
$canSeeAll = AdminCan(AdminNotificationCode('information.tickets.all'));
$isOperator = AdminCan(AdminNotificationCode('information.tickets.own'));
$administrator = AdminCan('notifications.all-records') ? 1 : 0;
$installment_manager = AdminScope('finance.installments') === 'all' ? 1 : 0;

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
$schoolDirectNotificationCount = CountSchoolDirectNotifications($currentUser, false);
$schoolNotificationTypes = array_values(array_filter($schoolNotificationTypes, static fn(string $type): bool =>
    IsSchoolDirectNotificationType($type)
    || AdminCan(AdminNotificationCode('categories.' . $type))));


$role = (int)($_SESSION['admin']['adminrole'] ?? $adminRole);

// مدیر مدرسه از رنگ هویتی مدرسه استفاده می‌کند؛ آموزشگاه رنگ فعلی خود را نگه می‌دارد.
if ($role === 0 && inst_is('school')) $c = '#1E4C4C';
elseif ($role === 1) $c = '#138420';
elseif ($role === 2) $c = '#516d9b';
elseif ($role === 6) $c = '#ff8a00';
elseif ($role >= 51 && $role <= 59) $c = '#244370';
else                  $c = '#15233f';
?>

<?php include_once __DIR__ . '/info-tooltip.php'; ?>

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
    .school-role-badge {
        display: block;
        margin: 4px 18px 14px;
        padding: 8px 10px;
        border-radius: 8px;
        background: #f0f4ff;
        color: #3448a3;
        text-align: center;
        font-size: 12px;
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
            <li><div class="school-role-badge">سمت: <?= htmlspecialchars($schoolRoleTitle !== '' ? $schoolRoleTitle : 'بدون سمت', ENT_QUOTES, 'UTF-8') ?></div></li>
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
            <?php if (AdminCanSee('public-relations')) { ?>

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
                        <?php if (AdminCanSee('public-relations.safe-call')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_call.php') ?>">تماس امن
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (AdminCanSee('public-relations.safe-sms')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_sms.php') ?>">پیامک امن
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>

            <?php if (AdminCanSee('students')): ?>
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
                        <?php if (inst_is('school') || AdminCanSee('students.definitive')): ?>
                        <?php if (inst_is('institute')): ?>
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                    style="font-size:16px; color: #79f57d;"><?php echo m('menu.students_definitive') ?></span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;"> <!-- فاصله زیرمنو -->
                        <?php endif; ?>
                                <?php if (AdminCanSee('students.definitive.register')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/add.php') ?>">ثبت نام</a>
                                    </li>
                                <?php endif; ?>
                                <!-- <//?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<//?= url('admin/Student/add-group.php') ?>">ثبت نام گروهی </a>
                                    </li>
                                <//?php endif; ?> -->
                                <?php if (AdminCanSee('students.definitive.enrolment')): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/addcourse.php') ?>"><?php echo m('menu.student_register_course') ?></a>
                                </li>
                                <?php endif; ?>
                                <?php if (inst_is('school') && AdminCanSee('students.temporary.enrolment')): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/democourse/addstudent.php') ?>"><?= m('menu.register_democourse_student') ?></a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminCanSee('students.definitive.edit')): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-absolute.php') ?>"><?= inst_is('school') ? 'ویرایش اطلاعات' : 'ویرایش اطلاعات قطعی' ?></a>
                                </li>
                                <?php endif; ?>

                                <?php if (true) : ?>

                                    <?php if (AdminCanSee('students.definitive.my-students')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/mystudent.php') ?>">
                                            <?php echo m('menu.students_individual') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (inst_is('institute') && AdminCanSee('students.definitive.my-courses')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/mypersonalcourse.php') ?>">
                                            <?php echo m('menu.students_per_course') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminCanSee('students.definitive.cancelled-support')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/canceled_student_list.php') ?>">
                                            <?php echo m('menu.students_canceled_support') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminCanSee('students.definitive.suspended-support')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/panel/suspension_list.php') ?>">
                                            <?php echo m('menu.students_suspended_list') ?>
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                <?php endif; ?>


                                <?php if (true): ?>
                                    <?php if (AdminCanSee('students.definitive.course-report')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                            href="<?= url('admin/Student/registration-Course-absolute.php') ?>">گزارش
                                            تفکیکی</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (inst_is('school') && AdminCanSee('students.temporary.report')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/registration-Course-experimental.php') ?>">گزارش تفکیکی فوق برنامه</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if (AdminCanSee('students.definitive.inactive')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/unactive-student.php') ?>">غیرفعال‌ها</a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (AdminCanSee('students.definitive.cancelled')): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/Student/canceled-student.php') ?>">انصرافی ها</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (AdminCanSee('students.definitive.suspended')): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                                href="<?= url('admin/Student/suspended-student.php') ?>">
                                                تعلیقی ها
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (AdminCanSee('students.definitive.cancelled-operator')): ?>
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
                        <?php if (inst_is('institute') && AdminCanSee('students.temporary')): ?>
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                    style="font-size:16px; color: #79f57d;"><?php echo m('menu.students_temporary') ?></span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;">
                                <?php if (AdminCanSee('students.temporary.register')): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/experimental-add.php') ?>">ثبت
                                        نام

                                        موقت</a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminCanSee('students.temporary.enrolment')): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/democourse/addstudent.php') ?>"><?php echo m('menu.register_democourse_student') ?></a>
                                    </li>
                                <?php endif; ?>
                                <?php if (AdminCanSee('students.temporary.edit')): ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-experimental.php') ?>">ویرایش
                                        اطلاعات موقت</a>
                                </li>
                                <?php endif; ?>
                                <?php if (AdminCanSee('students.temporary.report')): ?>
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
            if (AdminCanSee('executive')) { ?>
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
                        <?php if (AdminCanSee('executive.calendar')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/panel/calendar_panel.php') ?>">تقویم
                                    اجرایی</a>
                            </li>
                        <?php } ?>
                        <?php if (AdminCanSee('executive.academic-calendar')): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/panel/academic-calendar.php') ?>">تقویم
                                آموزشی</a>
                        </li>
                        <?php endif; ?>
                        <?php if (AdminCanSee('executive.daily-notices')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/notifications/daily_notifications.php') ?>">تابلو
                                    اعلان روزانه
                                </a>
                            </li>
                        <?php } ?>
                        <?php if (AdminCanSee('executive.news')) { ?>
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

            <?php if (AdminCanSee('courses')): ?>
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
                    <?php if (AdminCanSee('courses.main')): ?>
                    <li class="nav-item show">
                        <a class="nav with-sub" href="#"><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label" style="font-size:16px; color: #79f57d;"><?php echo m('menu.courses_sub') ?></span>
                        </a>

                        <ul class="nav-sub">
                            <?php if (true): ?>
                                <?php if (AdminCanSee('courses.main.create')) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?php echo url('admin/course/add-Course.php') ?>"><?php echo m('menu.course_create') ?></a>
                                    </li>
                                <?php } ?>
                                <?php if (AdminCanSee('courses.main.demo-create')): ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link"
                                        href="<?php echo url('admin/democourse/add-democourse.php') ?>"><?php echo m('menu.democourse_create') ?></a>
                                </li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (AdminCanSee('courses.main.active')): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>"><?php echo m('menu.active_courses') ?></a>
                            </li>
                            <?php endif; ?>

                            <?php if (AdminCanSee('courses.main.demo-active')): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link"
                                    href="<?php echo url('admin/democourse/list-democourse.php?paye=all') ?>"><?php echo m('menu.active_democourses') ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if (true): ?>
                                <?php if (AdminCanSee('courses.main.archive')) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                            href="<?php echo url('admin/course/unactive-course.php') ?>">
                                            بایگانی سال‌های قبل</a>
                                    </li>
                                <?php } ?>
                                <?php if (AdminCanSee('courses.main.discount')) { ?>
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
                    <?php if (AdminCanSee('courses.handhug')): ?>
                    <li class="nav-item show">
                        <a class="nav with-sub" href=""><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label "
                                style="font-size:16px; color: #79f57d;">"بغل دستی ها"</span></a>

                        <ul class="nav-sub">
                            <?php if (AdminCanSee('courses.handhug.create')): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link " href="<?php echo url('admin/handhug/add-handhug.php') ?>"> ساخت
                                    محصول "بغل دستی ها"</a>
                            </li>
                            <?php endif; ?>
                            <?php if (AdminCanSee('courses.handhug.manage')): ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link "
                                    href="<?php echo url('admin/handhug/handhug-management.php') ?>"> <?php echo m('menu.handhug_active') ?></a>
                            </li>
                            <?php endif; ?>
                            <?php if (AdminCanSee('courses.handhug.archive')): ?>
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
            <?php if (inst_is('school') && (AdminCanSee('school-notifications') || $schoolDirectNotificationCount > 0)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('admin/notifications/school_notifications.php') ?>">
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
            <?php endif; ?>

            <?php if (inst_is('institute') && AdminCanSee('institute-notifications')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('admin/notifications/institute_notifications.php') ?>">
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
                        <?php $instituteNotificationCount = CountInstituteNotificationMenu(); ?>
                        <?php if ($instituteNotificationCount > 0): ?><span class="badge badge-warning side-badge mg-r-20"><?= (int)$instituteNotificationCount ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
            <!-- The institute notification entry above opens the shared notification screen. -->
            <?php if (AdminCanSee('tickets')) { ?>
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
                        <?php if (AdminCanSee('tickets.referred')) { ?>
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
                        <?php if (AdminCanSee('tickets.all')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Tickets/allticket.php') ?>"> کارتابل
                                    همه تیکت ها </a>
                            </li>
                        <?php } ?>


                    </ul>
                </li>
            <?php } ?>
            <?php if (AdminCanSee('documents')) { ?>
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
                        <i class="angle fe fe-chevron-left"></i>
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

            <?php if (AdminCanSee('staff')) { ?>
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
                        <?php if (AdminCanSee('staff.directory')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/staff-directory.php') ?>">مشاهده همکاران</a>
                            </li>
                        <?php } ?>

                        <?php if (true) { ?>
                            <?php if (AdminCanSee('staff.manage')): ?><li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/listadmin.php') ?>">مدیریت همکاران</a>
                            </li><?php endif; ?>

                            <?php if (AdminCanSee('staff.teachers')): ?><li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/listteacher.php') ?>">مشاهده
                                    <?php echo t('teacher_pl') ?> </a>

                            </li><?php endif; ?>

                            <?php if (AdminCanSee('staff.create')): ?><li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/addemploy.php') ?>"> ثبت همکار
                                    جدید </a>
                            </li><?php endif; ?>

                            <?php if (AdminCanSee('staff.teacher-create')): ?><li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/employ/addteacher.php') ?>"> <?php echo m('page.add_teacher') ?> </a>
                            </li><?php endif; ?>
                        <?php } ?>

                    </ul>
                </li>

            <?php } ?>

            <?php if (AdminCanSee('assigned-tasks')) { ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo url('admin/tasks/assigned-tasks.php') ?>">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="fe fe-check-square sidemenu-icon"></i>
                        <span class="sidemenu-label">کارهای محول‌شده</span>
                    </a>
                </li>
            <?php } ?>

            <?php if (AdminCanSee('referrers')) { ?>
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
                        <?php if (AdminCanSee('referrers.report')) { ?>
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


            <?php if (AdminCanSee('attendance')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-user-check sidemenu-icon"></i>

                        <span class="sidemenu-label"
                            style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                            حضور و غیاب

                            <span class="menu-info-trigger"
                                data-tooltip="در این بخش دستگاه‌های تشخیص چهره، اثر انگشت یا کارت مدرسه ثبت می‌شوند و ورود و خروج دانش‌آموزان با کد ملی دریافت می‌شود. امکاناتی مانند گزارش روزانهٔ حاضر، تأخیر و غایب، بارگذاری گزارش دستگاه از فایل اکسل و تعیین قوانین ثبت خودکار حضور و غیاب در دفتر کلاسی از طریق این قسمت انجام می‌شود."
                                aria-label="راهنمای حضور و غیاب"
                                onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i class="angle fe fe-chevron-left"></i></a>
                    <ul class="nav-sub">
                        <?php if (AdminCanSee('attendance.report')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/attendance/report.php') ?>">گزارش روزانه</a></li><?php endif; ?>
                        <?php if (AdminCanSee('attendance.devices')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/attendance/devices.php') ?>">دستگاه‌ها و رکوردها</a></li><?php endif; ?>
                        <?php if (AdminCanSee('attendance.import')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/attendance/import.php') ?>">ورود از فایل اکسل</a></li><?php endif; ?>
                        <?php if (AdminCanSee('attendance.rules')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/attendance/rules.php') ?>">قوانین حضور و غیاب</a></li><?php endif; ?>
                    </ul>
                </li>
            <?php } ?>

            <?php if (AdminCanSee('reports')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="fe fe-radio sidemenu-icon"></i><span class="sidemenu-label">آمار و گزارشات</span><i class="angle fe fe-chevron-left"></i></a>
                    <ul class="nav-sub">
                        <?php if (AdminCanSee('reports.students')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/report/students.php') ?>"><?= m('menu.students') ?></a></li><?php endif; ?>
                        <?php if (AdminCanSee('reports.staff')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/report/colleague.php') ?>">همکاران</a></li><?php endif; ?>
                    </ul>
                </li>
            <?php } ?>
            <?php if (AdminCanSee('homework-approval')) : ?>
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
            <?php if (AdminCanSee('finance')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="fe fe-database sidemenu-icon"></i><span class="sidemenu-label">امور مالی</span><i class="angle fe fe-chevron-left"></i></a>
                    <ul class="nav-sub">
                        <?php if (AdminCanSee('finance.pending')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/active-factor.php') ?>">فاکتورهای منتظر تأیید</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.unpaid')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/factor-notpay.php') ?>">فاکتورهای پرداخت‌نشده</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.returned')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/returned.php') ?>">فاکتورهای مستردشده</a></li><?php endif; ?>
                        <?php if (inst_feature('wallet') && AdminCanSee('finance.wallet')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/waitingWallet.php') ?>">شارژهای کیف پول منتظر تأیید</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.referrer-commission')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/finance/referrer-commission.php') ?>">کارمزد معرف</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.cards')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/setting/credit-card.php') ?>">افزودن شماره کارت</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.statement')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/SearchBillView.php') ?>">صورتحساب‌ها</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.advanced-report')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/advanced-report.php') ?>">گزارش مالی پیشرفته</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.installments')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/Bill/payment.php') ?>">موارد اقساطی</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.monthly-payments')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/finance/monthly-payments.php') ?>">واریز ماهانه همکاران</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.staff-documents')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/employ/staff-documents.php') ?>">مستندات همکاران</a></li><?php endif; ?>
                        <?php if (AdminCanSee('finance.insurance')): ?><li class="nav-sub-item"><a class="nav-sub-link" href="<?= url('admin/employ/insurance.php') ?>">بیمه افراد</a></li><?php endif; ?>
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
                            <?php if (AdminCanSee('dashboard.desks.mine')) { ?>
                                <a class="dropdown-item" href="<?php echo url('admin/panel/desktop.php') ?>">میزکار
                                    من</a>
                            <?php } ?>

                            <?php if (AdminCanSee('dashboard.desks.manager')) { ?>
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
