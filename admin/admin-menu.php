<?php
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


$role = (int)($_SESSION['admin']['adminrole'] ?? 0);

if ($role === 1) $c = '#138420';
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
        z-index: 999999;
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
        transition: opacity 0.15s;
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
            <li class="nav-header"><span class="nav-label">پنل کاربری</span></li>
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

                             روابط عمومی

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
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_call.php') ?>">تماس امن
                            </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_sms.php') ?>">پیامک امن
                            </a>
                        </li>
                    </ul>
                </li>
            <?php } ?>

            <?php if (Permision('permision_viewstudent')): ?>
                <li class="nav-item" style="margin-bottom: 5px;">
                    <a class="nav-link with-sub" href="#">
                        <span class="shape1"></span><span class="shape2"></span>
                        <i class="si si-graduation sidemenu-icon"></i>
                        <span class="sidemenu-label"
                              style="display:inline-flex;
                                 align-items:center;
                                 gap:12px;">

                            دانش آموزان

                            <span class="menu-info-trigger"
                                  data-tooltip="در این بخش امکان ثبت نام دانش آموز به صورت قطعی و موقت و همچنین ثبت نام پایه آموزشی و ویرایش اطلاعات دانش آموز وجود دارد. همچنین امکان مشاهده گزارش‌های تفکیکی، دانش آموزان فردی و نفر پایه، افراد غیرفعال، انصرافی، تعلیقی و لغو پشتیبانی‌شده وجود دارد."
                                  aria-label="راهنمای مدیریت دانش آموزان"
                                  onclick="event.preventDefault(); event.stopPropagation();">

                                <i class="fe fe-info fs-4"></i>
                            </span>

                        </span>
                        <i class="angle fe fe-chevron-left"></i>
                    </a>

                    <ul class="nav-sub">

                        <!-- دانش آموزان قطعی -->
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                      style="font-size:16px; color: #79f57d;">دانش آموزان قطعی</span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;"> <!-- فاصله زیرمنو -->
                                <?php if ($adminRole !== 6): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/add.php') ?>">ثبت نام</a>
                                    </li>
                                <?php endif; ?>
                                <!-- <//?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<//?= url('admin/Student/add-group.php') ?>">ثبت نام گروهی </a>
                                    </li>
                                <//?php endif; ?> -->
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/addcourse.php') ?>">ثبت
                                        پایه</a>
                                </li>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-absolute.php') ?>">ویرایش
                                        اطلاعات قطعی</a>
                                </li>

                                <?php if ($adminRole == 1) : ?>

                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/panel/mystudent.php') ?>">
                                            دانش آموزان فردی
                                        </a>
                                    </li>

                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/panel/mypersonalcourse.php') ?>">
                                            دانش آموزان نفر پایه
                                        </a>
                                    </li>

                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/panel/canceled_student_list.php') ?>">
                                            دانش آموزان لغو پشتیبانی
                                        </a>
                                    </li>

                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/panel/suspension_list.php') ?>">
                                            دانش آموزان تعلیقی
                                        </a>
                                    </li>

                                <?php endif; ?>


                                <?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/Student/registration-Course-absolute.php') ?>">گزارش
                                            تفکیکی</a>
                                    </li>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/Student/unactive-student.php') ?>">غیرفعال‌ها</a>
                                    </li>

                                    <?php if (Permision('permision_reportstudent')): ?>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                               href="<?= url('admin/Student/canceled-student.php') ?>">انصرافی ها</a>
                                        </li>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                               href="<?= url('admin/Student/suspended-student.php') ?>">
                                                تعلیقی ها
                                            </a>
                                        </li>
                                        <li class="nav-sub-item" style="margin-bottom: 3px;">
                                            <a class="nav-sub-link"
                                               href="<?= url('admin/Student/canceled-op-student.php') ?>">لغو پشتیبانی
                                                شده ها</a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <!-- دانش آموزان آزمایشی -->
                        <li class="nav-item show" style="margin-bottom: 5px;">
                            <a class="d-flex flex-row with-sub" href="#" style="height: 35px; padding-left: 25px;">
                                <span class="shape1"></span><span class="shape2"></span>
                                <span class="sidemenu-label"
                                      style="font-size:16px; color: #79f57d;">دانش آموزان موقت</span>
                            </a>

                            <ul class="nav-sub" style="padding-left: 20px;">
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/experimental-add.php') ?>">ثبت
                                        نام

                                        موقت</a>
                                </li>
                                <?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link" href="<?= url('admin/democourse/addstudent.php') ?>">ثبت
                                            فوق برنامه</a>
                                    </li>
                                <?php endif; ?>
                                <li class="nav-sub-item" style="margin-bottom: 3px;">
                                    <a class="nav-sub-link" href="<?= url('admin/Student/edit-experimental.php') ?>">ویرایش
                                        اطلاعات موقت</a>
                                </li>
                                <?php if ($adminRole !== 2): ?>
                                    <li class="nav-sub-item" style="margin-bottom: 3px;">
                                        <a class="nav-sub-link"
                                           href="<?= url('admin/Student/registration-Course-experimental.php') ?>">گزارش
                                            تفکیکی</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>

                    </ul>
                </li>
            <?php endif; ?>


            <?php
            $execMenuIsAdmin = Permision('administrator');
            $execMenuCanViewAcademic = ((int)$adminRole === 3) || SchoolStaffRoleKey($currentUser) !== '';
            if ($execMenuIsAdmin || $execMenuCanViewAcademic) { ?>
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
                        <?php if ($execMenuIsAdmin) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/panel/calendar_panel.php') ?>">تقویم
                                    اجرایی</a>
                            </li>
                        <?php } ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/panel/academic-calendar.php') ?>">تقویم
                                آموزشی</a>
                        </li>
                        <?php if ($execMenuIsAdmin) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/notifications/daily_notifications.php') ?>">تابلو
                                    اعلان روزانه
                                </a>
                            </li>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/notifications/news_panel.php') ?>">تابلو
                                    اخبار</a>
                            </li>
                        <?php } ?>
                        <!--                    <li class="nav-sub-item">-->
                        <!--                        <a class="nav-sub-link" href="--><?php //echo url('admin/events-panel.php') ?><!--">تابلو رویداد</a>-->
                        <!--                    </li>-->
                    </ul>
                </li>
            <?php } ?>

            <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                        class="si si-organization sidemenu-icon"></i>

                    <span class="sidemenu-label"
                          style="display:inline-flex;
                             align-items:center;
                             gap:12px;">

                           پایه ها

                        <span class="menu-info-trigger"
                              data-tooltip="مدیریت پایه‌ها و دوره‌های فوق برنامه؛ در این بخش می‌توانید پایه‌ها و دوره‌های فوق برنامه را ثبت و مدیریت کنید، پایه‌های در حال اجرا و بایگانی سال‌های قبل را مشاهده نمایید و کد تخفیف بسازید. همچنین امکان مدیریت محصولات «بغل دستی‌ها» شامل ساخت محصول و مشاهده گروه‌های فعال و غیرفعال وجود دارد."
                              aria-label="راهنمای مدیریت محصولات"
                              onclick="event.preventDefault(); event.stopPropagation();">

                            <i class="fe fe-info fs-4"></i>
                        </span>

                   </span>
                    <i
                        class="angle fe fe-chevron-left"></i>
                </a>

                <ul class="nav-sub ">
                    <li class="nav-item show">
                        <a class="nav with-sub" href="#"><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label" style="font-size:16px; color: #79f57d;">پایه‌ها</span>
                        </a>

                        <ul class="nav-sub">
                            <?php if ($adminRole !== 2): ?>
                                <?php if (Permision('permision_viewcourse')) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link" href="<?php echo url('admin/course/add-Course.php') ?>">ثبت
                                            پایه</a>
                                    </li>
                                <?php } ?>
                                <li class="nav-sub-item">
                                    <a class="nav-sub-link"
                                       href="<?php echo url('admin/democourse/add-democourse.php') ?>">ثبت دوره
                                        فوق برنامه</a>
                                </li>
                            <?php endif; ?>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>">
                                    پایه‌های در حال اجرا</a>
                            </li>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link"
                                   href="<?php echo url('admin/democourse/list-democourse.php?paye=all') ?>">
                                    دوره‌های فوق برنامه در حال اجرا</a>
                            </li>
                            <?php if ($adminRole !== 2): ?>
                                <?php if (Permision('permision_viewcourse')) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                           href="<?php echo url('admin/course/unactive-course.php') ?>">
                                            بایگانی سال‌های قبل</a>
                                    </li>
                                <?php } ?>
                                <?php if (Permision('permision_discount')) { ?>
                                    <li class="nav-sub-item">
                                        <a class="nav-sub-link"
                                           href="<?php echo url('admin/course/add-discount.php') ?>"> ایجاد
                                            کد تخفیف</a>
                                    </li>
                                <?php } ?>
                            <?php endif; ?>

                        </ul>
                    </li>
                    <li class="nav-item show">
                        <a class="nav with-sub" href=""><span class="shape1"></span><span class="shape2"></span>
                            <span class="sidemenu-label "
                                  style="font-size:16px; color: #79f57d;">"بغل دستی ها"</span></a>

                        <ul class="nav-sub">
                            <li class="nav-sub-item">
                                <a class="nav-sub-link " href="<?php echo url('admin/handhug/add-handhug.php') ?>"> ساخت
                                    محصول "بغل دستی ها"</a>
                            </li>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link "
                                   href="<?php echo url('admin/handhug/handhug-management.php') ?>"> پایه های فعال "بغل
                                    دستی ها"</a>
                            </li>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link "
                                   href="<?php echo url('admin/handhug/disabled-handhugs.php') ?>"> بایگانی سال‌های قبل
                                    "بغل دستی ها"</a>
                            </li>
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


                </ul>
            </li>

            <?php if ($schoolNotificationTypes !== []): ?>
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
            <?php endif; ?>

            <!-- Legacy institute alerts are intentionally hidden in school mode. -->
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
                                <!--                            --><?php //if ($notifTotalCount > 0): ?>
                                <!--                                <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 87px; height: 20px;"></span>-->
                                <!--                            --><?php //endif; ?>
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
                                <!--                            --><?php //if ($notifTotalCount > 0): ?>
                                <!--                            <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 55px; height: 20px;"></span>-->
                                <!--                            --><?php //endif; ?>

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
                                <? //= url('admin/notifications/teachers_notif.php') ?><!--">-->
                                <!--                                                        تیکت اساتید-->
                                <!--                                                    --><?php //if ($teacherNotifCount > 0): ?>
                                <!--                                                        <span class="badge badge-warning mg-r-20">-->
                                <? //= (int)$teacherNotifCount ?><!--</span>-->
                                <!--                                                    --><?php //endif; ?>
                                <!--                                                    <span class="badge badge-light"></span>-->
                                <!--                                                </a>-->
                                <!--                                            </li>-->

                                <!--                                                <li class="nav-sub-item">-->
                                <!--                                                    <a class="nav-sub-link" href="-->
                                <? //= url('admin/notifications/studentoperator_changes_notif.php') ?><!--">-->
                                <!--                                                        --><? //= ((int)$administrator === 1) ? 'دانش آموزان تغییر پشتیبان یافته' : 'لیست دانش آموزان تغییر پشتیبان یافته به شما' ?>
                                <!--                                                        --><?php //if ($studentOpChangeCount > 0): ?>
                                <!--                                                            <span class="badge badge-warning pr-2">-->
                                <? //= (int)$studentOpChangeCount ?><!--</span>-->
                                <!--                                                        --><?php //endif; ?>
                                <!--                                                        <span class="badge badge-light"></span>-->
                                <!--                                                    </a>-->
                                <!--                                                </li>-->
                                <!---->
                                <!--                                                <li class="nav-sub-item">-->
                                <!--                                                    <a class="nav-sub-link" href="-->
                                <? //= url('admin/notifications/new_students.php') ?><!--">-->
                                <!--                                                        --><? //= ((int)$administrator === 1) ? 'دانش آموزان با پشتیبان جدید' : 'لیست دانش آموزان جدید شما' ?>
                                <!---->
                                <!--                                                        --><?php //if ($countNewStudent > 0): ?>
                                <!--                                                            <span class="badge badge-warning pr-2">-->
                                <? //= (int)$countNewStudent ?><!--</span>-->
                                <!--                                                        --><?php //endif; ?>
                                <!---->
                                <!--                                                        <span class="badge badge-light"></span>-->
                                <!--                                                    </a>-->
                                <!--                                                </li>-->
                                <!---->
                                <!--                                                <li class="nav-sub-item">-->
                                <!--                                                    <a class="nav-sub-link" href="-->
                                <? //= url('admin/notifications/new_student_tickets.php') ?><!--">-->
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
                    <!--                             --><?php //if ($teacherNotifCount > 0): ?>
                    <!--                                 <span class="badge badge-warning" style="border-radius: 50px; display: flex; margin-right: 80px; height: 20px;">-->
                    <? //= (int)$teacherNotifCount ?><!--</span>-->
                    <!--                             --><?php //endif; ?>
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
                  data-tooltip="در این بخش می‌توانید مدارک و فایل‌های ارسالی دانش آموزان را مدیریت کنید. از طریق این قسمت امکان مشاهده مستندات جدید، جستجو در میان مدارک ثبت‌شده و بررسی اطلاعات مربوط به هر دانش آموز فراهم شده است."
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
                            <a class="nav-sub-link" href="<?php echo url('admin/employ/addteacher.php') ?>"> <?php echo m('action.add_teacher') ?>
جدید </a>
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
                              data-tooltip="معرفان برون سازمانی: از طریق این بخش می‌توانید  معرفان برون‌سازمانی خود را سازماندهی کنید. هر معرف با یک کد اختصاصی شناسایی می‌شود که امکانِ پیگیری و گزارش‌گیری دقیق از عملکرد (تعداد ثبت‌نام‌های موفق دانش آموز)را فراهم می‌آورد."
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
                            <!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Collague/report-collague.php') ?><!--">گزارش-->
                            <!--                                    آماری همکاران</a>-->
                            <!--                            </li>-->
                        <?php } ?>
                        <!--                        --><?php //if (Permision('permision_reportcollague')) { ?>
                        <!--                            <li class="nav-sub-item">-->
                        <!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Collague/partnerspanel.php') ?><!--">آمار-->
                        <!--                                    دانش آموزان خارج از هنرآموزش</a>-->
                        <!--                            </li>-->
                        <!--                        --><?php //} ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/sub-collague.php') ?>">
                                زیرمجموعه های ثبت شده معرفان
                            </a>
                        </li>


                    </ul>
                </li>
            <?php } ?>


            <?php if (Permision('permision_viewsetting')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-settings t sidemenu-icon"></i>

                        <span class="sidemenu-label"
                              style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

            تنظیمات

            <span class="menu-info-trigger"
                  data-tooltip="در این بخش می‌توانید تنظیمات عمومی سامانه را مدیریت کنید. امکاناتی مانند تنظیمات کلی سیستم، مدیریت بلک‌لیست، ثبت و مدیریت شماره کارت‌ها، پشتیبان‌گیری از اطلاعات و مدیریت پیام‌های آماده از طریق این قسمت در دسترس است."
                  aria-label="راهنمای تنظیمات"
                  onclick="event.preventDefault(); event.stopPropagation();">

                <i class="fe fe-info fs-4"></i>
            </span>

        </span>
                        <i
                            class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/setting/setting.php') ?>"> تنظیمات
                                عمومی </a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/setting/blacklist.php') ?>">بلک لیست
                                <span class="badge badge-light "></span></a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/setting/credit-card.php') ?>"> افزودن
                                شماره کارت <span class="badge badge-light "></span></a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('') ?>"> پشتیبان گیری
                                <span class="badge badge-light "></span></a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/setting/ready-message.php') ?>"> پیام
                                های آماده
                                <span class="badge badge-light "></span></a>
                        </li>


                    </ul>
                </li>
            <?php } ?>
            <?php if (Permision('excel_permission')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-radio sidemenu-icon"></i>

                        <span class="sidemenu-label"
                              style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

                        آمار و گزارشات

                        <span class="menu-info-trigger"
                              data-tooltip="در این بخش می‌توانید گزارش‌ها و آمارهای مختلف سامانه را مشاهده و تهیه کنید. گزارش‌های مربوط به دانش آموزان و همکاران با استفاده از فیلترهای متنوع قابل استخراج هستند و می‌توان از آن‌ها برای تحلیل عملکرد، پیگیری وضعیت کاربران و تهیه گزارش‌های مدیریتی استفاده کرد."
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
                            <a class="nav-sub-link" href="<?php echo url('admin/report/students.php') ?>"> دانش آموزان
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
            <?php if (Permision('permision_viewbill') || Permision('permission_monthly_payment') || Permision('permission_staff_documents') || Permision('permission_insurance')) { ?>
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
                            class="angle fe fe-chevron-left"></i><?php $count = Permision('permision_viewbill') ? countCheckfactor() + countCheckWallet() : 0;
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

                        <?php if (Permision('permision_actwallet')) { ?>
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


                    </ul>
                </li>
            <?php } ?>
            <?php if (Permision('permision_viewwallet')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="si si-wallet sidemenu-icon"></i>

                        <span class="sidemenu-label"
                              style="display:inline-flex;
                     align-items:center;
                     gap:12px;">

            مدیریت کیف پول

            <span class="menu-info-trigger"
                  data-tooltip="در این بخش امکان مدیریت و بررسی کیف پول کاربران فراهم است. می‌توانید موجودی کیف پول را مشاهده و مدیریت کنید، افزایش یا کاهش اعتبار انجام دهید و گزارش تراکنش‌های ثبت‌شده را برای بررسی سوابق مالی مشاهده نمایید."
                  aria-label="راهنمای مدیریت کیف پول"
                  onclick="event.preventDefault(); event.stopPropagation();">

                <i class="fe fe-info fs-4"></i>
            </span>

        </span>
<i
                            class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/wallet/dashboard.php') ?>">داشبورد کیف
                                پول </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/wallet/exchange.php') ?>">افزودن
                                /کاهش </a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/wallet/searchwalletview.php') ?>">گزارش
                                تراکنش ها</a>
                        </li>


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
                            <?php $allowed_users_operator = [1, 6, 52];
                            if (in_array($_SESSION['admin']['adminrole'], $allowed_users_operator)) { ?>
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
                          data-tooltip="این بخش هسته مدیریت پشتیبانی و آموزش سامانه است و امکان پایش متمرکز عملکرد تیم پشتیبانی، وضعیت فعالیت دانش آموزان و نظارت بر فرآیندهای آموزشی را برای تضمین کیفیت خدمات آموزشی در اختیار مدیران قرار می‌دهد."
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
    document.addEventListener('DOMContentLoaded', function () {
        const tooltip = document.createElement('div');
        tooltip.id = 'menuInfoTooltip';
        document.body.appendChild(tooltip);

        const triggers = document.querySelectorAll('.menu-info-trigger');

        function showTooltip(trigger) {
            tooltip.textContent = trigger.getAttribute('data-tooltip');

            tooltip.classList.remove('place-left', 'place-right');
            tooltip.classList.add('show');

            const triggerRect = trigger.getBoundingClientRect();
            const tooltipWidth = tooltip.offsetWidth;
            const tooltipHeight = tooltip.offsetHeight;

            let left = triggerRect.left - tooltipWidth - 10;
            let placement = 'place-left';

            // اگر سمت چپ فضا نبود، سمت راست نمایش داده شود
            if (left < 10) {
                left = triggerRect.right + 10;
                placement = 'place-right';
            }

            let top = triggerRect.top + (triggerRect.height / 2);
            const halfHeight = tooltipHeight / 2;

            // جلوگیری از خروج Tooltip از بالا یا پایین صفحه
            top = Math.max(halfHeight + 10, top);
            top = Math.min(window.innerHeight - halfHeight - 10, top);

            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
            tooltip.classList.add(placement);
        }

        function hideTooltip() {
            tooltip.classList.remove('show', 'place-left', 'place-right');
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('mouseenter', function () {
                showTooltip(trigger);
            });

            trigger.addEventListener('mouseleave', hideTooltip);
        });

        window.addEventListener('scroll', hideTooltip, true);
        window.addEventListener('resize', hideTooltip);
    });
</script>
