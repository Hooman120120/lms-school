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
//* Start Check Session JUST ONE Session
$re = sessionAdmin($_SESSION['admin']['user']);
if (session_id() != $re['session_id']) {
    session_unset();
    header('location: ' . url('../admin/login.php'));
    die();
}
$currentUser = (string)$_SESSION['admin']['user'];
$administrator = (int)($row['administrator'] ?? 0);
$studentOpChangeCount = countStudentOperatorChanged( $currentUser, (int)$administrator);

if (!function_exists('countNewStudent')) {
    function countNewStudent( string $me, int $administrator): int
    {
        global $conn;
        if ($me === '') return 0;

        $where = [
            "d.first_suporter_assigned_at IS NOT NULL"
        ];
        $args = [];

        // Non-admin: only my students
        if ($administrator !== 1) {
            $where[] = "d.suporter = ?";
            $args[]  = $me;
        }

        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT
                    d.username AS student_username,
                    d.suporter AS suporter,
                    MAX(d.first_suporter_assigned_at) AS batch_ts
                FROM dbcourse d
                INNER JOIN dbpersonal p ON p.codemeli = d.username
                INNER JOIN codtbl_course c ON c.codecourse = d.codecourse
                WHERE " . implode(' AND ', $where) . "
                GROUP BY d.username, d.suporter
            ) s
            WHERE NOT EXISTS (
                SELECT 1
                FROM admin_tickets t
                WHERE t.type = 'new_students'
                  AND t.ticket_status = 1
                  AND t.student_username = s.student_username
                  AND t.first_suporter_assigned_at = s.batch_ts
                  AND t.ticket_assigned_to = " . ($administrator === 1 ? "s.suporter" : "?") . "
            )
        ";

        // If non-admin, we need one more bind for ticket_assigned_to = ?
        if ($administrator !== 1) {
            $args[] = $me;
        }

        $st = $conn->prepare($sql);
        $st->execute($args);
        return (int)$st->fetchColumn();
    }
}
$type = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT);

if ($type == 3 or $type == 1) {
    $sqlCountChanges = 'SELECT COUNT(*) FROM change_expert_requests WHERE status = ?';
    $stmtCountChanges = $conn->prepare($sqlCountChanges);
    $stmtCountChanges->execute(['pending']);
    $changeCount = $stmtCountChanges->fetchColumn();

    $sqlbackfromcrm = "
    SELECT COUNT(*)
    FROM admin_tickets
    WHERE type = 'reactivate_temp_user'
      AND ticket_status IN (0,3) ";
    $stmtbackfromcrm = $conn->prepare($sqlbackfromcrm);
    $stmtbackfromcrm->execute();
    $crmCount = (int)$stmtbackfromcrm->fetchColumn();
}

if (!in_array($type, [1, 2, 3, 4], true)) {
    http_response_code(400);
    exit('Invalid request');
}

// ===== دریافت تعداد پیامک‌های خوانده‌نشده (یک بار) =====
$sqlUnreadSms = "SELECT COUNT(*) FROM sms_inbox WHERE is_read = 0";
$stmtUnreadSms = $conn->prepare($sqlUnreadSms);
$stmtUnreadSms->execute();
$unreadSms = $stmtUnreadSms->fetchColumn();
// =====================================================

include_once '../../temp.html';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
    <meta name="description" content="e">
    <meta name="author" content="hamsepar.ir">
    <title> مشاهده اعلان اپرتور ها | <?php echo __TITLE ?></title>
    <?php include_once '../link.php'; ?>
    <style>
        .safe-call-menu {
            margin-top: 30px;
            justify-content: center;
        }

        .safe-call-card {
            display: block;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            background: #f7f7f7;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: all 0.2s ease-in-out;
            height: 120px;
        }

        .safe-call-card:hover {
            text-decoration: none;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            background: #ffffff;
        }

        .safe-call-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:12px;
        }

        .safe-call-card-desc {
            font-size: 13px;
            color: #666;
        }

        .sms-section {
            margin-bottom: 20px;
        }
        .sms-section .safe-call-card {
            height: auto;
            padding: 20px;
        }
    </style>
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

                <!-- ===== بخش پیامک‌های خوانده‌نشده (یک بار نمایش) ===== -->
                <?php if ($type == 1 || $type == 2 || $type == 3 || $type == 4): ?>
                    <div class="row row-sm mg-b-20 sms-section">
                        <div class="col-12">
                            <div class="card custom-card" style="background-color: #EAEDF0;">
                                <div class="card-header border-bottom-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <i class="fa fa-envelope"></i> پیامک‌های خوانده‌نشده
                                        </h5>
                                    </div>
                                </div>
                                <div class="card-body row justify-content-around">
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/unread_sms.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>مشاهده پیامک‌های جدید</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش پیامک‌های دریافتی جدید که هنوز خوانده نشده‌اند را مشاهده می‌کنید. با کلیک روی این بخش می‌توانید پیام‌ها را بررسی، پاسخ دهید یا به عنوان خوانده شده علامت‌گذاری کنید."
                                                      aria-label="راهنمای پیامک‌های خوانده‌نشده"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                            </div>
                                            <?php if ($unreadSms > 0): ?>
                                                <span class="badge pr-2 fs-5" style="background-color: #dc3545; font-size: 16px; padding: 4px 14px;">
                                                <?= $unreadSms ?> جدید
                                            </span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 14px;">همه پیام‌ها خوانده شده‌اند</span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- ======================================================== -->

                <div class="row row-sm mg-b-20">
                    <?php $allowed_users_inadministrators = [0,51,52];
                    if (in_array($_SESSION['admin']['adminrole']  , $allowed_users_inadministrators)) { ?>
                        <div class="col-12 ">
                            <?php if ($type == 1 or $type == 2 ) { ?>
                            <div class="card custom-card" style="background-color: #EAEDF0; ">
                                <div class="card-header border-bottom-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            بخش پشتیبانی
                                        </h5>
                                    </div>
                                </div>
                                <div class="card-body row justify-content-around">
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/thread_list.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>تیکت اعلان تماس</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تیکت‌های مربوط به اعلان تماس دانش آموزان نمایش داده می‌شود. این تیکت‌ها توسط پشتیبانانی ثبت می‌شوند که هنگام اجرای قانون اعلان تماس برای دانش آموز موردنظرشان با شرایط استثنا مواجه شده‌اند؛ مانند زمانی که دانش آموز پاسخگو نبوده، امکان برقراری تماس وجود نداشته یا هر وضعیت خاص دیگری رخ داده است. مدیریت می‌تواند این موارد را بررسی، پیگیری و در صورت نیاز تصمیم‌گیری کند."
                                                      aria-label="راهنمای تیکت اعلان تماس"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <?php if ($reportCount > 0): ?>
                                                <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= $reportCount ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/notif_rule_management.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>کنترل اعلان تماس</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید قوانین ارزیابی عملکرد پشتیبان‌ها را مدیریت کنید. در هر قانون، حداقل تعداد تماس با دانش آموزان و حداقل مدت مکالمه تعیین می‌شود. قانونی که در وضعیت فعال قرار دارد، مبنای بررسی عملکرد پشتیبان‌ها و ارسال اعلان‌های مربوطه خواهد بود."
                                                      aria-label="راهنمای کنترل اعلان تماس"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/new_student_tickets.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>تیکت دانش آموز جدید</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش تیکت‌های مربوط به دانش آموزان جدید نمایش داده می‌شود. این تیکت‌ها برای رسیدگی بیشتر، رفع ابهام و پیگیری سؤالات یا مشکلات دانش آموزان جدید ثبت می‌شوند. موضوع، جزئیات و دلیل ثبت هر تیکت در متن همان تیکت شرح داده شده است تا مدیریت یا مسئول مربوطه بتواند اقدامات لازم را انجام دهد."
                                                      aria-label="راهنمای تیکت دانش آموز جدید"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <?php if ($countNewStudentticket > 0): ?>
                                                <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countNewStudentticket ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>

                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/notification_view.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>اعلان پشتیبان‌ها</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید بر اساس قوانین اعلان‌هایی که مدیریت سیستم تعریف کرده است، اعلان‌های مربوط به هر پشتیبان را به‌صورت جداگانه مشاهده و مدیریت کنید. با انتخاب هر پشتیبان، وضعیت اعلان‌های اختصاص‌یافته به او قابل بررسی بوده و می‌توانید اطمینان حاصل کنید که قوانین تعریف‌شده توسط مدیریت به‌درستی برای آن پشتیبان اعمال شده‌اند."
                                                      aria-label="راهنمای اعلان پشتیبان‌ها"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB">*</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/verify_by_manager.php') ?>"
                                           class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>فاکتورهای منتظر تأیید</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش فاکتورهایی نمایش داده می‌شوند که واریز آن‌ها توسط کارشناسان فروش ثبت شده است. مدیر باید اطلاعات فاکتور و پرداخت را بررسی کرده و تأیید نهایی را انجام دهد."
                                                      aria-label="راهنمای فاکتورهای منتظر تأیید مدیر"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/referred_tickets.php') ?>"
                                           class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>تیکت‌های ارجاعی</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این صفحه درخواست های ثبت و ارجاع داده شده کارشناسان را مشاهده می کنید.در صورت تایید درخواست وضعیت دانش آموز تغییر کرده ( به حالت تعلیق و یا حذف پشتیبان در می آید) و در صورت لغو درخواست هیچ تغییری در وضعیت دانش آموز ایجاد نمیشود."
                                                      aria-label="راهنمای تیکت‌های ارجاعی"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                            <?php if ($countPendingTicketReferrals > 0): ?>
                                                <span class="badge pr-2 fs-5" style="background-color: #6259CB"><?= (int)$countPendingTicketReferrals ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/news_panel.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>اخبار</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="در این بخش می‌توانید اخبار و اطلاعیه‌های سیستم را مدیریت کنید. امکان ثبت و انتشار خبر به‌صورت تفکیک‌شده برای هر نقش از جمله پشتیبان (اپراتور)، کارشناس فروش، مدیر، معاون و همچنین دانش آموزان وجود دارد تا هر گروه تنها اخبار مرتبط با خود را مشاهده کند."
                                                      aria-label="راهنمای مدیریت اخبار"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                        <a href="<?= url('admin/notifications/daily_notifications.php') ?>" class="safe-call-card">
                                            <div class="safe-call-card-title">
                                                <span>اعلان روزانه</span>
                                                <span class="menu-info-trigger"
                                                      data-tooltip="این بخش مشابه مدیریت اخبار است، با این تفاوت که اعلان‌ها تنها به مدت یک روز برای کاربران قابل مشاهده هستند. می‌توانید اعلان‌های روزانه را به‌صورت تفکیک‌شده برای هر نقش از جمله پشتیبان (اپراتور)، کارشناس فروش، مدیر، معاون و همچنین دانش آموزان ثبت و منتشر کنید. پس از گذشت ۲۴ ساعت از زمان انتشار، اعلان به‌صورت خودکار از دسترس کاربران خارج شده و دیگر قابل مشاهده نخواهد بود."
                                                      aria-label="راهنمای اعلان روزانه"
                                                      onclick="event.preventDefault(); event.stopPropagation();">
                                                    <i class="fe fe-info fs-4"></i>
                                                </span>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- ===== حذف کارت پیامک از اینجا ===== -->
                                    <!-- دیگر کارت پیامک در این بخش نمایش داده نمی‌شود -->

                                </div>
                                <?php }
                                else
                                {

                                }?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="row row-sm mg-b-20">
                    <?php $allowed_users_inadministrators = [0, 51, 52, 53];
                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_inadministrators)) { ?>
                    <div class="col-12 ">
                        <?php if ($type == 1 or $type == 3 ) { ?>
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش فروش
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/change_expert_reqs.php') ?>"
                                       class="safe-call-card">
                                        <div class="safe-call-card-title">
                                            <span>درخواست تغییر کارشناس فروش</span>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش درخواست‌های تغییر کارشناس فروش نمایش داده می‌شود. این درخواست‌ها زمانی ثبت می‌شوند که کارشناس فروش به هر دلیلی در جذب یا پیگیری دانش آموز موفق نبوده و درخواست انتقال آن دانش آموز به کارشناس فروش دیگری را ثبت کرده است. در هر درخواست، کارشناس فروش پیشنهادی نیز مشخص می‌شود تا مدیریت بتواند پس از بررسی، درباره انتقال دانش آموز تصمیم‌گیری کند."
                                                  aria-label="راهنمای درخواست تغییر کارشناس فروش"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <?php if ($changeCount > 0): ?>
                                            <span class="badge pr-2 fs-5"
                                                  style="background-color: #6259CB"><?= $changeCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-4 col-xl-3 mb-3">
                                    <a href="<?= url('admin/notifications/reactivate_tickets.php') ?>" class="safe-call-card">
                                        <div class="safe-call-card-title">
                                            <span> دانش آموزان برگشت‌خورده از CRM</span>
                                            <span class="menu-info-trigger"
                                                  data-tooltip="در این بخش درخواست‌های بازگشت دانش آموزان به CRM نمایش داده می‌شود. این درخواست‌ها زمانی ثبت می‌شوند که سرنخ هیچ‌گونه خریدی انجام نداده باشد و کارشناس درخواست انتقال مجدد اطلاعات به CRM را با حفظ کامل سوابق و تاریخچه ثبت کند. پس از بررسی و تأیید، اطلاعات دانش آموز همراه با سوابق قبلی به CRM بازگردانده خواهد شد."
                                                  aria-label="راهنمای تیکت دانش آموزان برگشت‌خورده از CRM"
                                                  onclick="event.preventDefault(); event.stopPropagation();">
                                                <i class="fe fe-info fs-4"></i>
                                            </span>
                                        </div>
                                        <?php if ($crmCount > 0): ?>
                                            <span class="badge pr-2 fs-5" style="background-color: #6259CB">
                                             <?= (int)$crmCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <!-- ===== حذف کارت پیامک از اینجا ===== -->
                                <!-- دیگر کارت پیامک در این بخش نمایش داده نمی‌شود -->

                            </div>
                            <?php } ?>
                        </div>

                        <?php } ?>
                    </div>
                </div>
                <div class="row row-sm mg-b-20">
                    <?php $allowed_users_inadministrators = [0, 51, 52, 53, 54];
                    if (in_array($_SESSION['admin']['adminrole'], $allowed_users_inadministrators)) { ?>
                    <div class="col-12 ">
                        <?php if ($type == 4) { ?>
                        <div class="card custom-card" style="background-color: #EAEDF0; ">
                            <div class="card-header border-bottom-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        بخش فنی
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body row justify-content-around">
                                <!-- ===== حذف کارت پیامک از اینجا ===== -->
                                <!-- دیگر کارت پیامک در این بخش نمایش داده نمی‌شود -->

                            </div>
                            <?php } ?>
                        </div>

                        <?php } ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Main Footer-->
<?php include '../../footers.php'; ?>
<!--End Footer-->

<script>
    // Tooltip activation for info icons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.menu-info-trigger').forEach(function(el) {
            el.addEventListener('click', function(e) {
                // فقط نمایش توضیحات به صورت alert یا می‌توانید از تابع tooltip استفاده کنید
                var tooltipText = el.getAttribute('data-tooltip');
                if (tooltipText) {
                    // برای نمایش بهتر می‌توانید از یک tooltip سفارشی استفاده کنید
                    // در اینجا به عنوان نمونه با alert نمایش داده می‌شود
                    // alert(tooltipText);
                }
            });
        });
    });
</script>

</body>
</html>