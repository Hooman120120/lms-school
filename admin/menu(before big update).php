<?php
if (!isset($_SESSION['admin'])) { header('location: login.php'); exit; }

$currentUser = $_SESSION['admin']['user'] ?? null;

/* role (optional) */
$adminRole = $_SESSION['admin']['role'] ?? null;
if ($adminRole === null && $currentUser) {
    $stmt = $conn->prepare('SELECT role FROM dbadmin WHERE username = ? LIMIT 1');
    $stmt->execute([$currentUser]);
    $adminRole = (int)$stmt->fetchColumn();
}

$isManager = $canSeeAll = false;
if ($currentUser) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(permision_viewalert, 0)  AS perm_viewalert,
            COALESCE(permision_viewemploy, 0) AS perm_viewemploy,
            COALESCE(permision_suporter, 0)   AS perm_supporter
        FROM dbadmin
        WHERE username = ?
        LIMIT 1
    ");
    $stmt->execute([$currentUser]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $isManager  = !empty($perm['perm_viewalert']);
    $canSeeAll  = $isManager && !empty($perm['perm_viewemploy']);
    $isOperator = !empty($perm['perm_supporter']); // if you need it later
}

if (!function_exists('countReportThreadsVisible')) {
    function countReportThreadsVisible(PDO $conn, string $me, bool $isManager, bool $canSeeAll): int {
        $where = ["t.status IN ('open','chatting')"];
        $args  = [];

        if ($isManager) {
            if (!$canSeeAll) {
                $where[] = "(t.responder_username IS NULL OR t.responder_username = ?)";
                $args[]  = $me;
            }
        } else {
            $where[] = "t.operator_username = ?";
            $args[]  = $me;
        }

        $sql = "SELECT COUNT(*) FROM report_threads t WHERE " . implode(' AND ', $where);
        $st  = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}
$reportCount = countReportThreadsVisible($conn, $currentUser ?? '', $isManager, $canSeeAll);
?>
<div class="main-sidebar main-sidebar-sticky side-menu"
     style="<?= ((int)$adminRole === 3) ? 'background-color:#9d93f9;' : '' ?>">
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
                    <span class="sidemenu-label">نمایه</span>
                </a>
            </li>
            <?php if (Permision('permision_viewcall')) { ?>

                <li class="nav-item">
                <a class="nav-link with-sub" href="#">
                    <span class="shape1"></span>
                    <span class="shape2"></span>
                    <i class="ti-headphone sidemenu-icon"></i>
                    <span class="sidemenu-label">امور مشتریان</span>
                    <i class="angle fe fe-chevron-left"></i>
                </a>
                <ul class="nav-sub">
                    <li class="nav-sub-item">
                        <a class="nav-sub-link" href="<?php echo url('admin/Tel/safe_call.php') ?>">تماس امن
                             </a>
                    </li>

                </ul>
            </li>
            <?php } ?>

            <!--                <li class="nav-item">-->
<!--                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i-->
<!--                                class="fe fe-phone-call sidemenu-icon"></i><span-->
<!--                                class="sidemenu-label">تماس امن</span><i-->
<!--                                class="angle fe fe-chevron-left"></i></a>-->
<!---->
<!--                    <ul class="nav-sub">-->
<!--                        --><?php //if (Permision('permision_addcall')) { ?>
<!--                            <li class="nav-sub-item">-->
<!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/add-group.php') ?><!--">ایجاد گروه-->
<!--                                    تماس</a>-->
<!--                            </li>-->
<!--                        --><?php //} ?>
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/uncall.php') ?><!--">گرفته نشده</a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/unresponse.php') ?><!--">عدم پاسخ</a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/call-finish.php') ?><!--"> خاتمه-->
<!--                                یافته </a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/begin.php') ?><!--">نیاز به تماس-->
<!--                                مجدد </a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/dublicateTel.php') ?><!--">مخاطبین-->
<!--                                تکراری </a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/wrong.php') ?><!--">شماره های-->
<!--                                اشتباه </a>-->
<!--                        </li>-->
<!--                        <li class="nav-sub-item">-->
<!--                            <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/anytel.php') ?><!--">همه مخاطبین من </a>-->
<!--                        </li>-->
<!--                        --><?php //if (Permision('permision_managecall')) { ?>
<!--                            <li class="nav-sub-item">-->
<!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/managecall.php') ?><!--">مدیریت کل-->
<!--                                    تماس امن </a>-->
<!--                            </li>-->
<!--                            <li class="nav-sub-item">-->
<!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/hamtel.php') ?><!--">مرکز تبادل-->
<!--                                    تماس</a>-->
<!--                            </li>-->
<!--                        --><?php //} ?>
<!--                        --><?php //if (Permision('permision_addcall')) { ?>
<!--                            <li class="nav-sub-item">-->
<!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/importtel.php') ?><!--">ورودی </a>-->
<!--                            </li>-->
<!--                        --><?php //} ?>
<!---->
<!--                        --><?php //if (Permision('reportcall_permission')) { ?>
<!--                            <li class="nav-sub-item">-->
<!--                                <a class="nav-sub-link" href="--><?php //echo url('admin/Tel/reportcall.php') ?><!--">گزارش تماس و فایل صوتی </a>-->
<!--                            </li>-->
<!--                        --><?php //} ?>
<!--                    </ul>-->
<!--                </li>-->
            <?php if (Permision('permision_viewstudent')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="si si-graduation sidemenu-icon"></i><span class="sidemenu-label">دانش آموزان </span><i
                                class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/add.php') ?>">ثبت جدید</a>
                        </li>

                <?php if ($adminRole !== 2): ?>
                    <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/groupadd.php') ?>">ثبت گروهی دانش آموزان</a>
                        </li>
                <?php endif; ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/addcourse.php') ?>">ثبت
                                دوره </a>
                        </li>

                <?php if ($adminRole !== 2): ?>
                    <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/democourse/addstudent.php') ?>">ثبت
                                 دوره آزمایشی</a>
                        </li>
                <?php endif; ?>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/edit.php') ?>">ویرایش
                                اطلاعات</a>
                        </li>
                <?php if ($adminRole !== 2): ?>

                    <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/registration-Course.php') ?>">گزارش تفکیکی دانش آموزان </a>

                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/unactive-student.php') ?>">غیرفعال
                                ها</a>
                        </li>

                        <?php if (Permision('permision_reportstudent')) { ?>

                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Student/report-student.php') ?>">گزارش
                                    آماری دانش آموزان </a>
                            </li>
                        <?php } ?>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Student/canceled-student.php') ?>">لیست دانش آموزان انصرافی/غیرفعال </a>

                        </li>
                <?php endif; ?>


                    </ul>
                </li>
            <?php } ?>
            <?php if (Permision('permision_viewcollague')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="fe fe-link  sidemenu-icon"></i><span class="sidemenu-label">همکاران</span><i
                                class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/add-collague.php') ?>">ثبت
                                همکار آموزشی</a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/edit-collague.php') ?>">ویرایش
                                اطلاعات همکار</a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/unactive-collague.php') ?>">غیرفعال
                                ها</a>
                        </li>
                        <?php if (Permision('permision_reportcollague')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Collague/report-collague.php') ?>">گزارش
                                    آماری همکاران</a>
                            </li>
                        <?php } ?>
                        <?php if (Permision('permision_reportcollague')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/Collague/partnerspanel.php') ?>">آمار دانش آموزان خارج از هنرآموزش</a>
                            </li>
                        <?php } ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Collague/sub-collague.php') ?>">همکاران
                                و زیرمجموعه </a>
                        </li>


                    </ul>
                </li>
            <?php } ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="si si-organization sidemenu-icon"></i><span class="sidemenu-label">دوره ها</span><i
                                class="angle fe fe-chevron-left"></i></a>

                    <ul class="nav-sub">
                        <?php if ($adminRole !== 2): ?>
                        <?php if (Permision('permision_viewcourse')) { ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/add-Course.php') ?>"> ثبت دوره
                                جدید</a>
                        </li>
                        <?php } ?>
                        <?php if ((int)$adminRole !== 3) { ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/democourse/add-democourse.php') ?>"> ثبت دوره آزمایشی جدید</a>
                        </li>
                        <?php } ?>

                        <?php endif; ?>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/Active-course.php') ?>"> دوره های
                                فعال</a>
                        </li>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/democourse/list-democourse.php') ?>"> دوره های
                                آزمایشی</a>
                        </li>
                        <?php if ($adminRole !== 2): ?>
                        <?php if (Permision('permision_viewcourse')) { ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/unactive-course.php') ?>"> دوره
                                های غیر فعال</a>
                        </li>
                        <?php } ?>
                        <?php if (Permision('permision_discount')) { ?>
                            <li class="nav-sub-item">
                                <a class="nav-sub-link" href="<?php echo url('admin/course/add-discount.php') ?>"> ایجاد
                                    کد تخفیف</a>
                            </li>
                        <?php } ?>
                        <?php if (Permision('permision_viewcourse')) { ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/reportscourse.php') ?>"> آمار و
                                گزارشات</a>
                        </li>
                        <?php } ?>
                        <?php endif; ?>

                    </ul>
                </li>

            <?php if (Permision('permision_viewbill')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="fe fe-database  sidemenu-icon"></i><span class="sidemenu-label">حسابداری</span><i
                                class="angle fe fe-chevron-left"></i><?php $count = countCheckfactor() + countCheckWallet();
                        if ($count > 0) { ?> <span
                                class="badge badge-warning side-badge "><?php echo $count; ?></span><?php } ?></a>

                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Bill/active-factor.php') ?>">منتظر تایید
                                فاکتور <?php if (countCheckfactor() > 0) { ?><span
                                        class="badge badge-warning mg-r-20"><?php echo countCheckfactor(); ?> </span><?php } ?>
                            </a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Bill/factor-notpay.php') ?>"> فاکتورهای
                                پرداخت نشده </a>
                        </li>
                        <?php if ($adminRole !== 2): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Bill/returned.php') ?>"> فاکتورهای مسترد
                                شده </a>
                        </li>
                        <?php endif; ?>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/Bill/waitingWallet.php') ?>">منتظر تایید
                                کیف پول <?php if (countCheckWallet() > 0) { ?><span
                                        class="badge badge-warning mg-r-20 " ><?php echo countCheckWallet(); ?> </span><?php } ?>
                            </a>

                <?php if ($adminRole !== 2): ?>

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


                    </ul>
                </li>
            <?php } ?>

            <?php if (Permision('permision_viewwallet')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="si si-wallet sidemenu-icon"></i><span
                                class="sidemenu-label">مدیریت کیف پول</span><i
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

            <li class="nav-item">
                <a class="nav-link with-sub" href="#">
                    <span class="shape1"></span><span class="shape2"></span>
                    <i class="fe fe-bell sidemenu-icon"></i>
                    <span class="sidemenu-label">مدیریت اعلان</span>
                    <i class="angle fe fe-chevron-left"></i>
                    <?php if ($reportCount > 0): ?>
                        <span class="badge badge-warning side-badge mg-r-20"><?= $reportCount ?></span>
                    <?php endif; ?>
                </a>

                <ul class="nav-sub">
                    <?php if (Permision('permision_viewalert')): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?= url('admin/notifications/notif_rule_management.php') ?>">
                                کنترل محدودیت اعلان ها
                                <span class="badge badge-light"></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php
                    $canSeeThreadMenu = ($isManager || $isOperator) && ($adminRole !== 3);
                    ?>
                    <?php if ($canSeeThreadMenu): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?= url('admin/notifications/thread_list.php') ?>">
                                تیکت‌های اعلان‌ها
                                <?php if ($reportCount > 0): ?>
                                    <span class="badge badge-warning mg-r-20"><?= $reportCount ?></span>
                                <?php endif; ?>
                                <span class="badge badge-light"></span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (Permision('permision_viewalert')): ?>
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?= url('admin/notifications/notification_view.php') ?>">
 مشاهده اعلان اپراتور ها
                                <span class="badge badge-light"></span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (Permision('permision_viewsupport')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="si si-speech  sidemenu-icon"></i><span
                                class="sidemenu-label">تیکت ها</span><i
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
                                class="fe fe-paperclip t sidemenu-icon"></i><span class="sidemenu-label">مستندات</span><i
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

            <?php if (Permision('permision_viewemploy')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="si si-people t sidemenu-icon"></i><span class="sidemenu-label">کارمندان</span><i
                                class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/employ/addemploy.php') ?>"> ثبت کارمند
                                جدید </a>
                        </li>

                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/employ/listadmin.php') ?>">مشاهده
                                کارمندان </a>

                        </li>


                    </ul>
                </li>

            <?php } ?>


            <?php if (Permision('permision_viewsetting')) { ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                                class="fe fe-settings t sidemenu-icon"></i><span class="sidemenu-label">تنظیمات</span><i
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
                            <a class="nav-sub-link" href="<?php echo url('admin/setting/ready-message.php') ?>"> پیام های آماده
                                 <span class="badge badge-light "></span></a>
                        </li>


                    </ul>
                </li>
            <?php } ?>
            <?php if (Permision('excel_permission')) { ?>
                <li class="nav-item">
                <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i
                            class="fe fe-radio sidemenu-icon"></i><span class="sidemenu-label">آمار و گزارشات</span><i
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
            <?php if (($adminRole == 2 ) or (Permision('administrator')) ) : ?>
                <li class="nav-item">
                    <a class="nav-link with-sub" href="#"><span class="shape1"></span><span class="shape2"></span><i class="si si-badge t sidemenu-icon"></i>          <!-- approvals / verified -->
                        </i><span class="sidemenu-label">تایید تکالیف</span><i
                                class="angle fe fe-chevron-left"></i>
                    </a>
                    <ul class="nav-sub">
                        <li class="nav-sub-item">
                            <a class="nav-sub-link" href="<?php echo url('admin/course/homework_check.php') ?>">تایید تکالیف ارسالی اساتید</a>
                        </li>

                    </ul>
                </li>
            <?php endif ?>

        </ul>
        <hr>
        <?php if ($adminRole !== 3): ?>
            <ul class="nav" style="margin-left: 10px;">
                <li class="text-center text-white">
                    <div class="dropdown dropup">

                        <button aria-expanded="false" aria-haspopup="true"
                                class="btn ripple btn-block  btn-info dropdown-toggle" data-bs-toggle="dropdown"
                                type="button" style="font-size: 16px;"><i class="fe fe-hash"></i> سامانه من
                        </button>

                        <div class="dropdown-menu tx-13">

                            <a class="dropdown-item" href="<?php echo url('admin/panel/desktop.php') ?>">میزکار من</a>
                            <?php if (Permision('permision_manage_suporter')) { ?>

                            <a class="dropdown-item" href="<?php echo url('admin/panel/manager.php') ?>">داشبورد</a>
                        <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-success" href="">گزارش بروزرسانی</a>
                        </div>
                    </div>
                 <div class="mt-3">نسخه سامانه 2.7.4</div>
                </li>

            </ul>
        <?php endif; ?>
    </div>
</div>